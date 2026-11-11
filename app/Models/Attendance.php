<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class Attendance extends BaseModel
{
    protected string $table = 'attendance';

    public function filter(int $companyId, ?int $employeeId = null, ?string $dateFrom = null, ?string $dateTo = null): array
    {
        $sql = "SELECT a.*, e.full_name AS employee_name, e.employee_code
                FROM attendance a
                INNER JOIN employees e ON e.id = a.employee_id
                WHERE a.company_id = :cid";
        $params = ['cid' => $companyId];
        if ($employeeId !== null) {
            $sql .= " AND a.employee_id = :eid";
            $params['eid'] = $employeeId;
        }
        if ($dateFrom !== null) {
            $sql .= " AND a.attendance_date >= :df";
            $params['df'] = $dateFrom;
        }
        if ($dateTo !== null) {
            $sql .= " AND a.attendance_date <= :dt";
            $params['dt'] = $dateTo;
        }
        $sql .= " ORDER BY a.attendance_date DESC, e.employee_code ASC";
        return Database::fetchAll($sql, $params);
    }
}
