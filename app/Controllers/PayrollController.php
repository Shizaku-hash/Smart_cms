<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Models\Bonus;
use App\Models\Deduction;
use App\Models\Employee;
use App\Models\Payroll;
use App\Services\ExportService;
use App\Services\PayrollService;
use App\Services\ReportService;
use Throwable;

final class PayrollController extends BaseController
{
    private Payroll $payrollModel;
    private PayrollService $payrollService;

    public function __construct()
    {
        $this->payrollModel = new Payroll();
        $this->payrollService = new PayrollService();
    }

    public function index(Request $request): string
    {
        $companyId = Auth::companyId();
        $canViewAll = Auth::can('payroll.view_all');
        $ownEmpId = Auth::employeeId();

        $year = (int) $request->query('year', date('Y'));
        $month = (int) $request->query('month', date('n'));
        $status = $request->query('status') ? (string) $request->query('status') : null;

        $filters = ['year' => $year, 'month' => $month, 'status' => $status];
        if (!$canViewAll) {
            $filters['employee_id'] = $ownEmpId ?? -1;
        }

        $payrolls = $this->payrollModel->filter($companyId, $filters);
        $employees = $canViewAll ? (new Employee())->filter($companyId) : [];
        $bonuses = (new Bonus())->forCompany($companyId, $canViewAll ? null : ($ownEmpId ?? -1));
        $deductions = (new Deduction())->forCompany($companyId, $canViewAll ? null : ($ownEmpId ?? -1));

        return $this->render('payroll/index', [
            'title' => __('payroll.title'),
            'payrolls' => $payrolls,
            'employees' => $employees,
            'bonuses' => $bonuses,
            'deductions' => $deductions,
            'year' => $year,
            'month' => $month,
            'statusFilter' => $status ?? '',
            'canViewAll' => $canViewAll,
        ]);
    }

    public function calculate(Request $request): void
    {
        $year = (int) $request->input('year', date('Y'));
        $month = (int) $request->input('month', date('n'));
        $count = $this->payrollService->calculateMonthlyPayrollForCompany(Auth::companyId(), $year, $month, (int) Auth::id());
        $this->redirect("/payroll?year={$year}&month={$month}", ['success' => "Calculated payroll for {$count} employee(s)."]);
    }

    public function updateComponents(Request $request, string $id): void
    {
        try {
            $this->payrollService->updatePayrollComponents((int) $id, Auth::companyId(), (int) Auth::id(), $request->all());
            $this->redirect($_SERVER['HTTP_REFERER'] ?? '/payroll', ['success' => __('messages.saved')]);
        } catch (Throwable $e) {
            $this->redirect($_SERVER['HTTP_REFERER'] ?? '/payroll', ['error' => $e->getMessage()]);
        }
    }

    public function addBonus(Request $request): void
    {
        try {
            $this->payrollService->addBonus(Auth::companyId(), (int) Auth::id(), $request->all());
            $this->redirect($_SERVER['HTTP_REFERER'] ?? '/payroll', ['success' => __('messages.saved')]);
        } catch (Throwable $e) {
            $this->redirect('/payroll', ['error' => $e->getMessage()]);
        }
    }

    public function addDeduction(Request $request): void
    {
        $data = $request->all();
        // Never allow immediate authorization if user lacks payroll.authorize_deductions
        if (!Auth::can('payroll.authorize_deductions')) {
            unset($data['authorize_immediately']);
        }
        try {
            $this->payrollService->addDeduction(Auth::companyId(), (int) Auth::id(), $data);
            $this->redirect($_SERVER['HTTP_REFERER'] ?? '/payroll', ['success' => __('messages.saved')]);
        } catch (Throwable $e) {
            $this->redirect('/payroll', ['error' => $e->getMessage()]);
        }
    }

