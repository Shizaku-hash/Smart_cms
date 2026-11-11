<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Models\Employee;
use App\Models\Payroll;
use App\Models\Task;
use App\Models\Transaction;
use App\Services\DashboardService;
use App\Services\FinanceService;
use App\Services\PythonAnalyticsClient;
use Throwable;

/**
 * RESTful JSON API Controller (/api/v1/*) secured via session or Bearer/X-API-Token.
 */
final class ApiController extends BaseController
{
    public function health(Request $request): void
    {
        $pyHealthy = (new PythonAnalyticsClient())->isHealthy();
        $this->json([
            'status' => 'ok',
            'php_version' => PHP_VERSION,
            'python_analytics_connected' => $pyHealthy,
            'timestamp' => date('c'),
        ]);
    }

    public function dashboard(Request $request): void
    {
        $data = (new DashboardService())->getDashboardData(
            Auth::companyId(),
            $request->query('date_from') ? (string) $request->query('date_from') : null,
            $request->query('date_to') ? (string) $request->query('date_to') : null
        );
        $this->json(['ok' => true, 'data' => $data]);
    }

    public function transactions(Request $request): void
    {
        if (!Auth::can('finance.view')) {
            $this->json(['error' => 'Forbidden'], 403);
        }
        $rows = (new Transaction())->filter(Auth::companyId(), [
            'type' => $request->query('type'),
            'date_from' => $request->query('date_from'),
            'date_to' => $request->query('date_to'),
            'limit' => $request->query('limit', 100),
        ]);
        $summary = (new FinanceService())->getSummary(Auth::companyId());
        $this->json(['ok' => true, 'summary' => $summary, 'transactions' => $rows]);
    }

    public function createTransaction(Request $request): void
    {
        if (!Auth::can('finance.create')) {
            $this->json(['error' => 'Forbidden'], 403);
        }
        try {
            $id = (new FinanceService())->createTransaction(Auth::companyId(), (int) Auth::id(), $request->all());
            $this->json(['ok' => true, 'id' => $id], 201);
        } catch (Throwable $e) {
            $this->json(['ok' => false, 'error' => $e->getMessage()], 422);
        }
    }

    public function employees(Request $request): void
    {
        if (!Auth::can('employees.view_all')) {
            $own = Auth::employeeId();
            $emp = $own ? (new Employee())->findDetailed($own) : null;
            $this->json(['ok' => true, 'employees' => $emp ? [$emp] : []]);
        }
        $rows = (new Employee())->filter(Auth::companyId(), $request->query());
        $this->json(['ok' => true, 'employees' => $rows]);
    }

    public function payrolls(Request $request): void
    {
        $filters = ['year' => $request->query('year'), 'month' => $request->query('month')];
        if (!Auth::can('payroll.view_all')) {
            $filters['employee_id'] = Auth::employeeId() ?? -1;
        }
        $rows = (new Payroll())->filter(Auth::companyId(), $filters);
        $this->json(['ok' => true, 'payrolls' => $rows]);
    }

    public function tasks(Request $request): void
    {
        $filters = [];
        if (!Auth::can('tasks.manage')) {
            $filters['employee_id'] = Auth::employeeId() ?? -1;
        }
        $rows = (new Task())->filter(Auth::companyId(), $filters);
        $this->json(['ok' => true, 'tasks' => $rows]);
    }
}
