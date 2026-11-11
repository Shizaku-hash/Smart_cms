<?php
$curr = $summary['base_currency'] ?? 'USD';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <div>
        <h3 class="fw-bold mb-0"><?= e(__('finance.title')) ?></h3>
        <p class="text-muted small mb-0"><?= e(__('finance.net_balance_formula')) ?></p>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <?php if (can('finance.create')): ?>
            <button type="button" class="btn btn-success btn-sm" onclick="openTxModal('income')">
                <i class="bi bi-plus-circle me-1"></i><?= e(__('finance.add_income')) ?>
            </button>
            <button type="button" class="btn btn-danger btn-sm" onclick="openTxModal('expense')">
                <i class="bi bi-dash-circle me-1"></i><?= e(__('finance.add_expense')) ?>
            </button>
        <?php endif; ?>
        <a href="/reports/builder?report_type=complete_financial" class="btn btn-outline-primary btn-sm">
            <i class="bi bi-file-earmark-bar-graph me-1"></i><?= e(__('common.export')) ?>
        </a>
    </div>
</div>

<!-- Balance KPIs -->
<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="cms-card cms-kpi-card">
            <div class="cms-kpi-label"><?= e(__('dashboard.total_income')) ?></div>
            <div class="cms-kpi-value text-success"><?= e(format_money($summary['total_income'], $curr)) ?></div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="cms-card cms-kpi-card">
            <div class="cms-kpi-label"><?= e(__('dashboard.total_expenses')) ?></div>
            <div class="cms-kpi-value text-danger"><?= e(format_money($summary['total_expenses'], $curr)) ?></div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="cms-card cms-kpi-card">
            <div class="cms-kpi-label"><?= e(__('dashboard.total_balance')) ?></div>
            <div class="cms-kpi-value text-primary"><?= e(format_money($summary['net_balance'], $curr)) ?></div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="cms-card cms-kpi-card">
            <div class="cms-kpi-label">Pending Approvals</div>
            <div class="cms-kpi-value text-warning"><?= e(format_money($summary['pending_expenses_total'], $curr)) ?> <span class="fs-6 fw-normal text-muted">(<?= (int) $summary['pending_expenses_count'] ?>)</span></div>
        </div>
    </div>
</div>

<!-- Navigation Tabs -->
<ul class="nav nav-pills mb-3 gap-1">
    <li class="nav-item"><a class="nav-link <?= $tab === 'overview' ? 'active' : '' ?>" href="/finance?tab=overview">All Transactions</a></li>
    <li class="nav-item"><a class="nav-link <?= $tab === 'income' ? 'active' : '' ?>" href="/finance?tab=income"><?= e(__('finance.income')) ?></a></li>
    <li class="nav-item"><a class="nav-link <?= $tab === 'expenses' ? 'active' : '' ?>" href="/finance?tab=expenses"><?= e(__('finance.expenses')) ?></a></li>
    <li class="nav-item"><a class="nav-link <?= $tab === 'recurring' ? 'active' : '' ?>" href="/finance?tab=recurring"><?= e(__('finance.recurring')) ?></a></li>
    <li class="nav-item"><a class="nav-link <?= $tab === 'categories' ? 'active' : '' ?>" href="/finance?tab=categories"><?= e(__('finance.categories')) ?></a></li>
    <li class="nav-item"><a class="nav-link <?= $tab === 'currencies' ? 'active' : '' ?>" href="/finance?tab=currencies"><?= e(__('finance.currencies')) ?></a></li>
</ul>

