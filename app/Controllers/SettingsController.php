<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Models\ActivityLog;
use App\Models\Company;
use App\Models\Department;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\ActivityLogService;
use App\Services\BackupService;
use App\Services\CurrencyService;
use App\Services\FileUploadService;
use App\Services\PersonalizationService;
use Throwable;

final class SettingsController extends BaseController
{
    public function index(Request $request): string
    {
        $companyId = Auth::companyId();
        $tab = (string) $request->query('tab', 'personalization');

        $company = (new Company())->find($companyId);
        $currencies = (new CurrencyService())->getAllActive();
        $departments = (new Department())->forCompany($companyId);
        $roles = Auth::can('settings.permissions') ? (new Role())->getAllWithPermissionIds() : [];
        $permissions = Auth::can('settings.permissions') ? (new Permission())->all('module ASC, id ASC') : [];
        $users = Auth::can('settings.permissions') ? (new User())->allWithRole($companyId) : [];
        $logs = Auth::can('logs.view') ? (new ActivityLog())->recent($companyId, 100) : [];
        $backups = Auth::can('backup.manage') ? (new BackupService())->listBackups() : [];

        return $this->render('settings/index', [
            'title' => __('settings.title'),
            'tab' => $tab,
            'company' => $company,
            'currencies' => $currencies,
            'departments' => $departments,
            'roles' => $roles,
            'permissions' => $permissions,
            'users' => $users,
            'logs' => $logs,
            'backups' => $backups,
        ]);
    }

    public function updateCompany(Request $request): void
    {
        $companyId = Auth::companyId();
        $data = [
            'name' => trim((string) $request->input('name', '')),
            'legal_name' => trim((string) $request->input('legal_name', '')),
            'email' => trim((string) $request->input('email', '')),
            'phone' => trim((string) $request->input('phone', '')),
            'website' => trim((string) $request->input('website', '')),
            'address' => trim((string) $request->input('address', '')),
            'tax_id' => trim((string) $request->input('tax_id', '')),
            'tax_rate' => (float) $request->input('tax_rate', 0),
            'default_currency_code' => strtoupper((string) $request->input('default_currency_code', 'USD')),
            'financial_year_start_month' => max(1, min(12, (int) $request->input('financial_year_start_month', 1))),
        ];

        $logo = $request->file('logo');
        if ($logo !== null) {
            $stored = (new FileUploadService())->store($logo, 'logos');
            $data['logo_path'] = $stored['relative_path'];
        }

        (new Company())->update($companyId, $data);
        (new ActivityLogService())->log('update', 'company', $companyId, 'Updated company settings', null, $data, $companyId, Auth::id());
        $this->redirect('/settings?tab=company', ['success' => __('messages.saved')]);
    }

    public function updatePersonalization(Request $request): void
    {
        $input = $request->all();

        // Parse personalization arrays cleanly
        $payload = [];
        foreach (['language', 'theme', 'date_format', 'number_format', 'currency_format', 'default_landing_page'] as $k) {
            if (isset($input[$k])) {
                $payload[$k] = (string) $input[$k];
            }
        }
        if (array_key_exists('sidebar_collapsed_submitted', $input)) {
            $payload['sidebar_collapsed'] = !empty($input['sidebar_collapsed']) ? 1 : 0;
        }
        if (array_key_exists('hidden_sections_submitted', $input)) {
            $payload['hidden_sections_json'] = array_values((array) ($input['hidden_sections'] ?? []));
        }
        if (array_key_exists('pinned_functions_submitted', $input)) {
            $payload['pinned_functions_json'] = array_values((array) ($input['pinned_functions'] ?? []));
        }
        if (!empty($input['shortcuts_json_raw'])) {
            $decoded = json_decode((string) $input['shortcuts_json_raw'], true);
            if (is_array($decoded)) {
                $payload['shortcuts_json'] = $decoded;
            }
        }
        if (!empty($input['table_columns_json_raw'])) {
            $decoded = json_decode((string) $input['table_columns_json_raw'], true);
            if (is_array($decoded)) {
                $payload['table_columns_json'] = $decoded;
            }
        }

        (new PersonalizationService())->updatePreferences((int) Auth::id(), $payload);
        if ($request->wantsJson()) {
            $this->json(['ok' => true]);
        }
        $this->redirect('/settings?tab=' . ($input['redirect_tab'] ?? 'personalization'), ['success' => __('messages.saved')]);
    }

