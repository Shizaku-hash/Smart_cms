<?php
$kpis = $dashboard['kpis'];
$curr = $kpis['base_currency'] ?? 'USD';
$widgets = (array) ($prefs['dashboard_widgets_json'] ?? []);
usort($widgets, static fn ($a, $b) => ($a['order'] ?? 99) <=> ($b['order'] ?? 99));
$widgetMap = [];
foreach ($widgets as $w) {
    $widgetMap[$w['id']] = $w;
}
$isVisible = static fn (string $id): bool => ($widgetMap[$id]['visible'] ?? true) === true;
?>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <div>
        <h3 class="fw-bold mb-0"><?= e(__('dashboard.title')) ?></h3>
        <p class="text-muted small mb-0"><?= e(__('dashboard.subtitle')) ?></p>
    </div>

    <div class="d-flex flex-wrap align-items-center gap-2">
        <form action="/dashboard" method="GET" class="d-flex flex-wrap align-items-center gap-2">
            <div class="input-group input-group-sm" style="width:auto;">
                <span class="input-group-text"><?= e(__('dashboard.date_from')) ?></span>
                <input type="date" name="date_from" value="<?= e($dateFrom) ?>" class="form-control">
            </div>
            <div class="input-group input-group-sm" style="width:auto;">
                <span class="input-group-text"><?= e(__('dashboard.date_to')) ?></span>
                <input type="date" name="date_to" value="<?= e($dateTo) ?>" class="form-control">
            </div>
            <button type="submit" class="btn btn-sm btn-primary"><i class="bi bi-funnel me-1"></i><?= e(__('dashboard.apply_range')) ?></button>
            <?php if ($dateFrom || $dateTo): ?>
                <a href="/dashboard" class="btn btn-sm btn-outline-secondary"><?= e(__('common.reset')) ?></a>
            <?php endif; ?>
        </form>
        <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#customizeDashboardModal">
            <i class="bi bi-grid me-1"></i><?= e(__('dashboard.customize_widgets')) ?>
        </button>
    </div>
</div>

