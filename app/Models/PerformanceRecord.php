<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class PerformanceRecord extends BaseModel
{
    protected string $table = 'performance_records';
    protected bool $softDeletes = true;

    public function forCompany(int $companyId, ?int $employeeId = null): array
    {
        $sql = "SELECT pr.*, e.full_name AS employee_name, e.employee_code, d.name AS department_name, u.full_name AS evaluator_name
                FROM performance_records pr
                INNER JOIN employees e ON e.id = pr.employee_id
                LEFT JOIN departments d ON d.id = e.department_id
                LEFT JOIN users u ON u.id = pr.evaluated_by
                WHERE pr.company_id = :cid AND pr.deleted_at IS NULL";
        $params = ['cid' => $companyId];
        if ($employeeId !== null) {
            $sql .= " AND pr.employee_id = :eid";
            $params['eid'] = $employeeId;
        }
        $sql .= " ORDER BY pr.period_end DESC, pr.computed_score DESC";
        return Database::fetchAll($sql, $params);
    }
}
