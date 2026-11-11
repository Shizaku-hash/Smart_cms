<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class EmployeeDocument extends BaseModel
{
    protected string $table = 'employee_documents';
    protected bool $softDeletes = true;

    public function forEmployee(int $employeeId): array
    {
        return Database::fetchAll(
            "SELECT d.*, u.full_name AS uploader_name
             FROM employee_documents d
             LEFT JOIN users u ON u.id = d.uploaded_by
             WHERE d.employee_id = :eid AND d.deleted_at IS NULL
             ORDER BY d.created_at DESC",
            ['eid' => $employeeId]
        );
    }
}
