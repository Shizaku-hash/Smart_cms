<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Models\ApplicationSetting;
use App\Models\Bonus;
use App\Models\Deduction;
use App\Models\Payroll;
use App\Models\SalaryComponent;
use RuntimeException;

/**
 * Flexible Payroll Engine supporting fixed, hourly, daily, project, and task compensation.
 * Enforces strict authorization before applying any deduction/penalty.
 */
final class PayrollService
{
    private Payroll $payrollModel;
    private SalaryComponent $componentModel;
    private Bonus $bonusModel;
    private Deduction $deductionModel;
    private ActivityLogService $logger;
    private NotificationService $notifier;

    public function __construct()
    {
        $this->payrollModel = new Payroll();
        $this->componentModel = new SalaryComponent();
        $this->bonusModel = new Bonus();
        $this->deductionModel = new Deduction();
        $this->logger = new ActivityLogService();
        $this->notifier = new NotificationService();
    }

    /**
     * Pure salary calculation formula (easy to unit test with arbitrary scenarios).
     * Never deducts unauthorized deductions!
     */
    public function computeSalaryBreakdown(array $input): array
    {
        $paymentType = (string) ($input['payment_type'] ?? 'fixed');
        $workingDays = max(1.0, (float) ($input['working_days'] ?? 22));
        $workedDays = max(0.0, (float) ($input['worked_days'] ?? $workingDays));
        $workingHours = max(0.0, (float) ($input['working_hours'] ?? 176));
        $baseSalary = max(0.0, (float) ($input['base_salary'] ?? 0));
        $hourlyRate = max(0.0, (float) ($input['hourly_rate'] ?? 0));
        $dailyRate = max(0.0, (float) ($input['daily_rate'] ?? 0));
        $overtimeHours = max(0.0, (float) ($input['overtime_hours'] ?? 0));
        $overtimeRate = max(0.0, (float) ($input['overtime_rate'] ?? 0));
        $overtimePay = isset($input['overtime_pay']) ? max(0.0, (float) $input['overtime_pay']) : round($overtimeHours * $overtimeRate, 2);
        $taskBonus = max(0.0, (float) ($input['task_bonus'] ?? 0));
        $performanceBonus = max(0.0, (float) ($input['performance_bonus'] ?? 0));
        $otherBonuses = max(0.0, (float) ($input['other_bonuses'] ?? 0));
        $authorizedDeductions = max(0.0, (float) ($input['authorized_deductions'] ?? 0));
        $adjustments = (float) ($input['adjustments'] ?? 0);

        $computedBase = match ($paymentType) {
            'hourly' => round($hourlyRate * $workingHours, 2),
            'daily' => round($dailyRate * $workedDays, 2),
            'fixed' => ($workedDays < $workingDays && !empty($input['prorate_fixed']))
                ? round($baseSalary * ($workedDays / $workingDays), 2)
                : round($baseSalary, 2),
            default => round($baseSalary, 2), // project or task base retainer
        };

        $gross = round($computedBase + $overtimePay + $taskBonus + $performanceBonus + $otherBonuses + $adjustments, 2);
        $net = round(max(0.0, $gross - $authorizedDeductions), 2);

        return [
            'computed_base' => $computedBase,
            'overtime_pay' => $overtimePay,
            'gross_salary' => $gross,
            'authorized_deductions' => round($authorizedDeductions, 2),
            'net_salary' => $net,
        ];
    }

