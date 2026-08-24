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
            $this->nullable($input, 'default_payment_method'),
            $this->nullable($input, 'invoice_footer'),
        ];
    }

    private function nullable(array $input, string $field): ?string
    {
        $value = trim((string) ($input[$field] ?? ''));

        return $value === '' ? null : $value;
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
