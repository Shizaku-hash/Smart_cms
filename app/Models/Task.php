<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class Task extends BaseModel
{
    protected string $table = 'tasks';
    protected bool $softDeletes = true;

    public function filter(int $companyId, array $filters = []): array
    {
        $sql = "SELECT t.*, d.name AS department_name,
                       GROUP_CONCAT(DISTINCT e.full_name ORDER BY e.full_name SEPARATOR ', ') AS assignee_names,
                       GROUP_CONCAT(DISTINCT ta.employee_id) AS assignee_ids
                FROM tasks t
                LEFT JOIN departments d ON d.id = t.department_id
                LEFT JOIN task_assignments ta ON ta.task_id = t.id
                LEFT JOIN employees e ON e.id = ta.employee_id AND e.deleted_at IS NULL
                WHERE t.company_id = :cid AND t.deleted_at IS NULL";
        $params = ['cid' => $companyId];

        if (!empty($filters['status'])) {
            $sql .= " AND t.status = :status";
            $params['status'] = $filters['status'];
        }
        if (!empty($filters['priority'])) {
            $sql .= " AND t.priority = :priority";
            $params['priority'] = $filters['priority'];
        }
        if (!empty($filters['employee_id'])) {
            $sql .= " AND EXISTS (SELECT 1 FROM task_assignments ta2 WHERE ta2.task_id = t.id AND ta2.employee_id = :eid)";
            $params['eid'] = (int) $filters['employee_id'];
        }

        $sql .= " GROUP BY t.id ORDER BY FIELD(t.status, 'in_progress', 'pending', 'completed', 'cancelled'), t.deadline ASC, t.id DESC";
        return Database::fetchAll($sql, $params);
    }
}