    public function calculateMonthlyPayrollForCompany(int $companyId, int $year, int $month, int $userId): int
    {
        $employees = Database::fetchAll(
            "SELECT * FROM employees WHERE company_id = :cid AND deleted_at IS NULL AND employment_status IN ('active', 'on_leave')",
            ['cid' => $companyId]
        );
        $settings = (new ApplicationSetting())->getMap($companyId);
        $defaultWorkingDays = (float) ($settings['payroll_default_working_days'] ?? 22);
        $otMultiplier = (float) ($settings['payroll_overtime_multiplier'] ?? 1.5);

        $periodStart = sprintf('%04d-%02d-01', $year, $month);
        $periodEnd = date('Y-m-t', strtotime($periodStart));
        $count = 0;

        foreach ($employees as $idx => $emp) {
            $empId = (int) $emp['id'];

            // Skip if already paid for this period
            $existing = Database::fetchOne(
                "SELECT * FROM payrolls WHERE employee_id = :eid AND period_year = :y AND period_month = :m AND deleted_at IS NULL LIMIT 1",
                ['eid' => $empId, 'y' => $year, 'm' => $month]
            );
            if ($existing !== null && $existing['status'] === 'paid') {
                continue;
            }

            // Pull attendance aggregates
            $att = Database::fetchOne(
                "SELECT
                    SUM(CASE WHEN status IN ('present', 'late', 'remote') THEN 1 WHEN status = 'half_day' THEN 0.5 ELSE 0 END) AS days_worked,
                    COALESCE(SUM(worked_hours), 0) AS hours_worked,
                    COALESCE(SUM(overtime_hours), 0) AS ot_hours
                 FROM attendance
                 WHERE employee_id = :eid AND attendance_date BETWEEN :ps AND :pe",
                ['eid' => $empId, 'ps' => $periodStart, 'pe' => $periodEnd]
            );

            $workedDays = (float) ($att['days_worked'] ?? 0);
            $workedHours = (float) ($att['hours_worked'] ?? 0);
            $otHours = (float) ($att['ot_hours'] ?? 0);
            if ($workedDays <= 0 && $emp['payment_type'] === 'fixed') {
                $workedDays = $defaultWorkingDays;
                $workedHours = $defaultWorkingDays * 8;
            }

            $hourlyRate = (float) $emp['hourly_rate'] > 0 ? (float) $emp['hourly_rate'] : round((float) $emp['base_salary'] / max(1, $defaultWorkingDays * 8), 2);
            $otRate = round($hourlyRate * $otMultiplier, 2);

            // Completed rewarded tasks in period
            $taskAgg = Database::fetchOne(
                "SELECT COUNT(DISTINCT t.id) AS cnt,
                        COALESCE(SUM(t.reward_amount * (ta.reward_share_percent / 100.0)), 0) AS total_reward
                 FROM tasks t
                 INNER JOIN task_assignments ta ON ta.task_id = t.id
                 WHERE ta.employee_id = :eid AND t.status = 'completed' AND t.deleted_at IS NULL
                   AND DATE(t.completed_at) BETWEEN :ps AND :pe",
                ['eid' => $empId, 'ps' => $periodStart, 'pe' => $periodEnd]
            );
            $completedTasksCount = (int) ($taskAgg['cnt'] ?? 0);
            $taskBonus = round((float) ($taskAgg['total_reward'] ?? 0), 2);

            // Approved unattached bonuses
            $perfBonus = (float) Database::fetchColumn(
                "SELECT COALESCE(SUM(amount), 0) FROM bonuses
                 WHERE employee_id = :eid AND deleted_at IS NULL AND status = 'approved' AND bonus_type = 'performance'
                   AND (payroll_id IS NULL OR payroll_id = :pid)",
                ['eid' => $empId, 'pid' => $existing ? (int) $existing['id'] : 0]
            );
            $otherBonuses = (float) Database::fetchColumn(
                "SELECT COALESCE(SUM(amount), 0) FROM bonuses
                 WHERE employee_id = :eid AND deleted_at IS NULL AND status = 'approved' AND bonus_type != 'performance'
                   AND (payroll_id IS NULL OR payroll_id = :pid)",
                ['eid' => $empId, 'pid' => $existing ? (int) $existing['id'] : 0]
            );

            // CRITICAL: ONLY explicitly authorized deductions (is_authorized = 1)
            $authDeductions = (float) Database::fetchColumn(
                "SELECT COALESCE(SUM(amount), 0) FROM deductions
                 WHERE employee_id = :eid AND deleted_at IS NULL AND is_authorized = 1
                   AND status IN ('authorized', 'applied') AND (payroll_id IS NULL OR payroll_id = :pid)",
                ['eid' => $empId, 'pid' => $existing ? (int) $existing['id'] : 0]
            );

            $calc = $this->computeSalaryBreakdown([
                'payment_type' => $emp['payment_type'],
                'base_salary' => (float) $emp['base_salary'],
                'hourly_rate' => $hourlyRate,
                'daily_rate' => (float) $emp['daily_rate'],
                'working_days' => $defaultWorkingDays,
                'worked_days' => $workedDays,
                'working_hours' => $workedHours,
                'overtime_hours' => $otHours,
                'overtime_rate' => $otRate,
                'task_bonus' => $taskBonus,
                'performance_bonus' => $perfBonus,
                'other_bonuses' => $otherBonuses,
                'authorized_deductions' => $authDeductions,
                'adjustments' => $existing ? (float) $existing['adjustments'] : 0.0,
            ]);

            Database::transaction(function () use (
                $companyId, $emp, $empId, $year, $month, $periodStart, $periodEnd,
                $defaultWorkingDays, $workedDays, $workedHours, $otHours, $otRate,
                $completedTasksCount, $taskBonus, $perfBonus, $otherBonuses, $authDeductions,
                $calc, $existing, $userId, $idx
            ): void {
                $payload = [
                    'company_id' => $companyId,
                    'employee_id' => $empId,
                    'period_year' => $year,
                    'period_month' => $month,
                    'period_start' => $periodStart,
                    'period_end' => $periodEnd,
                    'payment_type' => $emp['payment_type'],
                    'base_salary' => $calc['computed_base'],
                    'working_days' => $defaultWorkingDays,
                    'worked_days' => $workedDays,
                    'working_hours' => $workedHours,
                    'overtime_hours' => $otHours,
                    'overtime_pay' => $calc['overtime_pay'],
                    'completed_tasks_count' => $completedTasksCount,
                    'task_bonus' => $taskBonus,
                    'performance_bonus' => $perfBonus,
                    'other_bonuses' => $otherBonuses,
                    'authorized_deductions' => $calc['authorized_deductions'],
                    'gross_salary' => $calc['gross_salary'],
                    'net_salary' => $calc['net_salary'],
                    'currency_code' => $emp['currency_code'],
                    'calculated_by' => $userId,
                ];

                if ($existing !== null) {
                    $prId = (int) $existing['id'];
                    $this->payrollModel->update($prId, $payload);
                    Database::execute('DELETE FROM salary_components WHERE payroll_id = :pid', ['pid' => $prId]);
                } else {
                    $payload['payroll_code'] = sprintf('PAY-%d%02d-%03d', $year, $month, $idx + 1);
                    $payload['status'] = 'pending';
                    $payload['payment_date'] = $periodEnd;
                    $prId = $this->payrollModel->create($payload);
                }

                // Populate itemized salary_components
                $this->componentModel->create([
                    'payroll_id' => $prId,
                    'component_type' => 'earning',
                    'code' => 'BASE',
                    'label' => ucfirst((string) $emp['payment_type']) . ' Base Compensation',
                    'quantity' => 1,
                    'rate' => $calc['computed_base'],
                    'amount' => $calc['computed_base'],
                    'is_authorized' => 1,
                    'authorized_by' => $userId,
                ]);
                if ($calc['overtime_pay'] > 0) {
                    $this->componentModel->create([
                        'payroll_id' => $prId,
                        'component_type' => 'overtime',
                        'code' => 'OT',
                        'label' => 'Overtime Payment',
                        'quantity' => $otHours,
                        'rate' => $otRate,
                        'amount' => $calc['overtime_pay'],
                        'is_authorized' => 1,
                        'authorized_by' => $userId,
                    ]);
                }
                if ($taskBonus > 0) {
                    $this->componentModel->create([
                        'payroll_id' => $prId,
                        'component_type' => 'bonus',
                        'code' => 'TASK',
                        'label' => "Completed Tasks Bonus ({$completedTasksCount} tasks)",
                        'quantity' => $completedTasksCount,
                        'rate' => $taskBonus / max(1, $completedTasksCount),
                        'amount' => $taskBonus,
                        'is_authorized' => 1,
                        'authorized_by' => $userId,
                    ]);
                }
                if ($perfBonus > 0) {
                    $this->componentModel->create([
                        'payroll_id' => $prId,
                        'component_type' => 'bonus',
                        'code' => 'PERF',
                        'label' => 'Performance Bonus',
                        'quantity' => 1,
                        'rate' => $perfBonus,
                        'amount' => $perfBonus,
                        'is_authorized' => 1,
                        'authorized_by' => $userId,
                    ]);
                }
                if ($otherBonuses > 0) {
                    $this->componentModel->create([
                        'payroll_id' => $prId,
                        'component_type' => 'bonus',
                        'code' => 'BONUS',
                        'label' => 'Additional Approved Bonuses',
                        'quantity' => 1,
                        'rate' => $otherBonuses,
                        'amount' => $otherBonuses,
                        'is_authorized' => 1,
                        'authorized_by' => $userId,
                    ]);
                }
                if ($authDeductions > 0) {
                    $this->componentModel->create([
                        'payroll_id' => $prId,
                        'component_type' => 'deduction',
                        'code' => 'DED',
                        'label' => 'Authorized Deductions',
                        'quantity' => 1,
                        'rate' => $authDeductions,
                        'amount' => $authDeductions,
                        'is_authorized' => 1,
                        'authorized_by' => $userId,
                    ]);
                }
            });
            $count++;
        }

        $this->logger->log('calculate_payroll', 'payroll', null, "Calculated {$year}-{$month} payroll for {$count} employees", null, ['year' => $year, 'month' => $month, 'count' => $count], $companyId, $userId);
        return $count;
    }

