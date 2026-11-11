<?php

declare(strict_types=1);

namespace Tests;

use App\Core\Database;
use App\Services\EmployeeService;
use App\Services\PayrollService;
use App\Services\TaskPerformanceService;

final class PayrollAndBonusTest extends TestCase
{
    public function run(): void
    {
        echo "\n=== PayrollAndBonusTest ===\n";

        $payrollSvc = new PayrollService();

        // 1. Prompt scenario: Base $500 + Tasks $100 + Perf $50 + OT $30 - Ded $20 = $660
        $res = $payrollSvc->computeSalaryBreakdown([
            'payment_type' => 'fixed',
            'base_salary' => 500,
            'task_bonus' => 100,
            'performance_bonus' => 50,
            'overtime_pay' => 30,
            'authorized_deductions' => 20,
        ]);
        $this->assertFloatEquals(660.00, $res['net_salary'], 'Computes exact prompt scenario: 500 + 100 + 50 + 30 - 20 = 660');

        // 2. Hourly & Daily compensation models
        $hourly = $payrollSvc->computeSalaryBreakdown([
            'payment_type' => 'hourly',
            'hourly_rate' => 18.50,
            'working_hours' => 100,
            'authorized_deductions' => 0,
        ]);
        $this->assertFloatEquals(1850.00, $hourly['net_salary'], 'Computes hourly compensation model (18.50 * 100 = 1850)');

        $daily = $payrollSvc->computeSalaryBreakdown([
            'payment_type' => 'daily',
            'daily_rate' => 95.00,
            'worked_days' => 15,
            'authorized_deductions' => 50,
        ]);
        $this->assertFloatEquals(1375.00, $daily['net_salary'], 'Computes daily compensation model (95 * 15 - 50 = 1375)');

        // 3. CRITICAL: Unconfirmed deduction MUST NOT be applied to DB payroll until explicitly authorized!
        $empSvc = new EmployeeService();
        $empId = $empSvc->createEmployee(1, 1, [
            'full_name' => 'Payroll Guard Tester',
            'position' => 'Tester',
            'payment_type' => 'fixed',
            'base_salary' => 1000.00,
        ]);

        $dedId = $payrollSvc->addDeduction(1, 1, [
            'employee_id' => $empId,
            'deduction_type' => 'penalty',
            'title' => 'Unconfirmed Penalty',
            'amount' => 150.00,
            // Note: authorize_immediately NOT set!
        ]);

        // Calculate payroll for test period 2099-01
        $payrollSvc->calculateMonthlyPayrollForCompany(1, 2099, 1, 1);
        $prRow = Database::fetchOne('SELECT * FROM payrolls WHERE employee_id = ? AND period_year = 2099 AND period_month = 1', [$empId]);
        $this->assertFloatEquals(1000.00, (float) $prRow['net_salary'], 'Unconfirmed deduction ($150) is NOT auto-deducted from salary ($1000)');

        // Now explicitly authorize the deduction and recalculate
        $payrollSvc->authorizeDeduction($dedId, 1, 1);
        $payrollSvc->calculateMonthlyPayrollForCompany(1, 2099, 1, 1);
        $prRowAfter = Database::fetchOne('SELECT * FROM payrolls WHERE employee_id = ? AND period_year = 2099 AND period_month = 1', [$empId]);
        $this->assertFloatEquals(850.00, (float) $prRowAfter['net_salary'], 'Authorized deduction ($150) IS deducted after explicit confirmation ($850)');

        // 4. Task completion bonus + configurable performance evaluation
        $taskSvc = new TaskPerformanceService();
        $taskId = $taskSvc->createTask(1, 1, [
            'title' => 'Test Reward Task',
            'reward_amount' => 125.00,
            'assignee_ids' => [$empId],
        ]);
        $taskSvc->updateTaskStatus($taskId, 1, 1, 'completed', 100, 'Done');
        $bonusAmt = (float) Database::fetchColumn('SELECT COALESCE(SUM(amount), 0) FROM bonuses WHERE employee_id = ? AND bonus_type = "task"', [$empId]);
        $this->assertFloatEquals(125.00, $bonusAmt, 'Completing rewarded task creates approved task bonus');

        $perfId = $taskSvc->evaluateEmployeePerformance(1, 1, [
            'employee_id' => $empId,
            'period_label' => '2099-01',
            'period_start' => '2099-01-01',
            'period_end' => '2099-01-31',
            'manager_rating' => 95,
        ]);
        $perfRow = Database::fetchOne('SELECT * FROM performance_records WHERE id = ?', [$perfId]);
        $this->assertTrue((float) $perfRow['computed_score'] >= 90.0 && (float) $perfRow['recommended_bonus'] == 150.00, 'Evaluates configurable KPI score and gold-tier bonus (15% of $1000 = $150)');

        // Clean up all test rows
        Database::execute('DELETE FROM salary_components WHERE payroll_id IN (SELECT id FROM payrolls WHERE period_year = 2099)');
        Database::execute('DELETE FROM payrolls WHERE period_year = 2099');
        Database::execute('DELETE FROM bonuses WHERE employee_id = ?', [$empId]);
        Database::execute('DELETE FROM deductions WHERE employee_id = ?', [$empId]);
        Database::execute('DELETE FROM performance_records WHERE employee_id = ?', [$empId]);
        Database::execute('DELETE FROM tasks WHERE id = ?', [$taskId]);
        Database::execute('DELETE FROM employees WHERE id = ?', [$empId]);
    }
}
