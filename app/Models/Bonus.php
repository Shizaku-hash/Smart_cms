<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class Bonus extends BaseModel
{
    protected string $table = 'bonuses';
    protected bool $softDeletes = true;

    public function forCompany(int $companyId, ?int $employeeId = null): array
    {
        $sql = "SELECT b.*, e.full_name AS employee_name, e.employee_code
                FROM bonuses b
                INNER JOIN employees e ON e.id = b.employee_id
                WHERE b.company_id = :cid AND b.deleted_at IS NULL";
        $params = ['cid' => $companyId];
        if ($employeeId !== null) {
            $sql .= " AND b.employee_id = :eid";
            $params['eid'] = $employeeId;
        }
        $sql .= " ORDER BY b.awarded_date DESC, b.id DESC";
        return Database::fetchAll($sql, $params);
    }
}