    public function updatePayrollComponents(int $payrollId, int $companyId, int $userId, array $data): void
    {
        $payroll = $this->payrollModel->find($payrollId);
        if ($payroll === null || (int) $payroll['company_id'] !== $companyId) {
            throw new RuntimeException('Payroll record not found.');
        }
        if ($payroll['status'] === 'paid') {
            throw new RuntimeException('Cannot edit a payroll record that has already been marked paid.');
        }

        $baseSalary = isset($data['base_salary']) ? max(0.0, (float) $data['base_salary']) : (float) $payroll['base_salary'];
        $overtimePay = isset($data['overtime_pay']) ? max(0.0, (float) $data['overtime_pay']) : (float) $payroll['overtime_pay'];
        $taskBonus = isset($data['task_bonus']) ? max(0.0, (float) $data['task_bonus']) : (float) $payroll['task_bonus'];
        $perfBonus = isset($data['performance_bonus']) ? max(0.0, (float) $data['performance_bonus']) : (float) $payroll['performance_bonus'];
        $otherBonuses = isset($data['other_bonuses']) ? max(0.0, (float) $data['other_bonuses']) : (float) $payroll['other_bonuses'];
        $authDed = isset($data['authorized_deductions']) ? max(0.0, (float) $data['authorized_deductions']) : (float) $payroll['authorized_deductions'];
        $adjustments = isset($data['adjustments']) ? (float) $data['adjustments'] : (float) $payroll['adjustments'];

        $gross = round($baseSalary + $overtimePay + $taskBonus + $perfBonus + $otherBonuses + $adjustments, 2);
        $net = round(max(0.0, $gross - $authDed), 2);

        Database::transaction(function () use ($payrollId, $payroll, $baseSalary, $overtimePay, $taskBonus, $perfBonus, $otherBonuses, $authDed, $adjustments, $gross, $net, $data, $companyId, $userId): void {
            $this->payrollModel->update($payrollId, [
                'base_salary' => $baseSalary,
                'overtime_pay' => $overtimePay,
                'task_bonus' => $taskBonus,
                'performance_bonus' => $perfBonus,
                'other_bonuses' => $otherBonuses,
                'authorized_deductions' => $authDed,
                'adjustments' => $adjustments,
                'gross_salary' => $gross,
                'net_salary' => $net,
                'notes' => $data['notes'] ?? $payroll['notes'],
            ]);

            Database::execute('DELETE FROM salary_components WHERE payroll_id = :pid', ['pid' => $payrollId]);
            $rows = [
                ['earning', 'BASE', 'Base Salary', $baseSalary],
                ['overtime', 'OT', 'Overtime Payment', $overtimePay],
                ['bonus', 'TASK', 'Task Completion Bonus', $taskBonus],
                ['bonus', 'PERF', 'Performance Bonus', $perfBonus],
                ['bonus', 'OTHER', 'Other Bonuses', $otherBonuses],
                ['adjustment', 'ADJ', 'Adjustments', $adjustments],
                ['deduction', 'DED', 'Authorized Deductions', $authDed],
            ];
            foreach ($rows as [$type, $code, $label, $amt]) {
                if (abs($amt) > 0.001 || $code === 'BASE') {
                    $this->componentModel->create([
                        'payroll_id' => $payrollId,
                        'component_type' => $type,
                        'code' => $code,
                        'label' => $label,
                        'quantity' => 1,
                        'rate' => $amt,
                        'amount' => $amt,
                        'is_authorized' => 1,
                        'authorized_by' => $userId,
                    ]);
                }
            }

            $this->logger->log('update', 'payroll', $payrollId, "Updated salary components for {$payroll['payroll_code']} (Net: {$net})", $payroll, ['net_salary' => $net], $companyId, $userId);
        });
    }

