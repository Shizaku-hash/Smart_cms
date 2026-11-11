<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <div>
        <h3 class="fw-bold mb-0"><?= e(__('reports.title')) ?></h3>
        <p class="text-muted small mb-0">Generate automatic database reports or build custom manual reports with PDF, XLSX, and CSV exports</p>
    </div>
    <div class="d-flex gap-2">
        <a href="/reports/builder?method=automatic" class="btn btn-primary btn-sm"><i class="bi bi-magic me-1"></i><?= e(__('reports.method_a')) ?></a>
        <a href="/reports/builder?method=manual" class="btn btn-outline-primary btn-sm"><i class="bi bi-table me-1"></i><?= e(__('reports.method_b')) ?></a>
    </div>
</div>

<!-- Quick Launch All 11 Report Types -->
<div class="cms-card p-3 mb-4">
    <h6 class="fw-bold mb-3">Quick Launch Report Types</h6>
    <div class="row g-2">
        <?php foreach ($reportTypes as $key => $label): ?>
            <div class="col-md-4 col-lg-3">
                <a href="/reports/builder?method=<?= $key === 'custom' ? 'manual' : 'automatic' ?>&report_type=<?= e($key) ?>"
                   class="d-flex align-items-center justify-content-between p-2 border rounded text-decoration-none text-body bg-body-tertiary">
                    <span class="small fw-semibold"><?= e($label) ?></span>
                    <i class="bi bi-arrow-right-short text-primary"></i>
                </a>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<div class="row g-3">
    <!-- Saved Templates -->
    <div class="col-lg-6">
        <div class="cms-card overflow-hidden h-100">
            <div class="p-3 border-bottom fw-bold"><?= e(__('reports.saved_templates')) ?></div>
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead><tr><th>Template</th><th>Method</th><th>Type</th><th class="text-end">Actions</th></tr></thead>
                    <tbody>
                    <?php if (empty($templates)): ?>
                        <tr><td colspan="4" class="text-center text-muted py-4"><?= e(__('common.no_data')) ?></td></tr>
                    <?php else: ?>
                        <?php foreach ($templates as $tpl): ?>
                            <tr>
                                <td>
                                    <div class="fw-semibold small"><?= e($tpl['name']) ?></div>
                                    <div class="text-muted" style="font-size:0.75rem;"><?= e($tpl['description'] ?: '') ?></div>
                                </td>
                                <td><span class="badge <?= $tpl['creation_method'] === 'manual' ? 'bg-info text-dark' : 'bg-primary' ?> bg-opacity-15"><?= e(ucfirst($tpl['creation_method'])) ?></span></td>
                                <td class="small"><?= e($reportTypes[$tpl['report_type']] ?? $tpl['report_type']) ?></td>
                                <td class="text-end text-nowrap">
                                    <a href="/reports/builder?template_id=<?= (int) $tpl['id'] ?>" class="btn btn-sm btn-outline-primary">Open</a>
                                    <?php if (can('reports.templates')): ?>
                                        <form action="/reports/templates/<?= (int) $tpl['id'] ?>/delete" method="POST" class="d-inline" data-confirm="<?= e(__('common.confirm_delete')) ?>">
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

    <!-- Generated History -->
    <div class="col-lg-6">
        <div class="cms-card overflow-hidden h-100">
            <div class="p-3 border-bottom fw-bold"><?= e(__('reports.history')) ?></div>
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead><tr><th>Title</th><th>Format</th><th>Engine</th><th>By</th><th>Date</th></tr></thead>
                    <tbody>
                    <?php if (empty($history)): ?>
                        <tr><td colspan="5" class="text-center text-muted py-4"><?= e(__('common.no_data')) ?></td></tr>
                    <?php else: ?>
                        <?php foreach ($history as $h): ?>
                            <tr>
                                <td class="small fw-semibold"><?= e($h['title']) ?></td>
                                <td><span class="badge bg-secondary text-uppercase"><?= e($h['export_format']) ?></span></td>
                                <td class="small text-uppercase"><?= e($h['engine']) ?></td>
                                <td class="small"><?= e($h['generator_name'] ?? 'System') ?></td>
                                <td class="small text-muted"><?= e(format_date($h['created_at'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
