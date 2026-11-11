<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <div>
        <h3 class="fw-bold mb-0"><?= e(__('payroll.title')) ?></h3>
        <p class="text-muted small mb-0">Flexible compensation (fixed, hourly, daily, project, task) with authorized deduction controls</p>
    </div>
    <?php if ($canViewAll): ?>
        <div class="d-flex flex-wrap gap-2">
            <a href="/payroll/export?year=<?= (int) $year ?>&month=<?= (int) $month ?>&format=pdf" class="btn btn-outline-danger btn-sm">
                <i class="bi bi-file-earmark-pdf me-1"></i><?= e(__('payroll.export_pdf')) ?>
            </a>
            <a href="/payroll/export?year=<?= (int) $year ?>&month=<?= (int) $month ?>&format=xlsx" class="btn btn-outline-success btn-sm">
                <i class="bi bi-file-earmark-excel me-1"></i><?= e(__('payroll.export_excel')) ?>
            </a>
            <?php if (can('payroll.calculate')): ?>
                <form action="/payroll/calculate" method="POST" class="d-inline">
                    <?= csrf_field() ?>
                    <input type="hidden" name="year" value="<?= (int) $year ?>">
                    <input type="hidden" name="month" value="<?= (int) $month ?>">
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="bi bi-calculator me-1"></i><?= e(__('payroll.calculate_monthly')) ?>
                    </button>
                </form>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Period Filter -->
<div class="cms-card p-3 mb-3">
    <form action="/payroll" method="GET" class="row g-2 align-items-end">
        <div class="col-md-2">
            <label class="form-label small fw-semibold">Year</label>
            <input type="number" name="year" value="<?= (int) $year ?>" class="form-control form-control-sm">
        </div>
        <div class="col-md-2">
            <label class="form-label small fw-semibold">Month</label>
            <select name="month" class="form-select form-select-sm">
                <?php for ($m = 1; $m <= 12; $m++): ?>
                    <option value="<?= $m ?>" <?= (int) $month === $m ? 'selected' : '' ?>><?= date('F', mktime(0, 0, 0, $m, 1)) ?></option>
                <?php endfor; ?>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label small fw-semibold"><?= e(__('common.status')) ?></label>
            <select name="status" class="form-select form-select-sm">
                <option value=""><?= e(__('common.all')) ?></option>
                <option value="pending" <?= $statusFilter === 'pending' ? 'selected' : '' ?>>Pending</option>
                <option value="paid" <?= $statusFilter === 'paid' ? 'selected' : '' ?>>Paid</option>
            </select>
        </div>
        <div class="col-md-2">
            <button type="submit" class="btn btn-primary btn-sm"><?= e(__('common.filter')) ?></button>
        </div>
    </form>
</div>

