<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class Notification extends BaseModel
{
    protected string $table = 'notifications';

    public function forUser(int $companyId, int $userId, bool $unreadOnly = false): array
    {
        $sql = "SELECT * FROM notifications
                WHERE company_id = :cid AND (user_id = :uid OR user_id IS NULL)";
        if ($unreadOnly) {
            $sql .= " AND is_read = 0";
        }
        $sql .= " ORDER BY created_at DESC LIMIT 50";
        return Database::fetchAll($sql, ['cid' => $companyId, 'uid' => $userId]);
    }

    public function unreadCount(int $companyId, int $userId): int
    {
        return (int) Database::fetchColumn(
            "SELECT COUNT(*) FROM notifications WHERE company_id = :cid AND (user_id = :uid OR user_id IS NULL) AND is_read = 0",
            ['cid' => $companyId, 'uid' => $userId]
        );
    }
}
