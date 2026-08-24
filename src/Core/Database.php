<?php

namespace App\Core;

use App\Core\Exceptions\DatabaseException;
use PDO;
use PDOException;

class Database
{
    private static ?PDO $instance = null;

    public static function getConnection(): PDO
    {
        if (self::$instance === null) {
            try {
                $dsn = sprintf(
                    'mysql:host=%s;port=%s;dbname=%s;charset=%s',
                    $_ENV['DB_HOST'] ?? '127.0.0.1',
                    $_ENV['DB_PORT'] ?? '3306',
                    $_ENV['DB_NAME'] ?? 'office',
                    $_ENV['DB_CHARSET'] ?? 'utf8mb4'
                );
                self::$instance = new PDO(
                    $dsn,
                    $_ENV['DB_USER'] ?? '',
                    $_ENV['DB_PASSWORD'] ?? '',
                    [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    ]
                );
            } catch (PDOException $e) {
                throw new DatabaseException('Connexion à la base de données impossible.', 0, $e);
            }
        }

        return self::$instance;
    }
}