<!-- Monthly Payroll Table -->
<div class="cms-card overflow-hidden mb-4">
    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead>
            <tr>
                <th>Code</th>
                <th>Employee</th>
                <th>Model</th>
                <th>Base</th>
                <th>Overtime</th>
                <th>Task Bonus</th>
                <th>Perf / Other</th>
                <th>Deductions</th>
                <th>Net Salary</th>
                <th><?= e(__('common.status')) ?></th>
                <th class="text-end"><?= e(__('common.actions')) ?></th>
            </tr>
            </thead>
            <tbody>
            <?php if (empty($payrolls)): ?>
                <tr><td colspan="11" class="text-center text-muted py-4"><?= e(__('common.no_data')) ?> Click "Calculate Monthly Payroll" to generate.</td></tr>
            <?php else: ?>
                <?php foreach ($payrolls as $p): ?>
                    <tr>
                        <td class="fw-semibold small"><?= e($p['payroll_code']) ?></td>
                        <td>
                            <div class="fw-semibold small"><?= e($p['full_name']) ?></div>
                            <div class="text-muted" style="font-size:0.74rem;"><?= e($p['employee_code']) ?> &bull; <?= e($p['department_name'] ?? '—') ?></div>
                        </td>
                        <td><span class="badge bg-secondary bg-opacity-10 text-body text-uppercase"><?= e($p['payment_type']) ?></span></td>
                        <td><?= e(format_money($p['base_salary'], $p['currency_code'])) ?></td>
                        <td class="text-info"><?= e(format_money($p['overtime_pay'], $p['currency_code'])) ?></td>
                        <td class="text-success"><?= e(format_money($p['task_bonus'], $p['currency_code'])) ?></td>
                        <td class="text-success"><?= e(format_money((float) $p['performance_bonus'] + (float) $p['other_bonuses'], $p['currency_code'])) ?></td>
                        <td class="text-danger">-<?= e(format_money($p['authorized_deductions'], $p['currency_code'])) ?></td>
                        <td class="fw-bold text-primary"><?= e(format_money($p['net_salary'], $p['currency_code'])) ?></td>
                        <td>
                            <span class="badge <?= $p['status'] === 'paid' ? 'bg-success bg-opacity-10 text-success' : 'bg-warning bg-opacity-15 text-warning' ?>">
                                <?= e(ucfirst($p['status'])) ?>
                            </span>
                        </td>
                        <td class="text-end text-nowrap">
                            <a href="/payroll/<?= (int) $p['id'] ?>/statement" class="btn btn-sm btn-outline-secondary" title="Salary Statement"><i class="bi bi-file-text"></i></a>
                            <?php if ($p['status'] !== 'paid' && can('payroll.calculate')): ?>
                                <button type="button" class="btn btn-sm btn-outline-primary" onclick='editPayroll(<?= json_encode($p, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)' title="Edit Components"><i class="bi bi-sliders"></i></button>
                            <?php endif; ?>
                            <?php if ($p['status'] !== 'paid' && can('payroll.pay')): ?>
                                <form action="/payroll/<?= (int) $p['id'] ?>/pay" method="POST" class="d-inline" data-confirm="Mark <?= e($p['payroll_code']) ?> as PAID and record salary expense?">
                                    <?= csrf_field() ?>
                                    <button type="submit" class="btn btn-sm btn-success" title="Mark Paid"><i class="bi bi-check2-circle"></i> Pay</button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Bonuses & Deductions Management -->
<div class="row g-3">
    <div class="col-lg-6">
        <div class="cms-card p-3 h-100">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h6 class="fw-bold mb-0 text-success"><i class="bi bi-award me-1"></i>Bonuses</h6>
                <?php if (can('payroll.calculate')): ?>
                    <button type="button" class="btn btn-sm btn-outline-success" data-bs-toggle="modal" data-bs-target="#addBonusModal">+ <?= e(__('payroll.add_bonus')) ?></button>
                <?php endif; ?>
            </div>
            <div class="table-responsive">
                <table class="table table-sm">
                    <thead><tr><th>Employee</th><th>Title</th><th>Amount</th><th>Status</th></tr></thead>
                    <tbody>
                    <?php foreach (array_slice($bonuses, 0, 12) as $b): ?>
                        <tr>
                            <td class="small fw-semibold"><?= e($b['employee_name']) ?></td>
                            <td class="small"><?= e($b['title']) ?> <span class="text-muted">(<?= e($b['bonus_type']) ?>)</span></td>
                            <td class="fw-bold text-success">+<?= e(format_money($b['amount'], $b['currency_code'])) ?></td>
                            <td><span class="badge bg-success bg-opacity-10 text-success"><?= e(ucfirst($b['status'])) ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="cms-card p-3 h-100">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <h6 class="fw-bold mb-0 text-danger"><i class="bi bi-shield-exclamation me-1"></i>Deductions &amp; Penalties</h6>
                <?php if (can('payroll.calculate')): ?>
                    <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#addDeductionModal">+ <?= e(__('payroll.add_deduction')) ?></button>
                <?php endif; ?>
            </div>
            <div class="alert alert-warning py-2 px-3 small mb-3">
                <i class="bi bi-info-circle me-1"></i><?= e(__('payroll.unconfirmed_warning')) ?>
            </div>
            <div class="table-responsive">
                <table class="table table-sm">
                    <thead><tr><th>Employee</th><th>Title</th><th>Amount</th><th>Authorization</th></tr></thead>
                    <tbody>
                    <?php foreach (array_slice($deductions, 0, 12) as $d): ?>
                        <tr>
                            <td class="small fw-semibold"><?= e($d['employee_name']) ?></td>
                            <td class="small"><?= e($d['title']) ?></td>
                            <td class="fw-bold text-danger">-<?= e(format_money($d['amount'], $d['currency_code'])) ?></td>
                            <td>
                                <?php if (!empty($d['is_authorized'])): ?>
                                    <span class="badge bg-success bg-opacity-10 text-success">Authorized<?= $d['authorizer_name'] ? ' (' . e($d['authorizer_name']) . ')' : '' ?></span>
                                <?php else: ?>
                                    <?php if (can('payroll.authorize_deductions')): ?>
                                        <form action="/payroll/deductions/<?= (int) $d['id'] ?>/authorize" method="POST" class="d-inline" data-confirm="Authorize this deduction so it applies to payroll?">
                                            <?= csrf_field() ?>
                                            <button type="submit" class="btn btn-xs btn-sm btn-warning py-0 px-2" style="font-size:0.75rem;"><?= e(__('payroll.authorize_deduction')) ?></button>
                                        </form>
                                    <?php else: ?>
                                        <span class="badge bg-warning bg-opacity-15 text-warning">Pending Confirmation</span>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Edit Payroll Components Modal -->
