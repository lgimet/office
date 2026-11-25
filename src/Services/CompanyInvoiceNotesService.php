<?php

namespace App\Services;

use Devsys\Shared\Api\Devsys\Invoices\InvoicesApi;

/** Routes only the three coded issuer notes through the authenticated invoice API. */
final class CompanyInvoiceNotesService
{
    private const FIELDS = ['PMD' => 'invoice_note_pmd', 'PMT' => 'invoice_note_pmt', 'AAB' => 'invoice_note_aab'];

    public function __construct(private readonly InvoicesApi $invoices) {}

    public function read(): array
    {
        return self::mapToOffice($this->invoices->issuerInvoiceNotes());
    }

    public function replace(array $input): array
    {
        $notes = [];
        foreach (self::FIELDS as $code => $field) {
            $value = $input[$field] ?? '';
            if (!is_string($value)) throw new \InvalidArgumentException('Les mentions de facture doivent être du texte.');
            $notes[$code] = trim($value);
        }
        return self::mapToOffice($this->invoices->updateIssuerInvoiceNotes($notes));
    }

    public static function mapToOffice(array $notes): array
    {
        $out = [];
        foreach (self::FIELDS as $code => $field) $out[$field] = $notes[$code] ?? null;
        return $out;
    }
}
