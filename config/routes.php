<?php

declare(strict_types=1);

use App\Controllers\ApiController;
use App\Controllers\AuthController;
use App\Controllers\DashboardController;
use App\Controllers\EmployeeController;
use App\Controllers\FinanceController;
use App\Controllers\NotificationController;
use App\Controllers\PayrollController;
use App\Controllers\PerformanceController;
use App\Controllers\ReportController;
use App\Controllers\SettingsController;
use App\Controllers\TaskController;
use App\Core\Router;
use App\Middleware\AuthMiddleware;
use App\Middleware\CsrfMiddleware;
use App\Middleware\PermissionMiddleware;

$router = new Router();

// Public Auth Routes
$router->get('/', [AuthController::class, 'showLogin']);
$router->get('/login', [AuthController::class, 'showLogin']);
$router->post('/login', [AuthController::class, 'login'], [CsrfMiddleware::class]);
$router->post('/logout', [AuthController::class, 'logout'], [AuthMiddleware::class, CsrfMiddleware::class]);
$router->post('/language/switch', [AuthController::class, 'switchLanguage'], [CsrfMiddleware::class]);

// Protected Web Routes
$auth = [AuthMiddleware::class];
$authCsrf = [AuthMiddleware::class, CsrfMiddleware::class];

// 1. Dashboard
$router->get('/dashboard', [DashboardController::class, 'index'], [AuthMiddleware::class, PermissionMiddleware::class . ':dashboard.view']);
$router->post('/dashboard/widgets', [DashboardController::class, 'saveWidgets'], [AuthMiddleware::class, CsrfMiddleware::class, PermissionMiddleware::class . ':settings.personalize']);

// 2. Finance
$router->get('/finance', [FinanceController::class, 'index'], [AuthMiddleware::class, PermissionMiddleware::class . ':finance.view']);
$router->post('/finance/transactions', [FinanceController::class, 'storeTransaction'], [AuthMiddleware::class, CsrfMiddleware::class, PermissionMiddleware::class . ':finance.create']);
$router->post('/finance/transactions/{id}/update', [FinanceController::class, 'updateTransaction'], [AuthMiddleware::class, CsrfMiddleware::class, PermissionMiddleware::class . ':finance.edit']);
$router->post('/finance/transactions/{id}/approve', [FinanceController::class, 'approveTransaction'], [AuthMiddleware::class, CsrfMiddleware::class, PermissionMiddleware::class . ':finance.approve']);
$router->post('/finance/transactions/{id}/delete', [FinanceController::class, 'deleteTransaction'], [AuthMiddleware::class, CsrfMiddleware::class, PermissionMiddleware::class . ':finance.delete']);
$router->post('/finance/recurring/process', [FinanceController::class, 'processRecurring'], [AuthMiddleware::class, CsrfMiddleware::class, PermissionMiddleware::class . ':finance.create']);
$router->post('/finance/categories', [FinanceController::class, 'storeCategory'], [AuthMiddleware::class, CsrfMiddleware::class, PermissionMiddleware::class . ':finance.edit']);
$router->post('/finance/exchange-rates', [FinanceController::class, 'saveExchangeRate'], [AuthMiddleware::class, CsrfMiddleware::class, PermissionMiddleware::class . ':finance.currencies']);
$router->get('/finance/transactions/{id}/attachment', [FinanceController::class, 'downloadAttachment'], [AuthMiddleware::class, PermissionMiddleware::class . ':finance.view']);

