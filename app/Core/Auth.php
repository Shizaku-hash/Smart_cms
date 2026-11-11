<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Session & API-token authentication and RBAC authorization manager.
 */
final class Auth
{
    private static ?array $cachedUser = null;
    private static ?array $cachedPermissions = null;
    private static ?array $cachedPreferences = null;

    public static function startSession(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE || PHP_SAPI === 'cli') {
            return;
        }

        session_set_cookie_params([
            'lifetime' => 86400,
            'path' => '/',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }

    public static function attempt(string $identifier, string $password): ?array
    {
        $sql = "SELECT u.*, r.slug AS role_slug, r.name AS role_name, e.id AS employee_id
                FROM users u
                INNER JOIN roles r ON r.id = u.role_id
                LEFT JOIN employees e ON e.user_id = u.id AND e.deleted_at IS NULL
                WHERE (u.email = :email OR u.username = :username)
                  AND u.deleted_at IS NULL
                  AND u.is_active = 1
                LIMIT 1";
        $user = Database::fetchOne($sql, [
            'email' => trim($identifier),
            'username' => trim($identifier),
        ]);

        if ($user === null || !password_verify($password, (string) $user['password_hash'])) {
            return null;
        }

        self::loginUser($user);

        Database::execute(
            'UPDATE users SET last_login_at = NOW() WHERE id = :id',
            ['id' => (int) $user['id']]
        );

        return $user;
    }

    public static function loginUser(array $user): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
            $_SESSION['auth_user_id'] = (int) $user['id'];
        }
        self::$cachedUser = $user;
        self::$cachedPermissions = null;
        self::$cachedPreferences = null;
    }

    public static function loginById(int $userId): ?array
    {
        $sql = "SELECT u.*, r.slug AS role_slug, r.name AS role_name, e.id AS employee_id
                FROM users u
                INNER JOIN roles r ON r.id = u.role_id
                LEFT JOIN employees e ON e.user_id = u.id AND e.deleted_at IS NULL
                WHERE u.id = :id AND u.deleted_at IS NULL AND u.is_active = 1
                LIMIT 1";
        $user = Database::fetchOne($sql, ['id' => $userId]);
        if ($user !== null) {
            self::loginUser($user);
        }
        return $user;
    }

    public static function logout(): void
    {
        self::$cachedUser = null;
        self::$cachedPermissions = null;
        self::$cachedPreferences = null;

        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION = [];
            if (ini_get('session.use_cookies')) {
                $params = session_get_cookie_params();
                setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'] ?? '', (bool) $params['secure'], (bool) $params['httponly']);
            }
            session_destroy();
        }
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    public static function user(): ?array
    {
        if (self::$cachedUser !== null) {
            return self::$cachedUser;
        }

        $userId = $_SESSION['auth_user_id'] ?? null;
        if (!$userId) {
            return null;
        }

        $sql = "SELECT u.*, r.slug AS role_slug, r.name AS role_name, e.id AS employee_id
                FROM users u
                INNER JOIN roles r ON r.id = u.role_id
                LEFT JOIN employees e ON e.user_id = u.id AND e.deleted_at IS NULL
                WHERE u.id = :id AND u.deleted_at IS NULL AND u.is_active = 1
                LIMIT 1";
        $user = Database::fetchOne($sql, ['id' => (int) $userId]);
        if ($user === null) {
            unset($_SESSION['auth_user_id']);
            return null;
        }

        self::$cachedUser = $user;
        return $user;
    }

    public static function id(): ?int
    {
        $u = self::user();
        return $u ? (int) $u['id'] : null;
    }

    public static function companyId(): int
    {
        $u = self::user();
        return $u ? (int) $u['company_id'] : 1;
    }

    public static function employeeId(): ?int
    {
        $u = self::user();
        return ($u && !empty($u['employee_id'])) ? (int) $u['employee_id'] : null;
    }

    public static function roleSlug(): string
    {
        $u = self::user();
        return $u ? (string) $u['role_slug'] : 'guest';
    }

    public static function permissions(): array
    {
        if (self::$cachedPermissions !== null) {
            return self::$cachedPermissions;
        }

        $user = self::user();
        if ($user === null) {
            return [];
        }

        if ($user['role_slug'] === 'super_admin') {
            $rows = Database::fetchAll('SELECT slug FROM permissions');
        } else {
            $rows = Database::fetchAll(
                'SELECT p.slug FROM permissions p
                 INNER JOIN role_permissions rp ON rp.permission_id = p.id
                 WHERE rp.role_id = :role_id',
                ['role_id' => (int) $user['role_id']]
            );
        }

        self::$cachedPermissions = array_column($rows, 'slug');
        return self::$cachedPermissions;
    }

    public static function can(string $permissionSlug): bool
    {
        $user = self::user();
        if ($user === null) {
            return false;
        }
        if ($user['role_slug'] === 'super_admin') {
            return true;
        }
        return in_array($permissionSlug, self::permissions(), true);
    }

    public static function hasRole(string|array $roles): bool
    {
        $slug = self::roleSlug();
        $list = is_array($roles) ? $roles : [$roles];
        return in_array($slug, $list, true);
    }

    public static function preferences(): array
    {
        if (self::$cachedPreferences !== null) {
            return self::$cachedPreferences;
        }

        $userId = self::id();
        $defaults = [
            'language' => 'en',
            'theme' => 'light',
            'date_format' => 'Y-m-d',
            'number_format' => 'comma_dot',
            'currency_format' => 'symbol_before',
            'default_landing_page' => '/dashboard',
            'sidebar_collapsed' => 0,
            'dashboard_widgets_json' => [
                ['id' => 'kpi_cards', 'visible' => true, 'order' => 1],
                ['id' => 'income_expense_chart', 'visible' => true, 'order' => 2],
                ['id' => 'profit_chart', 'visible' => true, 'order' => 3],
                ['id' => 'expense_categories_chart', 'visible' => true, 'order' => 4],
                ['id' => 'salary_distribution_chart', 'visible' => true, 'order' => 5],
                ['id' => 'performance_chart', 'visible' => true, 'order' => 6],
                ['id' => 'recent_transactions', 'visible' => true, 'order' => 7],
                ['id' => 'upcoming_salaries', 'visible' => true, 'order' => 8],
            ],
            'hidden_sections_json' => [],
            'pinned_functions_json' => ['/finance', '/payroll', '/reports'],
            'default_filters_json' => [],
            'table_columns_json' => [],
            'shortcuts_json' => [
                ['label' => 'Add Income', 'url' => '/finance?tab=income&action=new', 'icon' => 'bi-plus-circle'],
                ['label' => 'Run Payroll', 'url' => '/payroll', 'icon' => 'bi-cash-stack'],
                ['label' => 'Report Builder', 'url' => '/reports/builder', 'icon' => 'bi-file-earmark-bar-graph'],
            ],
            'notification_prefs_json' => [
                'salary_upcoming' => true,
                'expense_pending' => true,
                'financial_alert' => true,
                'task_deadline' => true,
                'task_assigned' => true,
                'employee_update' => true,
                'system' => true,
            ],
        ];

        if ($userId === null) {
            self::$cachedPreferences = $defaults;
            return $defaults;
        }

        $row = Database::fetchOne('SELECT * FROM user_preferences WHERE user_id = :uid LIMIT 1', ['uid' => $userId]);
        if ($row === null) {
            self::$cachedPreferences = $defaults;
            return $defaults;
        }

        foreach (['dashboard_widgets_json', 'hidden_sections_json', 'pinned_functions_json', 'default_filters_json', 'table_columns_json', 'shortcuts_json', 'notification_prefs_json'] as $jsonCol) {
            if (!empty($row[$jsonCol]) && is_string($row[$jsonCol])) {
                $decoded = json_decode($row[$jsonCol], true);
                $row[$jsonCol] = is_array($decoded) ? $decoded : $defaults[$jsonCol];
            } else {
                $row[$jsonCol] = $defaults[$jsonCol];
            }
        }

        self::$cachedPreferences = array_merge($defaults, $row);
        return self::$cachedPreferences;
    }

    public static function clearCache(): void
    {
        self::$cachedUser = null;
        self::$cachedPermissions = null;
        self::$cachedPreferences = null;
    }

    public static function authenticateApiToken(Request $request): bool
    {
        $authHeader = $request->header('Authorization', '') ?? '';
        $apiToken = $request->header('X-API-Token', '') ?? '';
        $bearer = '';
        if (str_starts_with($authHeader, 'Bearer ')) {
            $bearer = trim(substr($authHeader, 7));
        }

        $submitted = $bearer !== '' ? $bearer : trim($apiToken);
        $expected = (string) config('api_public_token', '');
        if ($submitted !== '' && $expected !== '' && hash_equals($expected, $submitted)) {
            if (!self::check()) {
                // Bind API service token session context to Super Admin user (id 1) if no browser session
                $admin = Database::fetchOne(
                    "SELECT u.*, r.slug AS role_slug, r.name AS role_name, e.id AS employee_id
                     FROM users u
                     INNER JOIN roles r ON r.id = u.role_id
                     LEFT JOIN employees e ON e.user_id = u.id AND e.deleted_at IS NULL
                     WHERE r.slug = 'super_admin' AND u.deleted_at IS NULL
                     ORDER BY u.id ASC LIMIT 1"
                );
                if ($admin !== null) {
                    self::$cachedUser = $admin;
                }
            }
            return true;
        }

        return self::check();
    }
}
