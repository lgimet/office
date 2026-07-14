<?php

namespace App\Repositories;

use App\Core\BaseRepository;

class Client extends BaseRepository
{
    public function __construct()
    {
        parent::__construct();
    }

    public function findFirst(): ?array
    {
        $stmt = $this->query('SELECT * FROM client ORDER BY id ASC LIMIT 1');
        return $stmt?->fetch() ?: null;
    }

    public function create(array $data): ?int
    {
        return $this->insert(
            'INSERT INTO client (
                company, email, phone, description, country, modules, notifications, iban, bic
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $data['company'],
                $data['email'],
                $data['phone'],
                $data['description'],
                $data['country'],
                $data['modules'],
                $data['notifications'],
                $data['iban'],
                $data['bic'],
            ]
        );
    }

    public function update(int $id, array $data): void
    {
        $this->query(
            'UPDATE client
             SET company = ?, email = ?, phone = ?, description = ?, country = ?,
                 modules = ?, notifications = ?, iban = ?, bic = ?
             WHERE id = ?',
            [
                $data['company'],
                $data['email'],
                $data['phone'],
                $data['description'],
                $data['country'],
                $data['modules'],
                $data['notifications'],
                $data['iban'],
                $data['bic'],
                $id,
            ]
        );
    }
}
