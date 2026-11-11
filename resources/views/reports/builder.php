<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
    <div>
        <h3 class="fw-bold mb-0"><?= e(__('reports.builder')) ?></h3>
        <p class="text-muted small mb-0">Build reports automatically from MySQL or design custom manual tables with live formulas</p>
    </div>
    <div class="btn-group btn-group-sm">
        <a href="/reports/builder?method=automatic&report_type=<?= e($reportType) ?>" class="btn <?= $method === 'automatic' ? 'btn-primary' : 'btn-outline-primary' ?>"><?= e(__('reports.method_a')) ?></a>
        <a href="/reports/builder?method=manual" class="btn <?= $method === 'manual' ? 'btn-primary' : 'btn-outline-primary' ?>"><?= e(__('reports.method_b')) ?></a>
    </div>
</div>

<div class="row g-3">
    <!-- Configuration Panel -->
    <div class="col-lg-5">
        <div class="cms-card p-3">
            <?php if ($method === 'automatic'): ?>
                <h6 class="fw-bold mb-3"><i class="bi bi-magic text-primary me-1"></i><?= e(__('reports.method_a')) ?></h6>
                <form id="autoReportForm">
                    <input type="hidden" name="creation_method" value="automatic">
                    <div class="mb-2">
                        <label class="form-label small fw-semibold">Report Type</label>
                        <select name="report_type" id="autoReportType" class="form-select form-select-sm">
                            <?php foreach ($reportTypes as $k => $lbl): ?>
                                <?php if ($k === 'custom') continue; ?>
                                <option value="<?= e($k) ?>" <?= $reportType === $k ? 'selected' : '' ?>><?= e($lbl) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small fw-semibold">Report Title</label>
                        <input type="text" name="title" id="autoTitle" value="<?= e($templateConfig['title'] ?? ($reportTypes[$reportType] ?? 'Report')) ?>" class="form-control form-control-sm">
                    </div>
                    <div class="row g-2 mb-2">
                        <div class="col-6"><label class="form-label small fw-semibold">Date From</label><input type="date" name="date_from" value="<?= e($templateConfig['date_from'] ?? '') ?>" class="form-control form-control-sm"></div>
                        <div class="col-6"><label class="form-label small fw-semibold">Date To</label><input type="date" name="date_to" value="<?= e($templateConfig['date_to'] ?? '') ?>" class="form-control form-control-sm"></div>
                    </div>
                    <div class="row g-2 mb-2">
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Employee Filter</label>
                            <select name="employee_id" class="form-select form-select-sm">
                                <option value="">All Employees</option>
                                <?php foreach ($employees as $emp): ?>
                                    <option value="<?= (int) $emp['id'] ?>"><?= e($emp['full_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Sorting</label>
                            <select name="sort_dir" class="form-select form-select-sm">
                                <option value="DESC">Newest / Highest First</option>
                                <option value="ASC">Oldest / Lowest First</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small fw-semibold">Columns to Include</label>
                        <div id="autoColumnsBox" class="border rounded p-2 d-flex flex-wrap gap-2" style="max-height:135px;overflow-y:auto;">
                            <?php foreach ($availableColumns as $colKey => $colLbl): ?>
                                <label class="form-check form-check-inline small m-0">
                                    <input class="form-check-input auto-col-chk" type="checkbox" name="columns[]" value="<?= e($colKey) ?>" checked>
                                    <span class="form-check-label"><?= e($colLbl) ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Report Notes</label>
                        <textarea name="notes" rows="2" class="form-control form-control-sm"><?= e($templateConfig['notes'] ?? '') ?></textarea>
                    </div>
                    <button type="button" id="btnPreviewAuto" class="btn btn-primary btn-sm w-100"><i class="bi bi-eye me-1"></i><?= e(__('reports.generate_preview')) ?></button>
                </form>

            <?php else: ?>
                <h6 class="fw-bold mb-3"><i class="bi bi-table text-primary me-1"></i><?= e(__('reports.method_b')) ?></h6>
                <form id="manualReportForm">
                    <input type="hidden" name="creation_method" value="manual">
                    <div class="mb-2">
                        <label class="form-label small fw-semibold">Report Title</label>
                        <input type="text" id="manTitle" value="<?= e($previewData['title'] ?? 'Custom Executive Report') ?>" class="form-control form-control-sm">
                    </div>
                    <div class="mb-2">
                        <label class="form-label small fw-semibold">Subtitle</label>
                        <input type="text" id="manSubtitle" value="<?= e($previewData['subtitle'] ?? '') ?>" class="form-control form-control-sm">
                    </div>
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="manIncludeLogo" <?= !empty($previewData['include_logo']) ? 'checked' : '' ?>>
                            <label class="form-check-label small" for="manIncludeLogo">Include Company Logo</label>
                        </div>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="manTotalsRow" <?= !empty($previewData['totals_row']) ? 'checked' : '' ?>>
                            <label class="form-check-label small" for="manTotalsRow">Auto Totals Row</label>
                        </div>
                    </div>

                    <div class="d-flex gap-1 mb-2">
                        <button type="button" class="btn btn-xs btn-sm btn-outline-secondary" onclick="addManualColumn()">+ Column</button>
                        <button type="button" class="btn btn-xs btn-sm btn-outline-secondary" onclick="removeManualColumn()">- Column</button>
                        <button type="button" class="btn btn-xs btn-sm btn-outline-primary" onclick="addManualRow()">+ Row</button>
                    </div>

                    <div class="table-responsive border rounded mb-2" style="max-height:260px;">
                        <table class="table table-sm table-bordered mb-0" id="manualGridTable"></table>
                    </div>
                    <div class="small text-muted mb-2">Supports formulas like <code>=B1-C1</code>, <code>=B1+C1</code>, <code>=SUM(B1:B3)</code>, <code>=AVG(B1:B3)</code>.</div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Notes / Commentary</label>
                        <textarea id="manNotes" rows="2" class="form-control form-control-sm"><?= e($previewData['notes'] ?? '') ?></textarea>
                    </div>
                    <button type="button" id="btnPreviewManual" class="btn btn-primary btn-sm w-100"><i class="bi bi-eye me-1"></i>Calculate Formulas &amp; Preview</button>
                </form>
            <?php endif; ?>

            <!-- Save Template -->
            <?php if (can('reports.templates')): ?>
                <hr>
                <div class="input-group input-group-sm">
                    <input type="text" id="saveTemplateName" class="form-control" placeholder="Template name..." value="<?= e($loadedTemplate['name'] ?? '') ?>">
                    <button type="button" id="btnSaveTemplate" class="btn btn-outline-secondary"><i class="bi bi-bookmark-plus me-1"></i><?= e(__('reports.save_template')) ?></button>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Live Preview & Export Panel -->
    <div class="col-lg-7">
        <div class="cms-card p-3 h-100 d-flex flex-column">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 border-bottom pb-3 mb-3">
                <h6 class="fw-bold mb-0"><i class="bi bi-file-earmark-check text-success me-1"></i>Live Report Preview</h6>
                <?php if (can('reports.export')): ?>
                    <div class="d-flex flex-wrap align-items-center gap-2">
                        <select id="exportEngine" class="form-select form-select-sm" style="width:auto;">
                            <option value="php">PHP (DomPDF / PhpSpreadsheet)</option>
                            <option value="python">Python (ReportLab / OpenPyXL)</option>
                        </select>
                        <button type="button" class="btn btn-sm btn-danger" onclick="submitReportExport('pdf')"><i class="bi bi-file-earmark-pdf me-1"></i>PDF</button>
                        <button type="button" class="btn btn-sm btn-success" onclick="submitReportExport('xlsx')"><i class="bi bi-file-earmark-excel me-1"></i>Excel</button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="submitReportExport('csv')"><i class="bi bi-filetype-csv me-1"></i>CSV</button>
                    </div>
                <?php endif; ?>
            </div>

            <div id="reportPreviewContainer" class="flex-grow-1 overflow-auto">
                <h5 class="fw-bold mb-0" id="prevTitle"><?= e($previewData['title']) ?></h5>
                <div class="small text-muted mb-3" id="prevSubtitle"><?= e($previewData['subtitle']) ?></div>
                <div class="table-responsive">
                    <table class="table table-bordered table-sm align-middle" id="prevTable">
                        <thead>
                        <tr>
                            <?php foreach ($previewData['columns'] as $col): ?>
                                <th><?= e($col) ?></th>
                            <?php endforeach; ?>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($previewData['rows'] as $row): ?>
                            <tr>
                                <?php foreach ($row as $cell): ?>
                                    <td><?= e((string) $cell) ?></td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                        <?php if (!empty($previewData['totals_row'])): ?>
                            <tfoot>
                            <tr class="table-secondary fw-bold">
                                <?php foreach ($previewData['totals_row'] as $cell): ?>
                                    <td><?= e((string) $cell) ?></td>
                                <?php endforeach; ?>
                            </tr>
                            </tfoot>
                        <?php endif; ?>
                    </table>
                </div>
                <?php if (!empty($previewData['notes'])): ?>
                    <div class="alert alert-light border small mt-3 mb-0" id="prevNotes"><strong>Notes:</strong> <?= e($previewData['notes']) ?></div>
                <?php else: ?>
                    <div class="alert alert-light border small mt-3 mb-0 d-none" id="prevNotes"></div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Hidden Form for File Download Exports -->
<form id="hiddenExportForm" action="/reports/export" method="POST" class="d-none">
    <?= csrf_field() ?>
</form>

<script>
window.REPORT_BUILDER_INIT = {
  method: <?= json_encode($method) ?>,
  allColumnsByType: <?= json_encode($allColumnsByType) ?>,
  manualColumns: <?= json_encode($previewData['columns'] ?? ['Column A', 'Column B']) ?>,
  manualRows: <?= json_encode($previewData['raw_rows'] ?? $previewData['rows'] ?? [['Item 1', '100']]) ?>
};
</script>
<script src="/assets/js/reports.js"></script>
