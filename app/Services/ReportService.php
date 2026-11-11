<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

/**
 * Generates all 11 automatic report types AND evaluates Method B manual custom-table reports.
 */
final class ReportService
{
    public const REPORT_TYPES = [
        'complete_financial' => 'Complete Financial Report',
        'income' => 'Income Report',
        'expense' => 'Expense Report',
        'monthly_financial' => 'Monthly Financial Report',
        'annual_financial' => 'Annual Financial Report',
        'employee_info' => 'Employee Information Report',
        'employee_salary' => 'Employee Salary Report',
        'payroll' => 'Payroll Report',
        'bonus' => 'Bonus Report',
        'performance' => 'Performance Report',
        'custom' => 'Custom Report',
    ];

    public function getAvailableColumns(string $reportType): array
    {
        return match ($reportType) {
            'income', 'expense', 'complete_financial' => [
                'transaction_code' => 'Code',
                'type' => 'Type',
                'category_name' => 'Category',
                'vendor_or_client' => 'Client / Vendor',
                'employee_name' => 'Responsible Employee',
                'transaction_date' => 'Date',
                'payment_method' => 'Payment Method',
                'original_amount' => 'Original Amount',
                'currency_code' => 'Currency',
                'base_amount' => 'Base Amount (USD)',
                'status' => 'Status',
                'description' => 'Description',
            ],
            'monthly_financial', 'annual_financial' => [
                'period' => 'Period',
                'income_total' => 'Total Income',
                'expense_total' => 'Total Expenses',
                'net_profit' => 'Net Profit',
                'tx_count' => 'Transactions Count',
            ],
            'employee_info' => [
                'employee_code' => 'Employee ID',
                'full_name' => 'Full Name',
                'department_name' => 'Department',
                'position' => 'Position',
                'email' => 'Email',
                'phone' => 'Phone',
                'employment_date' => 'Hire Date',
                'employment_status' => 'Status',
                'contract_type' => 'Contract',
                'payment_type' => 'Payment Model',
                'base_salary' => 'Base Salary',
            ],
            'employee_salary', 'payroll' => [
                'payroll_code' => 'Payroll ID',
                'employee_code' => 'Employee ID',
                'full_name' => 'Employee Name',
                'department_name' => 'Department',
                'period_label' => 'Period',
                'payment_type' => 'Model',
                'base_salary' => 'Base',
                'overtime_pay' => 'Overtime',
                'task_bonus' => 'Task Bonus',
                'performance_bonus' => 'Perf Bonus',
                'authorized_deductions' => 'Deductions',
                'gross_salary' => 'Gross',
                'net_salary' => 'Net Salary',
                'status' => 'Status',
            ],
            'bonus' => [
                'employee_code' => 'Employee ID',
                'employee_name' => 'Employee Name',
                'bonus_type' => 'Type',
                'title' => 'Title',
                'amount' => 'Amount',
                'currency_code' => 'Currency',
                'awarded_date' => 'Awarded Date',
                'status' => 'Status',
            ],
            'performance' => [
                'employee_code' => 'Employee ID',
                'employee_name' => 'Employee Name',
                'department_name' => 'Department',
                'period_label' => 'Period',
                'tasks_assigned' => 'Tasks Assigned',
                'tasks_completed' => 'Completed',
                'tasks_on_time' => 'On Time',
                'attendance_rate' => 'Attendance %',
                'manager_rating' => 'Manager Rating',
                'computed_score' => 'Final Score',
                'recommended_bonus' => 'Recommended Bonus',
            ],
            default => [
                'column_1' => 'Column 1',
                'column_2' => 'Column 2',
                'column_3' => 'Column 3',
            ],
        };
    }

