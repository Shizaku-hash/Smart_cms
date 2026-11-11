#!/usr/bin/env php
<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';
require dirname(__DIR__) . '/app/Core/Helpers.php';

use App\Core\Database;

$fresh = in_array('--fresh', $argv, true);
$pdo = Database::pdo();

if ($fresh) {
    echo "[migrate] Dropping all existing tables...\n";
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
    $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    foreach ($tables as $table) {
        $pdo->exec("DROP TABLE IF EXISTS `{$table}`");
    }
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
}

// Ensure migrations tracking table exists
$pdo->exec("
    CREATE TABLE IF NOT EXISTS migrations (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        migration_name VARCHAR(191) NOT NULL UNIQUE,
        executed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

$executed = $pdo->query('SELECT migration_name FROM migrations')->fetchAll(PDO::FETCH_COLUMN);
$executedMap = array_flip($executed);

$migrationFiles = glob(dirname(__DIR__) . '/database/migrations/*.sql');
sort($migrationFiles);

$count = 0;
foreach ($migrationFiles as $file) {
    $name = basename($file);
    if (isset($executedMap[$name])) {
        continue;
    }

    echo "[migrate] Running {$name}...\n";
    $sql = file_get_contents($file);
    if ($sql === false) {
        throw new RuntimeException("Unable to read migration file: {$file}");
    }

    // Split SQL statements safely on semicolon + newline
    $statements = array_filter(
        array_map('trim', preg_split('/;\s*(?:\r?\n|$)/', $sql) ?: [])
    );

    foreach ($statements as $statement) {
        if ($statement === '') {
            continue;
        }
        $pdo->exec($statement);
    }

    $stmt = $pdo->prepare('INSERT INTO migrations (migration_name) VALUES (:name)');
    $stmt->execute(['name' => $name]);
    $count++;
}

echo "[migrate] Complete. {$count} migration(s) applied.\n";
