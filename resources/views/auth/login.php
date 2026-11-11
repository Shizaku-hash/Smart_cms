<?php
$flashErr = $flashError ?? flash('error');
$currentLang = \App\Core\Translator::getLocale();
?>
<div class="row justify-content-center">
    <div class="col-lg-10 col-xl-9">
        <div class="card border-0 shadow-lg overflow-hidden rounded-4">
            <div class="row g-0">
                <div class="col-md-6 p-4 p-lg-5 bg-white text-dark">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <div class="d-flex align-items-center gap-2">
                            <span class="cms-brand-icon">SC</span>
                            <span class="fw-bold fs-5">SmartCMS</span>
                        </div>
                        <form action="/language/switch" method="POST">
                            <?= csrf_field() ?>
                            <select name="language" class="form-select form-select-sm" onchange="this.form.submit()">
                                <option value="en" <?= $currentLang === 'en' ? 'selected' : '' ?>>English</option>
                                <option value="uz" <?= $currentLang === 'uz' ? 'selected' : '' ?>>O‘zbekcha</option>
                                <option value="ja" <?= $currentLang === 'ja' ? 'selected' : '' ?>>日本語</option>
                            </select>
                        </form>
                    </div>

                    <h3 class="fw-bold mb-1"><?= e(__('auth.login_title')) ?></h3>
                    <p class="text-muted small mb-4"><?= e(__('auth.login_subtitle')) ?></p>

                    <?php if ($flashErr): ?>
                        <div class="alert alert-danger py-2 small"><?= e($flashErr) ?></div>
                    <?php endif; ?>

                    <form action="/login" method="POST" id="loginForm">
                        <?= csrf_field() ?>
                        <div class="mb-3">
                            <label class="form-label small fw-semibold"><?= e(__('auth.email_or_username')) ?></label>
                            <input type="text" name="identifier" id="loginIdentifier" class="form-control" placeholder="admin@novatech.io" required>
                        </div>
                        <div class="mb-4">
                            <label class="form-label small fw-semibold"><?= e(__('auth.password')) ?></label>
                            <input type="password" name="password" id="loginPassword" class="form-control" placeholder="••••••••" required>
                        </div>
                        <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold">
                            <i class="bi bi-shield-lock me-1"></i> <?= e(__('auth.sign_in')) ?>
                        </button>
                    </form>
                </div>

                <div class="col-md-6 p-4 p-lg-5 text-white d-flex flex-column justify-content-between" style="background: linear-gradient(160deg, #1d4ed8 0%, #0f172a 100%);">
                    <div>
                        <span class="badge bg-light text-primary mb-3">Native PHP 8.5 + Python FastAPI</span>
                        <h4 class="fw-bold mb-2"><?= e(__('app.name')) ?></h4>
                        <p class="small text-white-50 mb-4"><?= e(__('app.tagline')) ?></p>

                        <div class="small fw-semibold text-uppercase text-white-50 mb-2"><?= e(__('auth.demo_accounts')) ?></div>
                        <div class="d-grid gap-2">
                            <?php
                            $demos = [
                                ['Super Admin', 'admin@novatech.io', 'Admin123!'],
                                ['Company Owner', 'owner@novatech.io', 'Owner123!'],
                                ['Accountant', 'accountant@novatech.io', 'Accountant123!'],
                                ['HR Manager', 'hr@novatech.io', 'Hr123456!'],
                                ['Dept Manager', 'manager@novatech.io', 'Manager123!'],
                                ['Employee', 'employee@novatech.io', 'Employee123!'],
                            ];
                            foreach ($demos as [$rLabel, $dEmail, $dPass]):
                            ?>
                                <button type="button"
                                        class="btn btn-sm btn-outline-light text-start d-flex justify-content-between align-items-center"
                                        onclick="document.getElementById('loginIdentifier').value='<?= e($dEmail) ?>'; document.getElementById('loginPassword').value='<?= e($dPass) ?>';">
                                    <span><strong><?= e($rLabel) ?></strong> &middot; <span class="opacity-75"><?= e($dEmail) ?></span></span>
                                    <code class="text-warning small"><?= e($dPass) ?></code>
                                </button>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="small text-white-50 mt-4 pt-3 border-top border-white border-opacity-10">
                        MySQL 8 &bull; PhpSpreadsheet &bull; DomPDF &bull; Pandas &bull; OpenPyXL &bull; ReportLab
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
