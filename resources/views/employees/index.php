<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <div>
        <h3 class="fw-bold mb-0"><?= e(__('employees.title')) ?></h3>
        <p class="text-muted small mb-0">Comprehensive HRM directory, contracts, salary models, and employee profiles</p>
    </div>
    <?php if (can('employees.create')): ?>
        <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#newEmployeeModal">
            <i class="bi bi-person-plus me-1"></i><?= e(__('employees.add_employee')) ?>
        </button>
    <?php endif; ?>
</div>

<!-- Filters -->
<div class="cms-card p-3 mb-3">
    <form action="/employees" method="GET" class="row g-2 align-items-end">
        <div class="col-md-4">
            <label class="form-label small fw-semibold"><?= e(__('common.search')) ?></label>
            <input type="text" name="search" value="<?= e($filters['search'] ?? '') ?>" class="form-control form-control-sm" placeholder="Name, ID, email, position...">
        </div>
        <div class="col-md-3">
            <label class="form-label small fw-semibold">Department</label>
            <select name="department_id" class="form-select form-select-sm">
                <option value=""><?= e(__('common.all')) ?></option>
                <?php foreach ($departments as $d): ?>
                    <option value="<?= (int) $d['id'] ?>" <?= (string) ($filters['department_id'] ?? '') === (string) $d['id'] ? 'selected' : '' ?>><?= e($d['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label small fw-semibold"><?= e(__('common.status')) ?></label>
            <select name="status" class="form-select form-select-sm">
                <option value=""><?= e(__('common.all')) ?></option>
                <option value="active" <?= ($filters['status'] ?? '') === 'active' ? 'selected' : '' ?>>Active</option>
                <option value="on_leave" <?= ($filters['status'] ?? '') === 'on_leave' ? 'selected' : '' ?>>On Leave</option>
                <option value="suspended" <?= ($filters['status'] ?? '') === 'suspended' ? 'selected' : '' ?>>Suspended</option>
                <option value="resigned" <?= ($filters['status'] ?? '') === 'resigned' ? 'selected' : '' ?>>Resigned</option>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label small fw-semibold">Payment Type</label>
            <select name="payment_type" class="form-select form-select-sm">
                <option value=""><?= e(__('common.all')) ?></option>
                <option value="fixed" <?= ($filters['payment_type'] ?? '') === 'fixed' ? 'selected' : '' ?>>Fixed</option>
                <option value="hourly" <?= ($filters['payment_type'] ?? '') === 'hourly' ? 'selected' : '' ?>>Hourly</option>
                <option value="daily" <?= ($filters['payment_type'] ?? '') === 'daily' ? 'selected' : '' ?>>Daily</option>
                <option value="project" <?= ($filters['payment_type'] ?? '') === 'project' ? 'selected' : '' ?>>Project</option>
                <option value="task" <?= ($filters['payment_type'] ?? '') === 'task' ? 'selected' : '' ?>>Task</option>
            </select>
        </div>
        <div class="col-md-1 d-grid">
            <button type="submit" class="btn btn-primary btn-sm"><?= e(__('common.filter')) ?></button>
        </div>
    </form>
</div>

<!-- Employee Directory Table -->
<div class="cms-card overflow-hidden">
    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead>
            <tr>
                <th>Employee</th>
                <th>Department &amp; Position</th>
                <th>Contact</th>
                <th>Hire Date</th>
                <th>Salary Model</th>
                <th><?= e(__('common.status')) ?></th>
                <th class="text-end"><?= e(__('common.actions')) ?></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($employees as $emp): ?>
                <tr>
                    <td>
                        <div class="d-flex align-items-center gap-2">
                            <?php if (!empty($emp['photo_path'])): ?>
                                <img src="/employees/<?= (int) $emp['id'] ?>/photo" alt="" class="cms-avatar">
                            <?php else: ?>
                                <span class="cms-avatar"><?= e(mb_strtoupper(mb_substr($emp['full_name'], 0, 1))) ?></span>
                            <?php endif; ?>
                            <div>
                                <a href="/employees/<?= (int) $emp['id'] ?>" class="fw-bold text-decoration-none"><?= e($emp['full_name']) ?></a>
                                <div class="small text-muted"><?= e($emp['employee_code']) ?></div>
                            </div>
                        </div>
                    </td>
                    <td>
                        <div class="fw-semibold small"><?= e($emp['position']) ?></div>
                        <div class="small text-muted"><?= e($emp['department_name'] ?? 'Unassigned') ?></div>
                    </td>
                    <td class="small">
                        <div><?= e($emp['email'] ?: '—') ?></div>
                        <div class="text-muted"><?= e($emp['phone'] ?: '—') ?></div>
                    </td>
                    <td class="small"><?= e(format_date($emp['employment_date'])) ?></td>
                    <td class="small">
                        <span class="badge bg-primary bg-opacity-10 text-primary text-uppercase"><?= e($emp['payment_type']) ?></span>
                        <div class="fw-bold mt-1">
                            <?php if ($emp['payment_type'] === 'hourly'): ?>
                                <?= e(format_money($emp['hourly_rate'], $emp['currency_code'])) ?>/hr
                            <?php elseif ($emp['payment_type'] === 'daily'): ?>
                                <?= e(format_money($emp['daily_rate'], $emp['currency_code'])) ?>/day
                            <?php else: ?>
                                <?= e(format_money($emp['base_salary'], $emp['currency_code'])) ?>
                            <?php endif; ?>
                        </div>
                    </td>
                    <td>
                        <?php
                        $stCls = match ($emp['employment_status']) {
                            'active' => 'bg-success bg-opacity-10 text-success',
                            'on_leave' => 'bg-warning bg-opacity-15 text-warning',
                            'suspended' => 'bg-danger bg-opacity-10 text-danger',
                            default => 'bg-secondary bg-opacity-10 text-secondary',
                        };
                        ?>
                        <span class="badge <?= $stCls ?>"><?= e(ucwords(str_replace('_', ' ', $emp['employment_status']))) ?></span>
                    </td>
                    <td class="text-end text-nowrap">
                        <a href="/employees/<?= (int) $emp['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-person-vcard me-1"></i>Profile</a>
                        <?php if (can('employees.delete')): ?>
                            <form action="/employees/<?= (int) $emp['id'] ?>/delete" method="POST" class="d-inline" data-confirm="<?= e(__('common.confirm_delete')) ?>">
                                <?= csrf_field() ?>
                                <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Create Employee Modal -->
<div class="modal fade" id="newEmployeeModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <form action="/employees" method="POST" enctype="multipart/form-data" class="modal-content">
            <?= csrf_field() ?>
            <div class="modal-header">
                <h5 class="modal-title fw-bold"><?= e(__('employees.add_employee')) ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Full Name *</label>
                        <input type="text" name="full_name" class="form-control" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-semibold">Department</label>
                        <select name="department_id" class="form-select">
                            <?php foreach ($departments as $d): ?>
                                <option value="<?= (int) $d['id'] ?>"><?= e($d['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-semibold">Position *</label>
                        <input type="text" name="position" class="form-control" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold">Email</label>
                        <input type="email" name="email" class="form-control">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold">Phone</label>
                        <input type="text" name="phone" class="form-control">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold">Hire Date</label>
                        <input type="date" name="employment_date" value="<?= date('Y-m-d') ?>" class="form-control">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-semibold">Payment Model</label>
                        <select name="payment_type" class="form-select">
                            <option value="fixed">Fixed Monthly</option>
                            <option value="hourly">Hourly</option>
                            <option value="daily">Daily</option>
                            <option value="project">Project-based</option>
                            <option value="task">Task-based</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-semibold">Base Salary</label>
                        <input type="number" step="0.01" name="base_salary" value="1500" class="form-control">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-semibold">Hourly Rate</label>
                        <input type="number" step="0.01" name="hourly_rate" value="15" class="form-control">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-semibold">Currency</label>
                        <select name="currency_code" class="form-select">
                            <?php foreach ($currencies as $c): ?>
                                <option value="<?= e($c['code']) ?>"><?= e($c['code']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Profile Photo</label>
                        <input type="file" name="photo" class="form-control" accept="image/*">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Bank &amp; Account</label>
                        <input type="text" name="bank_account_number" class="form-control" placeholder="Account / Card Number">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal"><?= e(__('common.cancel')) ?></button>
                <button type="submit" class="btn btn-primary btn-sm"><?= e(__('common.create')) ?></button>
            </div>
        </form>
    </div>
</div>
