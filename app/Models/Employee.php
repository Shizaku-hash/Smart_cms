<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class Employee extends BaseModel
{
    protected string $table = 'employees';
    protected bool $softDeletes = true;

    public function filter(int $companyId, array $filters = []): array
    {
        $sql = "SELECT e.*, d.name AS department_name, d.code AS department_code
                FROM employees e
                LEFT JOIN departments d ON d.id = e.department_id
                WHERE e.company_id = :cid AND e.deleted_at IS NULL";
        $params = ['cid' => $companyId];

        if (!empty($filters['department_id'])) {
            $sql .= " AND e.department_id = :dept_id";
            $params['dept_id'] = (int) $filters['department_id'];
        }
        if (!empty($filters['status'])) {
            $sql .= " AND e.employment_status = :status";
            $params['status'] = $filters['status'];
        }
        if (!empty($filters['payment_type'])) {
            $sql .= " AND e.payment_type = :payment_type";
            $params['payment_type'] = $filters['payment_type'];
        }
        if (!empty($filters['search'])) {
            $sql .= " AND (e.full_name LIKE :q1 OR e.employee_code LIKE :q2 OR e.email LIKE :q3 OR e.position LIKE :q4)";
            $like = '%' . $filters['search'] . '%';
            $params['q1'] = $like;
            $params['q2'] = $like;
            $params['q3'] = $like;
            $params['q4'] = $like;
        }

        $sql .= " ORDER BY e.employee_code ASC";
        return Database::fetchAll($sql, $params);
    }

    public function findDetailed(int $id): ?array
    {
        return Database::fetchOne(
            "SELECT e.*, d.name AS department_name, u.username AS user_username, r.name AS user_role_name
             FROM employees e
             LEFT JOIN departments d ON d.id = e.department_id
             LEFT JOIN users u ON u.id = e.user_id
             LEFT JOIN roles r ON r.id = u.role_id
             WHERE e.id = :id AND e.deleted_at IS NULL
             LIMIT 1",
            ['id' => $id]
        );
    }

    public function generateNextCode(int $companyId): string
    {
        $count = (int) Database::fetchColumn('SELECT COUNT(*) FROM employees WHERE company_id = :cid', ['cid' => $companyId]);
        return sprintf('EMP-%03d', $count + 1);
    }
}