    public function buildAutomaticReport(int $companyId, array $params): array
    {
        $type = (string) ($params['report_type'] ?? 'complete_financial');
        $dateFrom = !empty($params['date_from']) ? (string) $params['date_from'] : null;
        $dateTo = !empty($params['date_to']) ? (string) $params['date_to'] : null;
        $employeeId = !empty($params['employee_id']) ? (int) $params['employee_id'] : null;
        $categoryId = !empty($params['category_id']) ? (int) $params['category_id'] : null;
        $sortDir = strtoupper((string) ($params['sort_dir'] ?? 'DESC')) === 'ASC' ? 'ASC' : 'DESC';

        $allCols = $this->getAvailableColumns($type);
        $selectedCols = !empty($params['columns']) && is_array($params['columns'])
            ? array_values(array_intersect($params['columns'], array_keys($allCols)))
            : array_keys($allCols);
        if ($selectedCols === []) {
            $selectedCols = array_keys($allCols);
        }

        $rawRows = $this->queryReportData($companyId, $type, $dateFrom, $dateTo, $employeeId, $categoryId, $sortDir);
        $headers = array_map(static fn (string $k): string => $allCols[$k] ?? $k, $selectedCols);

        $rows = [];
        $numericSums = array_fill(0, count($selectedCols), 0.0);
        $isSumCol = array_fill(0, count($selectedCols), false);
        $sumEligibleKeys = ['base_amount', 'original_amount', 'income_total', 'expense_total', 'net_profit', 'base_salary', 'overtime_pay', 'task_bonus', 'performance_bonus', 'authorized_deductions', 'gross_salary', 'net_salary', 'amount', 'recommended_bonus'];

        foreach ($rawRows as $r) {
            $rowVals = [];
            foreach ($selectedCols as $cIdx => $colKey) {
                $val = $r[$colKey] ?? '';
                if (in_array($colKey, $sumEligibleKeys, true) && is_numeric($val)) {
                    $num = round((float) $val, 2);
                    $numericSums[$cIdx] += $num;
                    $isSumCol[$cIdx] = true;
                    $rowVals[] = $num;
                } else {
                    $rowVals[] = (string) $val;
                }
            }
            $rows[] = $rowVals;
        }

        $totalsRow = [];
        foreach ($selectedCols as $cIdx => $colKey) {
            if ($cIdx === 0) {
                $totalsRow[] = 'TOTAL (' . count($rows) . ' rows)';
            } elseif ($isSumCol[$cIdx]) {
                $totalsRow[] = round($numericSums[$cIdx], 2);
            } else {
                $totalsRow[] = '';
            }
        }

        return [
            'title' => (string) ($params['title'] ?? (self::REPORT_TYPES[$type] ?? 'Report')),
            'subtitle' => trim(($dateFrom ? "From {$dateFrom} " : '') . ($dateTo ? "To {$dateTo}" : '')) ?: 'All Recorded Periods',
            'report_type' => $type,
            'creation_method' => 'automatic',
            'columns' => $headers,
            'column_keys' => $selectedCols,
            'rows' => $rows,
            'totals_row' => $totalsRow,
            'notes' => (string) ($params['notes'] ?? ''),
        ];
    }

    /**
     * Method B: Evaluate manual report tables including spreadsheet-style cell formulas
     * (=SUM(B1:B3), =AVG(B1:B3), =B1-C1, =B1+C1, =B1*C1, =B1/C1).
     */
    public function buildManualReport(array $payload): array
    {
        $columns = array_values(array_map('strval', (array) ($payload['columns'] ?? ['Item', 'Value'])));
        $rawRows = (array) ($payload['rows'] ?? []);
        $showTotals = !empty($payload['show_totals_row']);

        // Normalize grid for formula lookup (1-based row index, Excel column A, B, C...)
        $evaluatedRows = [];
        $grid = [];
        foreach ($rawRows as $rIdx => $r) {
            $rowArr = [];
            for ($cIdx = 0; $cIdx < count($columns); $cIdx++) {
                $cell = $r[$cIdx] ?? '';
                $rowArr[$cIdx] = $cell;
                $colLetter = $this->colIndexToLetter($cIdx);
                $grid[$colLetter . ($rIdx + 1)] = $cell;
            }
            $evaluatedRows[] = $rowArr;
        }

        // Evaluate formulas cleanly
        foreach ($evaluatedRows as $rIdx => &$rowArr) {
            foreach ($rowArr as $cIdx => &$cell) {
                if (is_string($cell) && str_starts_with(trim($cell), '=')) {
                    $cell = $this->evaluateFormula(trim($cell), $grid);
                }
            }
        }
        unset($rowArr, $cell);

        $totalsRow = null;
        if ($showTotals && count($columns) > 0) {
            $totalsRow = array_fill(0, count($columns), '');
            $totalsRow[0] = 'TOTAL';
            for ($cIdx = 1; $cIdx < count($columns); $cIdx++) {
                $sum = 0.0;
                $hasNum = false;
                foreach ($evaluatedRows as $r) {
                    if (is_numeric($r[$cIdx])) {
                        $sum += (float) $r[$cIdx];
                        $hasNum = true;
                    }
                }
                if ($hasNum) {
                    $totalsRow[$cIdx] = round($sum, 2);
                }
            }
        }

        return [
            'title' => (string) ($payload['title'] ?? 'Custom Manual Report'),
            'subtitle' => (string) ($payload['subtitle'] ?? 'Manual Report Builder'),
            'report_type' => 'custom',
            'creation_method' => 'manual',
            'include_logo' => !empty($payload['include_logo']),
            'columns' => $columns,
            'rows' => $evaluatedRows,
            'raw_rows' => $rawRows,
            'totals_row' => $totalsRow,
            'notes' => (string) ($payload['notes'] ?? ''),
        ];
    }

