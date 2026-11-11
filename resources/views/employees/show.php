<?php
$emp = $profile['employee'];
$eid = (int) $emp['id'];
?>

<div class="cms-card p-4 mb-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div class="d-flex align-items-center gap-3">
            <?php if (!empty($emp['photo_path'])): ?>
                <img src="/employees/<?= $eid ?>/photo" alt="" class="rounded-circle object-fit-cover" style="width:68px;height:68px;">
            <?php else: ?>
                <span class="cms-avatar" style="width:68px;height:68px;font-size:1.5rem;"><?= e(mb_strtoupper(mb_substr($emp['full_name'], 0, 1))) ?></span>
            <?php endif; ?>
            <div>
                <div class="d-flex align-items-center gap-2">
                    <h3 class="fw-bold mb-0"><?= e($emp['full_name']) ?></h3>
                    <span class="badge bg-primary bg-opacity-10 text-primary"><?= e($emp['employee_code']) ?></span>
                    <span class="badge bg-success bg-opacity-10 text-success"><?= e(ucwords(str_replace('_', ' ', $emp['employment_status']))) ?></span>
                </div>
                <div class="text-muted small mt-1">
                    <?= e($emp['position']) ?> &bull; <?= e($emp['department_name'] ?? 'Unassigned') ?> &bull; Joined <?= e(format_date($emp['employment_date'])) ?>
                </div>
            </div>
        </div>
        <a href="/employees" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>All Employees</a>
    </div>
</div>

<!-- 8 Required Profile Sections -->
<ul class="nav nav-tabs mb-3" role="tablist">
    <li class="nav-item"><button class="nav-link <?= $activeTab === 'personal' ? 'active' : '' ?>" data-bs-toggle="tab" data-bs-target="#tabPersonal" type="button">1. <?= e(__('employees.personal_info')) ?></button></li>
    <li class="nav-item"><button class="nav-link <?= $activeTab === 'employment' ? 'active' : '' ?>" data-bs-toggle="tab" data-bs-target="#tabEmployment" type="button">2. <?= e(__('employees.employment_info')) ?></button></li>
    <li class="nav-item"><button class="nav-link <?= $activeTab === 'salary' ? 'active' : '' ?>" data-bs-toggle="tab" data-bs-target="#tabSalary" type="button">3. <?= e(__('employees.salary_info')) ?></button></li>
    <li class="nav-item"><button class="nav-link <?= $activeTab === 'attendance' ? 'active' : '' ?>" data-bs-toggle="tab" data-bs-target="#tabAttendance" type="button">4. <?= e(__('employees.attendance_history')) ?></button></li>
    <li class="nav-item"><button class="nav-link <?= $activeTab === 'performance' ? 'active' : '' ?>" data-bs-toggle="tab" data-bs-target="#tabPerformance" type="button">5. <?= e(__('employees.performance_history')) ?></button></li>
    <li class="nav-item"><button class="nav-link <?= $activeTab === 'bonuses' ? 'active' : '' ?>" data-bs-toggle="tab" data-bs-target="#tabBonuses" type="button">6. <?= e(__('employees.bonuses_penalties')) ?></button></li>
    <li class="nav-item"><button class="nav-link <?= $activeTab === 'payments' ? 'active' : '' ?>" data-bs-toggle="tab" data-bs-target="#tabPayments" type="button">7. <?= e(__('employees.payment_history')) ?></button></li>
    <li class="nav-item"><button class="nav-link <?= $activeTab === 'documents' ? 'active' : '' ?>" data-bs-toggle="tab" data-bs-target="#tabDocuments" type="button">8. <?= e(__('employees.documents')) ?></button></li>
</ul>

