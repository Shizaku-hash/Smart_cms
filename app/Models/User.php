<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class User extends BaseModel
{
    protected string $table = 'users';
    protected bool $softDeletes = true;

    public function allWithRole(int $companyId): array
    {
        return Database::fetchAll(
            "SELECT u.*, r.name AS role_name, r.slug AS role_slug
             FROM users u
             INNER JOIN roles r ON r.id = u.role_id
             WHERE u.company_id = :cid AND u.deleted_at IS NULL
             ORDER BY u.id ASC",
            ['cid' => $companyId]
        );
    }
}
