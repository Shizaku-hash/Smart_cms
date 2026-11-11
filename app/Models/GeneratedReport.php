<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class GeneratedReport extends BaseModel
{
    protected string $table = 'generated_reports';

    public function recent(int $companyId, int $limit = 30): array
    {
        return Database::fetchAll(
            "SELECT gr.*, u.full_name AS generator_name
             FROM generated_reports gr
             LEFT JOIN users u ON u.id = gr.generated_by
             WHERE gr.company_id = :cid
             ORDER BY gr.created_at DESC
             LIMIT " . (int) $limit,
            ['cid' => $companyId]
        );
    }
}
