<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <div>
        <h3 class="fw-bold mb-0"><?= e(__('performance.title')) ?></h3>
        <p class="text-muted small mb-0">Configurable KPI scoring weights, bonus tiers, and attendance logs</p>
    </div>
    <?php if ($canManage): ?>
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#attendanceModal"><i class="bi bi-calendar-check me-1"></i><?= e(__('performance.record_attendance')) ?></button>
            <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#evaluateModal"><i class="bi bi-award me-1"></i><?= e(__('performance.evaluate')) ?></button>
        </div>
    <?php endif; ?>
</div>

<div class="row g-3 mb-4">
    <!-- Performance Records -->
    <div class="<?= $canManage ? 'col-lg-8' : 'col-12' ?>">
        <div class="cms-card overflow-hidden h-100">
            <div class="p-3 border-bottom fw-bold">Employee Performance Evaluations</div>
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead><tr><th>Employee</th><th>Period</th><th>Tasks</th><th>On Time</th><th>Attendance</th><th>Manager</th><th>Final Score</th><th>Rec. Bonus</th></tr></thead>
                    <tbody>
                    <?php foreach ($records as $r): ?>
                        <tr>
                            <td><div class="fw-semibold small"><?= e($r['employee_name']) ?></div><div class="text-muted" style="font-size:0.75rem;"><?= e($r['department_name'] ?? '—') ?></div></td>
                            <td class="small"><?= e($r['period_label']) ?></td>
                            <td><?= (int) $r['tasks_completed'] ?>/<?= (int) $r['tasks_assigned'] ?></td>
                            <td><?= (int) $r['tasks_on_time'] ?></td>
                            <td><?= e($r['attendance_rate']) ?>%</td>
                            <td><?= e($r['manager_rating']) ?></td>
                            <td><span class="badge bg-primary fs-6"><?= e($r['computed_score']) ?></span></td>
                            <td class="fw-bold text-success"><?= e(format_money($r['recommended_bonus'], 'USD')) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <?php if ($canManage): ?>
        <!-- Configurable Performance Rules -->
        <div class="col-lg-4">
            <div class="cms-card p-3 h-100">
                <h6 class="fw-bold mb-3"><i class="bi bi-sliders me-1 text-primary"></i><?= e(__('performance.rules')) ?></h6>
                <form action="/performance/rules" method="POST">
                    <?= csrf_field() ?>
                    <div class="row g-2 small">
                        <div class="col-6"><label class="form-label">Task Completion Weight %</label><input type="number" step="1" name="perf_weight_task_completion" value="<?= e($settings['perf_weight_task_completion'] ?? 40) ?>" class="form-control form-control-sm"></div>
                        <div class="col-6"><label class="form-label">On-Time Delivery Weight %</label><input type="number" step="1" name="perf_weight_on_time" value="<?= e($settings['perf_weight_on_time'] ?? 25) ?>" class="form-control form-control-sm"></div>
                        <div class="col-6"><label class="form-label">Attendance Weight %</label><input type="number" step="1" name="perf_weight_attendance" value="<?= e($settings['perf_weight_attendance'] ?? 15) ?>" class="form-control form-control-sm"></div>
                        <div class="col-6"><label class="form-label">Manager Rating Weight %</label><input type="number" step="1" name="perf_weight_manager_rating" value="<?= e($settings['perf_weight_manager_rating'] ?? 20) ?>" class="form-control form-control-sm"></div>
                        <div class="col-6"><label class="form-label">Gold Tier Min Score</label><input type="number" step="1" name="perf_bonus_tier_gold_min" value="<?= e($settings['perf_bonus_tier_gold_min'] ?? 90) ?>" class="form-control form-control-sm"></div>
                        <div class="col-6"><label class="form-label">Gold Bonus %</label><input type="number" step="0.5" name="perf_bonus_tier_gold_percent" value="<?= e($settings['perf_bonus_tier_gold_percent'] ?? 15) ?>" class="form-control form-control-sm"></div>
                        <div class="col-6"><label class="form-label">Silver Tier Min Score</label><input type="number" step="1" name="perf_bonus_tier_silver_min" value="<?= e($settings['perf_bonus_tier_silver_min'] ?? 75) ?>" class="form-control form-control-sm"></div>
                        <div class="col-6"><label class="form-label">Silver Bonus %</label><input type="number" step="0.5" name="perf_bonus_tier_silver_percent" value="<?= e($settings['perf_bonus_tier_silver_percent'] ?? 8) ?>" class="form-control form-control-sm"></div>
                    </div>
                    <div class="form-check form-switch mt-2">
                        <input class="form-check-input" type="checkbox" name="task_completion_auto_bonus_enabled" value="1" id="autoTaskBonus" <?= !empty($settings['task_completion_auto_bonus_enabled']) ? 'checked' : '' ?>>
                        <label class="form-check-label small" for="autoTaskBonus">Auto-award task reward bonus upon completion</label>
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm w-100 mt-3"><?= e(__('common.save')) ?></button>
                </form>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Recent Attendance Table -->