    public function addBonus(int $companyId, int $userId, array $data): int
    {
        $id = $this->bonusModel->create([
            'company_id' => $companyId,
            'employee_id' => (int) $data['employee_id'],
            'bonus_type' => $data['bonus_type'] ?? 'performance',
            'title' => trim((string) $data['title']),
            'amount' => round((float) $data['amount'], 2),
            'currency_code' => $data['currency_code'] ?? 'USD',
            'awarded_date' => $data['awarded_date'] ?? date('Y-m-d'),
            'status' => 'approved',
            'approved_by' => $userId,
            'notes' => $data['notes'] ?? null,
            'created_by' => $userId,
        ]);
        $this->logger->log('create', 'bonus', $id, "Awarded bonus '{$data['title']}' (\${$data['amount']})", null, $data, $companyId, $userId);
        return $id;
    }

    public function addDeduction(int $companyId, int $userId, array $data): int
    {
        // Always starts unconfirmed unless explicitly confirmed by an authorized user
        $authorizeNow = !empty($data['authorize_immediately']);
        $id = $this->deductionModel->create([
            'company_id' => $companyId,
            'employee_id' => (int) $data['employee_id'],
            'deduction_type' => $data['deduction_type'] ?? 'other',
            'title' => trim((string) $data['title']),
            'amount' => round((float) $data['amount'], 2),
            'currency_code' => $data['currency_code'] ?? 'USD',
            'deduction_date' => $data['deduction_date'] ?? date('Y-m-d'),
            'is_authorized' => $authorizeNow ? 1 : 0,
            'authorized_by' => $authorizeNow ? $userId : null,
            'authorized_at' => $authorizeNow ? date('Y-m-d H:i:s') : null,
            'status' => $authorizeNow ? 'authorized' : 'pending_confirmation',
            'notes' => $data['notes'] ?? null,
            'created_by' => $userId,
        ]);

        $this->logger->log('create', 'deduction', $id, "Recorded deduction '{$data['title']}' (authorized=" . ($authorizeNow ? 'yes' : 'no') . ")", null, $data, $companyId, $userId);
        return $id;
    }

