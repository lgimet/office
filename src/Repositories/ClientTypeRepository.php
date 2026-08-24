<?php

namespace App\Repositories;

use App\Core\BaseRepository;

class ClientTypeRepository extends BaseRepository
{
    public function active(): array
    {
        return $this->query('SELECT id, name FROM client_types WHERE is_active = 1 ORDER BY position, name')?->fetchAll() ?: [];
    }
    public function activeApiList(): array
    {
        return $this->query('SELECT id, name, slug FROM client_types WHERE is_active = 1 ORDER BY position, name')?->fetchAll() ?: [];
    }
    public function findActiveBySlug(string $slug): ?array
    {
        return $this->query(
            'SELECT id, name, slug FROM client_types WHERE slug = ? AND is_active = 1',
            [$slug]
        )?->fetch() ?: null;
    }
    public function all(): array
    {
        return $this->query('SELECT * FROM client_types ORDER BY position, name')?->fetchAll() ?: [];
    }
    public function find(int $id): ?array
    {
        return $this->query('SELECT * FROM client_types WHERE id = ?', [$id])?->fetch() ?: null;
    }
    public function slugExists(string $slug, ?int $except = null): bool
    {
        $sql = 'SELECT 1 FROM client_types WHERE slug = ?';
        $p = [$slug];
        if ($except) {
            $sql .= ' AND id != ?';
            $p[] = $except;
        } return (bool) $this->query($sql, $p)?->fetchColumn();
    }
    public function save(array $d, ?int $id = null): int
    {
        if ($id) {
            $this->query('UPDATE client_types SET name=?, slug=?, description=?, position=?, is_active=?, updated_at=CURRENT_TIMESTAMP WHERE id=?', [...$d, $id]);
            return $id;
        } return (int) $this->insert('INSERT INTO client_types (name, slug, description, position, is_active) VALUES (?, ?, ?, ?, ?)', $d);
    }
    public function toggle(int $id): void
    {
        $this->query('UPDATE client_types SET is_active = NOT is_active, updated_at=CURRENT_TIMESTAMP WHERE id=?', [$id]);
    }
    public function delete(int $id): void
    {
        $this->query('DELETE FROM client_types WHERE id=?', [$id]);
    }
}