<div id="dashboardWidgetsContainer" class="row g-3">
    <?php
    $orderedIds = array_map(static fn ($w) => $w['id'], $widgets);
    $allWidgetIds = ['kpi_cards', 'income_expense_chart', 'profit_chart', 'expense_categories_chart', 'salary_distribution_chart', 'performance_chart', 'recent_transactions', 'upcoming_salaries'];
    foreach ($allWidgetIds as $wid) {
        if (!in_array($wid, $orderedIds, true)) {
            $orderedIds[] = $wid;
        }
    }
    foreach ($orderedIds as $wid):
        if (!$isVisible($wid)) {
            continue;
        }
    ?>

    <?php if ($wid === 'kpi_cards'): ?>
        <div class="col-12" data-widget-id="kpi_cards">
            <div class="row g-3">
                <div class="col-sm-6 col-xl-3">
                    <div class="cms-card cms-kpi-card h-100">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <div class="cms-kpi-label"><?= e(__('dashboard.total_balance')) ?></div>
                                <div class="cms-kpi-value text-primary"><?= e(format_money($kpis['total_balance'], $curr)) ?></div>
                                <div class="small text-muted mt-1"><?= e(__('dashboard.net_profit')) ?>: <?= e(format_money($kpis['net_profit'], $curr)) ?></div>
                            </div>
                            <span class="cms-kpi-icon bg-primary bg-opacity-10 text-primary"><i class="bi bi-bank"></i></span>
                        </div>
                    </div>
                </div>
                <div class="col-sm-6 col-xl-3">
                    <div class="cms-card cms-kpi-card h-100">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <div class="cms-kpi-label"><?= e(__('dashboard.total_income')) ?></div>
                                <div class="cms-kpi-value text-success"><?= e(format_money($kpis['total_income'], $curr)) ?></div>
                                <div class="small text-muted mt-1"><?= e(__('dashboard.monthly_income')) ?>: <?= e(format_money($kpis['monthly_income'], $curr)) ?></div>
                            </div>
                            <span class="cms-kpi-icon bg-success bg-opacity-10 text-success"><i class="bi bi-arrow-down-left-circle"></i></span>
                        </div>
                    </div>
                </div>
                <div class="col-sm-6 col-xl-3">
                    <div class="cms-card cms-kpi-card h-100">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <div class="cms-kpi-label"><?= e(__('dashboard.total_expenses')) ?></div>
                                <div class="cms-kpi-value text-danger"><?= e(format_money($kpis['total_expenses'], $curr)) ?></div>
                                <div class="small text-muted mt-1"><?= e(__('dashboard.monthly_expenses')) ?>: <?= e(format_money($kpis['monthly_expenses'], $curr)) ?></div>
                            </div>
                            <span class="cms-kpi-icon bg-danger bg-opacity-10 text-danger"><i class="bi bi-arrow-up-right-circle"></i></span>
                        </div>
                    </div>
                </div>
                <div class="col-sm-6 col-xl-3">
                    <div class="cms-card cms-kpi-card h-100">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <div class="cms-kpi-label"><?= e(__('dashboard.employee_count')) ?> &amp; <?= e(__('nav.payroll')) ?></div>
                                <div class="cms-kpi-value"><?= (int) $kpis['active_employees'] ?> <span class="fs-6 text-muted fw-normal">/ <?= (int) $kpis['total_employees'] ?></span></div>
                                <div class="small text-muted mt-1">
                                    <?= e(__('dashboard.monthly_salary_expense')) ?>: <strong><?= e(format_money($kpis['monthly_salary_expense'], $curr)) ?></strong>
                                    &bull; <?= e(__('dashboard.pending_payments')) ?>: <span class="text-warning fw-semibold"><?= e(format_money($kpis['pending_payments_total'], $curr)) ?> (<?= (int) $kpis['pending_payments_count'] ?>)</span>
                                </div>
                            </div>
                            <span class="cms-kpi-icon bg-warning bg-opacity-10 text-warning"><i class="bi bi-people"></i></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    <?php elseif ($wid === 'income_expense_chart'): ?>
        <div class="col-lg-7" data-widget-id="income_expense_chart">
            <div class="cms-card p-3 h-100">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="fw-bold mb-0"><?= e(__('dashboard.income_vs_expenses')) ?></h6>
                    <span class="badge bg-primary bg-opacity-10 text-primary">Monthly Statistics</span>
                </div>
                <div style="height:280px;"><canvas id="chartIncomeExpense"></canvas></div>
            </div>
        </div>

    <?php elseif ($wid === 'profit_chart'): ?>
        <div class="col-lg-5" data-widget-id="profit_chart">
            <div class="cms-card p-3 h-100">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="fw-bold mb-0"><?= e(__('dashboard.monthly_profit')) ?></h6>
                    <span class="badge bg-success bg-opacity-10 text-success">Margin <?= e((string) ($dashboard['python_insights']['profit_margin_percent'] ?? 0)) ?>%</span>
                </div>
                <div style="height:280px;"><canvas id="chartMonthlyProfit"></canvas></div>
            </div>
        </div>

    <?php elseif ($wid === 'expense_categories_chart'): ?>
        <div class="col-lg-4" data-widget-id="expense_categories_chart">
            <div class="cms-card p-3 h-100">
                <h6 class="fw-bold mb-3"><?= e(__('dashboard.expense_categories')) ?></h6>
                <div style="height:260px;"><canvas id="chartExpenseCategories"></canvas></div>
            </div>
        </div>

    <?php elseif ($wid === 'salary_distribution_chart'): ?>
        <div class="col-lg-4" data-widget-id="salary_distribution_chart">
            <div class="cms-card p-3 h-100">
                <h6 class="fw-bold mb-3"><?= e(__('dashboard.salary_distribution')) ?></h6>
                <div style="height:260px;"><canvas id="chartSalaryDistribution"></canvas></div>
            </div>
        </div>

    <?php elseif ($wid === 'performance_chart'): ?>
        <div class="col-lg-4" data-widget-id="performance_chart">
            <div class="cms-card p-3 h-100">
                <h6 class="fw-bold mb-3"><?= e(__('dashboard.employee_performance')) ?></h6>
                <div style="height:260px;"><canvas id="chartPerformance"></canvas></div>
            </div>
        </div>

    <?php elseif ($wid === 'recent_transactions'): ?>
        <div class="col-lg-7" data-widget-id="recent_transactions">
            <div class="cms-card overflow-hidden h-100">
                <div class="p-3 border-bottom d-flex justify-content-between align-items-center">
                    <h6 class="fw-bold mb-0"><?= e(__('dashboard.recent_transactions')) ?></h6>
                    <a href="/finance" class="btn btn-sm btn-outline-primary">View All</a>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead>
                        <tr>
                            <th>Code</th>
                            <th><?= e(__('common.category')) ?></th>
                            <th><?= e(__('common.date')) ?></th>
                            <th><?= e(__('common.amount')) ?></th>
                            <th><?= e(__('common.status')) ?></th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($dashboard['recent_transactions'] as $tx): ?>
                            <tr>
                                <td class="fw-semibold small"><?= e($tx['transaction_code']) ?></td>
                                <td>
                                    <span class="badge <?= $tx['type'] === 'income' ? 'bg-success' : 'bg-danger' ?> bg-opacity-10 <?= $tx['type'] === 'income' ? 'text-success' : 'text-danger' ?>">
                                        <?= e($tx['category_name']) ?>
                                    </span>
                                </td>
                                <td class="small text-muted"><?= e(format_date($tx['transaction_date'])) ?></td>
                                <td class="fw-semibold <?= $tx['type'] === 'income' ? 'text-success' : 'text-danger' ?>">
                                    <?= $tx['type'] === 'income' ? '+' : '-' ?><?= e(format_money($tx['base_amount'], $curr)) ?>
                                </td>
                                <td><span class="badge bg-secondary bg-opacity-10 text-body"><?= e(ucfirst($tx['status'])) ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    <?php elseif ($wid === 'upcoming_salaries'): ?>
        <div class="col-lg-5" data-widget-id="upcoming_salaries">
            <div class="cms-card overflow-hidden h-100">
                <div class="p-3 border-bottom d-flex justify-content-between align-items-center">
                    <h6 class="fw-bold mb-0"><?= e(__('dashboard.upcoming_salaries')) ?></h6>
                    <a href="/payroll" class="btn btn-sm btn-outline-primary">Manage Payroll</a>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead>
                        <tr>
                            <th>Employee</th>
                            <th>Due Date</th>
                            <th>Net Salary</th>
                            <th>Status</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php if (empty($dashboard['upcoming_salaries'])): ?>
                            <tr><td colspan="4" class="text-center text-muted py-4"><?= e(__('common.no_data')) ?></td></tr>
                        <?php else: ?>
                            <?php foreach ($dashboard['upcoming_salaries'] as $pr): ?>
                                <tr>
                                    <td>
                                        <div class="fw-semibold small"><?= e($pr['full_name']) ?></div>
                                        <div class="text-muted" style="font-size:0.75rem;"><?= e($pr['department_name'] ?? '—') ?></div>
                                    </td>
                                    <td class="small"><?= e(format_date($pr['payment_date'])) ?></td>
                                    <td class="fw-bold"><?= e(format_money($pr['net_salary'], $pr['currency_code'])) ?></td>
                                    <td><span class="badge bg-warning bg-opacity-15 text-warning"><?= e(ucfirst($pr['status'])) ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endif; ?>
    <?php endforeach; ?>

    <!-- Python FastAPI Predictive Analytics Panel -->
    <div class="col-12">
        <div class="cms-card p-3">
            <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-cpu text-primary fs-5"></i>
                    <h6 class="fw-bold mb-0"><?= e(__('dashboard.python_insights')) ?></h6>
                </div>
                <span class="badge <?= !empty($dashboard['python_insights']['connected']) ? 'bg-success' : 'bg-secondary' ?>">
                    <?= !empty($dashboard['python_insights']['connected']) ? 'FastAPI + Pandas Online' : 'Local SQL Fallback Mode' ?>
                </span>
            </div>
            <div class="row g-3">
                <div class="col-lg-7">
                    <div class="small fw-semibold text-muted mb-2">3-Month Pandas Linear Cashflow Forecast</div>
                    <?php if (empty($dashboard['python_insights']['forecast_next_3_months'])): ?>
                        <div class="text-muted small">Start the Python FastAPI service (<code>start.sh</code>) to view live Pandas projections.</div>
                    <?php else: ?>
                        <div class="row g-2">
                            <?php foreach ($dashboard['python_insights']['forecast_next_3_months'] as $fc): ?>
                                <div class="col-md-4">
                                    <div class="p-2 border rounded bg-body-tertiary">
                                        <div class="fw-bold small"><?= e($fc['month']) ?></div>
                                        <div class="small text-success">Inc: <?= e(format_money($fc['projected_income'], $curr)) ?></div>
                                        <div class="small text-danger">Exp: <?= e(format_money($fc['projected_expense'], $curr)) ?></div>
                                        <div class="small fw-semibold">Net: <?= e(format_money($fc['projected_profit'], $curr)) ?></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="col-lg-5">
                    <div class="small fw-semibold text-muted mb-2">Statistical Expense Anomaly Detector (Mean + 1.5&sigma;)</div>
                    <?php if (empty($dashboard['python_insights']['anomalies'])): ?>
                        <div class="small text-success"><i class="bi bi-shield-check me-1"></i>No abnormal expense spikes detected in selected period.</div>
                    <?php else: ?>
                        <ul class="list-unstyled mb-0 small">
                            <?php foreach ($dashboard['python_insights']['anomalies'] as $an): ?>
                                <li class="py-1 border-bottom d-flex justify-content-between">
                                    <span><strong><?= e($an['transaction_code']) ?></strong> (<?= e($an['category']) ?>)</span>
                                    <span class="text-danger fw-semibold"><?= e(format_money($an['amount'], $curr)) ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Customize Dashboard Modal -->
