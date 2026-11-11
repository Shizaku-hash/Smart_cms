<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class Role extends BaseModel
{
    protected string $table = 'roles';

    public function getAllWithPermissionIds(): array
    {
        $roles = $this->all('id ASC');
        $mappings = Database::fetchAll('SELECT role_id, permission_id FROM role_permissions');
        $byRole = [];
        foreach ($mappings as $m) {
            $byRole[(int) $m['role_id']][] = (int) $m['permission_id'];
        }
        foreach ($roles as &$role) {
            $role['permission_ids'] = $byRole[(int) $role['id']] ?? [];
        }
        return $roles;
    }

    public function syncPermissions(int $roleId, array $permissionIds): void
    {
        Database::transaction(function () use ($roleId, $permissionIds): void {
            Database::execute('DELETE FROM role_permissions WHERE role_id = :rid', ['rid' => $roleId]);
            foreach ($permissionIds as $pid) {
                Database::execute(
                    'INSERT IGNORE INTO role_permissions (role_id, permission_id) VALUES (:rid, :pid)',
                    ['rid' => $roleId, 'pid' => (int) $pid]
                );
            }
        });
    }
}
