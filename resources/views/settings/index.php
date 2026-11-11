<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <div>
        <h3 class="fw-bold mb-0"><?= e(__('settings.title')) ?></h3>
        <p class="text-muted small mb-0">Customize workspace, company profile, RBAC permissions, departments, security logs, and backups</p>
    </div>
</div>

<ul class="nav nav-pills mb-4 gap-1">
    <li class="nav-item"><a class="nav-link <?= $tab === 'personalization' ? 'active' : '' ?>" href="/settings?tab=personalization"><?= e(__('settings.personalization')) ?></a></li>
    <?php if (can('settings.company')): ?>
        <li class="nav-item"><a class="nav-link <?= $tab === 'company' ? 'active' : '' ?>" href="/settings?tab=company"><?= e(__('settings.company')) ?></a></li>
        <li class="nav-item"><a class="nav-link <?= $tab === 'departments' ? 'active' : '' ?>" href="/settings?tab=departments"><?= e(__('settings.departments')) ?></a></li>
    <?php endif; ?>
    <?php if (can('settings.permissions')): ?>
        <li class="nav-item"><a class="nav-link <?= $tab === 'roles' ? 'active' : '' ?>" href="/settings?tab=roles"><?= e(__('settings.roles')) ?></a></li>
    <?php endif; ?>
    <?php if (can('logs.view')): ?>
        <li class="nav-item"><a class="nav-link <?= $tab === 'logs' ? 'active' : '' ?>" href="/settings?tab=logs"><?= e(__('settings.logs')) ?></a></li>
    <?php endif; ?>
    <?php if (can('backup.manage')): ?>
        <li class="nav-item"><a class="nav-link <?= $tab === 'backup' ? 'active' : '' ?>" href="/settings?tab=backup"><?= e(__('settings.backup')) ?></a></li>
    <?php endif; ?>
</ul>

