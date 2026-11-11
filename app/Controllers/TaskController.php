<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Validator;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Task;
use App\Services\TaskPerformanceService;
use Throwable;

final class TaskController extends BaseController
{
    public function index(Request $request): string
    {
        $companyId = Auth::companyId();
        $canManage = Auth::can('tasks.manage');
        $filters = [
            'status' => $request->query('status'),
            'priority' => $request->query('priority'),
            'employee_id' => $canManage ? $request->query('employee_id') : Auth::employeeId(),
        ];

        $tasks = (new Task())->filter($companyId, $filters);
        $employees = (new Employee())->filter($companyId);
        $departments = (new Department())->forCompany($companyId);

        return $this->render('tasks/index', [
            'title' => __('tasks.title'),
            'tasks' => $tasks,
            'employees' => $employees,
            'departments' => $departments,
            'filters' => $filters,
            'canManage' => $canManage,
        ]);
    }

    public function store(Request $request): void
    {
        $data = $request->all();
        $v = Validator::make($data, ['title' => 'required|min:2']);
        if ($v->fails()) {
            $this->redirect('/tasks', ['error' => $v->firstError()]);
        }

        try {
            (new TaskPerformanceService())->createTask(Auth::companyId(), (int) Auth::id(), $data);
            $this->redirect('/tasks', ['success' => __('messages.saved')]);
        } catch (Throwable $e) {
            $this->redirect('/tasks', ['error' => $e->getMessage()]);
        }
    }

    public function updateStatus(Request $request, string $id): void
    {
        try {
            (new TaskPerformanceService())->updateTaskStatus(
                (int) $id,
                Auth::companyId(),
                (int) Auth::id(),
                (string) $request->input('status', 'in_progress'),
                (int) $request->input('progress_percent', 50),
                (string) $request->input('completion_notes', '')
            );
            $this->redirect('/tasks', ['success' => __('messages.saved')]);
        } catch (Throwable $e) {
            $this->redirect('/tasks', ['error' => $e->getMessage()]);
        }
    }

    public function delete(Request $request, string $id): void
    {
        (new Task())->delete((int) $id);
        $this->redirect('/tasks', ['success' => __('messages.deleted')]);
    }
}
