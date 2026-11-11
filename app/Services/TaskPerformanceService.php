<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Models\ApplicationSetting;
use App\Models\Attendance;
use App\Models\Bonus;
use App\Models\PerformanceRecord;
use App\Models\Task;
use App\Models\TaskAssignment;
use RuntimeException;

final class TaskPerformanceService
{
    private Task $taskModel;
    private TaskAssignment $assignmentModel;
    private PerformanceRecord $perfModel;
    private Attendance $attModel;
    private Bonus $bonusModel;
    private ApplicationSetting $settingsModel;
    private PythonAnalyticsClient $pythonClient;
    private ActivityLogService $logger;
    private NotificationService $notifier;

    public function __construct()
    {
        $this->taskModel = new Task();
        $this->assignmentModel = new TaskAssignment();
        $this->perfModel = new PerformanceRecord();
        $this->attModel = new Attendance();
        $this->bonusModel = new Bonus();
        $this->settingsModel = new ApplicationSetting();
        $this->pythonClient = new PythonAnalyticsClient();
        $this->logger = new ActivityLogService();
        $this->notifier = new NotificationService();
    }

    public function createTask(int $companyId, int $userId, array $data): int
    {
        $assigneeIds = array_filter(array_map('intval', (array) ($data['assignee_ids'] ?? [])));

        return Database::transaction(function () use ($companyId, $userId, $data, $assigneeIds): int {
            $count = (int) Database::fetchColumn('SELECT COUNT(*) FROM tasks WHERE company_id = :cid', ['cid' => $companyId]);
            $code = sprintf('TSK-%03d', $count + 101);

            $taskId = $this->taskModel->create([
                'company_id' => $companyId,
                'department_id' => !empty($data['department_id']) ? (int) $data['department_id'] : null,
                'task_code' => $code,
                'title' => trim((string) $data['title']),
                'description' => $data['description'] ?? null,
                'priority' => $data['priority'] ?? 'medium',
                'status' => $data['status'] ?? 'pending',
                'is_project_task' => !empty($data['is_project_task']) ? 1 : 0,
                'project_name' => $data['project_name'] ?? null,
                'reward_amount' => round((float) ($data['reward_amount'] ?? 0), 2),
                'currency_code' => $data['currency_code'] ?? 'USD',
                'deadline' => !empty($data['deadline']) ? $data['deadline'] : null,
                'progress_percent' => (int) ($data['progress_percent'] ?? 0),
                'created_by' => $userId,
            ]);

            $share = count($assigneeIds) > 0 ? round(100.0 / count($assigneeIds), 2) : 100.0;
            foreach ($assigneeIds as $eid) {
                $this->assignmentModel->create([
                    'task_id' => $taskId,
                    'employee_id' => $eid,
                    'assigned_by' => $userId,
                    'reward_share_percent' => $share,
                    'completion_status' => 'assigned',
                ]);

                $empUserId = Database::fetchColumn('SELECT user_id FROM employees WHERE id = :eid LIMIT 1', ['eid' => $eid]);
                if ($empUserId) {
                    $this->notifier->notify($companyId, (int) $empUserId, 'task_assigned', 'New Task Assigned', "You have been assigned {$code}: {$data['title']}", '/tasks', 'info');
                }
            }

            $this->logger->log('create', 'task', $taskId, "Created task {$code}: {$data['title']}", null, $data, $companyId, $userId);
            return $taskId;
        });
    }

    public function updateTaskStatus(int $taskId, int $companyId, int $userId, string $status, int $progress, string $notes = ''): void
    {
        $task = $this->taskModel->find($taskId);
        if ($task === null || (int) $task['company_id'] !== $companyId) {
            throw new RuntimeException('Task not found.');
        }

        $settings = $this->settingsModel->getMap($companyId);
        $autoBonus = (bool) ($settings['task_completion_auto_bonus_enabled'] ?? true);

        Database::transaction(function () use ($taskId, $task, $companyId, $userId, $status, $progress, $notes, $autoBonus): void {
            $update = [
                'status' => $status,
                'progress_percent' => $status === 'completed' ? 100 : max(0, min(100, $progress)),
                'completion_notes' => $notes !== '' ? $notes : $task['completion_notes'],
            ];
            if ($status === 'completed' && empty($task['completed_at'])) {
                $update['completed_at'] = date('Y-m-d H:i:s');
            }
            $this->taskModel->update($taskId, $update);

            if ($status === 'completed' && $task['status'] !== 'completed') {
                Database::execute(
                    "UPDATE task_assignments SET completion_status = 'verified', completed_work_notes = :notes WHERE task_id = :tid",
                    ['notes' => $notes, 'tid' => $taskId]
                );

                // Award configured task reward bonus to assigned employees if enabled
                if ($autoBonus && (float) $task['reward_amount'] > 0) {
                    $assignees = Database::fetchAll('SELECT employee_id, reward_share_percent FROM task_assignments WHERE task_id = :tid', ['tid' => $taskId]);
                    foreach ($assignees as $a) {
                        $shareAmt = round((float) $task['reward_amount'] * ((float) $a['reward_share_percent'] / 100.0), 2);
                        if ($shareAmt > 0) {
                            $this->bonusModel->create([
                                'company_id' => $companyId,
                                'employee_id' => (int) $a['employee_id'],
                                'bonus_type' => 'task',
                                'title' => "Task Reward: {$task['task_code']} — {$task['title']}",
                                'amount' => $shareAmt,
                                'currency_code' => $task['currency_code'],
                                'awarded_date' => date('Y-m-d'),
                                'status' => 'approved',
                                'approved_by' => $userId,
                                'created_by' => $userId,
                            ]);
                        }
                    }
                }
            }

            $this->logger->log('update_status', 'task', $taskId, "Updated task {$task['task_code']} to {$status}", ['status' => $task['status']], $update, $companyId, $userId);
        });
    }

