<?php

namespace App\Services;

class InvoiceCalculationService
{
    public function calculate(array $lines): array
    {
        $totals = [
            'subtotal_excl_tax' => 0,
            'discount_total_excl_tax' => 0,
            'tax_total' => 0,
            'total_incl_tax' => 0,
        ];
        $calculatedLines = [];

        foreach ($lines as $line) {
            if ($this->isEmptyLine($line)) {
                continue;
            }

            $label = trim((string) ($line['label'] ?? ''));

            if ($label === '') {
                throw new \InvalidArgumentException('Chaque ligne de facture doit avoir une désignation.');
            }

            $quantity = $this->decimal($line['quantity'] ?? null, 3);
            $unitPrice = $this->cents($line['unit_price_excl_tax'] ?? null);
            $taxRate = $this->decimal($line['tax_rate'] ?? null, 2);

            if ($quantity <= 0 || $unitPrice < 0) {
                throw new \InvalidArgumentException('Chaque ligne doit avoir une quantité positive et un prix valide.');
            }

            if ($taxRate < 0) {
                throw new \InvalidArgumentException('Le taux de TVA doit être supérieur ou égal à zéro.');
            }

            $grossAmount = (int) round($quantity * $unitPrice);
            [$discountType, $discountValue, $discountAmount] = $this->calculateDiscount($line, $grossAmount);
            $netAmount = $grossAmount - $discountAmount;
            $taxAmount = (int) round($netAmount * $taxRate / 100);
            $totalAmount = $netAmount + $taxAmount;

            $calculatedLines[] = [
                ...$line,
                'label' => $label,
                'quantity' => number_format($quantity, 3, '.', ''),
                'unit_price_excl_tax' => $this->money($unitPrice),
                'tax_rate' => number_format($taxRate, 2, '.', ''),
                'discount_type' => $discountType,
                'discount_value' => $discountValue,
                'discount_note' => $discountType === null
                    ? null
                    : (trim((string) ($line['discount_note'] ?? '')) ?: null),
                'line_subtotal_excl_tax' => $this->money($grossAmount),
                'line_discount_excl_tax' => $this->money($discountAmount),
                'line_tax_total' => $this->money($taxAmount),
                'line_total_incl_tax' => $this->money($totalAmount),
            ];

            $totals['subtotal_excl_tax'] += $grossAmount;
            $totals['discount_total_excl_tax'] += $discountAmount;
            $totals['tax_total'] += $taxAmount;
            $totals['total_incl_tax'] += $totalAmount;
        }

        return [
            'lines' => $calculatedLines,
            'totals' => array_map(fn (int $amount) => $this->money($amount), $totals),
        ];
    }

    private function calculateDiscount(array $line, int $grossAmount): array
    {
        $type = trim((string) ($line['discount_type'] ?? '')) ?: null;

        if ($type === null) {
            return [null, '0.00', 0];
        }

        if (!in_array($type, ['percentage', 'fixed'], true)) {
            throw new \InvalidArgumentException('Le type de remise sélectionné est invalide.');
        }

        if ($type === 'percentage') {
            $percentage = $this->decimal($line['discount_value'] ?? null, 2);

            if ($percentage < 0 || $percentage > 100) {
                throw new \InvalidArgumentException('La remise en pourcentage doit être comprise entre 0 et 100 %.');
            }

            return [
                'percentage',
                number_format($percentage, 2, '.', ''),
                (int) round($grossAmount * $percentage / 100),
            ];
        }

        $fixedAmount = $this->cents($line['discount_value'] ?? null);

        if ($fixedAmount < 0 || $fixedAmount > $grossAmount) {
            throw new \InvalidArgumentException('La remise fixe ne peut pas dépasser le montant HT de la ligne.');
        }

        return ['fixed', $this->money($fixedAmount), $fixedAmount];
    }

    private function isEmptyLine(array $line): bool
    {
        return trim((string) ($line['label'] ?? '')) === ''
            && trim((string) ($line['description'] ?? '')) === '';
    }

    private function cents(mixed $value): int
    {
        return (int) round($this->decimal($value, 2) * 100);
    }

    private function decimal(mixed $value, int $precision): float
    {
        $normalized = str_replace(',', '.', trim((string) $value));

        if ($normalized === '' || !is_numeric($normalized)) {
            throw new \InvalidArgumentException('Une valeur numérique valide est attendue sur chaque ligne.');
        }

        return round((float) $normalized, $precision);
    }

    private function money(int $amount): string
    {
        return number_format($amount / 100, 2, '.', '');
    }
}