<div class="modal fade" id="customizeDashboardModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold"><?= e(__('dashboard.customize_widgets')) ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="small text-muted">Toggle visibility or reorder dashboard widgets to match your workflow.</p>
                <div id="widgetCustomizerList" class="list-group">
                    <?php
                    $widgetLabels = [
                        'kpi_cards' => 'KPI Overview Cards',
                        'income_expense_chart' => __('dashboard.income_vs_expenses'),
                        'profit_chart' => __('dashboard.monthly_profit'),
                        'expense_categories_chart' => __('dashboard.expense_categories'),
                        'salary_distribution_chart' => __('dashboard.salary_distribution'),
                        'performance_chart' => __('dashboard.employee_performance'),
                        'recent_transactions' => __('dashboard.recent_transactions'),
                        'upcoming_salaries' => __('dashboard.upcoming_salaries'),
                    ];
                    foreach ($orderedIds as $idx => $wid):
                        $checked = $isVisible($wid) ? 'checked' : '';
                    ?>
                        <div class="list-group-item d-flex align-items-center justify-content-between gap-2" data-wid="<?= e($wid) ?>">
                            <div class="form-check form-switch mb-0">
                                <input class="form-check-input widget-vis-toggle" type="checkbox" <?= $checked ?> id="wchk_<?= e($wid) ?>">
                                <label class="form-check-label small fw-semibold" for="wchk_<?= e($wid) ?>"><?= e($widgetLabels[$wid] ?? $wid) ?></label>
                            </div>
                            <div class="btn-group btn-group-sm">
                                <button type="button" class="btn btn-outline-secondary move-up-btn"><i class="bi bi-arrow-up"></i></button>
                                <button type="button" class="btn btn-outline-secondary move-down-btn"><i class="bi bi-arrow-down"></i></button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal"><?= e(__('common.close')) ?></button>
                <button type="button" class="btn btn-primary btn-sm" id="saveDashboardWidgetsBtn"><?= e(__('common.save')) ?></button>
            </div>
        </div>
    </div>
</div>

<script>
window.DASHBOARD_CHARTS = <?= json_encode($dashboard['charts'], JSON_UNESCAPED_UNICODE) ?>;
</script>
<script src="/assets/js/dashboard.js"></script>