<div class="tab-content">
    <!-- 1. Personal Info -->
    <div class="tab-pane fade <?= $activeTab === 'personal' ? 'show active' : '' ?>" id="tabPersonal">
        <div class="cms-card p-4">
            <form action="/employees/<?= $eid ?>/update" method="POST" enctype="multipart/form-data">
                <?= csrf_field() ?>
                <div class="row g-3">
                    <div class="col-md-4"><label class="form-label small fw-semibold">Full Name</label><input type="text" name="full_name" value="<?= e($emp['full_name']) ?>" class="form-control" <?= $canEditAll ? '' : 'readonly' ?>></div>
                    <div class="col-md-4"><label class="form-label small fw-semibold">Date of Birth</label><input type="date" name="date_of_birth" value="<?= e($emp['date_of_birth'] ?? '') ?>" class="form-control" <?= $canEditAll ? '' : 'readonly' ?>></div>
                    <div class="col-md-4"><label class="form-label small fw-semibold">Gender</label>
                        <select name="gender" class="form-select" <?= $canEditAll ? '' : 'disabled' ?>>
                            <option value="male" <?= $emp['gender'] === 'male' ? 'selected' : '' ?>>Male</option>
                            <option value="female" <?= $emp['gender'] === 'female' ? 'selected' : '' ?>>Female</option>
                            <option value="other" <?= $emp['gender'] === 'other' ? 'selected' : '' ?>>Other</option>
                        </select>
                    </div>
                    <div class="col-md-4"><label class="form-label small fw-semibold">Email</label><input type="email" name="email" value="<?= e($emp['email'] ?? '') ?>" class="form-control" <?= $canEditAll ? '' : 'readonly' ?>></div>
                    <div class="col-md-4"><label class="form-label small fw-semibold">Phone</label><input type="text" name="phone" value="<?= e($emp['phone'] ?? '') ?>" class="form-control"></div>
                    <div class="col-md-4"><label class="form-label small fw-semibold">Profile Photo</label><input type="file" name="photo" class="form-control" accept="image/*"></div>
                    <div class="col-12"><label class="form-label small fw-semibold">Home Address</label><input type="text" name="home_address" value="<?= e($emp['home_address'] ?? '') ?>" class="form-control"></div>
                    <div class="col-md-4"><label class="form-label small fw-semibold">Emergency Contact Name</label><input type="text" name="emergency_contact_name" value="<?= e($emp['emergency_contact_name'] ?? '') ?>" class="form-control"></div>
                    <div class="col-md-4"><label class="form-label small fw-semibold">Emergency Phone</label><input type="text" name="emergency_contact_phone" value="<?= e($emp['emergency_contact_phone'] ?? '') ?>" class="form-control"></div>
                    <div class="col-md-4"><label class="form-label small fw-semibold">Relationship</label><input type="text" name="emergency_contact_relation" value="<?= e($emp['emergency_contact_relation'] ?? '') ?>" class="form-control"></div>
                    <div class="col-12"><label class="form-label small fw-semibold">Additional Notes</label><textarea name="notes" rows="2" class="form-control" <?= $canEditAll ? '' : 'readonly' ?>><?= e($emp['notes'] ?? '') ?></textarea></div>
                </div>
                <button type="submit" class="btn btn-primary btn-sm mt-3"><?= e(__('common.save')) ?></button>
            </form>
        </div>
    </div>

    <!-- 2. Employment Info -->
    <div class="tab-pane fade <?= $activeTab === 'employment' ? 'show active' : '' ?>" id="tabEmployment">
        <div class="cms-card p-4">
            <form action="/employees/<?= $eid ?>/update" method="POST">
                <?= csrf_field() ?>
                <div class="row g-3">
                    <div class="col-md-4"><label class="form-label small fw-semibold">Position</label><input type="text" name="position" value="<?= e($emp['position']) ?>" class="form-control" <?= $canEditAll ? '' : 'readonly' ?>></div>
                    <div class="col-md-4"><label class="form-label small fw-semibold">Department</label>
                        <select name="department_id" class="form-select" <?= $canEditAll ? '' : 'disabled' ?>>
                            <?php foreach ($departments as $d): ?>
                                <option value="<?= (int) $d['id'] ?>" <?= (int) $emp['department_id'] === (int) $d['id'] ? 'selected' : '' ?>><?= e($d['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4"><label class="form-label small fw-semibold">Employment Status</label>
                        <select name="employment_status" class="form-select" <?= $canEditAll ? '' : 'disabled' ?>>
                            <?php foreach (['active' => 'Active', 'on_leave' => 'On Leave', 'suspended' => 'Suspended', 'resigned' => 'Resigned'] as $k => $lbl): ?>
                                <option value="<?= $k ?>" <?= $emp['employment_status'] === $k ? 'selected' : '' ?>><?= $lbl ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4"><label class="form-label small fw-semibold">Employment Date</label><input type="date" name="employment_date" value="<?= e($emp['employment_date']) ?>" class="form-control" <?= $canEditAll ? '' : 'readonly' ?>></div>
                    <div class="col-md-4"><label class="form-label small fw-semibold">Contract Type</label>
                        <select name="contract_type" class="form-select" <?= $canEditAll ? '' : 'disabled' ?>>
                            <?php foreach (['full_time' => 'Full Time', 'part_time' => 'Part Time', 'contract' => 'Contract', 'freelance' => 'Freelance'] as $k => $lbl): ?>
                                <option value="<?= $k ?>" <?= $emp['contract_type'] === $k ? 'selected' : '' ?>><?= $lbl ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4"><label class="form-label small fw-semibold">Contract End Date</label><input type="date" name="contract_end_date" value="<?= e($emp['contract_end_date'] ?? '') ?>" class="form-control" <?= $canEditAll ? '' : 'readonly' ?>></div>
                    <div class="col-12"><label class="form-label small fw-semibold">Contract Notes</label><textarea name="contract_notes" rows="2" class="form-control" <?= $canEditAll ? '' : 'readonly' ?>><?= e($emp['contract_notes'] ?? '') ?></textarea></div>
                </div>
                <?php if ($canEditAll): ?><button type="submit" class="btn btn-primary btn-sm mt-3"><?= e(__('common.save')) ?></button><?php endif; ?>
            </form>
        </div>
    </div>

    <!-- 3. Salary Info -->
    <div class="tab-pane fade <?= $activeTab === 'salary' ? 'show active' : '' ?>" id="tabSalary">
        <div class="cms-card p-4">
            <form action="/employees/<?= $eid ?>/update" method="POST">
                <?= csrf_field() ?>
                <div class="row g-3">
                    <div class="col-md-3"><label class="form-label small fw-semibold">Payment Type</label>
                        <select name="payment_type" class="form-select" <?= $canEditAll ? '' : 'disabled' ?>>
                            <?php foreach (['fixed', 'hourly', 'daily', 'project', 'task'] as $pt): ?>
                                <option value="<?= $pt ?>" <?= $emp['payment_type'] === $pt ? 'selected' : '' ?>><?= ucfirst($pt) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3"><label class="form-label small fw-semibold">Base Salary</label><input type="number" step="0.01" name="base_salary" value="<?= e($emp['base_salary']) ?>" class="form-control" <?= $canEditAll ? '' : 'readonly' ?>></div>
                    <div class="col-md-2"><label class="form-label small fw-semibold">Hourly Rate</label><input type="number" step="0.01" name="hourly_rate" value="<?= e($emp['hourly_rate']) ?>" class="form-control" <?= $canEditAll ? '' : 'readonly' ?>></div>
                    <div class="col-md-2"><label class="form-label small fw-semibold">Daily Rate</label><input type="number" step="0.01" name="daily_rate" value="<?= e($emp['daily_rate']) ?>" class="form-control" <?= $canEditAll ? '' : 'readonly' ?>></div>
                    <div class="col-md-2"><label class="form-label small fw-semibold">Currency</label>
                        <select name="currency_code" class="form-select" <?= $canEditAll ? '' : 'disabled' ?>>
                            <?php foreach ($currencies as $c): ?>
                                <option value="<?= e($c['code']) ?>" <?= $emp['currency_code'] === $c['code'] ? 'selected' : '' ?>><?= e($c['code']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4"><label class="form-label small fw-semibold">Bank Name</label><input type="text" name="bank_name" value="<?= e($emp['bank_name'] ?? '') ?>" class="form-control" <?= $canEditAll ? '' : 'readonly' ?>></div>
                    <div class="col-md-4"><label class="form-label small fw-semibold">Account / Card Number</label><input type="text" name="bank_account_number" value="<?= e($emp['bank_account_number'] ?? '') ?>" class="form-control" <?= $canEditAll ? '' : 'readonly' ?>></div>
                    <div class="col-md-4"><label class="form-label small fw-semibold">Routing / SWIFT</label><input type="text" name="bank_routing_code" value="<?= e($emp['bank_routing_code'] ?? '') ?>" class="form-control" <?= $canEditAll ? '' : 'readonly' ?>></div>
                </div>
                <?php if ($canEditAll): ?><button type="submit" class="btn btn-primary btn-sm mt-3"><?= e(__('common.save')) ?></button><?php endif; ?>
            </form>
        </div>
    </div>

    <!-- 4. Attendance History -->
    <div class="tab-pane fade <?= $activeTab === 'attendance' ? 'show active' : '' ?>" id="tabAttendance">
        <div class="cms-card overflow-hidden">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead><tr><th>Date</th><th>Status</th><th>Check In</th><th>Check Out</th><th>Worked Hrs</th><th>Overtime Hrs</th></tr></thead>
                    <tbody>
                    <?php foreach ($profile['attendance'] as $att): ?>
                        <tr>
                            <td><?= e(format_date($att['attendance_date'])) ?></td>
                            <td><span class="badge bg-info bg-opacity-10 text-info"><?= e(ucwords(str_replace('_', ' ', $att['status']))) ?></span></td>
                            <td><?= e($att['check_in'] ?? '—') ?></td>
                            <td><?= e($att['check_out'] ?? '—') ?></td>
                            <td><?= e($att['worked_hours']) ?></td>
                            <td><?= e($att['overtime_hours']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- 5. Performance History -->
    <div class="tab-pane fade <?= $activeTab === 'performance' ? 'show active' : '' ?>" id="tabPerformance">
        <div class="cms-card overflow-hidden">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead><tr><th>Period</th><th>Tasks (Done/Assigned)</th><th>Attendance %</th><th>Manager Rating</th><th>Score</th><th>Recommended Bonus</th><th>Feedback</th></tr></thead>
                    <tbody>
                    <?php foreach ($profile['performance'] as $pr): ?>
                        <tr>
                            <td class="fw-semibold"><?= e($pr['period_label']) ?></td>
                            <td><?= (int) $pr['tasks_completed'] ?> / <?= (int) $pr['tasks_assigned'] ?></td>
                            <td><?= e($pr['attendance_rate']) ?>%</td>
                            <td><?= e($pr['manager_rating']) ?></td>
                            <td><span class="badge bg-success"><?= e($pr['computed_score']) ?></span></td>
                            <td class="fw-bold"><?= e(format_money($pr['recommended_bonus'], $emp['currency_code'])) ?></td>
                            <td class="small"><?= e($pr['feedback'] ?? '') ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- 6. Bonuses and Penalties -->
    <div class="tab-pane fade <?= $activeTab === 'bonuses' ? 'show active' : '' ?>" id="tabBonuses">
        <div class="row g-3">
            <div class="col-lg-6">
                <div class="cms-card overflow-hidden">
                    <div class="p-3 border-bottom fw-bold text-success">Bonuses</div>
                    <table class="table table-sm">
                        <thead><tr><th>Title</th><th>Type</th><th>Amount</th><th>Status</th></tr></thead>
                        <tbody>
                        <?php foreach ($profile['bonuses'] as $b): ?>
                            <tr>
                                <td><?= e($b['title']) ?></td>
                                <td class="small"><?= e(ucfirst($b['bonus_type'])) ?></td>
                                <td class="fw-bold text-success">+<?= e(format_money($b['amount'], $b['currency_code'])) ?></td>
                                <td><span class="badge bg-success bg-opacity-10 text-success"><?= e(ucfirst($b['status'])) ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="cms-card overflow-hidden">
                    <div class="p-3 border-bottom fw-bold text-danger">Deductions &amp; Penalties</div>
                    <table class="table table-sm">
                        <thead><tr><th>Title</th><th>Amount</th><th>Authorization</th></tr></thead>
                        <tbody>
                        <?php foreach ($profile['deductions'] as $d): ?>
                            <tr>
                                <td><?= e($d['title']) ?></td>
                                <td class="fw-bold text-danger">-<?= e(format_money($d['amount'], $d['currency_code'])) ?></td>
                                <td>
                                    <?php if (!empty($d['is_authorized'])): ?>
                                        <span class="badge bg-success bg-opacity-10 text-success">Authorized</span>
                                    <?php else: ?>
                                        <span class="badge bg-warning bg-opacity-15 text-warning">Pending Confirmation</span>
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

    <!-- 7. Payment History -->
    <div class="tab-pane fade <?= $activeTab === 'payments' ? 'show active' : '' ?>" id="tabPayments">
        <div class="cms-card overflow-hidden">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead><tr><th>Payroll Code</th><th>Period</th><th>Gross</th><th>Deductions</th><th>Net Salary</th><th>Status</th><th>Statement</th></tr></thead>
                    <tbody>
                    <?php foreach ($profile['payrolls'] as $pay): ?>
                        <tr>
                            <td class="fw-semibold"><?= e($pay['payroll_code']) ?></td>
                            <td><?= (int) $pay['period_year'] ?>-<?= str_pad((string) $pay['period_month'], 2, '0', STR_PAD_LEFT) ?></td>
                            <td><?= e(format_money($pay['gross_salary'], $pay['currency_code'])) ?></td>
                            <td class="text-danger">-<?= e(format_money($pay['authorized_deductions'], $pay['currency_code'])) ?></td>
                            <td class="fw-bold"><?= e(format_money($pay['net_salary'], $pay['currency_code'])) ?></td>
                            <td><span class="badge <?= $pay['status'] === 'paid' ? 'bg-success' : 'bg-warning' ?>"><?= e(ucfirst($pay['status'])) ?></span></td>
                            <td><a href="/payroll/<?= (int) $pay['id'] ?>/statement" class="btn btn-sm btn-outline-primary">View Statement</a></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- 8. Documents -->
    <div class="tab-pane fade <?= $activeTab === 'documents' ? 'show active' : '' ?>" id="tabDocuments">
        <div class="row g-3">
            <?php if ($canEditAll): ?>
                <div class="col-lg-4">
                    <div class="cms-card p-3">
                        <h6 class="fw-bold mb-3">Upload Employee Document</h6>
                        <form action="/employees/<?= $eid ?>/documents" method="POST" enctype="multipart/form-data">
                            <?= csrf_field() ?>
                            <div class="mb-2"><label class="form-label small fw-semibold">Title</label><input type="text" name="title" class="form-control form-control-sm" required placeholder="Employment Contract 2026"></div>
                            <div class="mb-2"><label class="form-label small fw-semibold">Document Type</label>
                                <select name="document_type" class="form-select form-select-sm">
                                    <option value="contract">Contract</option>
                                    <option value="id_passport">Passport / ID</option>
                                    <option value="certificate">Certificate</option>
                                    <option value="tax">Tax Form</option>
                                    <option value="other">Other</option>
                                </select>
                            </div>
                            <div class="mb-2"><label class="form-label small fw-semibold">File</label><input type="file" name="document" class="form-control form-control-sm" required></div>
                            <div class="mb-3"><label class="form-label small fw-semibold">Notes</label><input type="text" name="notes" class="form-control form-control-sm"></div>
                            <button type="submit" class="btn btn-primary btn-sm w-100">Upload Document</button>
                        </form>
                    </div>
                </div>
            <?php endif; ?>
            <div class="<?= $canEditAll ? 'col-lg-8' : 'col-12' ?>">
                <div class="cms-card overflow-hidden">
                    <table class="table table-hover">
                        <thead><tr><th>Title</th><th>Type</th><th>File</th><th>Uploaded</th><th class="text-end">Actions</th></tr></thead>
                        <tbody>
                        <?php if (empty($profile['documents'])): ?>
                            <tr><td colspan="5" class="text-center text-muted py-4"><?= e(__('common.no_data')) ?></td></tr>
                        <?php else: ?>
                            <?php foreach ($profile['documents'] as $doc): ?>
                                <tr>
                                    <td class="fw-semibold"><?= e($doc['title']) ?></td>
                                    <td><span class="badge bg-secondary bg-opacity-10 text-body"><?= e($doc['document_type']) ?></span></td>
                                    <td class="small"><?= e($doc['original_filename']) ?></td>
                                    <td class="small text-muted"><?= e(format_date($doc['created_at'])) ?></td>
                                    <td class="text-end">
                                        <a href="/employees/documents/<?= (int) $doc['id'] ?>/download" class="btn btn-sm btn-outline-primary"><i class="bi bi-download"></i></a>
                                        <?php if ($canEditAll): ?>
                                            <form action="/employees/documents/<?= (int) $doc['id'] ?>/delete" method="POST" class="d-inline" data-confirm="<?= e(__('common.confirm_delete')) ?>">
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
        </div>
    </div>
</div>
