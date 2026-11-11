<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class Department extends BaseModel
{
    protected string $table = 'departments';
    protected bool $softDeletes = true;

    public function forCompany(int $companyId): array
    {
        return Database::fetchAll(
            "SELECT d.*, e.full_name AS manager_name,
                    (SELECT COUNT(*) FROM employees emp WHERE emp.department_id = d.id AND emp.deleted_at IS NULL) AS employee_count
             FROM departments d
             LEFT JOIN employees e ON e.id = d.manager_employee_id
             WHERE d.company_id = :cid AND d.deleted_at IS NULL
             ORDER BY d.name ASC",
            ['cid' => $companyId]
        );
    }
}