<?php if (in_array($tab, ['overview', 'income', 'expenses', 'recurring'], true)): ?>
    <!-- Filter Bar -->
    <div class="cms-card p-3 mb-3">
        <form action="/finance" method="GET" class="row g-2 align-items-end">
            <input type="hidden" name="tab" value="<?= e($tab) ?>">
            <div class="col-md-3">
                <label class="form-label small fw-semibold"><?= e(__('common.search')) ?></label>
                <input type="text" name="search" value="<?= e($filters['search'] ?? '') ?>" class="form-control form-control-sm" placeholder="Code, client, description...">
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-semibold"><?= e(__('common.category')) ?></label>
                <?php if ($tab === 'income'): ?>
                    <select name="income_category_id" class="form-select form-select-sm">
                        <option value=""><?= e(__('common.all')) ?></option>
                        <?php foreach ($incomeCategories as $cat): ?>
                            <option value="<?= (int) $cat['id'] ?>" <?= (string) ($filters['income_category_id'] ?? '') === (string) $cat['id'] ? 'selected' : '' ?>><?= e($cat['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                <?php else: ?>
                    <select name="expense_category_id" class="form-select form-select-sm">
                        <option value=""><?= e(__('common.all')) ?></option>
                        <?php foreach ($expenseCategories as $cat): ?>
                            <option value="<?= (int) $cat['id'] ?>" <?= (string) ($filters['expense_category_id'] ?? '') === (string) $cat['id'] ? 'selected' : '' ?>><?= e($cat['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                <?php endif; ?>
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-semibold"><?= e(__('finance.responsible_employee')) ?></label>
                <select name="employee_id" class="form-select form-select-sm">
                    <option value=""><?= e(__('common.all')) ?></option>
                    <?php foreach ($employees as $emp): ?>
                        <option value="<?= (int) $emp['id'] ?>" <?= (string) ($filters['employee_id'] ?? '') === (string) $emp['id'] ? 'selected' : '' ?>><?= e($emp['full_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-semibold"><?= e(__('dashboard.date_from')) ?></label>
                <input type="date" name="date_from" value="<?= e($filters['date_from'] ?? '') ?>" class="form-control form-control-sm">
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-semibold"><?= e(__('dashboard.date_to')) ?></label>
                <input type="date" name="date_to" value="<?= e($filters['date_to'] ?? '') ?>" class="form-control form-control-sm">
            </div>
            <div class="col-md-1 d-grid">
                <button type="submit" class="btn btn-primary btn-sm"><?= e(__('common.filter')) ?></button>
            </div>
        </form>

        <?php if ($tab === 'recurring' && can('finance.create')): ?>
            <div class="mt-3 pt-3 border-top d-flex justify-content-between align-items-center">
                <span class="small text-muted">Recurring expenses generate scheduled entries automatically when due.</span>
                <form action="/finance/recurring/process" method="POST">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-sm btn-outline-primary"><i class="bi bi-arrow-repeat me-1"></i><?= e(__('finance.process_recurring')) ?></button>
                </form>
            </div>
        <?php endif; ?>
    </div>

    <!-- Transactions Table -->
    <div class="cms-card overflow-hidden">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                <tr>
                    <th>ID</th>
                    <th><?= e(__('common.date')) ?></th>
                    <th><?= e(__('common.category')) ?></th>
                    <th><?= e(__('finance.vendor_client')) ?></th>
                    <th><?= e(__('finance.responsible_employee')) ?></th>
                    <th><?= e(__('finance.payment_method')) ?></th>
                    <th><?= e(__('common.amount')) ?></th>
                    <th>Base (<?= e($curr) ?>)</th>
                    <th><?= e(__('common.status')) ?></th>
                    <th class="text-end"><?= e(__('common.actions')) ?></th>
                </tr>
                </thead>
                <tbody>
                <?php if (empty($transactions)): ?>
                    <tr><td colspan="10" class="text-center text-muted py-4"><?= e(__('common.no_data')) ?></td></tr>
                <?php else: ?>
                    <?php foreach ($transactions as $tx): ?>
                        <tr>
                            <td>
                                <div class="fw-semibold small"><?= e($tx['transaction_code']) ?></div>
                                <?php if (!empty($tx['is_recurring'])): ?>
                                    <span class="badge bg-info bg-opacity-10 text-info" style="font-size:0.65rem;">Recurring (<?= e($tx['recurring_frequency']) ?>)</span>
                                <?php endif; ?>
                            </td>
                            <td class="small"><?= e(format_date($tx['transaction_date'])) ?></td>
                            <td>
                                <span class="badge" style="background-color:<?= e($tx['category_color']) ?>22;color:<?= e($tx['category_color']) ?>;">
                                    <?= e($tx['category_name']) ?>
                                </span>
                            </td>
                            <td>
                                <div class="small fw-semibold"><?= e($tx['vendor_or_client'] ?: '—') ?></div>
                                <div class="text-muted text-truncate" style="max-width:220px;font-size:0.75rem;"><?= e($tx['description'] ?: '') ?></div>
                            </td>
                            <td class="small"><?= e($tx['employee_name'] ?: '—') ?></td>
                            <td class="small text-capitalize"><?= e(str_replace('_', ' ', $tx['payment_method'])) ?></td>
                            <td class="small">
                                <?= e(format_money($tx['original_amount'], $tx['currency_code'])) ?>
                                <span class="text-muted">(<?= e($tx['currency_code']) ?>)</span>
                            </td>
                            <td class="fw-bold <?= $tx['type'] === 'income' ? 'text-success' : 'text-danger' ?>">
                                <?= $tx['type'] === 'income' ? '+' : '-' ?><?= e(format_money($tx['base_amount'], $curr)) ?>
                            </td>
                            <td>
                                <?php
                                $stBadge = match ($tx['status']) {
                                    'completed', 'approved' => 'bg-success bg-opacity-10 text-success',
                                    'pending' => 'bg-warning bg-opacity-15 text-warning',
                                    default => 'bg-secondary bg-opacity-10 text-secondary',
                                };
                                ?>
                                <span class="badge <?= $stBadge ?>"><?= e(ucfirst($tx['status'])) ?></span>
                            </td>
                            <td class="text-end text-nowrap">
                                <?php if (!empty($tx['attachment_path'])): ?>
                                    <a href="/finance/transactions/<?= (int) $tx['id'] ?>/attachment" class="btn btn-sm btn-outline-secondary" title="Download Attachment"><i class="bi bi-paperclip"></i></a>
                                <?php endif; ?>
                                <?php if ($tx['status'] === 'pending' && can('finance.approve')): ?>
                                    <form action="/finance/transactions/<?= (int) $tx['id'] ?>/approve" method="POST" class="d-inline">
                                        <?= csrf_field() ?>
                                        <button type="submit" class="btn btn-sm btn-outline-success" title="Approve"><i class="bi bi-check-lg"></i></button>
                                    </form>
                                <?php endif; ?>
                                <?php if (can('finance.edit')): ?>
                                    <button type="button" class="btn btn-sm btn-outline-primary" onclick='editTx(<?= json_encode($tx, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'><i class="bi bi-pencil"></i></button>
                                <?php endif; ?>
                                <?php if (can('finance.delete')): ?>
                                    <form action="/finance/transactions/<?= (int) $tx['id'] ?>/delete" method="POST" class="d-inline" data-confirm="<?= e(__('common.confirm_delete')) ?>">
                                        <?= csrf_field() ?>
                                        <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
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

<?php elseif ($tab === 'categories'): ?>
    <div class="row g-3">
        <div class="col-lg-4">
            <div class="cms-card p-3">
                <h6 class="fw-bold mb-3">Create Custom Category</h6>
                <form action="/finance/categories" method="POST">
                    <?= csrf_field() ?>
                    <div class="mb-2">
                        <label class="form-label small fw-semibold">Type</label>
                        <select name="kind" class="form-select form-select-sm">
                            <option value="expense">Expense Category</option>
                            <option value="income">Income Category</option>
                        </select>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small fw-semibold">Category Name</label>
                        <input type="text" name="name" class="form-control form-control-sm" required placeholder="e.g. R&D Lab Supplies">
                    </div>
                    <div class="mb-2">
                        <label class="form-label small fw-semibold">Color Tag</label>
                        <input type="color" name="color" value="#6366f1" class="form-control form-control-color form-control-sm">
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold"><?= e(__('common.description')) ?></label>
                        <input type="text" name="description" class="form-control form-control-sm">
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm w-100"><?= e(__('common.create')) ?></button>
                </form>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="cms-card p-3 h-100">
                <h6 class="fw-bold mb-3 text-success">Income Categories</h6>
                <ul class="list-group list-group-flush">
                    <?php foreach ($incomeCategories as $c): ?>
                        <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                            <span><span class="badge me-2" style="background:<?= e($c['color']) ?>;">&nbsp;</span><?= e($c['name']) ?></span>
                            <small class="text-muted"><?= $c['is_system'] ? 'Default' : 'Custom' ?></small>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="cms-card p-3 h-100">
                <h6 class="fw-bold mb-3 text-danger">Expense Categories</h6>
                <ul class="list-group list-group-flush">
                    <?php foreach ($expenseCategories as $c): ?>
                        <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                            <span><span class="badge me-2" style="background:<?= e($c['color']) ?>;">&nbsp;</span><?= e($c['name']) ?></span>
                            <small class="text-muted"><?= $c['is_system'] ? 'Default' : 'Custom' ?></small>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    </div>

<?php elseif ($tab === 'currencies'): ?>
    <div class="row g-3">
        <div class="col-lg-5">
            <div class="cms-card p-3">
                <h6 class="fw-bold mb-3">Update Exchange Rate</h6>
                <form action="/finance/exchange-rates" method="POST">
                    <?= csrf_field() ?>
                    <div class="row g-2">
                        <div class="col-6">
                            <label class="form-label small fw-semibold">From Currency</label>
                            <select name="from_currency" class="form-select form-select-sm">
                                <?php foreach ($currencies as $c): ?>
                                    <option value="<?= e($c['code']) ?>"><?= e($c['code']) ?> — <?= e($c['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-semibold">To Currency</label>
                            <select name="to_currency" class="form-select form-select-sm">
                                <?php foreach ($currencies as $c): ?>
                                    <option value="<?= e($c['code']) ?>" <?= $c['code'] === 'USD' ? 'selected' : '' ?>><?= e($c['code']) ?> — <?= e($c['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Exchange Rate</label>
                            <input type="number" step="0.00000001" name="rate" class="form-control form-control-sm" required placeholder="e.g. 1.08">
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Effective Date</label>
                            <input type="date" name="effective_date" value="<?= date('Y-m-d') ?>" class="form-control form-control-sm" required>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm mt-3 w-100"><?= e(__('common.save')) ?></button>
                </form>
            </div>
        </div>
        <div class="col-lg-7">
            <div class="cms-card overflow-hidden">
                <div class="p-3 border-bottom"><h6 class="fw-bold mb-0">Configured Exchange Rates</h6></div>
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead><tr><th>Pair</th><th>Rate</th><th>Effective Date</th></tr></thead>
                        <tbody>
                        <?php foreach ($exchangeRates as $er): ?>
                            <tr>
                                <td class="fw-semibold"><?= e($er['from_currency']) ?> &rarr; <?= e($er['to_currency']) ?></td>
                                <td><?= e(rtrim(rtrim((string) $er['rate'], '0'), '.')) ?></td>
                                <td class="small text-muted"><?= e(format_date($er['effective_date'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- Add / Edit Transaction Modal -->
<div class="modal fade" id="txModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <form id="txForm" action="/finance/transactions" method="POST" enctype="multipart/form-data" class="modal-content">
            <?= csrf_field() ?>
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="txModalTitle">Record Transaction</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="type" id="txType" value="income">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold"><?= e(__('common.amount')) ?> *</label>
                        <input type="number" step="0.01" min="0.01" name="original_amount" id="txAmount" class="form-control" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold"><?= e(__('common.currency')) ?> *</label>
                        <select name="currency_code" id="txCurrency" class="form-select">
                            <?php foreach ($currencies as $c): ?>
                                <option value="<?= e($c['code']) ?>" <?= $c['is_default'] ? 'selected' : '' ?>><?= e($c['code']) ?> (<?= e($c['symbol']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold"><?= e(__('common.date')) ?> *</label>
                        <input type="date" name="transaction_date" id="txDate" value="<?= date('Y-m-d') ?>" class="form-control" required>
                    </div>
                    <div class="col-md-6" id="txIncCatWrap">
                        <label class="form-label small fw-semibold">Income Category</label>
                        <select name="income_category_id" id="txIncCat" class="form-select">
                            <?php foreach ($incomeCategories as $c): ?>
                                <option value="<?= (int) $c['id'] ?>"><?= e($c['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6 d-none" id="txExpCatWrap">
                        <label class="form-label small fw-semibold">Expense Category</label>
                        <select name="expense_category_id" id="txExpCat" class="form-select">
                            <?php foreach ($expenseCategories as $c): ?>
                                <option value="<?= (int) $c['id'] ?>"><?= e($c['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold"><?= e(__('finance.payment_method')) ?></label>
                        <select name="payment_method" id="txMethod" class="form-select">
                            <option value="bank_transfer">Bank Transfer</option>
                            <option value="cash">Cash</option>
                            <option value="card">Card</option>
                            <option value="online">Online</option>
                            <option value="check">Check</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold"><?= e(__('finance.vendor_client')) ?></label>
                        <input type="text" name="vendor_or_client" id="txVendor" class="form-control">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold"><?= e(__('finance.responsible_employee')) ?></label>
                        <select name="employee_id" id="txEmp" class="form-select">
                            <option value="">— None —</option>
                            <?php foreach ($employees as $emp): ?>
                                <option value="<?= (int) $emp['id'] ?>"><?= e($emp['employee_code']) ?> — <?= e($emp['full_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold"><?= e(__('common.status')) ?></label>
                        <select name="status" id="txStatus" class="form-select">
                            <option value="completed">Completed</option>
                            <option value="pending">Pending Approval</option>
                            <option value="approved">Approved</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold"><?= e(__('finance.attachment')) ?></label>
                        <input type="file" name="attachment" class="form-control">
                    </div>
                    <div class="col-12">
                        <label class="form-label small fw-semibold"><?= e(__('common.description')) ?></label>
                        <textarea name="description" id="txDesc" rows="2" class="form-control"></textarea>
                    </div>
                    <div class="col-12" id="txRecurringWrap">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="is_recurring" value="1" id="txRecurring">
                            <label class="form-check-label small fw-semibold" for="txRecurring">Recurring Expense Schedule</label>
                        </div>
                        <select name="recurring_frequency" class="form-select form-select-sm mt-2" style="max-width:220px;">
                            <option value="monthly">Monthly</option>
                            <option value="weekly">Weekly</option>
                            <option value="quarterly">Quarterly</option>
                            <option value="yearly">Yearly</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal"><?= e(__('common.cancel')) ?></button>
                <button type="submit" class="btn btn-primary btn-sm"><?= e(__('common.save')) ?></button>
            </div>
        </form>
    </div>
</div>

<script>
const txModal = new bootstrap.Modal(document.getElementById('txModal'));
function openTxModal(type) {
  document.getElementById('txForm').action = '/finance/transactions';
  document.getElementById('txForm').reset();
  document.getElementById('txType').value = type;
  document.getElementById('txDate').value = new Date().toISOString().slice(0, 10);
  document.getElementById('txModalTitle').textContent = type === 'income' ? 'Record Income' : 'Register Expense';
  document.getElementById('txIncCatWrap').classList.toggle('d-none', type !== 'income');
  document.getElementById('txExpCatWrap').classList.toggle('d-none', type !== 'expense');
  document.getElementById('txRecurringWrap').classList.toggle('d-none', type !== 'expense');
  txModal.show();
}
function editTx(tx) {
  openTxModal(tx.type);
  document.getElementById('txForm').action = '/finance/transactions/' + tx.id + '/update';
  document.getElementById('txModalTitle').textContent = 'Edit ' + tx.transaction_code;
  document.getElementById('txAmount').value = tx.original_amount;
  document.getElementById('txCurrency').value = tx.currency_code;
  document.getElementById('txDate').value = tx.transaction_date;
  document.getElementById('txMethod').value = tx.payment_method;
  document.getElementById('txVendor').value = tx.vendor_or_client || '';
  document.getElementById('txEmp').value = tx.employee_id || '';
  document.getElementById('txStatus').value = tx.status;
  document.getElementById('txDesc').value = tx.description || '';
  if (tx.income_category_id) document.getElementById('txIncCat').value = tx.income_category_id;
  if (tx.expense_category_id) document.getElementById('txExpCat').value = tx.expense_category_id;
}
</script>
