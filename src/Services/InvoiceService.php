<?php

namespace App\Services;

use App\Repositories\CompanySettingsRepository;
use App\Repositories\InvoiceRepository;

class InvoiceService
{
    public function __construct(
        private InvoiceRepository $invoices,
        private InvoiceCalculationService $calculator,
        private CompanySettingsRepository $companySettings,
        private CompanySettingsService $companySettingsService
    ) {
    }

    public function list(array $query): array
    {
        $limit = max(1, min(100, (int) ($query['limit'] ?? 25)));
        $page = max(1, (int) ($query['page'] ?? 1));
        $result = $this->invoices->paginate(trim((string) ($query['search'] ?? '')), (string) ($query['status'] ?? ''), $page, $limit);
        return ['rows' => $result['rows'], 'pages' => max(1, (int) ceil($result['total'] / $limit))];
    }

    public function form(?int $id = null): array
    {
        $defaults = $this->billingDefaults();

        if ($id === null) {
            return [
                'invoice' => null,
                'lines' => [],
                'defaults' => $defaults,
            ];
        }

        $invoice = $this->invoices->find($id);

        if ($invoice === null) {
            throw new \InvalidArgumentException('La facture demandée est introuvable.');
        }

        if ($invoice['status'] !== 'draft') {
            throw new \InvalidArgumentException('Seules les factures en brouillon peuvent être modifiées.');
        }

        return [
            'invoice' => $invoice,
            'lines' => $this->invoices->lines($id),
            'defaults' => $defaults,
        ];
    }

    public function saveDraft(array $input, ?int $id = null): int
    {
        ['invoice' => $invoice, 'lines' => $lines] = $this->prepareDraft($input, $id);

        if ($id !== null) {
            $this->invoices->updateDraft($id, $invoice, $lines);

            return $id;
        }

        return $this->invoices->createDraft($invoice, $lines);
    }

    public function deleteDraft(int $id): void
    {
        if ($id <= 0) {
            throw new \InvalidArgumentException('La facture demandée est invalide.');
        }

        $this->invoices->deleteDraft($id);
    }

    public function issueDraft(array $input, ?int $id = null): string
    {
        if ($id === null || $id <= 0) {
            throw new \InvalidArgumentException('Enregistrez la facture avant de l’émettre.');
        }

        $this->companySettingsService->validateIssuerForInvoice($this->companySettings->find());
        ['invoice' => $invoice, 'lines' => $lines] = $this->prepareDraft($input, $id);

        return $this->invoices->issueDraft($id, $invoice, $lines);
    }

    private function prepareDraft(array $input, ?int $id): array
    {
        $defaults = $this->billingDefaults();
        $existingInvoice = null;

        if ($id !== null) {
            $existingInvoice = $this->invoices->find($id);
            if ($existingInvoice === null) throw new \InvalidArgumentException('La facture demandée est introuvable.');
            if ($existingInvoice['status'] !== 'draft') throw new \InvalidArgumentException('Seules les factures en brouillon peuvent être modifiées.');
        }

        $client = $this->invoices->client((int) ($input['client_id'] ?? 0));
        if ($client === null) throw new \InvalidArgumentException('Le client sélectionné est invalide.');
        $issueDate = (string) ($input['issue_date'] ?? '');
        if ($issueDate === '') throw new \InvalidArgumentException('La date de facture est obligatoire.');
        if (!empty($input['due_date']) && $input['due_date'] < $issueDate) throw new \InvalidArgumentException('La date d’échéance ne peut pas être antérieure à la date de facture.');

        $calculated = $this->calculator->calculate($input['lines'] ?? []);
        if ($calculated['lines'] === []) throw new \InvalidArgumentException('Ajoutez au moins une ligne de facture valide.');

        $contact = trim(($client['contact_first_name'] ?? '') . ' ' . ($client['contact_last_name'] ?? '')) ?: null;
        $invoice = [
            (int) $client['id'], $issueDate, $input['due_date'] ?: null,
            $existingInvoice['currency'] ?? $defaults['currency'],
            $client['display_name'] ?: $client['company_name'], $contact,
            $client['email'], $client['phone'], $client['address_line1'], $client['address_line2'],
            $client['postal_code'], $client['city'], $client['country'], $client['siret'], $client['vat_number'],
            $calculated['totals']['subtotal_excl_tax'], $calculated['totals']['discount_total_excl_tax'],
            $calculated['totals']['tax_total'], $calculated['totals']['total_incl_tax'],
            $this->paymentTermsCode($input['payment_terms_code'] ?? null, $input['payment_terms'] ?? null),
            $input['payment_terms'] ?: null, $input['payment_method'] ?: null,
            $input['public_note'] ?: null, $input['internal_note'] ?: null,
        ];
        $lines = array_map(
            static fn (array $line, int $position) => [
                $position + 1, $line['label'], $line['description'] ?: null, $line['quantity'], $line['unit'] ?: null,
                $line['unit_price_excl_tax'], $line['discount_type'], $line['discount_value'], $line['discount_note'],
                $line['tax_rate'], $line['line_subtotal_excl_tax'], $line['line_discount_excl_tax'],
                $line['line_tax_total'], $line['line_total_incl_tax'],
            ],
            $calculated['lines'], array_keys($calculated['lines'])
        );

        return compact('invoice', 'lines');
    }

    private function billingDefaults(): array
    {
        $company = $this->companySettings->find() ?? [];
        $taxRate = (string) ($company['default_tax_rate'] ?? '20.00');
        $currency = strtoupper((string) ($company['default_currency'] ?? 'EUR'));

        return [
            'tax_rate' => is_numeric($taxRate) ? $taxRate : '20.00',
            'currency' => preg_match('/^[A-Z]{3}$/', $currency) ? $currency : 'EUR',
            'invoice_number_prefix' => preg_match('/^[A-Z0-9]{1,8}$/', (string) ($company['invoice_number_prefix'] ?? 'F'))
                ? strtoupper((string) $company['invoice_number_prefix'])
                : 'F',
            'payment_terms' => $company['default_payment_terms'] ?? null,
            'payment_terms_code' => $company['default_payment_terms_code'] ?? null,
            'payment_method' => $company['default_payment_method'] ?? null,
        ];
    }

    private function paymentTermsCode(?string $code, ?string $label): string
    {
        $allowed = ['cash', 'receipt', 'days_15', 'days_30', 'days_45', 'days_60', 'days_30_then_eom', 'days_45_then_eom', 'custom'];
        if (in_array($code, $allowed, true)) return $code;

        $normalized = mb_strtolower(trim((string) $label));
        return match ($normalized) {
            'comptant' => 'cash',
            'à réception', 'a réception', 'a reception' => 'receipt',
            '15 jours', 'sous 15 jours' => 'days_15',
            '30 jours', '30 jours date de facture', 'sous 30 jours' => 'days_30',
            '45 jours' => 'days_45',
            '60 jours' => 'days_60',
            '30 jours fin de mois', '30 jours puis fin de mois' => 'days_30_then_eom',
            '45 jours fin de mois', '45 jours puis fin de mois' => 'days_45_then_eom',
            default => 'custom',
        };
    }
}