    public function authorizeDeduction(int $deductionId, int $companyId, int $userId): void
    {
        $ded = $this->deductionModel->find($deductionId);
        if ($ded === null || (int) $ded['company_id'] !== $companyId) {
            throw new RuntimeException('Deduction record not found.');
        }

        $this->deductionModel->update($deductionId, [
            'is_authorized' => 1,
            'authorized_by' => $userId,
            'authorized_at' => date('Y-m-d H:i:s'),
            'status' => 'authorized',
        ]);
        $this->logger->log('authorize', 'deduction', $deductionId, "Authorized deduction '{$ded['title']}' (\${$ded['amount']})", ['is_authorized' => 0], ['is_authorized' => 1], $companyId, $userId);
    }

    public function markPaid(int $payrollId, int $companyId, int $userId, string $paymentMethod = 'bank_transfer', ?string $paymentDate = null): void
    {
        $payroll = $this->payrollModel->findDetailed($payrollId);
        if ($payroll === null || (int) $payroll['company_id'] !== $companyId) {
            throw new RuntimeException('Payroll record not found.');
        }
        if ($payroll['status'] === 'paid') {
            return;
        }

        $payDate = $paymentDate ?: date('Y-m-d');
        $settings = (new ApplicationSetting())->getMap($companyId);
        $autoPostExpense = (bool) ($settings['payroll_auto_post_expense_on_pay'] ?? true);

        Database::transaction(function () use ($payrollId, $payroll, $companyId, $userId, $paymentMethod, $payDate, $autoPostExpense): void {
            $txId = $payroll['transaction_id'] ? (int) $payroll['transaction_id'] : null;

            if ($autoPostExpense && $txId === null && (float) $payroll['net_salary'] > 0) {
                $expCatId = (int) Database::fetchColumn(
                    "SELECT id FROM expense_categories WHERE company_id = :cid AND slug = 'employee-salaries' AND deleted_at IS NULL LIMIT 1",
                    ['cid' => $companyId]
                );
                $currencyService = new CurrencyService();
                $baseCurrency = $currencyService->getDefaultCode($companyId);
                $conv = $currencyService->convert((float) $payroll['net_salary'], (string) $payroll['currency_code'], $baseCurrency);

                $txCode = sprintf('EXP-%s-PAY-%d', date('Y'), $payrollId);
                $txId = Database::insert(
                    "INSERT INTO transactions (company_id, transaction_code, type, expense_category_id, employee_id, payroll_id, original_amount, currency_code, exchange_rate, base_amount, transaction_date, payment_method, status, vendor_or_client, description, created_by)
                     VALUES (:cid, :code, 'expense', :ecid, :eid, :pid, :orig, :curr, :rate, :base, :dt, :pm, 'completed', :vendor, :desc, :uid)",
                    [
                        'cid' => $companyId,
                        'code' => $txCode,
                        'ecid' => $expCatId ?: null,
                        'eid' => (int) $payroll['employee_id'],
                        'pid' => $payrollId,
                        'orig' => (float) $payroll['net_salary'],
                        'curr' => $payroll['currency_code'],
                        'rate' => $conv['rate'],
                        'base' => $conv['converted_amount'],
                        'dt' => $payDate,
                        'pm' => $paymentMethod,
                        'vendor' => $payroll['full_name'],
                        'desc' => "Salary disbursement {$payroll['payroll_code']}",
                        'uid' => $userId,
                    ]
                );
            }

            $this->payrollModel->update($payrollId, [
                'status' => 'paid',
                'payment_date' => $payDate,
                'payment_method' => $paymentMethod,
                'transaction_id' => $txId,
                'paid_by' => $userId,
            ]);

            // Settle linked/open bonuses & authorized deductions
            Database::execute(
                "UPDATE bonuses SET payroll_id = :pid, status = 'paid'
                 WHERE employee_id = :eid AND deleted_at IS NULL AND status = 'approved' AND payroll_id IS NULL",
                ['pid' => $payrollId, 'eid' => (int) $payroll['employee_id']]
            );
            Database::execute(
                "UPDATE deductions SET payroll_id = :pid, status = 'applied'
                 WHERE employee_id = :eid AND deleted_at IS NULL AND is_authorized = 1 AND status = 'authorized' AND payroll_id IS NULL",
                ['pid' => $payrollId, 'eid' => (int) $payroll['employee_id']]
            );

            $empUserId = Database::fetchColumn('SELECT user_id FROM employees WHERE id = :eid LIMIT 1', ['eid' => (int) $payroll['employee_id']]);
            if ($empUserId) {
                $this->notifier->notify($companyId, (int) $empUserId, 'salary_upcoming', 'Salary Paid', "Your salary statement {$payroll['payroll_code']} has been marked paid.", "/payroll/{$payrollId}/statement", 'success');
            }

            $this->logger->log('mark_paid', 'payroll', $payrollId, "Marked payroll {$payroll['payroll_code']} as paid (\${$payroll['net_salary']})", ['status' => $payroll['status']], ['status' => 'paid', 'transaction_id' => $txId], $companyId, $userId);
        });
    }
}
