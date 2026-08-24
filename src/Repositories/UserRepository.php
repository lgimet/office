<?php

namespace App\Repositories;

use App\Core\BaseRepository;
use PDO;

class UserRepository extends BaseRepository
{
    public function __construct()
    {
        parent::__construct();
    }

    public function findByEmail(string $email): array|false
    {
        $stmt = $this->pdo->prepare('SELECT * FROM users WHERE email = ?');
        $stmt->execute([$email]);

        return $stmt->fetch();
    }

    public function storeRefreshTokenHash(int $userId, string $tokenHash, int $ttlSeconds): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO user_tokens (user_id, token_hash, expires_at)
             VALUES (?, ?, FROM_UNIXTIME(UNIX_TIMESTAMP(CURRENT_TIMESTAMP()) + ?))
             ON DUPLICATE KEY UPDATE
                 token_hash = VALUES(token_hash),
                 expires_at = VALUES(expires_at)'
        );
        $stmt->execute([$userId, $tokenHash, $ttlSeconds]);
    }

    public function clearRefreshTokenHash(int $userId): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM user_tokens WHERE user_id = ?');
        $stmt->execute([$userId]);
    }

    public function hasValidRefreshTokenHash(int $userId, string $tokenHash): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT 1
             FROM user_tokens
             WHERE user_id = ?
               AND token_hash = ?
               AND expires_at > CURRENT_TIMESTAMP()'
        );
        $stmt->execute([$userId, $tokenHash]);

        return (bool) $stmt->fetchColumn();
    }
}
