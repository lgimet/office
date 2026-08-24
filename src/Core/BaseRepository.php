<?php

namespace App\Core;

use App\Core\Exceptions\DatabaseException;
use PDO;
use PDOException;
use PDOStatement;

class BaseRepository
{
    protected PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getConnection();
    }

    protected function query(string $sql, array $params = []): ?PDOStatement
    {
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (\PDOException $e) {
            $this->handleError($e);
        }
        return null;
    }
    protected function insert(string $sql, array $params = []): ?int
    {
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $this->pdo->lastInsertId();
        } catch (PDOException $e) {
            $this->handleError($e);
        }
        return null;
    }

    private function handleError(PDOException $e): never
    {
        throw new DatabaseException('Erreur de base de données.', 0, $e);
    }
}