// 3. Employees
$router->get('/employees', [EmployeeController::class, 'index'], [AuthMiddleware::class, PermissionMiddleware::class . ':employees.view_own']);
$router->post('/employees', [EmployeeController::class, 'store'], [AuthMiddleware::class, CsrfMiddleware::class, PermissionMiddleware::class . ':employees.create']);
$router->get('/employees/{id}', [EmployeeController::class, 'show'], [AuthMiddleware::class, PermissionMiddleware::class . ':employees.view_own']);
$router->post('/employees/{id}/update', [EmployeeController::class, 'update'], [AuthMiddleware::class, CsrfMiddleware::class, PermissionMiddleware::class . ':employees.view_own']);
$router->post('/employees/{id}/delete', [EmployeeController::class, 'delete'], [AuthMiddleware::class, CsrfMiddleware::class, PermissionMiddleware::class . ':employees.delete']);
$router->post('/employees/{id}/documents', [EmployeeController::class, 'uploadDocument'], [AuthMiddleware::class, CsrfMiddleware::class, PermissionMiddleware::class . ':employees.edit']);
$router->get('/employees/documents/{docId}/download', [EmployeeController::class, 'downloadDocument'], [AuthMiddleware::class, PermissionMiddleware::class . ':employees.view_own']);
$router->post('/employees/documents/{docId}/delete', [EmployeeController::class, 'deleteDocument'], [AuthMiddleware::class, CsrfMiddleware::class, PermissionMiddleware::class . ':employees.edit']);
$router->get('/employees/{id}/photo', [EmployeeController::class, 'servePhoto'], $auth);

// 4. Payroll
$router->get('/payroll', [PayrollController::class, 'index'], [AuthMiddleware::class, PermissionMiddleware::class . ':payroll.view_own']);
$router->post('/payroll/calculate', [PayrollController::class, 'calculate'], [AuthMiddleware::class, CsrfMiddleware::class, PermissionMiddleware::class . ':payroll.calculate']);
$router->post('/payroll/{id}/components', [PayrollController::class, 'updateComponents'], [AuthMiddleware::class, CsrfMiddleware::class, PermissionMiddleware::class . ':payroll.calculate']);
$router->post('/payroll/bonuses', [PayrollController::class, 'addBonus'], [AuthMiddleware::class, CsrfMiddleware::class, PermissionMiddleware::class . ':payroll.calculate']);
$router->post('/payroll/deductions', [PayrollController::class, 'addDeduction'], [AuthMiddleware::class, CsrfMiddleware::class, PermissionMiddleware::class . ':payroll.calculate']);
$router->post('/payroll/deductions/{id}/authorize', [PayrollController::class, 'authorizeDeduction'], [AuthMiddleware::class, CsrfMiddleware::class, PermissionMiddleware::class . ':payroll.authorize_deductions']);
$router->post('/payroll/{id}/pay', [PayrollController::class, 'markPaid'], [AuthMiddleware::class, CsrfMiddleware::class, PermissionMiddleware::class . ':payroll.pay']);
$router->get('/payroll/{id}/statement', [PayrollController::class, 'statement'], [AuthMiddleware::class, PermissionMiddleware::class . ':payroll.view_own']);
$router->get('/payroll/export', [PayrollController::class, 'exportList'], [AuthMiddleware::class, PermissionMiddleware::class . ':payroll.view_all']);

// 5. Tasks
$router->get('/tasks', [TaskController::class, 'index'], [AuthMiddleware::class, PermissionMiddleware::class . ':tasks.view']);
$router->post('/tasks', [TaskController::class, 'store'], [AuthMiddleware::class, CsrfMiddleware::class, PermissionMiddleware::class . ':tasks.manage']);
$router->post('/tasks/{id}/status', [TaskController::class, 'updateStatus'], [AuthMiddleware::class, CsrfMiddleware::class, PermissionMiddleware::class . ':tasks.view']);
$router->post('/tasks/{id}/delete', [TaskController::class, 'delete'], [AuthMiddleware::class, CsrfMiddleware::class, PermissionMiddleware::class . ':tasks.manage']);

// 6. Performance & Attendance
$router->get('/performance', [PerformanceController::class, 'index'], [AuthMiddleware::class, PermissionMiddleware::class . ':performance.view']);
$router->post('/performance/evaluate', [PerformanceController::class, 'evaluate'], [AuthMiddleware::class, CsrfMiddleware::class, PermissionMiddleware::class . ':performance.manage']);
$router->post('/performance/attendance', [PerformanceController::class, 'recordAttendance'], [AuthMiddleware::class, CsrfMiddleware::class, PermissionMiddleware::class . ':performance.manage']);
$router->post('/performance/rules', [PerformanceController::class, 'updateRules'], [AuthMiddleware::class, CsrfMiddleware::class, PermissionMiddleware::class . ':performance.manage']);

