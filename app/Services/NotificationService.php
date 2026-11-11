<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

final class NotificationService
{
    public function notify(
        int $companyId,
        ?int $userId,
        string $type,
        string $title,
        string $message,
        ?string $linkUrl = null,
        string $severity = 'info'
    ): void {
        Database::execute(
            "INSERT INTO notifications (company_id, user_id, type, title, message, link_url, severity)
             VALUES (:cid, :uid, :type, :title, :msg, :link, :sev)",
            [
                'cid' => $companyId,
                'uid' => $userId,
                'type' => $type,
                'title' => mb_substr($title, 0, 190),
                'msg' => $message,
                'link' => $linkUrl,
                'sev' => $severity,
            ]
        );
    }

    public function notifyRole(
        int $companyId,
        string $roleSlug,
        string $type,
        string $title,
        string $message,
        ?string $linkUrl = null,
        string $severity = 'info'
    ): void {
        $users = Database::fetchAll(
            "SELECT u.id FROM users u INNER JOIN roles r ON r.id = u.role_id
             WHERE u.company_id = :cid AND r.slug = :slug AND u.is_active = 1 AND u.deleted_at IS NULL",
            ['cid' => $companyId, 'slug' => $roleSlug]
        );
        foreach ($users as $u) {
            $this->notify($companyId, (int) $u['id'], $type, $title, $message, $linkUrl, $severity);
        }
    }

    public function markRead(int $notificationId, int $userId): void
    {
        Database::execute(
            "UPDATE notifications SET is_read = 1, read_at = NOW() WHERE id = :id AND (user_id = :uid OR user_id IS NULL)",
            ['id' => $notificationId, 'uid' => $userId]
        );
    }

    public function markAllRead(int $companyId, int $userId): void
    {
        Database::execute(
            "UPDATE notifications SET is_read = 1, read_at = NOW() WHERE company_id = :cid AND (user_id = :uid OR user_id IS NULL) AND is_read = 0",
            ['cid' => $companyId, 'uid' => $userId]
        );
    }
}
