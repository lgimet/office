<?php

namespace App\Repositories;

use App\Core\BaseRepository;

class InvoiceRepository extends BaseRepository
{
    public function paginate(string $search, string $status, int $page, int $limit): array
    {
        $where = [];
        $params = [];

        if ($search !== '') {
            $where[] = '(i.invoice_number LIKE ? OR i.client_name LIKE ? OR i.client_email LIKE ? OR CAST(i.id AS CHAR) LIKE ?)';
            $params = array_fill(0, 4, '%' . $search . '%');
        }
        if ($status !== '') {
            $where[] = 'i.status = ?';
            $params[] = $status;
        }

        $condition = $where ? ' WHERE ' . implode(' AND ', $where) : '';
        $total = (int) $this->query('SELECT COUNT(*) FROM invoices i' . $condition, $params)?->fetchColumn();
        $offset = ($page - 1) * $limit;
        $rows = $this->query(
            'SELECT i.*, COALESCE(i.invoice_number, CONCAT(\'Brouillon #\', i.id)) AS reference
             FROM invoices i' . $condition . ' ORDER BY i.created_at DESC, i.id DESC LIMIT ' . $limit . ' OFFSET ' . $offset,
            $params
        )?->fetchAll() ?: [];

        return ['rows' => $rows, 'total' => $total];
    }

    public function client(int $id): ?array
    {
        return $this->query('SELECT * FROM clients WHERE id = ? AND is_active = 1', [$id])?->fetch() ?: null;
    }

    public function clientOptions(string $query = ''): array
    {
        $sql = 'SELECT id, COALESCE(NULLIF(display_name, \'\'), company_name) AS label
                FROM clients
                WHERE is_active = 1';
        $params = [];

        if ($query !== '') {
            $sql .= ' AND (company_name LIKE ? OR display_name LIKE ? OR email LIKE ?)';
            $params = array_fill(0, 3, '%' . $query . '%');
        }

        $sql .= ' ORDER BY company_name LIMIT 25';

        return $this->query($sql, $params)?->fetchAll() ?: [];
    }

    public function find(int $id): ?array
    {
        return $this->query('SELECT * FROM invoices WHERE id = ?', [$id])?->fetch() ?: null;
    }

    public function lines(int $invoiceId): array
    {
        return $this->query(
            'SELECT * FROM invoice_lines WHERE invoice_id = ? ORDER BY position ASC, id ASC',
            [$invoiceId]
        )?->fetchAll() ?: [];
    }

    public function createDraft(array $invoice, array $lines): int
    {
        $this->pdo->beginTransaction();
        try {
            $id = $this->insert(
                'INSERT INTO invoices (client_id, status, issue_date, due_date, currency, client_name, client_contact_name, client_email, client_phone, client_address_line1, client_address_line2, client_postal_code, client_city, client_country, client_siret, client_vat_number, subtotal_excl_tax, discount_total_excl_tax, tax_total, total_incl_tax, payment_terms, payment_method, public_note, internal_note)
                 VALUES (?, \'draft\', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                $invoice
            );
            $this->insertLines((int) $id, $lines);
            $this->pdo->commit();
            return (int) $id;
        } catch (\Throwable $exception) {
            $this->pdo->rollBack();
            throw $exception;
        }
    }

    public function updateDraft(int $id, array $invoice, array $lines): void
    {
        $this->pdo->beginTransaction();

        try {
            $draft = $this->query(
                'SELECT id FROM invoices WHERE id = ? AND status = \'draft\' FOR UPDATE',
                [$id]
            )?->fetch();

            if ($draft === false || $draft === null) {
                throw new \LogicException('Cette facture ne peut plus être modifiée.');
            }

            $this->query(
                'UPDATE invoices
                 SET client_id = ?, issue_date = ?, due_date = ?, currency = ?, client_name = ?,
                     client_contact_name = ?, client_email = ?, client_phone = ?,
                     client_address_line1 = ?, client_address_line2 = ?,
                     client_postal_code = ?, client_city = ?, client_country = ?,
                     client_siret = ?, client_vat_number = ?, subtotal_excl_tax = ?,
                     discount_total_excl_tax = ?, tax_total = ?, total_incl_tax = ?,
                     payment_terms = ?, payment_method = ?, public_note = ?,
                     internal_note = ?, updated_at = CURRENT_TIMESTAMP
                 WHERE id = ?',
                [...$invoice, $id]
            );

            $this->query('DELETE FROM invoice_lines WHERE invoice_id = ?', [$id]);
            $this->insertLines($id, $lines);
            $this->pdo->commit();
        } catch (\Throwable $exception) {
            $this->pdo->rollBack();
            throw $exception;
        }
    }

    public function deleteDraft(int $id): void
    {
        $this->pdo->beginTransaction();

        try {
            $invoice = $this->query(
                'SELECT id, status FROM invoices WHERE id = ? FOR UPDATE',
                [$id]
            )?->fetch();

            if ($invoice === false || $invoice === null) {
                throw new \InvalidArgumentException('La facture demandée est introuvable.');
            }

            if ($invoice['status'] !== 'draft') {
                throw new \LogicException('Seules les factures en brouillon peuvent être supprimées.');
            }

            $this->query('DELETE FROM invoice_lines WHERE invoice_id = ?', [$id]);
            $this->query('DELETE FROM invoices WHERE id = ? AND status = \'draft\'', [$id]);
            $this->pdo->commit();
        } catch (\Throwable $exception) {
            $this->pdo->rollBack();
            throw $exception;
        }
    }

    private function insertLines(int $invoiceId, array $lines): void
    {
        foreach ($lines as $line) {
            $this->insert(
                'INSERT INTO invoice_lines (
                    invoice_id, position, label, description, quantity, unit,
                    unit_price_excl_tax, discount_type, discount_value, discount_note, tax_rate,
                    line_subtotal_excl_tax, line_discount_excl_tax,
                    line_tax_total, line_total_incl_tax
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [$invoiceId, ...$line]
            );
        }
    }
}