    public function recordAttendance(int $companyId, int $userId, array $data): void
    {
        Database::execute(
            "INSERT INTO attendance (company_id, employee_id, attendance_date, status, check_in, check_out, worked_hours, overtime_hours, notes, recorded_by)
             VALUES (:cid, :eid, :dt, :st, :ci, :co, :wh, :oh, :notes, :uid)
             ON DUPLICATE KEY UPDATE status = VALUES(status), check_in = VALUES(check_in), check_out = VALUES(check_out), worked_hours = VALUES(worked_hours), overtime_hours = VALUES(overtime_hours), notes = VALUES(notes), recorded_by = VALUES(recorded_by)",
            [
                'cid' => $companyId,
                'eid' => (int) $data['employee_id'],
                'dt' => (string) $data['attendance_date'],
                'st' => (string) ($data['status'] ?? 'present'),
                'ci' => !empty($data['check_in']) ? $data['check_in'] : null,
                'co' => !empty($data['check_out']) ? $data['check_out'] : null,
                'wh' => (float) ($data['worked_hours'] ?? 8),
                'oh' => (float) ($data['overtime_hours'] ?? 0),
                'notes' => $data['notes'] ?? null,
                'uid' => $userId,
            ]
        );
        $this->logger->log('record', 'attendance', (int) $data['employee_id'], "Recorded attendance ({$data['status']}) on {$data['attendance_date']}", null, $data, $companyId, $userId);
    }

