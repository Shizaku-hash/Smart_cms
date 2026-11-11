<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

/**
 * Pure PDO SQL Backup generator writing portable database snapshots to storage/backups/.
 */
final class BackupService
{
    public function createBackup(int $companyId, ?int $userId = null): array
    {
        $pdo = Database::pdo();
        $dir = rtrim((string) config('backup_path'), '/');
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $filename = 'smart_cms_backup_' . date('Ymd_His') . '.sql';
        $fullPath = $dir . '/' . $filename;

        $fp = fopen($fullPath, 'w');
        if ($fp === false) {
            throw new \RuntimeException('Could not write backup file.');
        }

        fwrite($fp, "-- SmartCMS MySQL Backup\n-- Generated: " . date('Y-m-d H:i:s') . "\nSET FOREIGN_KEY_CHECKS = 0;\n\n");

        $tables = $pdo->query('SHOW TABLES')->fetchAll(\PDO::FETCH_COLUMN);
        foreach ($tables as $table) {
            $createRow = $pdo->query("SHOW CREATE TABLE `{$table}`")->fetch(\PDO::FETCH_ASSOC);
            $createSql = $createRow['Create Table'] ?? '';
            fwrite($fp, "DROP TABLE IF EXISTS `{$table}`;\n{$createSql};\n\n");

            $rows = $pdo->query("SELECT * FROM `{$table}`")->fetchAll(\PDO::FETCH_ASSOC);
            if ($rows !== []) {
                $cols = array_keys($rows[0]);
                $colList = '`' . implode('`, `', $cols) . '`';
                foreach ($rows as $row) {
                    $vals = [];
                    foreach ($row as $v) {
                        $vals[] = $v === null ? 'NULL' : $pdo->quote((string) $v);
                    }
                    fwrite($fp, "INSERT INTO `{$table}` ({$colList}) VALUES (" . implode(', ', $vals) . ");\n");
                }
                fwrite($fp, "\n");
            }
        }

        fwrite($fp, "SET FOREIGN_KEY_CHECKS = 1;\n");
        fclose($fp);

        (new ActivityLogService())->log('backup', 'database', null, "Created database backup {$filename}", null, ['filename' => $filename, 'size' => filesize($fullPath)], $companyId, $userId);

        return [
            'filename' => $filename,
            'path' => $fullPath,
            'size' => filesize($fullPath),
        ];
    }

    public function listBackups(): array
    {
        $dir = rtrim((string) config('backup_path'), '/');
        if (!is_dir($dir)) {
            return [];
        }
        $files = glob($dir . '/*.sql') ?: [];
        rsort($files);
        $out = [];
        foreach ($files as $f) {
            $out[] = [
                'filename' => basename($f),
                'size' => filesize($f),
                'created_at' => date('Y-m-d H:i:s', filemtime($f) ?: time()),
            ];
        }
        return $out;
    }
}
