<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class UserPreference extends BaseModel
{
    protected string $table = 'user_preferences';

    public function saveForUser(int $userId, array $data): void
    {
        $existing = Database::fetchOne('SELECT id FROM user_preferences WHERE user_id = :uid LIMIT 1', ['uid' => $userId]);
        if ($existing === null) {
            $data['user_id'] = $userId;
            $this->create($data);
        } else {
            $this->update((int) $existing['id'], $data);
        }
    }
}
