<?php
$currentLang = \App\Core\Translator::getLocale();
$currentTheme = ($prefs['theme'] ?? 'light');
?>
<header class="cms-topbar no-print">
    <div class="d-flex align-items-center gap-3">
        <button type="button" class="btn btn-sm btn-outline-secondary border-0" onclick="CMS.toggleSidebar()" title="Toggle sidebar">
            <i class="bi bi-layout-sidebar fs-5"></i>
        </button>
        <div class="d-none d-md-flex align-items-center gap-2">
            <?php foreach (array_slice((array) ($prefs['shortcuts_json'] ?? []), 0, 3) as $sc): ?>
                <a href="<?= e($sc['url'] ?? '#') ?>" class="cms-shortcut-pill">
                    <i class="bi <?= e($sc['icon'] ?? 'bi-lightning') ?>"></i>
                    <span><?= e($sc['label'] ?? '') ?></span>
                </a>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="d-flex align-items-center gap-2">
        <!-- Language Switcher -->
        <form action="/language/switch" method="POST" class="d-inline">
            <?= csrf_field() ?>
            <select name="language" class="form-select form-select-sm" onchange="this.form.submit()" aria-label="Language">
                <option value="en" <?= $currentLang === 'en' ? 'selected' : '' ?>>EN</option>
                <option value="uz" <?= $currentLang === 'uz' ? 'selected' : '' ?>>UZ</option>
                <option value="ja" <?= $currentLang === 'ja' ? 'selected' : '' ?>>JA</option>
            </select>
        </form>

        <!-- Theme Switcher -->
        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="CMS.toggleTheme()" title="Toggle theme">
            <i class="bi <?= $currentTheme === 'dark' ? 'bi-sun' : 'bi-moon-stars' ?>"></i>
        </button>

        <!-- Notifications Dropdown -->
        <div class="dropdown">
            <button class="btn btn-sm btn-outline-secondary position-relative" type="button" data-bs-toggle="dropdown">
                <i class="bi bi-bell"></i>
                <?php if (!empty($unreadCount)): ?>
                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size:0.65rem;">
                        <?= (int) $unreadCount ?>
                    </span>
                <?php endif; ?>
            </button>
            <div class="dropdown-menu dropdown-menu-end shadow p-0" style="width:320px;">
                <div class="px-3 py-2 border-bottom d-flex justify-content-between align-items-center">
                    <strong class="small"><?= e(__('nav.notifications')) ?></strong>
                    <a href="/notifications" class="small text-decoration-none">View all</a>
                </div>
                <div class="list-group list-group-flush">
                    <?php if (empty($recentNotifications)): ?>
                        <div class="p-3 text-center text-muted small"><?= e(__('common.no_data')) ?></div>
                    <?php else: ?>
                        <?php foreach ($recentNotifications as $n): ?>
                            <a href="<?= e($n['link_url'] ?: '/notifications') ?>" class="list-group-item list-group-item-action py-2 px-3">
                                <div class="d-flex justify-content-between">
                                    <span class="fw-semibold small"><?= e($n['title']) ?></span>
                                    <?php if (empty($n['is_read'])): ?>
                                        <span class="badge bg-primary rounded-pill" style="font-size:0.6rem;">New</span>
                                    <?php endif; ?>
                                </div>
                                <div class="text-muted text-truncate" style="font-size:0.78rem;"><?= e($n['message']) ?></div>
                            </a>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- User Badge -->
        <?php if (!empty($currentUser)): ?>
            <div class="dropdown">
                <button class="btn btn-sm btn-outline-secondary d-flex align-items-center gap-2" type="button" data-bs-toggle="dropdown">
                    <span class="cms-avatar" style="width:26px;height:26px;font-size:0.75rem;">
                        <?= e(mb_strtoupper(mb_substr((string) $currentUser['full_name'], 0, 1))) ?>
                    </span>
                    <span class="d-none d-sm-inline small fw-semibold"><?= e($currentUser['full_name']) ?></span>
                </button>
                <ul class="dropdown-menu dropdown-menu-end shadow">
                    <li class="px-3 py-2 border-bottom">
                        <div class="fw-semibold small"><?= e($currentUser['full_name']) ?></div>
                        <div class="text-muted" style="font-size:0.75rem;"><?= e($currentUser['role_name']) ?></div>
                    </li>
                    <?php if (!empty($currentUser['employee_id'])): ?>
                        <li><a class="dropdown-item small" href="/employees/<?= (int) $currentUser['employee_id'] ?>"><i class="bi bi-person me-2"></i>My Profile</a></li>
                    <?php endif; ?>
                    <li><a class="dropdown-item small" href="/settings?tab=personalization"><i class="bi bi-sliders me-2"></i><?= e(__('settings.personalization')) ?></a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li>
                        <form action="/logout" method="POST" class="px-2">
                            <?= csrf_field() ?>
                            <button type="submit" class="dropdown-item text-danger small rounded"><i class="bi bi-box-arrow-right me-2"></i><?= e(__('nav.logout')) ?></button>
                        </form>
                    </li>
                </ul>
            </div>
        <?php endif; ?>
    </div>
</header>
