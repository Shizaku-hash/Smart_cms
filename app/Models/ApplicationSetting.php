<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class ApplicationSetting extends BaseModel
{
    protected string $table = 'application_settings';

    public function getMap(int $companyId): array
    {
        $rows = Database::fetchAll(
            'SELECT setting_key, setting_value, setting_type FROM application_settings WHERE company_id = :cid',
            ['cid' => $companyId]
        );
        $map = [];
        foreach ($rows as $row) {
            $val = $row['setting_value'];
            $map[$row['setting_key']] = match ($row['setting_type']) {
                'integer' => (int) $val,
                'float' => (float) $val,
                'boolean' => (bool) ((int) $val),
                'json' => json_decode((string) $val, true) ?? [],
                default => (string) $val,
            };
        }
        return $map;
    }

    public function upsert(int $companyId, string $key, mixed $value, string $type = 'string', string $group = 'general', ?int $userId = null): void
    {
        $stored = is_array($value) ? json_encode($value) : (is_bool($value) ? ($value ? '1' : '0') : (string) $value);
        Database::execute(
            "INSERT INTO application_settings (company_id, setting_key, setting_value, setting_type, group_name, updated_by)
             VALUES (:cid, :k, :v, :t, :g, :uid)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), setting_type = VALUES(setting_type), updated_by = VALUES(updated_by)",
            ['cid' => $companyId, 'k' => $key, 'v' => $stored, 't' => $type, 'g' => $group, 'uid' => $userId]
        );
    }
}
