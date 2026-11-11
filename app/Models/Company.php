<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class Company extends BaseModel
{
    protected string $table = 'companies';

    public function getDefault(): ?array
    {
        return Database::fetchOne('SELECT * FROM companies ORDER BY id ASC LIMIT 1');
    }
}
