<?php

declare(strict_types=1);

namespace App\Services;

use Devsys\Shared\Api\Devsys\Invoices\Dto\InvoiceListQuery;
use Devsys\Shared\Api\Devsys\Invoices\InvoicesApi;

/** Adapts the shared invoice list DTOs to the existing Office table contract. */
final class DevsysInvoiceReadService
{
    public function __construct(private readonly InvoicesApi $invoices)
    {
    }

    public function list(array $query): array
    {
        $limit = $this->boundedInteger($query['limit'] ?? 25, 25, 1, 100);
        $page = $this->boundedInteger($query['page'] ?? 1, 1, 1, intdiv(PHP_INT_MAX, $limit));
        $search = trim($this->scalarString($query['search'] ?? null));
        if (strlen($search) > 120) {
            $search = function_exists('mb_strcut') ? mb_strcut($search, 0, 120, 'UTF-8') : substr($search, 0, 120);
        }
        $status = trim($this->scalarString($query['status'] ?? null));
        if (!in_array($status, ['draft', 'issued', 'cancelled'], true)) {
            $status = '';
        }

        $result = $this->invoices->list(new InvoiceListQuery(
            page: $page,
            perPage: $limit,
            search: $search === '' ? null : $search,
            status: $status === '' ? null : $status,
        ));

        $rows = [];
        foreach ($result->items as $item) {
            $invoice = $item->data;
            if (!in_array($invoice['status'], ['draft', 'issued', 'cancelled'], true)) {
                throw new \InvalidArgumentException('Invoice list status is unsupported.');
            }
            $invoiceNumber = $invoice['invoice_number'];
            $rows[] = [
                'id' => $invoice['id'],
                'reference' => is_string($invoiceNumber) && trim($invoiceNumber) !== ''
                    ? $invoiceNumber
                    : ('Brouillon #' . $invoice['id']),
                'invoice_number' => $invoiceNumber,
                'client_name' => $invoice['client']['name'],
                'issue_date' => $invoice['issue_date'],
                'due_date' => $invoice['due_date'],
                'subtotal_excl_tax' => $invoice['amounts']['subtotal_excl_tax'],
                'tax_total' => $invoice['amounts']['tax_total'],
                'total_incl_tax' => $invoice['amounts']['total_incl_tax'],
                'status' => $invoice['status'],
            ];
        }

        return [
            'rows' => $rows,
            'pages' => max(1, $result->pagination->totalPages),
        ];
    }

    /** Returns only API snapshots for API-origin invoices; unknown provenance fails closed. */
    public function view(int $invoiceId): array
    {
        if ($invoiceId < 1) throw new \InvalidArgumentException('La facture demandée est invalide.');
        $details = $this->invoices->get($invoiceId);
        if ($details->id !== $invoiceId) throw new \UnexpectedValueException('The invoice detail response did not match the requested invoice.');
        if ($details->origin === null) throw new \LogicException('La provenance de cette facture API est inconnue.');
        if ($details->origin->kind === 'historical') return ['source' => 'historical'];
        if (!in_array($details->status, ['issued', 'cancelled'], true) || !is_string($details->invoiceNumber) || trim($details->invoiceNumber) === '') {
            throw new \LogicException('Les brouillons API doivent être ouverts dans l’éditeur de facture.');
        }
        if ($details->issuer === null) throw new \UnexpectedValueException('The issued API invoice is missing its immutable issuer snapshot.');

        $client = $details->client;
        $issuer = $details->issuer;
        $invoice = [
            'id' => $details->id,
            'invoice_number' => $details->invoiceNumber,
            'business_reference' => $details->businessReference,
            'status' => $details->status,
            'issue_date' => $details->issueDate,
            'due_date' => $details->dueDate,
            'issued_at' => $details->metadata['issued_at'] ?? null,
            'currency' => $details->currency ?? 'EUR',
            'client_name' => $client->get('name'),
            'client_contact_name' => $client->get('contact_name'),
            'client_address_line1' => $client->get('address_line1'),
            'client_address_line2' => $client->get('address_line2'),
            'client_postal_code' => $client->get('postal_code'),
            'client_city' => $client->get('city'),
            'client_country' => $client->get('country'),
            'client_phone' => $client->get('phone'),
            'client_email' => $client->get('email'),
            'client_siret' => $client->get('siret'),
            'client_vat_number' => $client->get('vat_number'),
            'issuer_legal_name' => $issuer->get('legal_name'),
            'issuer_trading_name' => $issuer->get('trading_name'),
            'issuer_legal_form' => $issuer->get('legal_form'),
            'issuer_share_capital' => $issuer->get('share_capital'),
            'issuer_address_line1' => $issuer->get('address_line1'),
            'issuer_address_line2' => $issuer->get('address_line2'),
            'issuer_postal_code' => $issuer->get('postal_code'),
            'issuer_city' => $issuer->get('city'),
            'issuer_country' => $issuer->get('country'),
            'issuer_siret' => $issuer->get('siret'),
            'issuer_siren' => $issuer->get('siren'),
            'issuer_vat_number' => $issuer->get('vat_number'),
            'issuer_rcs_city' => $issuer->get('rcs_city'),
            'issuer_invoice_footer' => $issuer->get('invoice_footer'),
            'payment_terms' => $details->metadata['payment_terms'] ?? null,
            'payment_method' => $details->metadata['payment_method'] ?? null,
            'public_note' => $details->metadata['public_note'] ?? null,
            'internal_note' => null,
            'subtotal_excl_tax' => $details->amounts->subtotalExclTax,
            'discount_total_excl_tax' => $details->amounts->discountTotalExclTax,
            'tax_total' => $details->amounts->taxTotal,
            'total_incl_tax' => $details->amounts->totalInclTax,
        ];
        $lines = array_map(static fn (object $line): array => $line->values, $details->lines);

        return ['source' => 'api', 'api_mode' => true, 'pdf_enabled' => false, 'invoice' => $invoice, 'lines' => $lines];
    }

    private function boundedInteger(mixed $value, int $default, int $minimum, int $maximum): int
    {
        if (is_int($value)) {
            $integer = $value;
        } elseif (is_string($value) && preg_match('/^-?\d+$/', $value) === 1) {
            $integer = (int) $value;
        } else {
            return $default;
        }

        return max($minimum, min($maximum, $integer));
    }

    private function scalarString(mixed $value): string
    {
        return is_string($value) || is_numeric($value) ? (string) $value : '';
    }
}
