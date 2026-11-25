<?php

namespace App\Services;

use Devsys\Shared\Api\Devsys\Exception\DevsysApiValidationException;

final class InvoiceFacturXErrorFormatter
{
    private const GENERIC_MESSAGE = 'La facture émise ne peut pas être exportée en Factur-X à partir de ses données figées. Elle reste inchangée. Contactez l’administrateur de facturation.';

    public function snapshotIncomplete(DevsysApiValidationException $exception): string
    {
        return $this->format($exception, 'FACTURX_SNAPSHOT_INCOMPLETE', self::GENERIC_MESSAGE, 'Cette facture émise ne peut pas être exportée en Factur-X : ');
    }

    public function preIssueIncomplete(DevsysApiValidationException $exception): string
    {
        return $this->format(
            $exception,
            'INVOICE_FACTURX_PREISSUE_INCOMPLETE',
            'Le brouillon ne peut pas être émis avec le profil Factur-X sélectionné. Contactez l’administrateur de facturation; aucun numéro n’a été attribué.',
            'Le brouillon Factur-X doit être complété : ',
            '. Corrigez le brouillon ou la configuration de l’émetteur avant de réessayer; aucun numéro n’a été attribué.',
        );
    }

    private function format(DevsysApiValidationException $exception, string $expectedCode, string $generic, string $prefix, string $suffix = ' Elle reste inchangée.'): string
    {
        if ($exception->getErrorCode() !== $expectedCode) {
            return $generic;
        }

        $fields = $exception->getErrors()['missing_fields'] ?? null;
        if (!is_array($fields) || !array_is_list($fields) || $fields === [] || count($fields) > 20) {
            return $generic;
        }

        $groups = [];
        foreach ($fields as $field) {
            if (!is_string($field) || !$this->isAllowedFieldCode($field)) {
                return $generic;
            }
            $groups[$this->groupFor($field)] = true;
        }

        return $prefix.implode(', ', array_keys($groups)).$suffix;
    }

    private function isAllowedFieldCode(string $field): bool
    {
        if (in_array($field, [
            'invoice_number', 'invoice_number_year', 'issue_date', 'due_date', 'currency',
            'payment_terms', 'payment_method', 'payment_method_code', 'issuer_iban', 'issuer_vat_number',
            'lines', 'lines.count', 'amounts.subtotal_excl_tax', 'amounts.discount_total_excl_tax',
            'amounts.tax_total', 'amounts.total_incl_tax', 'amounts.arithmetic',
        ], true)) {
            return true;
        }

        $partyFields = '(?:name|contact_name|legal_name|trading_name|legal_form|share_capital|address_line1|address_line2|postal_code|city|country|email|phone|website|siret|siren|vat_number|ape_code|rcs_city|bank_name|iban|bic|invoice_footer)';
        if (preg_match('/^client_'.$partyFields.'$/D', $field) === 1
            || preg_match('/^issuer_'.$partyFields.'$/D', $field) === 1
            || preg_match('/^issuer_invoice_footer_(?:PMT|PMD|AAB)$/D', $field) === 1) {
            return true;
        }

        if (preg_match('/^line_([1-9][0-9]{0,2})(?:\.(unit_or_label|tax_category_code|numeric_fields|numeric_range|discount_type|discount_value|tax_category_rate|line_subtotal_excl_tax|line_discount_excl_tax|line_tax_total|line_total_incl_tax))?$/D', $field, $match) !== 1) {
            return false;
        }

        return (int) $match[1] <= 500;
    }

    private function groupFor(string $field): string
    {
        if (str_starts_with($field, 'client_')) return 'coordonnées du client';
        if (str_starts_with($field, 'issuer_invoice_footer_')) return 'mentions légales de l’émetteur';
        if ($field === 'issuer_iban') return 'coordonnées de règlement de l’émetteur';
        if (str_starts_with($field, 'issuer_') || $field === 'issuer_vat_number') return 'identification ou coordonnées de l’émetteur';
        if (str_starts_with($field, 'line_') || $field === 'lines' || $field === 'lines.count') return 'données des lignes';
        if (str_starts_with($field, 'amounts.')) return 'cohérence des totaux';
        if (str_starts_with($field, 'payment_')) return 'conditions de règlement';
        return 'informations de facture';
    }
}
