<?php

namespace App\Repositories;

use App\Core\BaseRepository;

class CompanySettingsRepository extends BaseRepository
{
    public function find(): ?array
    {
        return $this->query('SELECT * FROM company_settings WHERE id = 1')?->fetch() ?: null;
    }

    public function save(array $settings): void
    {
        $this->query(
            'INSERT INTO company_settings (
                id, legal_name, trading_name, legal_form, share_capital,
                address_line1, address_line2, postal_code, city, country,
                email, phone, website, siret, siren, vat_number, ape_code,
                rcs_city, bank_name, iban, bic, default_currency,
                default_tax_rate, default_payment_terms, default_payment_terms_code,
                default_payment_method, invoice_footer
            ) VALUES (
                1, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
            ) ON DUPLICATE KEY UPDATE
                legal_name = VALUES(legal_name),
                trading_name = VALUES(trading_name),
                legal_form = VALUES(legal_form),
                share_capital = VALUES(share_capital),
                address_line1 = VALUES(address_line1),
                address_line2 = VALUES(address_line2),
                postal_code = VALUES(postal_code),
                city = VALUES(city),
                country = VALUES(country),
                email = VALUES(email),
                phone = VALUES(phone),
                website = VALUES(website),
                siret = VALUES(siret),
                siren = VALUES(siren),
                vat_number = VALUES(vat_number),
                ape_code = VALUES(ape_code),
                rcs_city = VALUES(rcs_city),
                bank_name = VALUES(bank_name),
                iban = VALUES(iban),
                bic = VALUES(bic),
                default_currency = VALUES(default_currency),
                default_tax_rate = VALUES(default_tax_rate),
                default_payment_terms = VALUES(default_payment_terms),
                default_payment_terms_code = VALUES(default_payment_terms_code),
                default_payment_method = VALUES(default_payment_method),
                invoice_footer = VALUES(invoice_footer),
                updated_at = CURRENT_TIMESTAMP',
            $settings
        );
    }
}
