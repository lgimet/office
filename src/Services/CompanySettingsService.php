<?php

namespace App\Services;

use App\Repositories\CompanySettingsRepository;

class CompanySettingsService
{
    public function __construct(private CompanySettingsRepository $settings)
    {
    }

    public function form(): array
    {
        return [
            'company' => $this->settings->find() ?? $this->defaults(),
        ];
    }

    public function save(array $input): void
    {
        $settings = $this->normalize($input);
        $this->settings->save($settings);
    }

    public function validateIssuerForInvoice(?array $company): void
    {
        $company ??= [];
        $missing = [];

        foreach ([
            'legal_name' => 'raison sociale',
            'address_line1' => 'adresse',
            'postal_code' => 'code postal',
            'city' => 'ville',
            'country' => 'pays',
        ] as $field => $label) {
            if (trim((string) ($company[$field] ?? '')) === '') {
                $missing[] = $label;
            }
        }

        if (trim((string) ($company['siren'] ?? '')) === '' && trim((string) ($company['siret'] ?? '')) === '') {
            $missing[] = 'SIREN ou SIRET';
        }

        if ($missing !== []) {
            throw new \InvalidArgumentException(
                'Complétez les informations de votre société : ' . implode(', ', $missing) . '.'
            );
        }
    }

    private function normalize(array $input): array
    {
        $legalName = trim((string) ($input['legal_name'] ?? ''));

        if ($legalName === '') {
            throw new \InvalidArgumentException('La raison sociale est obligatoire.');
        }

        $email = trim((string) ($input['email'] ?? ''));

        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new \InvalidArgumentException('L’adresse e-mail de la société est invalide.');
        }

        $taxRate = str_replace(',', '.', trim((string) ($input['default_tax_rate'] ?? '20')));

        if (!is_numeric($taxRate) || (float) $taxRate < 0 || (float) $taxRate > 100) {
            throw new \InvalidArgumentException('Le taux de TVA par défaut doit être compris entre 0 et 100 %.');
        }

        $currency = strtoupper(trim((string) ($input['default_currency'] ?? 'EUR')));

        if (!preg_match('/^[A-Z]{3}$/', $currency)) {
            throw new \InvalidArgumentException('La devise par défaut doit comporter trois lettres.');
        }

        return [
            $legalName,
            $this->nullable($input, 'trading_name'),
            $this->nullable($input, 'legal_form'),
            $this->nullable($input, 'share_capital'),
            $this->nullable($input, 'address_line1'),
            $this->nullable($input, 'address_line2'),
            $this->nullable($input, 'postal_code'),
            $this->nullable($input, 'city'),
            trim((string) ($input['country'] ?? 'France')) ?: 'France',
            $email === '' ? null : $email,
            $this->nullable($input, 'phone'),
            $this->nullable($input, 'website'),
            $this->nullable($input, 'siret'),
            $this->nullable($input, 'siren'),
            $this->nullable($input, 'vat_number'),
            $this->nullable($input, 'ape_code'),
            $this->nullable($input, 'rcs_city'),
            $this->nullable($input, 'bank_name'),
            $this->nullable($input, 'iban'),
            $this->nullable($input, 'bic'),
            $currency,
            number_format((float) $taxRate, 2, '.', ''),
            $this->nullable($input, 'default_payment_terms'),
            $this->paymentTermsCode($input['default_payment_terms_code'] ?? null, $input['default_payment_terms'] ?? null),
            $this->nullable($input, 'default_payment_method'),
            $this->nullable($input, 'invoice_footer'),
        ];
    }

    private function nullable(array $input, string $field): ?string
    {
        $value = trim((string) ($input[$field] ?? ''));

        return $value === '' ? null : $value;
    }

    private function paymentTermsCode(?string $code, ?string $label): string
    {
        $allowed = ['cash', 'receipt', 'days_15', 'days_30', 'days_45', 'days_60', 'days_30_then_eom', 'days_45_then_eom', 'custom'];
        if (in_array($code, $allowed, true)) return $code;

        return match (mb_strtolower(trim((string) $label))) {
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

    private function defaults(): array
    {
        return [
            'legal_name' => '',
            'country' => 'France',
            'default_currency' => 'EUR',
            'default_tax_rate' => '20.00',
        ];
    }
}