    public function evaluateEmployeePerformance(int $companyId, int $userId, array $data): int
    {
        $empId = (int) $data['employee_id'];
        $periodStart = (string) ($data['period_start'] ?? date('Y-m-01'));
        $periodEnd = (string) ($data['period_end'] ?? date('Y-m-t'));
        $periodLabel = (string) ($data['period_label'] ?? date('Y-m', strtotime($periodStart)));
        $managerRating = max(0.0, min(100.0, (float) ($data['manager_rating'] ?? 85.0)));

        $emp = Database::fetchOne('SELECT id, base_salary, full_name FROM employees WHERE id = :eid AND company_id = :cid AND deleted_at IS NULL', ['eid' => $empId, 'cid' => $companyId]);
        if ($emp === null) {
            throw new RuntimeException('Employee not found.');
        }

        // Gather objective metrics from tasks & attendance
        $taskStats = Database::fetchOne(
            "SELECT
                COUNT(DISTINCT t.id) AS assigned,
                SUM(CASE WHEN t.status = 'completed' THEN 1 ELSE 0 END) AS completed,
                SUM(CASE WHEN t.status = 'completed' AND (t.deadline IS NULL OR DATE(t.completed_at) <= t.deadline) THEN 1 ELSE 0 END) AS on_time
             FROM tasks t
             INNER JOIN task_assignments ta ON ta.task_id = t.id
             WHERE ta.employee_id = :eid AND t.deleted_at IS NULL AND DATE(t.created_at) <= :pe",
            ['eid' => $empId, 'pe' => $periodEnd]
        );
        $attStats = Database::fetchOne(
            "SELECT COUNT(*) AS total, SUM(CASE WHEN status IN ('present','remote','late') THEN 1 ELSE 0 END) AS attended
             FROM attendance WHERE employee_id = :eid AND attendance_date BETWEEN :ps AND :pe",
            ['eid' => $empId, 'ps' => $periodStart, 'pe' => $periodEnd]
        );

        $tasksAssigned = (int) ($taskStats['assigned'] ?? 0);
        $tasksCompleted = (int) ($taskStats['completed'] ?? 0);
        $tasksOnTime = (int) ($taskStats['on_time'] ?? 0);
        $attTotal = (int) ($attStats['total'] ?? 0);
        $attRate = $attTotal > 0 ? round(((int) $attStats['attended'] / $attTotal) * 100.0, 2) : 100.0;

        $settings = $this->settingsModel->getMap($companyId);
        $weights = [
            'task_completion' => (float) ($settings['perf_weight_task_completion'] ?? 40),
            'on_time' => (float) ($settings['perf_weight_on_time'] ?? 25),
            'attendance' => (float) ($settings['perf_weight_attendance'] ?? 15),
            'manager_rating' => (float) ($settings['perf_weight_manager_rating'] ?? 20),
        ];
        $goldMin = (float) ($settings['perf_bonus_tier_gold_min'] ?? 90);
        $goldPct = (float) ($settings['perf_bonus_tier_gold_percent'] ?? 15);
        $silverMin = (float) ($settings['perf_bonus_tier_silver_min'] ?? 75);
        $silverPct = (float) ($settings['perf_bonus_tier_silver_percent'] ?? 8);

        // Try Python FastAPI evaluation engine first, fall back cleanly to identical local formula
        $pyResult = $this->pythonClient->evaluatePerformance([
            'tasks_assigned' => $tasksAssigned,
            'tasks_completed' => $tasksCompleted,
            'tasks_on_time' => $tasksOnTime,
            'attendance_rate' => $attRate,
            'manager_rating' => $managerRating,
            'base_salary' => (float) $emp['base_salary'],
            'weights' => $weights,
            'gold_min_score' => $goldMin,
            'gold_bonus_percent' => $goldPct,
            'silver_min_score' => $silverMin,
            'silver_bonus_percent' => $silverPct,
        ]);

        if ($pyResult !== null && isset($pyResult['computed_score'])) {
            $computedScore = (float) $pyResult['computed_score'];
            $recBonus = (float) $pyResult['recommended_bonus'];
        } else {
            $totalW = array_sum($weights) ?: 100.0;
            $tRate = $tasksAssigned > 0 ? ($tasksCompleted / $tasksAssigned) * 100.0 : 100.0;
            $oRate = $tasksCompleted > 0 ? ($tasksOnTime / $tasksCompleted) * 100.0 : 100.0;
            $computedScore = round(($tRate * $weights['task_completion'] + $oRate * $weights['on_time'] + $attRate * $weights['attendance'] + $managerRating * $weights['manager_rating']) / $totalW, 2);
            $pct = $computedScore >= $goldMin ? $goldPct : ($computedScore >= $silverMin ? $silverPct : 0.0);
            $recBonus = round((float) $emp['base_salary'] * ($pct / 100.0), 2);
        }

        $approvedBonus = isset($data['approved_bonus']) && $data['approved_bonus'] !== '' ? (float) $data['approved_bonus'] : $recBonus;

        return Database::transaction(function () use (
            $companyId, $empId, $emp, $periodLabel, $periodStart, $periodEnd,
            $tasksAssigned, $tasksCompleted, $tasksOnTime, $attRate, $managerRating,
            $computedScore, $recBonus, $approvedBonus, $weights, $data, $userId
        ): int {
            $id = $this->perfModel->create([
                'company_id' => $companyId,
                'employee_id' => $empId,
                'period_label' => $periodLabel,
                'period_start' => $periodStart,
                'period_end' => $periodEnd,
                'tasks_assigned' => $tasksAssigned,
                'tasks_completed' => $tasksCompleted,
                'tasks_on_time' => $tasksOnTime,
                'attendance_rate' => $attRate,
                'manager_rating' => $managerRating,
                'computed_score' => $computedScore,
                'recommended_bonus' => $recBonus,
                'approved_bonus' => $approvedBonus,
                'evaluation_rule_snapshot' => json_encode($weights),
                'feedback' => $data['feedback'] ?? null,
                'evaluated_by' => $userId,
            ]);

            if (!empty($data['issue_bonus']) && $approvedBonus > 0) {
                $this->bonusModel->create([
                    'company_id' => $companyId,
                    'employee_id' => $empId,
                    'bonus_type' => 'performance',
                    'title' => "Performance Evaluation Bonus ({$periodLabel})",
                    'amount' => $approvedBonus,
                    'currency_code' => 'USD',
                    'awarded_date' => date('Y-m-d'),
                    'status' => 'approved',
                    'approved_by' => $userId,
                    'created_by' => $userId,
                ]);
            }

            $this->logger->log('evaluate', 'performance', $id, "Evaluated {$emp['full_name']} ({$periodLabel}) Score: {$computedScore}", null, ['score' => $computedScore, 'bonus' => $approvedBonus], $companyId, $userId);
            return $id;
        });
    }
}