<?php if ($tab === 'personalization'): ?>
    <div class="cms-card p-4">
        <form action="/settings/personalization" method="POST">
            <?= csrf_field() ?>
            <input type="hidden" name="redirect_tab" value="personalization">
            <input type="hidden" name="hidden_sections_submitted" value="1">
            <input type="hidden" name="pinned_functions_submitted" value="1">

            <h6 class="fw-bold mb-3"><?= e(__('settings.application')) ?></h6>
            <div class="row g-3 mb-4">
                <div class="col-md-4">
                    <label class="form-label small fw-semibold">Interface Language</label>
                    <select name="language" class="form-select">
                        <option value="en" <?= ($prefs['language'] ?? 'en') === 'en' ? 'selected' : '' ?>>English</option>
                        <option value="uz" <?= ($prefs['language'] ?? 'en') === 'uz' ? 'selected' : '' ?>>O‘zbekcha (Uzbek)</option>
                        <option value="ja" <?= ($prefs['language'] ?? 'en') === 'ja' ? 'selected' : '' ?>>日本語 (Japanese)</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label small fw-semibold">Theme</label>
                    <select name="theme" class="form-select">
                        <option value="light" <?= ($prefs['theme'] ?? 'light') === 'light' ? 'selected' : '' ?>>Light Mode</option>
                        <option value="dark" <?= ($prefs['theme'] ?? 'light') === 'dark' ? 'selected' : '' ?>>Dark Mode</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label small fw-semibold">Default Landing Page</label>
                    <select name="default_landing_page" class="form-select">
                        <?php
                        $pages = ['/dashboard' => 'Dashboard', '/finance' => 'Finance', '/employees' => 'Employees', '/payroll' => 'Payroll', '/tasks' => 'Tasks', '/reports' => 'Reports'];
                        foreach ($pages as $url => $lbl):
                        ?>
                            <option value="<?= e($url) ?>" <?= ($prefs['default_landing_page'] ?? '/dashboard') === $url ? 'selected' : '' ?>><?= e($lbl) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label small fw-semibold">Date Format</label>
                    <select name="date_format" class="form-select">
                        <option value="Y-m-d" <?= ($prefs['date_format'] ?? 'Y-m-d') === 'Y-m-d' ? 'selected' : '' ?>>YYYY-MM-DD (2026-11-11)</option>
                        <option value="d/m/Y" <?= ($prefs['date_format'] ?? '') === 'd/m/Y' ? 'selected' : '' ?>>DD/MM/YYYY (11/11/2026)</option>
                        <option value="m/d/Y" <?= ($prefs['date_format'] ?? '') === 'm/d/Y' ? 'selected' : '' ?>>MM/DD/YYYY (11/11/2026)</option>
                        <option value="M d, Y" <?= ($prefs['date_format'] ?? '') === 'M d, Y' ? 'selected' : '' ?>>Nov 11, 2026</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label small fw-semibold">Number Format</label>
                    <select name="number_format" class="form-select">
                        <option value="comma_dot" <?= ($prefs['number_format'] ?? 'comma_dot') === 'comma_dot' ? 'selected' : '' ?>>1,234.56</option>
                        <option value="space_comma" <?= ($prefs['number_format'] ?? '') === 'space_comma' ? 'selected' : '' ?>>1 234,56</option>
                        <option value="dot_comma" <?= ($prefs['number_format'] ?? '') === 'dot_comma' ? 'selected' : '' ?>>1.234,56</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label small fw-semibold">Currency Format</label>
                    <select name="currency_format" class="form-select">
                        <option value="symbol_before" <?= ($prefs['currency_format'] ?? 'symbol_before') === 'symbol_before' ? 'selected' : '' ?>>$1,234.56</option>
                        <option value="symbol_after" <?= ($prefs['currency_format'] ?? '') === 'symbol_after' ? 'selected' : '' ?>>1,234.56 $</option>
                        <option value="code_before" <?= ($prefs['currency_format'] ?? '') === 'code_before' ? 'selected' : '' ?>>USD 1,234.56</option>
                    </select>
                </div>
            </div>

            <div class="row g-4 mb-4">
                <div class="col-md-6">
                    <h6 class="fw-bold mb-2">Pin Frequently Used Modules to Top of Sidebar</h6>
                    <?php
                    $pinned = (array) ($prefs['pinned_functions_json'] ?? []);
                    $pinCandidates = ['/finance' => 'Finance', '/employees' => 'Employees', '/payroll' => 'Payroll', '/tasks' => 'Tasks', '/performance' => 'Performance', '/reports' => 'Reports'];
                    foreach ($pinCandidates as $u => $l):
                    ?>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="checkbox" name="pinned_functions[]" value="<?= e($u) ?>" id="pin_<?= md5($u) ?>" <?= in_array($u, $pinned, true) ? 'checked' : '' ?>>
                            <label class="form-check-label small" for="pin_<?= md5($u) ?>"><?= e($l) ?></label>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="col-md-6">
                    <h6 class="fw-bold mb-2">Hide Unnecessary Sidebar Sections</h6>
                    <?php
                    $hidden = (array) ($prefs['hidden_sections_json'] ?? []);
                    $hideCandidates = ['finance' => 'Finance', 'employees' => 'Employees', 'payroll' => 'Payroll', 'tasks' => 'Tasks', 'performance' => 'Performance', 'reports' => 'Reports', 'notifications' => 'Notifications'];
                    foreach ($hideCandidates as $sec => $l):
                    ?>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="checkbox" name="hidden_sections[]" value="<?= e($sec) ?>" id="hide_<?= e($sec) ?>" <?= in_array($sec, $hidden, true) ? 'checked' : '' ?>>
                            <label class="form-check-label small" for="hide_<?= e($sec) ?>"><?= e($l) ?></label>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label small fw-semibold">Personal Quick Shortcuts (JSON: label, url, icon)</label>
                <textarea name="shortcuts_json_raw" rows="3" class="form-control form-control-sm font-monospace"><?= e(json_encode($prefs['shortcuts_json'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) ?></textarea>
            </div>

            <button type="submit" class="btn btn-primary btn-sm"><?= e(__('common.save')) ?></button>
        </form>
    </div>

<?php elseif ($tab === 'company' && can('settings.company')): ?>
    <div class="cms-card p-4">
        <form action="/settings/company" method="POST" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <div class="row g-3">
                <div class="col-md-6"><label class="form-label small fw-semibold">Company Name</label><input type="text" name="name" value="<?= e($company['name']) ?>" class="form-control" required></div>
                <div class="col-md-6"><label class="form-label small fw-semibold">Legal Name</label><input type="text" name="legal_name" value="<?= e($company['legal_name'] ?? '') ?>" class="form-control"></div>
                <div class="col-md-4"><label class="form-label small fw-semibold">Email</label><input type="email" name="email" value="<?= e($company['email'] ?? '') ?>" class="form-control"></div>
                <div class="col-md-4"><label class="form-label small fw-semibold">Phone</label><input type="text" name="phone" value="<?= e($company['phone'] ?? '') ?>" class="form-control"></div>
                <div class="col-md-4"><label class="form-label small fw-semibold">Website</label><input type="text" name="website" value="<?= e($company['website'] ?? '') ?>" class="form-control"></div>
                <div class="col-md-4"><label class="form-label small fw-semibold">Tax ID</label><input type="text" name="tax_id" value="<?= e($company['tax_id'] ?? '') ?>" class="form-control"></div>
                <div class="col-md-2"><label class="form-label small fw-semibold">Tax Rate %</label><input type="number" step="0.01" name="tax_rate" value="<?= e($company['tax_rate'] ?? 0) ?>" class="form-control"></div>
                <div class="col-md-3"><label class="form-label small fw-semibold">Default Currency</label>
                    <select name="default_currency_code" class="form-select">
                        <?php foreach ($currencies as $c): ?>
                            <option value="<?= e($c['code']) ?>" <?= ($company['default_currency_code'] ?? 'USD') === $c['code'] ? 'selected' : '' ?>><?= e($c['code']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3"><label class="form-label small fw-semibold">Financial Year Start Month</label>
                    <input type="number" min="1" max="12" name="financial_year_start_month" value="<?= (int) ($company['financial_year_start_month'] ?? 1) ?>" class="form-control">
                </div>
                <div class="col-md-8"><label class="form-label small fw-semibold">Address</label><input type="text" name="address" value="<?= e($company['address'] ?? '') ?>" class="form-control"></div>
                <div class="col-md-4"><label class="form-label small fw-semibold">Company Logo</label><input type="file" name="logo" class="form-control" accept="image/*"></div>
            </div>
            <button type="submit" class="btn btn-primary btn-sm mt-3"><?= e(__('common.save')) ?></button>
        </form>
    </div>

<?php elseif ($tab === 'departments' && can('settings.company')): ?>
    <div class="row g-3">
        <div class="col-lg-4">
            <div class="cms-card p-3">
                <h6 class="fw-bold mb-3">Add Department</h6>
                <form action="/settings/departments" method="POST">
                    <?= csrf_field() ?>
                    <div class="mb-2"><label class="form-label small fw-semibold">Name</label><input type="text" name="name" class="form-control form-control-sm" required></div>
                    <div class="mb-2"><label class="form-label small fw-semibold">Code</label><input type="text" name="code" class="form-control form-control-sm" required placeholder="e.g. RND"></div>
                    <div class="mb-2"><label class="form-label small fw-semibold">Monthly Budget</label><input type="number" step="0.01" name="budget" value="10000" class="form-control form-control-sm"></div>
                    <div class="mb-3"><label class="form-label small fw-semibold">Description</label><textarea name="description" rows="2" class="form-control form-control-sm"></textarea></div>
                    <button type="submit" class="btn btn-primary btn-sm w-100"><?= e(__('common.create')) ?></button>
                </form>
            </div>
        </div>
        <div class="col-lg-8">
            <div class="cms-card overflow-hidden">
                <table class="table table-hover">
                    <thead><tr><th>Code</th><th>Name</th><th>Manager</th><th>Employees</th><th>Budget</th></tr></thead>
                    <tbody>
                    <?php foreach ($departments as $d): ?>
                        <tr>
                            <td class="fw-bold"><?= e($d['code']) ?></td>
                            <td><?= e($d['name']) ?></td>
                            <td class="small"><?= e($d['manager_name'] ?? '—') ?></td>
                            <td><?= (int) $d['employee_count'] ?></td>
                            <td><?= e(format_money($d['budget'], $company['default_currency_code'] ?? 'USD')) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

<?php elseif ($tab === 'roles' && can('settings.permissions')): ?>
    <div class="row g-3">
        <div class="col-12">
            <div class="cms-card p-3 mb-3">
                <h6 class="fw-bold mb-3">Role-Based Access Control (RBAC) Matrix</h6>
                <?php foreach ($roles as $role): ?>
                    <form action="/settings/roles/permissions" method="POST" class="border rounded p-3 mb-3">
                        <?= csrf_field() ?>
                        <input type="hidden" name="role_id" value="<?= (int) $role['id'] ?>">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <div>
                                <strong><?= e($role['name']) ?></strong>
                                <span class="badge bg-secondary ms-1"><?= e($role['slug']) ?></span>
                                <span class="small text-muted ms-2"><?= e($role['description'] ?? '') ?></span>
                            </div>
                            <button type="submit" class="btn btn-sm btn-primary">Save Role</button>
                        </div>
                        <div class="d-flex flex-wrap gap-2">
                            <?php foreach ($permissions as $perm): ?>
                                <?php $checked = in_array((int) $perm['id'], $role['permission_ids'], true) ? 'checked' : ''; ?>
                                <label class="form-check form-check-inline small m-0 border rounded px-2 py-1">
                                    <input class="form-check-input" type="checkbox" name="permission_ids[]" value="<?= (int) $perm['id'] ?>" <?= $checked ?>>
                                    <span class="form-check-label"><?= e($perm['slug']) ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </form>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="cms-card p-3">
                <h6 class="fw-bold mb-3">Create System User</h6>
                <form action="/settings/users" method="POST">
                    <?= csrf_field() ?>
                    <div class="mb-2"><label class="form-label small fw-semibold">Full Name</label><input type="text" name="full_name" class="form-control form-control-sm" required></div>
                    <div class="mb-2"><label class="form-label small fw-semibold">Username</label><input type="text" name="username" class="form-control form-control-sm" required></div>
                    <div class="mb-2"><label class="form-label small fw-semibold">Email</label><input type="email" name="email" class="form-control form-control-sm" required></div>
                    <div class="mb-2"><label class="form-label small fw-semibold">Role</label>
                        <select name="role_id" class="form-select form-select-sm">
                            <?php foreach ($roles as $r): ?><option value="<?= (int) $r['id'] ?>"><?= e($r['name']) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3"><label class="form-label small fw-semibold">Password (min 6 chars)</label><input type="password" name="password" class="form-control form-control-sm" required minlength="6"></div>
                    <button type="submit" class="btn btn-primary btn-sm w-100">Create User</button>
                </form>
            </div>
        </div>
        <div class="col-lg-7">
            <div class="cms-card overflow-hidden">
                <div class="p-3 border-bottom fw-bold">User Accounts</div>
                <table class="table table-sm">
                    <thead><tr><th>Name</th><th>Username</th><th>Email</th><th>Role</th><th>Last Login</th></tr></thead>
                    <tbody>
                    <?php foreach ($users as $u): ?>
                        <tr>
                            <td class="fw-semibold small"><?= e($u['full_name']) ?></td>
                            <td class="small"><?= e($u['username']) ?></td>
                            <td class="small"><?= e($u['email']) ?></td>
                            <td><span class="badge bg-primary bg-opacity-10 text-primary"><?= e($u['role_name']) ?></span></td>
                            <td class="small text-muted"><?= e($u['last_login_at'] ?: 'Never') ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

<?php elseif ($tab === 'logs' && can('logs.view')): ?>
    <div class="cms-card overflow-hidden">
        <div class="p-3 border-bottom fw-bold">System Activity &amp; Audit Logs</div>
        <div class="table-responsive">
            <table class="table table-sm table-hover">
                <thead><tr><th>Time</th><th>User</th><th>Action</th><th>Entity</th><th>Description</th><th>IP</th></tr></thead>
                <tbody>
                <?php foreach ($logs as $l): ?>
                    <tr>
                        <td class="small text-muted text-nowrap"><?= e($l['created_at']) ?></td>
                        <td class="small fw-semibold"><?= e($l['user_name'] ?? 'System') ?></td>
                        <td><span class="badge bg-secondary bg-opacity-10 text-body"><?= e($l['action']) ?></span></td>
                        <td class="small"><?= e($l['entity_type']) ?><?= $l['entity_id'] ? ' #' . (int) $l['entity_id'] : '' ?></td>
                        <td class="small"><?= e($l['description']) ?></td>
                        <td class="small text-muted"><?= e($l['ip_address'] ?? '') ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

<?php elseif ($tab === 'backup' && can('backup.manage')): ?>
    <div class="cms-card p-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h6 class="fw-bold mb-0">Database Backups</h6>
                <div class="small text-muted">Generate complete MySQL SQL snapshots stored safely in <code>storage/backups/</code></div>
            </div>
            <form action="/settings/backup/create" method="POST">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-database-down me-1"></i>Create Backup Now</button>
            </form>
        </div>
        <table class="table table-sm">
            <thead><tr><th>Filename</th><th>Size</th><th>Created At</th><th class="text-end">Download</th></tr></thead>
            <tbody>
            <?php if (empty($backups)): ?>
                <tr><td colspan="4" class="text-center text-muted py-3">No backups generated yet.</td></tr>
            <?php else: ?>
                <?php foreach ($backups as $b): ?>
                    <tr>
                        <td class="fw-semibold small"><?= e($b['filename']) ?></td>
                        <td class="small"><?= e(number_format($b['size'] / 1024, 1)) ?> KB</td>
                        <td class="small text-muted"><?= e($b['created_at']) ?></td>
                        <td class="text-end"><a href="/settings/backup/<?= e($b['filename']) ?>/download" class="btn btn-sm btn-outline-primary"><i class="bi bi-download"></i></a></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