    private function evaluateFormula(string $formula, array $grid): float|string
    {
        $expr = strtoupper(ltrim(substr($formula, 1)));

        // =SUM(A1:A5) or =AVG(A1:A5)
        if (preg_match('/^(SUM|AVG)\(([A-Z]+\d+):([A-Z]+\d+)\)$/', $expr, $m)) {
            $vals = $this->extractRange($m[2], $m[3], $grid);
            if ($vals === []) {
                return 0.0;
            }
            $sum = array_sum($vals);
            return $m[1] === 'AVG' ? round($sum / count($vals), 2) : round($sum, 2);
        }

        // Binary arithmetic: =B1-C1, =B1+C1, =B1*C1, =B1/C1
        if (preg_match('/^([A-Z]+\d+|\d+(?:\.\d+)?)\s*([\+\-\*\/])\s*([A-Z]+\d+|\d+(?:\.\d+)?)$/', $expr, $m)) {
            $left = is_numeric($m[1]) ? (float) $m[1] : (float) ($grid[$m[1]] ?? 0);
            $right = is_numeric($m[3]) ? (float) $m[3] : (float) ($grid[$m[3]] ?? 0);
            return match ($m[2]) {
                '+' => round($left + $right, 2),
                '-' => round($left - $right, 2),
                '*' => round($left * $right, 2),
                '/' => $right != 0.0 ? round($left / $right, 2) : 0.0,
            };
        }

        return $formula;
    }

    private function extractRange(string $startRef, string $endRef, array $grid): array
    {
        if (!preg_match('/^([A-Z]+)(\d+)$/', $startRef, $s) || !preg_match('/^([A-Z]+)(\d+)$/', $endRef, $e)) {
            return [];
        }
        $vals = [];
        $cStart = $this->letterToColIndex($s[1]);
        $cEnd = $this->letterToColIndex($e[1]);
        $rStart = (int) $s[2];
        $rEnd = (int) $e[2];
        for ($r = min($rStart, $rEnd); $r <= max($rStart, $rEnd); $r++) {
            for ($c = min($cStart, $cEnd); $c <= max($cStart, $cEnd); $c++) {
                $ref = $this->colIndexToLetter($c) . $r;
                if (isset($grid[$ref]) && is_numeric($grid[$ref])) {
                    $vals[] = (float) $grid[$ref];
                }
            }
        }
        return $vals;
    }

    private function colIndexToLetter(int $index): string
    {
        return chr(65 + ($index % 26));
    }

    private function letterToColIndex(string $letter): int
    {
        return ord($letter[0]) - 65;
    }

