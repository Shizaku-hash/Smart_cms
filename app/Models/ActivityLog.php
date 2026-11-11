<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class ActivityLog extends BaseModel
{
    protected string $table = 'activity_logs';

    public function recent(int $companyId, int $limit = 100): array
    {
        return Database::fetchAll(
            "SELECT al.*, u.full_name AS user_name, u.username
             FROM activity_logs al
             LEFT JOIN users u ON u.id = al.user_id
             WHERE al.company_id = :cid
             ORDER BY al.created_at DESC, al.id DESC
             LIMIT " . (int) $limit,
            ['cid' => $companyId]
        );
    }
}
