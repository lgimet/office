<?php

namespace App\Repositories;

use App\Core\BaseRepository;
use App\Services\TenantContext;

class ClientRepository extends BaseRepository
{
    public function __construct(private readonly TenantContext $tenant)
    {
        parent::__construct();
    }

    public function paginate(string $search, ?int $typeId, int $page, int $limit): array
    {
        $where = ['c.tenant_id = ?'];
        $params = [$this->tenant->id()];
        if ($search !== '') {
            $where[] = '(c.company_name LIKE ? OR c.display_name LIKE ? OR c.contact_first_name LIKE ? OR c.contact_last_name LIKE ? OR c.email LIKE ? OR c.phone LIKE ? OR c.siret LIKE ? OR c.city LIKE ?)';
            array_push($params, ...array_fill(0, 8, '%' . $search . '%'));
        }
        if ($typeId !== null) {
            $where[] = 'c.client_type_id = ?';
            $params[] = $typeId;
        }
        $condition = $where ? ' WHERE ' . implode(' AND ', $where) : '';
        $count = $this->query('SELECT COUNT(*) FROM clients c' . $condition, $params)?->fetchColumn() ?: 0;
        $offset = ($page - 1) * $limit;
        $sql = 'SELECT c.*, ct.name AS client_type_name, COALESCE(NULLIF(c.display_name, \'\'), c.company_name) AS client_name,
                TRIM(CONCAT(COALESCE(c.contact_first_name, \'\'), \' \', COALESCE(c.contact_last_name, \'\'))) AS contact_name
                FROM clients c INNER JOIN client_types ct ON ct.id = c.client_type_id' . $condition . ' ORDER BY c.company_name ASC LIMIT ' . (int) $limit . ' OFFSET ' . (int) $offset;
        $rows = $this->query($sql, $params)?->fetchAll() ?: [];
        return ['rows' => $rows, 'total' => (int) $count];
    }

    public function find(int $id): ?array
    {
        return $this->query('SELECT * FROM clients WHERE id = ? AND tenant_id = ?', [$id, $this->tenant->id()])?->fetch() ?: null;
    }
    public function create(array $data): int
    {
        return (int) $this->insert($this->writeSql(false), [$this->tenant->id(), ...$this->writeParams($data)]);
    }
    public function update(int $id, array $data): void
    {
        $this->query($this->writeSql(true), [...$this->writeParams($data), $id, $this->tenant->id()]);
    }
    public function toggle(int $id): void
    {
        $this->query('UPDATE clients SET is_active = NOT is_active, updated_at = CURRENT_TIMESTAMP WHERE id = ? AND tenant_id = ?', [$id, $this->tenant->id()]);
    }
    public function countByType(int $typeId): int
    {
        return (int) ($this->query('SELECT COUNT(*) FROM clients WHERE client_type_id = ? AND tenant_id = ?', [$typeId, $this->tenant->id()])?->fetchColumn() ?: 0);
    }
    private function writeSql(bool $update): string
    {
        $fields = 'tenant_id, client_type_id, company_name, display_name, contact_first_name, contact_last_name, email, phone, address_line1, address_line2, postal_code, city, country, siret, vat_number, notes, is_active';
        if (!$update) {
            return 'INSERT INTO clients (' . $fields . ') VALUES (' . rtrim(str_repeat('?, ', 17), ', ') . ')';
        }
        return 'UPDATE clients SET client_type_id=?, company_name=?, display_name=?, contact_first_name=?, contact_last_name=?, email=?, phone=?, address_line1=?, address_line2=?, postal_code=?, city=?, country=?, siret=?, vat_number=?, notes=?, is_active=?, updated_at=CURRENT_TIMESTAMP WHERE id=? AND tenant_id=?';
    }
    private function writeParams(array $d): array
    {
        return [$d['client_type_id'], $d['company_name'], $d['display_name'], $d['contact_first_name'], $d['contact_last_name'], $d['email'], $d['phone'], $d['address_line1'], $d['address_line2'], $d['postal_code'], $d['city'], $d['country'], $d['siret'], $d['vat_number'], $d['notes'], $d['is_active']];
    }
}
