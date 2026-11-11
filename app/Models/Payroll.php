<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class Payroll extends BaseModel
{
    protected string $table = 'payrolls';
    protected bool $softDeletes = true;

    public function filter(int $companyId, array $filters = []): array
    {
        $sql = "SELECT p.*, e.full_name, e.employee_code, e.position, e.bank_name, e.bank_account_number, d.name AS department_name
                FROM payrolls p
                INNER JOIN employees e ON e.id = p.employee_id
                LEFT JOIN departments d ON d.id = e.department_id
                WHERE p.company_id = :cid AND p.deleted_at IS NULL";
        $params = ['cid' => $companyId];

        if (!empty($filters['year'])) {
            $sql .= " AND p.period_year = :yr";
            $params['yr'] = (int) $filters['year'];
        }
        if (!empty($filters['month'])) {
            $sql .= " AND p.period_month = :mo";
            $params['mo'] = (int) $filters['month'];
        }
        if (!empty($filters['employee_id'])) {
            $sql .= " AND p.employee_id = :eid";
            $params['eid'] = (int) $filters['employee_id'];
        }
        if (!empty($filters['status'])) {
            $sql .= " AND p.status = :status";
            $params['status'] = $filters['status'];
        }

        $sql .= " ORDER BY p.period_year DESC, p.period_month DESC, e.employee_code ASC";
        return Database::fetchAll($sql, $params);
    }

    public function findDetailed(int $id): ?array
    {
        $payroll = Database::fetchOne(
            "SELECT p.*, e.full_name, e.employee_code, e.position, e.email, e.phone, e.bank_name, e.bank_account_number, d.name AS department_name
             FROM payrolls p
             INNER JOIN employees e ON e.id = p.employee_id
             LEFT JOIN departments d ON d.id = e.department_id
             WHERE p.id = :id AND p.deleted_at IS NULL
             LIMIT 1",
            ['id' => $id]
        );
        if ($payroll === null) {
            return null;
        }
        $payroll['components'] = Database::fetchAll(
            'SELECT * FROM salary_components WHERE payroll_id = :pid ORDER BY id ASC',
            ['pid' => $id]
        );
        return $payroll;
    }
}
