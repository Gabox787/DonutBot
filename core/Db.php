<?php

declare(strict_types=1);

final class Db
{
    private static ?\PDO $pdo = null;

    public static function pdo(array $dbConfig): \PDO
    {
        if (self::$pdo instanceof \PDO) {
            return self::$pdo;
        }
        $charset = $dbConfig['charset'] ?? 'utf8mb4';
        $dsn = sprintf(
            'mysql:host=%s;dbname=%s;charset=%s',
            $dbConfig['host'],
            $dbConfig['name'],
            $charset
        );
        self::$pdo = new \PDO($dsn, $dbConfig['user'], $dbConfig['pass'], [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
        ]);
        return self::$pdo;
    }
}
