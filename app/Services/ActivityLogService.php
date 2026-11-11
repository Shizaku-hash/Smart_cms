<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\Database;

final class ActivityLogService
{
    public function log(
        string $action,
        string $entityType,
        ?int $entityId,
        string $description,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?int $companyId = null,
        ?int $userId = null
    ): void {
        try {
            $cid = $companyId ?? Auth::companyId();
            $uid = $userId ?? Auth::id();
            $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
            $ua = substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? 'CLI'), 0, 250);

            Database::execute(
                "INSERT INTO activity_logs (company_id, user_id, action, entity_type, entity_id, description, old_values_json, new_values_json, ip_address, user_agent)
                 VALUES (:cid, :uid, :action, :etype, :eid, :desc, :old_json, :new_json, :ip, :ua)",
                [
                    'cid' => $cid,
                    'uid' => $uid,
                    'action' => $action,
                    'etype' => $entityType,
                    'eid' => $entityId,
                    'desc' => mb_substr($description, 0, 250),
                    'old_json' => $oldValues !== null ? json_encode($oldValues, JSON_UNESCAPED_UNICODE) : null,
                    'new_json' => $newValues !== null ? json_encode($newValues, JSON_UNESCAPED_UNICODE) : null,
                    'ip' => $ip,
                    'ua' => $ua,
                ]
            );
        } catch (\Throwable) {
            // Never crash business operations if logging fails
        }
    }
}
