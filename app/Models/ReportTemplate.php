<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class ReportTemplate extends BaseModel
{
    protected string $table = 'report_templates';
    protected bool $softDeletes = true;

    public function accessibleBy(int $companyId, int $userId): array
    {
        return Database::fetchAll(
            "SELECT rt.*, u.full_name AS creator_name
             FROM report_templates rt
             LEFT JOIN users u ON u.id = rt.created_by
             WHERE rt.company_id = :cid AND rt.deleted_at IS NULL AND (rt.is_shared = 1 OR rt.created_by = :uid)
             ORDER BY rt.updated_at DESC",
            ['cid' => $companyId, 'uid' => $userId]
        );
    }
}
