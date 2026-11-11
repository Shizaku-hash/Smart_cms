<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <div>
        <h3 class="fw-bold mb-0"><?= e(__('notifications.title')) ?></h3>
        <p class="text-muted small mb-0">Salary alerts, pending expense approvals, task deadlines, and system events</p>
    </div>
    <form action="/notifications/read-all" method="POST">
        <?= csrf_field() ?>
        <button type="submit" class="btn btn-outline-primary btn-sm"><i class="bi bi-check2-all me-1"></i><?= e(__('notifications.mark_all_read')) ?></button>
    </form>
</div>

<div class="row g-3">
    <div class="col-lg-8">
        <div class="cms-card overflow-hidden">
            <div class="list-group list-group-flush">
                <?php if (empty($notifications)): ?>
                    <div class="p-4 text-center text-muted"><?= e(__('common.no_data')) ?></div>
                <?php else: ?>
                    <?php foreach ($notifications as $n): ?>
                        <div class="list-group-item d-flex justify-content-between align-items-start gap-3 py-3 <?= empty($n['is_read']) ? 'bg-primary bg-opacity-10' : '' ?>">
                            <div>
                                <div class="d-flex align-items-center gap-2">
                                    <span class="badge bg-<?= e($n['severity'] === 'danger' ? 'danger' : ($n['severity'] === 'warning' ? 'warning text-dark' : ($n['severity'] === 'success' ? 'success' : 'info text-dark'))) ?>">
                                        <?= e(strtoupper(str_replace('_', ' ', $n['type']))) ?>
                                    </span>
                                    <strong class="small"><?= e($n['title']) ?></strong>
                                    <?php if (empty($n['is_read'])): ?>
                                        <span class="badge bg-primary rounded-pill">Unread</span>
                                    <?php endif; ?>
                                </div>
                                <div class="small text-muted mt-1"><?= e($n['message']) ?></div>
                                <div class="text-muted mt-1" style="font-size:0.74rem;"><?= e($n['created_at']) ?></div>
                            </div>
                            <div class="d-flex gap-2">
                                <?php if (!empty($n['link_url'])): ?>
                                    <a href="<?= e($n['link_url']) ?>" class="btn btn-sm btn-outline-primary">Open</a>
                                <?php endif; ?>
                                <?php if (empty($n['is_read'])): ?>
                                    <form action="/notifications/<?= (int) $n['id'] ?>/read" method="POST">
                                        <?= csrf_field() ?>
                                        <button type="submit" class="btn btn-sm btn-outline-secondary">Mark Read</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="cms-card p-3">
            <h6 class="fw-bold mb-3"><?= e(__('notifications.preferences')) ?></h6>
            <?php $np = (array) ($prefs['notification_prefs_json'] ?? []); ?>
            <form action="/notifications/preferences" method="POST">
                <?= csrf_field() ?>
                <?php
                $labels = [
                    'salary_upcoming' => 'Upcoming Salary Payments',
                    'expense_pending' => 'Pending Expense Approvals',
                    'financial_alert' => 'Important Financial Changes',
                    'task_deadline' => 'Approaching Task Deadlines',
                    'task_assigned' => 'New Task Assignments',
                    'employee_update' => 'Employee Profile Updates',
                    'system' => 'System Events & Backups',
                ];
                foreach ($labels as $k => $lbl):
                ?>
                    <div class="form-check form-switch mb-2">
                        <input class="form-check-input" type="checkbox" name="<?= e($k) ?>" value="1" id="np_<?= e($k) ?>" <?= !empty($np[$k]) ? 'checked' : '' ?>>
                        <label class="form-check-label small" for="np_<?= e($k) ?>"><?= e($lbl) ?></label>
                    </div>
                <?php endforeach; ?>
                <button type="submit" class="btn btn-primary btn-sm w-100 mt-3"><?= e(__('common.save')) ?></button>
            </form>
        </div>
    </div>
</div>
