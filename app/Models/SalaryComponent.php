<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class SalaryComponent extends BaseModel
{
    protected string $table = 'salary_components';

    public function forPayroll(int $payrollId): array
    {
        return Database::fetchAll(
            'SELECT * FROM salary_components WHERE payroll_id = :pid ORDER BY id ASC',
            ['pid' => $payrollId]
        );
    }
}
