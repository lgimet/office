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

    /** @return array<string, ?string> */
    public function invoiceIssuerSnapshot(?array $company): array
    {
        $company ??= [];
        $fields = [
            'legal_name', 'trading_name', 'legal_form', 'share_capital',
            'address_line1', 'address_line2', 'postal_code', 'city', 'country',
            'email', 'phone', 'website', 'siret', 'siren', 'vat_number',
            'ape_code', 'rcs_city', 'bank_name', 'iban', 'bic', 'invoice_footer',
        ];

        $snapshot = [];
        foreach ($fields as $field) {
            $value = trim((string) ($company[$field] ?? ''));
            $snapshot[$field] = $value === '' ? null : $value;
        }

        return $snapshot;
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

        $prefix = strtoupper(trim((string) ($input['invoice_number_prefix'] ?? 'F')));
        if (!preg_match('/^[A-Z0-9]{1,8}$/', $prefix)) {
            throw new \InvalidArgumentException('Le préfixe des factures doit comporter de 1 à 8 caractères alphanumériques.');
        }

        return [
            'legal_name' => $legalName,
            'trading_name' => $this->nullable($input, 'trading_name'),
            'legal_form' => $this->nullable($input, 'legal_form'),
            'share_capital' => $this->nullable($input, 'share_capital'),
            'address_line1' => $this->nullable($input, 'address_line1'),
            'address_line2' => $this->nullable($input, 'address_line2'),
            'postal_code' => $this->nullable($input, 'postal_code'),
            'city' => $this->nullable($input, 'city'),
            'country' => trim((string) ($input['country'] ?? 'France')) ?: 'France',
            'email' => $email === '' ? null : $email,
            'phone' => $this->nullable($input, 'phone'),
            'website' => $this->nullable($input, 'website'),
            'siret' => $this->nullable($input, 'siret'),
            'siren' => $this->nullable($input, 'siren'),
            'vat_number' => $this->nullable($input, 'vat_number'),
            'ape_code' => $this->nullable($input, 'ape_code'),
            'rcs_city' => $this->nullable($input, 'rcs_city'),
            'bank_name' => $this->nullable($input, 'bank_name'),
            'iban' => $this->nullable($input, 'iban'),
            'bic' => $this->nullable($input, 'bic'),
            'default_currency' => $currency,
            'default_tax_rate' => number_format((float) $taxRate, 2, '.', ''),
            'invoice_number_prefix' => $prefix,
            'default_payment_terms' => $this->nullable($input, 'default_payment_terms'),
            'default_payment_terms_code' => $this->paymentTermsCode($input['default_payment_terms_code'] ?? null, $input['default_payment_terms'] ?? null),
            'default_payment_method' => $this->nullable($input, 'default_payment_method'),
            'invoice_footer' => $this->nullable($input, 'invoice_footer'),
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
            'invoice_number_prefix' => 'F',
        ];
    }
}