    private function queryReportData(int $companyId, string $type, ?string $dateFrom, ?string $dateTo, ?int $employeeId, ?int $categoryId, string $sortDir): array
    {
        $params = ['cid' => $companyId];

        if (in_array($type, ['complete_financial', 'income', 'expense'], true)) {
            $sql = "SELECT t.transaction_code, t.type,
                           COALESCE(ic.name, ec.name, 'Uncategorized') AS category_name,
                           COALESCE(t.vendor_or_client, '—') AS vendor_or_client,
                           COALESCE(e.full_name, '—') AS employee_name,
                           t.transaction_date, t.payment_method, t.original_amount, t.currency_code, t.base_amount, t.status, t.description
                    FROM transactions t
                    LEFT JOIN income_categories ic ON ic.id = t.income_category_id
                    LEFT JOIN expense_categories ec ON ec.id = t.expense_category_id
                    LEFT JOIN employees e ON e.id = t.employee_id
                    WHERE t.company_id = :cid AND t.deleted_at IS NULL";
            if ($type === 'income') {
                $sql .= " AND t.type = 'income'";
                if ($categoryId) {
                    $sql .= " AND t.income_category_id = :cat_id";
                    $params['cat_id'] = $categoryId;
                }
            } elseif ($type === 'expense') {
                $sql .= " AND t.type = 'expense'";
                if ($categoryId) {
                    $sql .= " AND t.expense_category_id = :cat_id";
                    $params['cat_id'] = $categoryId;
                }
            }
            if ($employeeId) {
                $sql .= " AND t.employee_id = :eid";
                $params['eid'] = $employeeId;
            }
            if ($dateFrom) {
                $sql .= " AND t.transaction_date >= :df";
                $params['df'] = $dateFrom;
            }
            if ($dateTo) {
                $sql .= " AND t.transaction_date <= :dt";
                $params['dt'] = $dateTo;
            }
            $sql .= " ORDER BY t.transaction_date {$sortDir}, t.id {$sortDir}";
            return Database::fetchAll($sql, $params);
        }

        if ($type === 'monthly_financial' || $type === 'annual_financial') {
            $fmt = $type === 'annual_financial' ? '%Y' : '%Y-%m';
            $sql = "SELECT DATE_FORMAT(transaction_date, '{$fmt}') AS period,
                           SUM(CASE WHEN type = 'income' THEN base_amount ELSE 0 END) AS income_total,
                           SUM(CASE WHEN type = 'expense' THEN base_amount ELSE 0 END) AS expense_total,
                           SUM(CASE WHEN type = 'income' THEN base_amount ELSE -base_amount END) AS net_profit,
                           COUNT(*) AS tx_count
                    FROM transactions
                    WHERE company_id = :cid AND deleted_at IS NULL AND status IN ('completed', 'approved')";
            if ($dateFrom) {
                $sql .= " AND transaction_date >= :df";
                $params['df'] = $dateFrom;
            }
            if ($dateTo) {
                $sql .= " AND transaction_date <= :dt";
                $params['dt'] = $dateTo;
            }
            $sql .= " GROUP BY period ORDER BY period {$sortDir}";
            return Database::fetchAll($sql, $params);
        }

        if ($type === 'employee_info') {
            $sql = "SELECT e.*, COALESCE(d.name, '—') AS department_name
                    FROM employees e
                    LEFT JOIN departments d ON d.id = e.department_id
                    WHERE e.company_id = :cid AND e.deleted_at IS NULL";
            if ($employeeId) {
                $sql .= " AND e.id = :eid";
                $params['eid'] = $employeeId;
            }
            $sql .= " ORDER BY e.employee_code {$sortDir}";
            return Database::fetchAll($sql, $params);
        }

        if ($type === 'employee_salary' || $type === 'payroll') {
            $sql = "SELECT p.*, e.employee_code, e.full_name, COALESCE(d.name, '—') AS department_name,
                           CONCAT(p.period_year, '-', LPAD(p.period_month, 2, '0')) AS period_label
                    FROM payrolls p
                    INNER JOIN employees e ON e.id = p.employee_id
                    LEFT JOIN departments d ON d.id = e.department_id
                    WHERE p.company_id = :cid AND p.deleted_at IS NULL";
            if ($employeeId) {
                $sql .= " AND p.employee_id = :eid";
                $params['eid'] = $employeeId;
            }
            if ($dateFrom) {
                $sql .= " AND p.period_start >= :df";
                $params['df'] = $dateFrom;
            }
            if ($dateTo) {
                $sql .= " AND p.period_end <= :dt";
                $params['dt'] = $dateTo;
            }
            $sql .= " ORDER BY p.period_year {$sortDir}, p.period_month {$sortDir}, e.employee_code ASC";
            return Database::fetchAll($sql, $params);
        }

        if ($type === 'bonus') {
            $sql = "SELECT b.*, e.employee_code, e.full_name AS employee_name
                    FROM bonuses b
                    INNER JOIN employees e ON e.id = b.employee_id
                    WHERE b.company_id = :cid AND b.deleted_at IS NULL";
            if ($employeeId) {
                $sql .= " AND b.employee_id = :eid";
                $params['eid'] = $employeeId;
            }
            if ($dateFrom) {
                $sql .= " AND b.awarded_date >= :df";
                $params['df'] = $dateFrom;
            }
            if ($dateTo) {
                $sql .= " AND b.awarded_date <= :dt";
                $params['dt'] = $dateTo;
            }
            $sql .= " ORDER BY b.awarded_date {$sortDir}";
            return Database::fetchAll($sql, $params);
        }

        if ($type === 'performance') {
            $sql = "SELECT pr.*, e.employee_code, e.full_name AS employee_name, COALESCE(d.name, '—') AS department_name
                    FROM performance_records pr
                    INNER JOIN employees e ON e.id = pr.employee_id
                    LEFT JOIN departments d ON d.id = e.department_id
                    WHERE pr.company_id = :cid AND pr.deleted_at IS NULL";
            if ($employeeId) {
                $sql .= " AND pr.employee_id = :eid";
                $params['eid'] = $employeeId;
            }
            $sql .= " ORDER BY pr.computed_score {$sortDir}";
            return Database::fetchAll($sql, $params);
        }

        return [];
    }
}
