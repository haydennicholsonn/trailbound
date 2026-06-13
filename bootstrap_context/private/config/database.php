<?php

declare(strict_types=1);

function trailbound_db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $host = trailbound_env('DB_HOST');
    $name = trailbound_env('DB_NAME');
    $user = trailbound_env('DB_USER');
    $pass = trailbound_env('DB_PASS');

    if (!$host || !$name || !$user) {
        throw new RuntimeException('Database is not configured. Copy private/.env.example to private/.env and update DB credentials.');
    }

    $dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', $host, $name);
    $pdo = new PDO($dsn, $user, $pass ?? '', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    return $pdo;
}

function trailbound_table_has_rows(PDO $db, string $table): bool
{
    $statement = $db->query('SELECT COUNT(*) AS total FROM `' . str_replace('`', '', $table) . '`');
    $row = $statement->fetch();

    return (int) ($row['total'] ?? 0) > 0;
}