// 7. Reports
$router->get('/reports', [ReportController::class, 'index'], [AuthMiddleware::class, PermissionMiddleware::class . ':reports.view']);
$router->get('/reports/builder', [ReportController::class, 'builder'], [AuthMiddleware::class, PermissionMiddleware::class . ':reports.view']);
$router->post('/reports/preview', [ReportController::class, 'preview'], [AuthMiddleware::class, CsrfMiddleware::class, PermissionMiddleware::class . ':reports.view']);
$router->post('/reports/export', [ReportController::class, 'export'], [AuthMiddleware::class, CsrfMiddleware::class, PermissionMiddleware::class . ':reports.export']);
$router->post('/reports/templates', [ReportController::class, 'saveTemplate'], [AuthMiddleware::class, CsrfMiddleware::class, PermissionMiddleware::class . ':reports.templates']);
$router->post('/reports/templates/{id}/delete', [ReportController::class, 'deleteTemplate'], [AuthMiddleware::class, CsrfMiddleware::class, PermissionMiddleware::class . ':reports.templates']);

// 8. Notifications
$router->get('/notifications', [NotificationController::class, 'index'], $auth);
$router->post('/notifications/{id}/read', [NotificationController::class, 'markRead'], $authCsrf);
$router->post('/notifications/read-all', [NotificationController::class, 'markAllRead'], $authCsrf);
$router->post('/notifications/preferences', [NotificationController::class, 'savePreferences'], $authCsrf);

// 9. Settings, RBAC, Logs & Backup
$router->get('/settings', [SettingsController::class, 'index'], $auth);
$router->post('/settings/company', [SettingsController::class, 'updateCompany'], [AuthMiddleware::class, CsrfMiddleware::class, PermissionMiddleware::class . ':settings.company']);
$router->get('/settings/company/logo', [SettingsController::class, 'serveLogo'], $auth);
$router->post('/settings/personalization', [SettingsController::class, 'updatePersonalization'], $authCsrf);
$router->post('/settings/roles/permissions', [SettingsController::class, 'updateRolePermissions'], [AuthMiddleware::class, CsrfMiddleware::class, PermissionMiddleware::class . ':settings.permissions']);
$router->post('/settings/users', [SettingsController::class, 'createUser'], [AuthMiddleware::class, CsrfMiddleware::class, PermissionMiddleware::class . ':settings.permissions']);
$router->post('/settings/departments', [SettingsController::class, 'storeDepartment'], [AuthMiddleware::class, CsrfMiddleware::class, PermissionMiddleware::class . ':settings.company']);
$router->post('/settings/backup/create', [SettingsController::class, 'createBackup'], [AuthMiddleware::class, CsrfMiddleware::class, PermissionMiddleware::class . ':backup.manage']);
$router->get('/settings/backup/{filename}/download', [SettingsController::class, 'downloadBackup'], [AuthMiddleware::class, PermissionMiddleware::class . ':backup.manage']);

// REST API v1 (Session or API Token auth)
$router->get('/api/v1/health', [ApiController::class, 'health']);
$router->get('/api/v1/dashboard', [ApiController::class, 'dashboard'], $auth);
$router->get('/api/v1/transactions', [ApiController::class, 'transactions'], $auth);
$router->post('/api/v1/transactions', [ApiController::class, 'createTransaction'], $authCsrf);
$router->get('/api/v1/employees', [ApiController::class, 'employees'], $auth);
$router->get('/api/v1/payrolls', [ApiController::class, 'payrolls'], $auth);
$router->get('/api/v1/tasks', [ApiController::class, 'tasks'], $auth);

return $router;
