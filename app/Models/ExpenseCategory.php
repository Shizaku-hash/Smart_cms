<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class ExpenseCategory extends BaseModel
{
    protected string $table = 'expense_categories';
    protected bool $softDeletes = true;

    public function forCompany(int $companyId): array
    {
        return Database::fetchAll(
            'SELECT * FROM expense_categories WHERE company_id = :cid AND deleted_at IS NULL ORDER BY name ASC',
            ['cid' => $companyId]
        );
    }
}