<div class="modal fade" id="editPayrollModal" tabindex="-1">
    <div class="modal-dialog">
        <form id="editPayrollForm" method="POST" class="modal-content">
            <?= csrf_field() ?>
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="epTitle">Edit Salary Components</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row g-2">
                    <div class="col-6"><label class="form-label small fw-semibold">Base Salary</label><input type="number" step="0.01" name="base_salary" id="epBase" class="form-control form-control-sm ep-calc"></div>
                    <div class="col-6"><label class="form-label small fw-semibold">Overtime Pay</label><input type="number" step="0.01" name="overtime_pay" id="epOt" class="form-control form-control-sm ep-calc"></div>
                    <div class="col-6"><label class="form-label small fw-semibold">Completed Tasks Bonus</label><input type="number" step="0.01" name="task_bonus" id="epTask" class="form-control form-control-sm ep-calc"></div>
                    <div class="col-6"><label class="form-label small fw-semibold">Performance Bonus</label><input type="number" step="0.01" name="performance_bonus" id="epPerf" class="form-control form-control-sm ep-calc"></div>
                    <div class="col-6"><label class="form-label small fw-semibold">Other Bonuses</label><input type="number" step="0.01" name="other_bonuses" id="epOther" class="form-control form-control-sm ep-calc"></div>
                    <div class="col-6"><label class="form-label small fw-semibold">Adjustments (+/-)</label><input type="number" step="0.01" name="adjustments" id="epAdj" class="form-control form-control-sm ep-calc"></div>
                    <div class="col-12"><label class="form-label small fw-semibold text-danger">Authorized Deductions</label><input type="number" step="0.01" name="authorized_deductions" id="epDed" class="form-control form-control-sm ep-calc"></div>
                    <div class="col-12"><label class="form-label small fw-semibold">Notes</label><input type="text" name="notes" id="epNotes" class="form-control form-control-sm"></div>
                </div>
                <div class="mt-3 p-2 rounded bg-body-tertiary d-flex justify-content-between align-items-center">
                    <span class="small fw-semibold">Calculated Net Salary:</span>
                    <strong class="fs-5 text-primary" id="epNetPreview">$0.00</strong>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal"><?= e(__('common.cancel')) ?></button>
                <button type="submit" class="btn btn-primary btn-sm"><?= e(__('common.save')) ?></button>
            </div>
        </form>
    </div>
</div>

