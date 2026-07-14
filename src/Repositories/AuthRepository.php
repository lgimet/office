<?php

namespace App\Repositories;

use App\Core\BaseRepository;

class AuthRepository extends BaseRepository
{

    public function __construct()
    {
        parent::__construct();
    }
    public function getByEmail(string $email): ?array
    {
        $stmt = $this->query(
            'SELECT id, email, password_hash,
                    first_name AS firstname,
                    last_name AS lastname,
                    CONCAT(UPPER(LEFT(first_name, 1)), UPPER(LEFT(last_name, 1))) AS initials
             FROM users
             WHERE email = ? AND is_active = 1',
            [$email]
        );

        return $stmt->fetch() ?: null;
    }

    public function getIdentityById(int $userId): ?array
    {
        $stmt = $this->query(
            'SELECT id, email,
                    first_name AS firstname,
                    last_name AS lastname,
                    CONCAT(UPPER(LEFT(first_name, 1)), UPPER(LEFT(last_name, 1))) AS initials
             FROM users
             WHERE id = ? AND is_active = 1',
            [$userId]
        );
        return $stmt->fetch() ?: null;
    }

    public function completeSuccessfulLogin(int $userId, ?string $newPasswordHash = null): void
    {
        if ($newPasswordHash !== null) {
            $this->query(
                'UPDATE users
                 SET password_hash = ?, last_login_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP
                 WHERE id = ?',
                [$newPasswordHash, $userId]
            );
            return;
        }

        $this->query(
            'UPDATE users
             SET last_login_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP
             WHERE id = ?',
            [$userId]
        );
    }

    public function existsByEmail(string $email): bool
    {
        $stmt = $this->query('SELECT 1 FROM users WHERE email = ?', [$email]);

        return (bool) $stmt->fetchColumn();
    }

    public function createAdministrator(string $email, string $passwordHash): int
    {
        return (int) $this->insert(
            'INSERT INTO users (email, password_hash, role, is_active, created_at)
             VALUES (?, ?, ?, 1, CURRENT_TIMESTAMP)',
            [$email, $passwordHash, 'admin']
        );
    }
}
