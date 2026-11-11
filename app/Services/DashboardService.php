<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

final class DashboardService
{
    private FinanceService $financeService;
    private PythonAnalyticsClient $pythonClient;

    public function __construct()
    {
        $this->financeService = new FinanceService();
        $this->pythonClient = new PythonAnalyticsClient();
    }

    public function getDashboardData(int $companyId, ?string $dateFrom = null, ?string $dateTo = null): array
    {
        $fin = $this->financeService->getSummary($companyId, $dateFrom, $dateTo);

        $activeEmployees = (int) Database::fetchColumn(
            "SELECT COUNT(*) FROM employees WHERE company_id = :cid AND deleted_at IS NULL AND employment_status = 'active'",
            ['cid' => $companyId]
        );
        $totalEmployees = (int) Database::fetchColumn(
            "SELECT COUNT(*) FROM employees WHERE company_id = :cid AND deleted_at IS NULL",
            ['cid' => $companyId]
        );

        $currYear = (int) date('Y');
        $currMonth = (int) date('n');
        $monthlySalaryExpense = (float) Database::fetchColumn(
            "SELECT COALESCE(SUM(net_salary), 0) FROM payrolls
             WHERE company_id = :cid AND deleted_at IS NULL AND period_year = :y AND period_month = :m",
            ['cid' => $companyId, 'y' => $currYear, 'm' => $currMonth]
        );

        $pendingPayrolls = Database::fetchOne(
            "SELECT COUNT(*) AS cnt, COALESCE(SUM(net_salary), 0) AS total
             FROM payrolls WHERE company_id = :cid AND deleted_at IS NULL AND status != 'paid'",
            ['cid' => $companyId]
        );

        $pendingTotal = round((float) $fin['pending_expenses_total'] + (float) ($pendingPayrolls['total'] ?? 0), 2);
        $pendingCount = (int) $fin['pending_expenses_count'] + (int) ($pendingPayrolls['cnt'] ?? 0);

        // Recent financial transactions
        $txParams = ['cid' => $companyId];
        $txWhere = "t.company_id = :cid AND t.deleted_at IS NULL";
        if ($dateFrom) {
            $txWhere .= " AND t.transaction_date >= :df";
            $txParams['df'] = $dateFrom;
        }
        if ($dateTo) {
            $txWhere .= " AND t.transaction_date <= :dt";
            $txParams['dt'] = $dateTo;
        }
        $recentTransactions = Database::fetchAll(
            "SELECT t.*, COALESCE(ic.name, ec.name, 'Uncategorized') AS category_name, e.full_name AS employee_name
             FROM transactions t
             LEFT JOIN income_categories ic ON ic.id = t.income_category_id
             LEFT JOIN expense_categories ec ON ec.id = t.expense_category_id
             LEFT JOIN employees e ON e.id = t.employee_id
             WHERE {$txWhere}
             ORDER BY t.transaction_date DESC, t.id DESC
             LIMIT 8",
            $txParams
        );

        // Upcoming salary payments
        $upcomingSalaries = Database::fetchAll(
            "SELECT p.*, e.full_name, e.employee_code, e.position, d.name AS department_name
             FROM payrolls p
             INNER JOIN employees e ON e.id = p.employee_id
             LEFT JOIN departments d ON d.id = e.department_id
             WHERE p.company_id = :cid AND p.deleted_at IS NULL AND p.status != 'paid'
             ORDER BY p.payment_date ASC, p.net_salary DESC
             LIMIT 8",
            ['cid' => $companyId]
        );

        // SQL Monthly chart series (works even if FastAPI is stopped)
        $monthlySeries = Database::fetchAll(
            "SELECT DATE_FORMAT(transaction_date, '%Y-%m') AS month,
                    SUM(CASE WHEN type = 'income' THEN base_amount ELSE 0 END) AS income,
                    SUM(CASE WHEN type = 'expense' THEN base_amount ELSE 0 END) AS expense
             FROM transactions
             WHERE company_id = :cid AND deleted_at IS NULL AND status IN ('completed', 'approved')
             " . ($dateFrom ? " AND transaction_date >= :df" : "") . ($dateTo ? " AND transaction_date <= :dt" : "") . "
             GROUP BY DATE_FORMAT(transaction_date, '%Y-%m')
             ORDER BY month ASC",
            $txParams
        );
        foreach ($monthlySeries as &$m) {
            $m['income'] = round((float) $m['income'], 2);
            $m['expense'] = round((float) $m['expense'], 2);
            $m['profit'] = round($m['income'] - $m['expense'], 2);
        }

        // Expense categories breakdown
        $expenseCategories = Database::fetchAll(
            "SELECT COALESCE(ec.name, 'Other') AS category, COALESCE(ec.color, '#64748b') AS color,
                    SUM(t.base_amount) AS amount
             FROM transactions t
             LEFT JOIN expense_categories ec ON ec.id = t.expense_category_id
             WHERE {$txWhere} AND t.type = 'expense' AND t.status IN ('completed', 'approved')
             GROUP BY ec.id, ec.name, ec.color
             ORDER BY amount DESC",
            $txParams
        );

        // Salary distribution by department
        $salaryDistribution = Database::fetchAll(
            "SELECT COALESCE(d.name, 'Unassigned') AS department, SUM(p.net_salary) AS total
             FROM payrolls p
             INNER JOIN employees e ON e.id = p.employee_id
             LEFT JOIN departments d ON d.id = e.department_id
             WHERE p.company_id = :cid AND p.deleted_at IS NULL AND p.period_year = :y AND p.period_month = :m
             GROUP BY d.id, d.name
             ORDER BY total DESC",
            ['cid' => $companyId, 'y' => $currYear, 'm' => $currMonth]
        );

        // Employee performance statistics
        $performanceStats = Database::fetchAll(
            "SELECT e.full_name, e.employee_code, pr.computed_score, pr.recommended_bonus
             FROM performance_records pr
             INNER JOIN employees e ON e.id = pr.employee_id
             WHERE pr.company_id = :cid AND pr.deleted_at IS NULL
             ORDER BY pr.computed_score DESC
             LIMIT 8",
            ['cid' => $companyId]
        );

        // Live Python FastAPI insights (forecast + anomaly alerts)
        $pythonFinancial = $this->pythonClient->getFinancialAnalytics($companyId, $dateFrom, $dateTo);

        return [
            'kpis' => [
                'base_currency' => $fin['base_currency'],
                'total_balance' => $fin['net_balance'],
                'total_income' => $fin['total_income'],
                'total_expenses' => $fin['total_expenses'],
                'net_profit' => $fin['net_balance'],
                'monthly_income' => $fin['monthly_income'],
                'monthly_expenses' => $fin['monthly_expenses'],
                'active_employees' => $activeEmployees,
                'total_employees' => $totalEmployees,
                'monthly_salary_expense' => round($monthlySalaryExpense, 2),
                'pending_payments_total' => $pendingTotal,
                'pending_payments_count' => $pendingCount,
            ],
            'recent_transactions' => $recentTransactions,
            'upcoming_salaries' => $upcomingSalaries,
            'charts' => [
                'monthly_series' => $monthlySeries,
                'expense_categories' => $expenseCategories,
                'salary_distribution' => $salaryDistribution,
                'performance_stats' => $performanceStats,
            ],
            'python_insights' => [
                'connected' => $pythonFinancial !== null,
                'forecast_next_3_months' => $pythonFinancial['forecast_next_3_months'] ?? [],
                'anomalies' => $pythonFinancial['anomalies'] ?? [],
                'profit_margin_percent' => $pythonFinancial['profit_margin_percent'] ?? ($fin['total_income'] > 0 ? round($fin['net_balance'] / $fin['total_income'] * 100, 2) : 0),
            ],
        ];
    }
}
