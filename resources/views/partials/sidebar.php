<?php
$hiddenSections = (array) ($prefs['hidden_sections_json'] ?? []);
$pinned = (array) ($prefs['pinned_functions_json'] ?? []);

$navItems = [
    ['id' => 'dashboard', 'url' => '/dashboard', 'icon' => 'bi-grid-1x2', 'label' => __('nav.dashboard'), 'perm' => 'dashboard.view'],
    ['id' => 'finance', 'url' => '/finance', 'icon' => 'bi-wallet2', 'label' => __('nav.finance'), 'perm' => 'finance.view'],
    ['id' => 'employees', 'url' => '/employees', 'icon' => 'bi-people', 'label' => __('nav.employees'), 'perm' => 'employees.view_own'],
    ['id' => 'payroll', 'url' => '/payroll', 'icon' => 'bi-cash-stack', 'label' => __('nav.payroll'), 'perm' => 'payroll.view_own'],
    ['id' => 'tasks', 'url' => '/tasks', 'icon' => 'bi-check2-square', 'label' => __('nav.tasks'), 'perm' => 'tasks.view'],
    ['id' => 'performance', 'url' => '/performance', 'icon' => 'bi-graph-up-arrow', 'label' => __('nav.performance'), 'perm' => 'performance.view'],
    ['id' => 'reports', 'url' => '/reports', 'icon' => 'bi-file-earmark-bar-graph', 'label' => __('nav.reports'), 'perm' => 'reports.view'],
    ['id' => 'notifications', 'url' => '/notifications', 'icon' => 'bi-bell', 'label' => __('nav.notifications'), 'perm' => null, 'badge' => $unreadCount ?? 0],
    ['id' => 'settings', 'url' => '/settings', 'icon' => 'bi-sliders', 'label' => __('nav.settings'), 'perm' => null],
];

// Prioritize pinned items cleanly if user pinned any
$orderedNav = $navItems;
if ($pinned !== []) {
    usort($orderedNav, static function ($a, $b) use ($pinned): int {
        if ($a['id'] === 'dashboard') {
            return -1;
        }
        if ($b['id'] === 'dashboard') {
            return 1;
        }
        $aPin = in_array($a['url'], $pinned, true) ? 0 : 1;
        $bPin = in_array($b['url'], $pinned, true) ? 0 : 1;
        return $aPin <=> $bPin;
    });
}
?>
<aside class="cms-sidebar no-print">
    <a href="/dashboard" class="cms-brand">
        <?php if (!empty($company['logo_path'])): ?>
            <img src="/settings/company/logo" alt="Logo" style="width:34px;height:34px;border-radius:8px;object-fit:cover;">
        <?php else: ?>
            <span class="cms-brand-icon">SC</span>
        <?php endif; ?>
        <div class="cms-brand-text overflow-hidden">
            <div class="fw-bold text-truncate" style="font-size:0.95rem;"><?= e($company['name'] ?? 'SmartCMS') ?></div>
            <div class="text-secondary" style="font-size:0.7rem;">Enterprise CMS</div>
        </div>
    </a>

    <nav class="cms-nav">
        <?php foreach ($orderedNav as $item): ?>
            <?php
            if ($item['perm'] !== null && !can($item['perm'])) {
                continue;
            }
            if ($item['id'] !== 'settings' && in_array($item['id'], $hiddenSections, true)) {
                continue;
            }
            $isActive = str_starts_with($currentPath ?? '', $item['url']);
            ?>
            <a href="<?= e($item['url']) ?>" class="cms-nav-link <?= $isActive ? 'active' : '' ?>" title="<?= e($item['label']) ?>">
                <i class="bi <?= e($item['icon']) ?>"></i>
                <span class="cms-nav-label flex-grow-1 text-truncate"><?= e($item['label']) ?></span>
                <?php if (in_array($item['url'], $pinned, true)): ?>
                    <i class="bi bi-pin-angle-fill text-warning cms-nav-label" style="font-size:0.72rem;width:auto;"></i>
                <?php endif; ?>
                <?php if (!empty($item['badge'])): ?>
                    <span class="badge rounded-pill bg-danger cms-nav-badge"><?= (int) $item['badge'] ?></span>
                <?php endif; ?>
            </a>
        <?php endforeach; ?>

        <?php if (!empty($prefs['shortcuts_json'])): ?>
            <div class="cms-sidebar-section-title"><?= e(__('nav.shortcuts')) ?></div>
            <div class="cms-shortcut-list px-2 d-flex flex-column gap-1">
                <?php foreach ($prefs['shortcuts_json'] as $sc): ?>
                    <a href="<?= e($sc['url'] ?? '#') ?>" class="cms-nav-link py-1 px-2" style="font-size:0.8rem;">
                        <i class="bi <?= e($sc['icon'] ?? 'bi-lightning') ?>"></i>
                        <span class="cms-nav-label text-truncate"><?= e($sc['label'] ?? '') ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </nav>

    <div class="p-3 border-top border-secondary border-opacity-25">
        <form action="/logout" method="POST">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-outline-light btn-sm w-100 d-flex align-items-center justify-content-center gap-2">
                <i class="bi bi-box-arrow-right"></i>
                <span class="cms-nav-label"><?= e(__('nav.logout')) ?></span>
            </button>
        </form>
    </div>
</aside>
