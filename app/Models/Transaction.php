<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class Transaction extends BaseModel
{
    protected string $table = 'transactions';
    protected bool $softDeletes = true;

    public function filter(int $companyId, array $filters = []): array
    {
        $sql = "SELECT t.*,
                       COALESCE(ic.name, ec.name, 'Uncategorized') AS category_name,
                       COALESCE(ic.color, ec.color, '#64748b') AS category_color,
                       e.full_name AS employee_name,
                       e.employee_code AS employee_code,
                       d.name AS department_name
                FROM transactions t
                LEFT JOIN income_categories ic ON ic.id = t.income_category_id
                LEFT JOIN expense_categories ec ON ec.id = t.expense_category_id
                LEFT JOIN employees e ON e.id = t.employee_id
                LEFT JOIN departments d ON d.id = t.department_id
                WHERE t.company_id = :cid AND t.deleted_at IS NULL";
        $params = ['cid' => $companyId];

        if (!empty($filters['type'])) {
            $sql .= " AND t.type = :type";
            $params['type'] = $filters['type'];
        }
        if (!empty($filters['income_category_id'])) {
            $sql .= " AND t.income_category_id = :ic_id";
            $params['ic_id'] = (int) $filters['income_category_id'];
        }
        if (!empty($filters['expense_category_id'])) {
            $sql .= " AND t.expense_category_id = :ec_id";
            $params['ec_id'] = (int) $filters['expense_category_id'];
        }
        if (!empty($filters['employee_id'])) {
            $sql .= " AND t.employee_id = :emp_id";
            $params['emp_id'] = (int) $filters['employee_id'];
        }
        if (!empty($filters['payment_method'])) {
            $sql .= " AND t.payment_method = :pm";
            $params['pm'] = $filters['payment_method'];
        }
        if (!empty($filters['status'])) {
            $sql .= " AND t.status = :status";
            $params['status'] = $filters['status'];
        }
        if (!empty($filters['date_from'])) {
            $sql .= " AND t.transaction_date >= :date_from";
            $params['date_from'] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $sql .= " AND t.transaction_date <= :date_to";
            $params['date_to'] = $filters['date_to'];
        }
        if (!empty($filters['is_recurring'])) {
            $sql .= " AND t.is_recurring = 1";
        }
        if (!empty($filters['search'])) {
            $sql .= " AND (t.transaction_code LIKE :q1 OR t.description LIKE :q2 OR t.vendor_or_client LIKE :q3 OR t.reference_number LIKE :q4)";
            $like = '%' . $filters['search'] . '%';
            $params['q1'] = $like;
            $params['q2'] = $like;
            $params['q3'] = $like;
            $params['q4'] = $like;
        }

        $sql .= " ORDER BY t.transaction_date DESC, t.id DESC";
        if (!empty($filters['limit'])) {
            $sql .= " LIMIT " . (int) $filters['limit'];
        }

        return Database::fetchAll($sql, $params);
    }

    public function generateNextCode(string $type): string
    {
        $prefix = $type === 'income' ? 'INC' : 'EXP';
        $year = date('Y');
        $count = (int) Database::fetchColumn(
            'SELECT COUNT(*) FROM transactions WHERE type = :t AND YEAR(created_at) = :y',
            ['t' => $type, 'y' => (int) $year]
        );
        return sprintf('%s-%s-%04d-%d', $prefix, $year, $count + 1, random_int(10, 99));
    }
}
