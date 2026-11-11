<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class Deduction extends BaseModel
{
    protected string $table = 'deductions';
    protected bool $softDeletes = true;

    public function forCompany(int $companyId, ?int $employeeId = null): array
    {
        $sql = "SELECT d.*, e.full_name AS employee_name, e.employee_code, u.full_name AS authorizer_name
                FROM deductions d
                INNER JOIN employees e ON e.id = d.employee_id
                LEFT JOIN users u ON u.id = d.authorized_by
                WHERE d.company_id = :cid AND d.deleted_at IS NULL";
        $params = ['cid' => $companyId];
        if ($employeeId !== null) {
            $sql .= " AND d.employee_id = :eid";
            $params['eid'] = $employeeId;
        }
        $sql .= " ORDER BY d.is_authorized ASC, d.deduction_date DESC, d.id DESC";
        return Database::fetchAll($sql, $params);
    }
}
