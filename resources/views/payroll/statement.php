<div class="d-flex justify-content-between align-items-center mb-3 no-print">
    <a href="/payroll" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Back to Payroll</a>
    <div class="d-flex gap-2">
        <a href="/payroll/<?= (int) $payroll['id'] ?>/statement?format=pdf" class="btn btn-sm btn-outline-danger"><i class="bi bi-file-earmark-pdf me-1"></i>PDF</a>
        <a href="/payroll/<?= (int) $payroll['id'] ?>/statement?format=xlsx" class="btn btn-sm btn-outline-success"><i class="bi bi-file-earmark-excel me-1"></i>Excel</a>
        <button type="button" class="btn btn-sm btn-primary" onclick="window.print()"><i class="bi bi-printer me-1"></i>Print</button>
    </div>
</div>

<div class="cms-card p-4 mx-auto" style="max-width:780px;">
    <div class="d-flex justify-content-between align-items-start border-bottom pb-3 mb-4">
        <div>
            <h4 class="fw-bold mb-1"><?= e($company['name'] ?? 'NovaTech Solutions') ?></h4>
            <div class="text-muted small"><?= e($company['address'] ?? '') ?></div>
        </div>
        <div class="text-end">
            <div class="fw-bold fs-5 text-primary"><?= e(__('payroll.statement')) ?></div>
            <div class="small text-muted"><?= e($payroll['payroll_code']) ?></div>
            <span class="badge <?= $payroll['status'] === 'paid' ? 'bg-success' : 'bg-warning' ?> mt-1"><?= e(strtoupper($payroll['status'])) ?></span>
        </div>
    </div>

    <div class="row g-3 mb-4 small">
        <div class="col-6">
            <div><strong>Employee:</strong> <?= e($payroll['full_name']) ?> (<?= e($payroll['employee_code']) ?>)</div>
            <div><strong>Department:</strong> <?= e($payroll['department_name'] ?? '—') ?></div>
            <div><strong>Position:</strong> <?= e($payroll['position']) ?></div>
        </div>
        <div class="col-6 text-end">
            <div><strong>Period:</strong> <?= e(format_date($payroll['period_start'])) ?> &mdash; <?= e(format_date($payroll['period_end'])) ?></div>
            <div><strong>Payment Model:</strong> <?= e(ucfirst($payroll['payment_type'])) ?></div>
            <div><strong>Payment Date:</strong> <?= e(format_date($payroll['payment_date'])) ?></div>
        </div>
    </div>

    <table class="table table-bordered">
        <thead>
        <tr><th>Component</th><th>Type</th><th>Qty</th><th>Rate</th><th class="text-end">Amount</th></tr>
        </thead>
        <tbody>
        <?php foreach ($payroll['components'] as $c): ?>
            <tr>
                <td class="fw-semibold"><?= e($c['label']) ?></td>
                <td class="small text-uppercase"><?= e($c['component_type']) ?></td>
                <td><?= e($c['quantity']) ?></td>
                <td><?= e(format_money($c['rate'], $payroll['currency_code'])) ?></td>
                <td class="text-end fw-semibold <?= $c['component_type'] === 'deduction' ? 'text-danger' : '' ?>">
                    <?= $c['component_type'] === 'deduction' ? '-' : '' ?><?= e(format_money($c['amount'], $payroll['currency_code'])) ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot>
        <tr>
            <th colspan="4" class="text-end">Gross Salary</th>
            <th class="text-end"><?= e(format_money($payroll['gross_salary'], $payroll['currency_code'])) ?></th>
        </tr>
        <tr>
            <th colspan="4" class="text-end text-danger">Authorized Deductions</th>
            <th class="text-end text-danger">-<?= e(format_money($payroll['authorized_deductions'], $payroll['currency_code'])) ?></th>
        </tr>
        <tr class="table-primary">
            <th colspan="4" class="text-end fs-5">Net Payable Salary</th>
            <th class="text-end fs-5"><?= e(format_money($payroll['net_salary'], $payroll['currency_code'])) ?></th>
        </tr>
        </tfoot>
    </table>

    <div class="mt-3 small text-muted">
        Bank Account: <?= e($payroll['bank_name'] ?? '—') ?> &bull; <?= e($payroll['bank_account_number'] ?? '—') ?>
    </div>
</div>