    public function authorizeDeduction(Request $request, string $id): void
    {
        try {
            $this->payrollService->authorizeDeduction((int) $id, Auth::companyId(), (int) Auth::id());
            $this->redirect($_SERVER['HTTP_REFERER'] ?? '/payroll', ['success' => 'Deduction authorized. Re-calculate payroll to include it.']);
        } catch (Throwable $e) {
            $this->redirect('/payroll', ['error' => $e->getMessage()]);
        }
    }

    public function markPaid(Request $request, string $id): void
    {
        try {
            $this->payrollService->markPaid(
                (int) $id,
                Auth::companyId(),
                (int) Auth::id(),
                (string) $request->input('payment_method', 'bank_transfer'),
                $request->input('payment_date') ? (string) $request->input('payment_date') : null
            );
            $this->redirect($_SERVER['HTTP_REFERER'] ?? '/payroll', ['success' => 'Payroll marked as paid and recorded in ledger.']);
        } catch (Throwable $e) {
            $this->redirect('/payroll', ['error' => $e->getMessage()]);
        }
    }

    public function statement(Request $request, string $id): string
    {
        $payroll = $this->payrollModel->findDetailed((int) $id);
        if ($payroll === null || (int) $payroll['company_id'] !== Auth::companyId()) {
            Response::html(View::render('errors/404', [], null), 404);
        }
        if (!Auth::can('payroll.view_all') && Auth::employeeId() !== (int) $payroll['employee_id']) {
            Response::html(View::render('errors/403', ['requiredPermission' => 'payroll.view_all']), 403);
        }

        $format = $request->query('format');
        if (in_array($format, ['pdf', 'xlsx'], true)) {
            $rows = [];
            foreach ($payroll['components'] as $c) {
                $rows[] = [
                    strtoupper((string) $c['component_type']),
                    $c['label'],
                    (string) $c['quantity'],
                    number_format((float) $c['rate'], 2),
                    ($c['component_type'] === 'deduction' ? '-' : '') . number_format((float) $c['amount'], 2),
                ];
            }
            $report = [
                'title' => "Salary Statement — {$payroll['payroll_code']}",
                'subtitle' => "{$payroll['full_name']} ({$payroll['employee_code']}) | Period: {$payroll['period_start']} to {$payroll['period_end']} | Status: " . strtoupper((string) $payroll['status']),
                'report_type' => 'employee_salary',
                'creation_method' => 'automatic',
                'columns' => ['Type', 'Component', 'Qty', 'Rate', 'Amount (' . $payroll['currency_code'] . ')'],
                'rows' => $rows,
                'totals_row' => ['NET PAY', '', '', '', number_format((float) $payroll['net_salary'], 2)],
                'notes' => "Bank: {$payroll['bank_name']} | Account: {$payroll['bank_account_number']}",
            ];
            $out = (new ExportService())->export($report, (string) $format, 'php', Auth::companyId(), Auth::id());
            Response::streamDownload($out['bytes'], $out['filename'], $out['mime']);
        }

        return $this->render('payroll/statement', [
            'title' => __('payroll.statement') . ' — ' . $payroll['payroll_code'],
            'payroll' => $payroll,
        ]);
    }

    public function exportList(Request $request): void
    {
        $year = (int) $request->query('year', date('Y'));
        $month = (int) $request->query('month', date('n'));
        $format = (string) $request->query('format', 'xlsx');
        $engine = (string) $request->query('engine', 'php');

        $report = (new ReportService())->buildAutomaticReport(Auth::companyId(), [
            'report_type' => 'payroll',
            'title' => sprintf('Payroll Summary %04d-%02d', $year, $month),
            'date_from' => sprintf('%04d-%02d-01', $year, $month),
            'date_to' => date('Y-m-t', strtotime(sprintf('%04d-%02d-01', $year, $month))),
        ]);

        $out = (new ExportService())->export($report, $format, $engine, Auth::companyId(), Auth::id());
        Response::streamDownload($out['bytes'], $out['filename'], $out['mime']);
    }
}