    public function updateRolePermissions(Request $request): void
    {
        $roleId = (int) $request->input('role_id', 0);
        $permIds = array_map('intval', (array) ($request->input('permission_ids', [])));
        if ($roleId > 0) {
            (new Role())->syncPermissions($roleId, $permIds);
            Auth::clearCache();
            (new ActivityLogService())->log('update_permissions', 'role', $roleId, "Updated permissions for role #{$roleId}", null, ['permission_ids' => $permIds], Auth::companyId(), Auth::id());
        }
        $this->redirect('/settings?tab=roles', ['success' => __('messages.saved')]);
    }

    public function storeDepartment(Request $request): void
    {
        $name = trim((string) $request->input('name', ''));
        $code = strtoupper(trim((string) $request->input('code', '')));
        if ($name === '' || $code === '') {
            $this->redirect('/settings?tab=departments', ['error' => 'Department name and code are required.']);
        }
        $id = (new Department())->create([
            'company_id' => Auth::companyId(),
            'name' => $name,
            'code' => $code,
            'description' => trim((string) $request->input('description', '')),
            'budget' => (float) $request->input('budget', 0),
        ]);
        (new ActivityLogService())->log('create', 'department', $id, "Created department {$name} ({$code})", null, null, Auth::companyId(), Auth::id());
        $this->redirect('/settings?tab=departments', ['success' => __('messages.saved')]);
    }

    public function createUser(Request $request): void
    {
        $username = trim((string) $request->input('username', ''));
        $email = trim((string) $request->input('email', ''));
        $fullName = trim((string) $request->input('full_name', ''));
        $password = (string) $request->input('password', '');
        $roleId = (int) $request->input('role_id', 0);

        if ($username === '' || $email === '' || $fullName === '' || strlen($password) < 6 || $roleId <= 0) {
            $this->redirect('/settings?tab=roles', ['error' => 'All user fields and a 6+ char password are required.']);
        }

        try {
            $uid = (new User())->create([
                'company_id' => Auth::companyId(),
                'role_id' => $roleId,
                'username' => $username,
                'email' => $email,
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                'full_name' => $fullName,
                'is_active' => 1,
            ]);
            Database::execute('INSERT INTO user_preferences (user_id) VALUES (?)', [$uid]);
            (new ActivityLogService())->log('create', 'user', $uid, "Created user account {$username}", null, null, Auth::companyId(), Auth::id());
            $this->redirect('/settings?tab=roles', ['success' => __('messages.saved')]);
        } catch (Throwable $e) {
            $this->redirect('/settings?tab=roles', ['error' => $e->getMessage()]);
        }
    }

    public function createBackup(Request $request): void
    {
        $res = (new BackupService())->createBackup(Auth::companyId(), Auth::id());
        $this->redirect('/settings?tab=backup', ['success' => "Backup {$res['filename']} created successfully."]);
    }

    public function downloadBackup(Request $request, string $filename): void
    {
        $clean = basename($filename);
        $path = rtrim((string) config('backup_path'), '/') . '/' . $clean;
        if (!is_file($path)) {
            Response::html('Backup not found', 404);
        }
        Response::downloadFile($path, $clean, 'application/sql');
    }

    public function serveLogo(Request $request): void
    {
        $company = (new Company())->find(Auth::companyId());
        if (!$company || empty($company['logo_path'])) {
            Response::html('No logo', 404);
        }
        $path = (new FileUploadService())->resolveSafePath((string) $company['logo_path']);
        if ($path === null) {
            Response::html('Missing logo', 404);
        }
        $mime = mime_content_type($path) ?: 'image/png';
        http_response_code(200);
        header('Content-Type: ' . $mime);
        readfile($path);
        exit;
    }
}