<!-- Add Bonus Modal -->
<div class="modal fade" id="addBonusModal" tabindex="-1">
    <div class="modal-dialog">
        <form action="/payroll/bonuses" method="POST" class="modal-content">
            <?= csrf_field() ?>
            <div class="modal-header"><h5 class="modal-title fw-bold"><?= e(__('payroll.add_bonus')) ?></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="mb-2"><label class="form-label small fw-semibold">Employee</label>
                    <select name="employee_id" class="form-select form-select-sm" required>
                        <?php foreach ($employees as $emp): ?><option value="<?= (int) $emp['id'] ?>"><?= e($emp['full_name']) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-2"><label class="form-label small fw-semibold">Bonus Type</label>
                    <select name="bonus_type" class="form-select form-select-sm">
                        <option value="performance">Performance</option><option value="task">Task</option><option value="project">Project</option><option value="holiday">Holiday</option><option value="spot">Spot</option>
                    </select>
                </div>
                <div class="mb-2"><label class="form-label small fw-semibold">Title</label><input type="text" name="title" class="form-control form-control-sm" required></div>
                <div class="mb-2"><label class="form-label small fw-semibold">Amount (USD)</label><input type="number" step="0.01" min="0.01" name="amount" class="form-control form-control-sm" required></div>
            </div>
            <div class="modal-footer"><button type="submit" class="btn btn-success btn-sm"><?= e(__('common.save')) ?></button></div>
        </form>
    </div>
</div>

<!-- Add Deduction Modal -->
<div class="modal fade" id="addDeductionModal" tabindex="-1">
    <div class="modal-dialog">
        <form action="/payroll/deductions" method="POST" class="modal-content">
            <?= csrf_field() ?>
            <div class="modal-header"><h5 class="modal-title fw-bold"><?= e(__('payroll.add_deduction')) ?></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="mb-2"><label class="form-label small fw-semibold">Employee</label>
                    <select name="employee_id" class="form-select form-select-sm" required>
                        <?php foreach ($employees as $emp): ?><option value="<?= (int) $emp['id'] ?>"><?= e($emp['full_name']) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-2"><label class="form-label small fw-semibold">Deduction Type</label>
                    <select name="deduction_type" class="form-select form-select-sm">
                        <option value="tax">Tax</option><option value="advance">Advance</option><option value="penalty">Penalty</option><option value="absence">Absence</option><option value="other">Other</option>
                    </select>
                </div>
                <div class="mb-2"><label class="form-label small fw-semibold">Title</label><input type="text" name="title" class="form-control form-control-sm" required></div>
                <div class="mb-2"><label class="form-label small fw-semibold">Amount (USD)</label><input type="number" step="0.01" min="0.01" name="amount" class="form-control form-control-sm" required></div>
                <?php if (can('payroll.authorize_deductions')): ?>
                    <div class="form-check mt-2">
                        <input class="form-check-input" type="checkbox" name="authorize_immediately" value="1" id="authImm">
                        <label class="form-check-label small" for="authImm">Confirm &amp; authorize deduction immediately</label>
                    </div>
                <?php endif; ?>
            </div>
            <div class="modal-footer"><button type="submit" class="btn btn-danger btn-sm"><?= e(__('common.save')) ?></button></div>
        </form>
    </div>
</div>

<script>
const epModal = new bootstrap.Modal(document.getElementById('editPayrollModal'));
function updateEpNet() {
  const sum = ['epBase','epOt','epTask','epPerf','epOther','epAdj'].reduce((a,id) => a + (parseFloat(document.getElementById(id).value) || 0), 0);
  const ded = parseFloat(document.getElementById('epDed').value) || 0;
  document.getElementById('epNetPreview').textContent = '$' + Math.max(0, sum - ded).toFixed(2);
}
document.querySelectorAll('.ep-calc').forEach(el => el.addEventListener('input', updateEpNet));
function editPayroll(p) {
  document.getElementById('editPayrollForm').action = '/payroll/' + p.id + '/components';
  document.getElementById('epTitle').textContent = p.payroll_code + ' — ' + p.full_name;
  document.getElementById('epBase').value = p.base_salary;
  document.getElementById('epOt').value = p.overtime_pay;
  document.getElementById('epTask').value = p.task_bonus;
  document.getElementById('epPerf').value = p.performance_bonus;
  document.getElementById('epOther').value = p.other_bonuses;
  document.getElementById('epAdj').value = p.adjustments;
  document.getElementById('epDed').value = p.authorized_deductions;
  document.getElementById('epNotes').value = p.notes || '';
  updateEpNet();
  epModal.show();
}
</script>
