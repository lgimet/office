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
