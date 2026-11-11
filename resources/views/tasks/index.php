<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <div>
        <h3 class="fw-bold mb-0"><?= e(__('tasks.title')) ?></h3>
        <p class="text-muted small mb-0">Assign tasks, set deadlines &amp; completion rewards linked to payroll bonuses</p>
    </div>
    <?php if ($canManage): ?>
        <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#newTaskModal">
            <i class="bi bi-plus-lg me-1"></i><?= e(__('tasks.new_task')) ?>
        </button>
    <?php endif; ?>
</div>

<div class="cms-card p-3 mb-3">
    <form action="/tasks" method="GET" class="row g-2 align-items-end">
        <div class="col-md-3">
            <label class="form-label small fw-semibold"><?= e(__('common.status')) ?></label>
            <select name="status" class="form-select form-select-sm">
                <option value=""><?= e(__('common.all')) ?></option>
                <option value="pending" <?= ($filters['status'] ?? '') === 'pending' ? 'selected' : '' ?>>Pending</option>
                <option value="in_progress" <?= ($filters['status'] ?? '') === 'in_progress' ? 'selected' : '' ?>>In Progress</option>
                <option value="completed" <?= ($filters['status'] ?? '') === 'completed' ? 'selected' : '' ?>>Completed</option>
                <option value="cancelled" <?= ($filters['status'] ?? '') === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label small fw-semibold"><?= e(__('tasks.priority')) ?></label>
            <select name="priority" class="form-select form-select-sm">
                <option value=""><?= e(__('common.all')) ?></option>
                <option value="low" <?= ($filters['priority'] ?? '') === 'low' ? 'selected' : '' ?>>Low</option>
                <option value="medium" <?= ($filters['priority'] ?? '') === 'medium' ? 'selected' : '' ?>>Medium</option>
                <option value="high" <?= ($filters['priority'] ?? '') === 'high' ? 'selected' : '' ?>>High</option>
                <option value="urgent" <?= ($filters['priority'] ?? '') === 'urgent' ? 'selected' : '' ?>>Urgent</option>
            </select>
        </div>
        <div class="col-md-2">
            <button type="submit" class="btn btn-primary btn-sm"><?= e(__('common.filter')) ?></button>
        </div>
    </form>
</div>

<div class="cms-card overflow-hidden">
    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead>
            <tr>
                <th>Task</th>
                <th><?= e(__('tasks.assignees')) ?></th>
                <th><?= e(__('tasks.priority')) ?></th>
                <th><?= e(__('tasks.deadline')) ?></th>
                <th><?= e(__('tasks.reward')) ?></th>
                <th>Progress</th>
                <th><?= e(__('common.status')) ?></th>
                <th class="text-end"><?= e(__('common.actions')) ?></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($tasks as $t): ?>
                <tr>
                    <td>
                        <div class="fw-bold small"><?= e($t['task_code']) ?> — <?= e($t['title']) ?></div>
                        <div class="text-muted" style="font-size:0.75rem;"><?= e($t['department_name'] ?? 'General') ?><?= $t['project_name'] ? ' &bull; Project: ' . e($t['project_name']) : '' ?></div>
                    </td>
                    <td class="small"><?= e($t['assignee_names'] ?: 'Unassigned') ?></td>
                    <td>
                        <?php
                        $pBadge = match ($t['priority']) {
                            'urgent' => 'bg-danger',
                            'high' => 'bg-warning text-dark',
                            'medium' => 'bg-info text-dark',
                            default => 'bg-secondary',
                        };
                        ?>
                        <span class="badge <?= $pBadge ?>"><?= e(ucfirst($t['priority'])) ?></span>
                    </td>
                    <td class="small"><?= e(format_date($t['deadline'])) ?></td>
                    <td class="fw-bold text-success"><?= e(format_money($t['reward_amount'], $t['currency_code'])) ?></td>
                    <td style="min-width:110px;">
                        <div class="progress" style="height:7px;">
                            <div class="progress-bar bg-primary" style="width:<?= (int) $t['progress_percent'] ?>%"></div>
                        </div>
                        <small class="text-muted"><?= (int) $t['progress_percent'] ?>%</small>
                    </td>
                    <td>
                        <form action="/tasks/<?= (int) $t['id'] ?>/status" method="POST" class="d-flex gap-1 align-items-center">
                            <?= csrf_field() ?>
                            <input type="hidden" name="progress_percent" value="<?= $t['status'] === 'completed' ? 100 : 75 ?>">
                            <select name="status" class="form-select form-select-sm" style="width:130px;" onchange="this.form.submit()">
                                <option value="pending" <?= $t['status'] === 'pending' ? 'selected' : '' ?>>Pending</option>
                                <option value="in_progress" <?= $t['status'] === 'in_progress' ? 'selected' : '' ?>>In Progress</option>
                                <option value="completed" <?= $t['status'] === 'completed' ? 'selected' : '' ?>>Completed</option>
                                <option value="cancelled" <?= $t['status'] === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                            </select>
                        </form>
                    </td>
                    <td class="text-end">
                        <?php if ($canManage): ?>
                            <form action="/tasks/<?= (int) $t['id'] ?>/delete" method="POST" class="d-inline" data-confirm="<?= e(__('common.confirm_delete')) ?>">
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

<?php if ($canManage): ?>
<div class="modal fade" id="newTaskModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <form action="/tasks" method="POST" class="modal-content">
            <?= csrf_field() ?>
            <div class="modal-header"><h5 class="modal-title fw-bold"><?= e(__('tasks.new_task')) ?></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-8"><label class="form-label small fw-semibold">Task Title *</label><input type="text" name="title" class="form-control" required></div>
                    <div class="col-md-4"><label class="form-label small fw-semibold">Department</label>
                        <select name="department_id" class="form-select">
                            <?php foreach ($departments as $d): ?><option value="<?= (int) $d['id'] ?>"><?= e($d['name']) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4"><label class="form-label small fw-semibold"><?= e(__('tasks.priority')) ?></label>
                        <select name="priority" class="form-select"><option value="medium">Medium</option><option value="low">Low</option><option value="high">High</option><option value="urgent">Urgent</option></select>
                    </div>
                    <div class="col-md-4"><label class="form-label small fw-semibold"><?= e(__('tasks.deadline')) ?></label><input type="date" name="deadline" value="<?= date('Y-m-d', strtotime('+7 days')) ?>" class="form-control"></div>
                    <div class="col-md-4"><label class="form-label small fw-semibold"><?= e(__('tasks.reward')) ?> (USD)</label><input type="number" step="0.01" name="reward_amount" value="100" class="form-control"></div>
                    <div class="col-md-6"><label class="form-label small fw-semibold">Project Name (optional)</label><input type="text" name="project_name" class="form-control"></div>
                    <div class="col-md-6"><label class="form-label small fw-semibold"><?= e(__('tasks.assignees')) ?></label>
                        <select name="assignee_ids[]" class="form-select" multiple size="3">
                            <?php foreach ($employees as $emp): ?><option value="<?= (int) $emp['id'] ?>"><?= e($emp['employee_code']) ?> — <?= e($emp['full_name']) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12"><label class="form-label small fw-semibold"><?= e(__('common.description')) ?></label><textarea name="description" rows="2" class="form-control"></textarea></div>
                </div>
            </div>
            <div class="modal-footer"><button type="submit" class="btn btn-primary btn-sm"><?= e(__('common.create')) ?></button></div>
        </form>
    </div>
</div>
<?php endif; ?>