<div class="cms-card overflow-hidden">
    <div class="p-3 border-bottom fw-bold">Recent Attendance Records</div>
    <div class="table-responsive">
        <table class="table table-sm table-hover">
            <thead><tr><th>Date</th><th>Employee</th><th>Status</th><th>Check In</th><th>Check Out</th><th>Hours</th><th>Overtime</th></tr></thead>
            <tbody>
            <?php foreach ($attendance as $a): ?>
                <tr>
                    <td><?= e(format_date($a['attendance_date'])) ?></td>
                    <td class="fw-semibold small"><?= e($a['employee_name']) ?> (<?= e($a['employee_code']) ?>)</td>
                    <td><span class="badge bg-info bg-opacity-10 text-info"><?= e(ucwords(str_replace('_', ' ', $a['status']))) ?></span></td>
                    <td><?= e($a['check_in'] ?? '—') ?></td>
                    <td><?= e($a['check_out'] ?? '—') ?></td>
                    <td><?= e($a['worked_hours']) ?></td>
                    <td><?= e($a['overtime_hours']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if ($canManage): ?>
<div class="modal fade" id="evaluateModal" tabindex="-1">
    <div class="modal-dialog">
        <form action="/performance/evaluate" method="POST" class="modal-content">
            <?= csrf_field() ?>
            <div class="modal-header"><h5 class="modal-title fw-bold"><?= e(__('performance.evaluate')) ?></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="mb-2"><label class="form-label small fw-semibold">Employee</label>
                    <select name="employee_id" class="form-select form-select-sm" required>
                        <?php foreach ($employees as $emp): ?><option value="<?= (int) $emp['id'] ?>"><?= e($emp['employee_code']) ?> — <?= e($emp['full_name']) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="row g-2 mb-2">
                    <div class="col-6"><label class="form-label small fw-semibold">Period Start</label><input type="date" name="period_start" value="<?= date('Y-m-01') ?>" class="form-control form-control-sm"></div>
                    <div class="col-6"><label class="form-label small fw-semibold">Period End</label><input type="date" name="period_end" value="<?= date('Y-m-t') ?>" class="form-control form-control-sm"></div>
                </div>
                <div class="mb-2"><label class="form-label small fw-semibold">Manager Rating (0–100)</label><input type="number" min="0" max="100" step="1" name="manager_rating" value="90" class="form-control form-control-sm" required></div>
                <div class="mb-2"><label class="form-label small fw-semibold">Feedback</label><textarea name="feedback" rows="2" class="form-control form-control-sm"></textarea></div>
                <div class="form-check mt-2">
                    <input class="form-check-input" type="checkbox" name="issue_bonus" value="1" id="issuePerfBonus" checked>
                    <label class="form-check-label small" for="issuePerfBonus">Automatically issue recommended tier bonus</label>
                </div>
            </div>
            <div class="modal-footer"><button type="submit" class="btn btn-primary btn-sm">Compute &amp; Save Evaluation</button></div>
        </form>
    </div>
</div>

<div class="modal fade" id="attendanceModal" tabindex="-1">
    <div class="modal-dialog">
        <form action="/performance/attendance" method="POST" class="modal-content">
            <?= csrf_field() ?>
            <div class="modal-header"><h5 class="modal-title fw-bold"><?= e(__('performance.record_attendance')) ?></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="mb-2"><label class="form-label small fw-semibold">Employee</label>
                    <select name="employee_id" class="form-select form-select-sm" required>
                        <?php foreach ($employees as $emp): ?><option value="<?= (int) $emp['id'] ?>"><?= e($emp['employee_code']) ?> — <?= e($emp['full_name']) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="row g-2 mb-2">
                    <div class="col-6"><label class="form-label small fw-semibold">Date</label><input type="date" name="attendance_date" value="<?= date('Y-m-d') ?>" class="form-control form-control-sm" required></div>
                    <div class="col-6"><label class="form-label small fw-semibold">Status</label>
                        <select name="status" class="form-select form-select-sm"><option value="present">Present</option><option value="remote">Remote</option><option value="late">Late</option><option value="half_day">Half Day</option><option value="on_leave">On Leave</option><option value="absent">Absent</option></select>
                    </div>
                </div>
                <div class="row g-2 mb-2">
                    <div class="col-6"><label class="form-label small fw-semibold">Worked Hours</label><input type="number" step="0.5" name="worked_hours" value="8" class="form-control form-control-sm"></div>
                    <div class="col-6"><label class="form-label small fw-semibold">Overtime Hours</label><input type="number" step="0.5" name="overtime_hours" value="0" class="form-control form-control-sm"></div>
                </div>
            </div>
            <div class="modal-footer"><button type="submit" class="btn btn-primary btn-sm"><?= e(__('common.save')) ?></button></div>
        </form>
    </div>
</div>
<?php endif; ?>
