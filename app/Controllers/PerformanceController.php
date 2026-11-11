<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Models\ApplicationSetting;
use App\Models\Attendance;
use App\Models\Employee;
use App\Models\PerformanceRecord;
use App\Services\TaskPerformanceService;
use Throwable;

final class PerformanceController extends BaseController
{
    public function index(Request $request): string
    {
        $companyId = Auth::companyId();
        $canManage = Auth::can('performance.manage');
        $empFilter = $canManage ? ($request->query('employee_id') ? (int) $request->query('employee_id') : null) : Auth::employeeId();

        $records = (new PerformanceRecord())->forCompany($companyId, $empFilter);
        $attendance = (new Attendance())->filter($companyId, $empFilter);
        $employees = $canManage ? (new Employee())->filter($companyId) : [];
        $settings = (new ApplicationSetting())->getMap($companyId);

        return $this->render('performance/index', [
            'title' => __('performance.title'),
            'records' => $records,
            'attendance' => array_slice($attendance, 0, 40),
            'employees' => $employees,
            'settings' => $settings,
            'canManage' => $canManage,
        ]);
    }

    public function evaluate(Request $request): void
    {
        try {
            (new TaskPerformanceService())->evaluateEmployeePerformance(Auth::companyId(), (int) Auth::id(), $request->all());
            $this->redirect('/performance', ['success' => __('messages.saved')]);
        } catch (Throwable $e) {
            $this->redirect('/performance', ['error' => $e->getMessage()]);
        }
    }

    public function recordAttendance(Request $request): void
    {
        try {
            (new TaskPerformanceService())->recordAttendance(Auth::companyId(), (int) Auth::id(), $request->all());
            $this->redirect('/performance', ['success' => __('messages.saved')]);
        } catch (Throwable $e) {
            $this->redirect('/performance', ['error' => $e->getMessage()]);
        }
    }

    public function updateRules(Request $request): void
    {
        $companyId = Auth::companyId();
        $userId = Auth::id();
        $settingModel = new ApplicationSetting();
        $keys = [
            'perf_weight_task_completion' => 'float',
            'perf_weight_on_time' => 'float',
            'perf_weight_attendance' => 'float',
            'perf_weight_manager_rating' => 'float',
            'perf_bonus_tier_gold_min' => 'float',
            'perf_bonus_tier_gold_percent' => 'float',
            'perf_bonus_tier_silver_min' => 'float',
            'perf_bonus_tier_silver_percent' => 'float',
            'task_completion_auto_bonus_enabled' => 'boolean',
        ];
        foreach ($keys as $k => $type) {
            $val = $type === 'boolean' ? ($request->input($k) ? '1' : '0') : $request->input($k);
            if ($val !== null) {
                $settingModel->upsert($companyId, $k, $val, $type, 'performance', $userId);
            }
        }
        $this->redirect('/performance', ['success' => __('messages.saved')]);
    }
}
