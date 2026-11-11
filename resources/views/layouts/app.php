<?php
$theme = ($prefs['theme'] ?? 'light') === 'dark' ? 'dark' : 'light';
$collapsed = !empty($prefs['sidebar_collapsed']) ? 'sidebar-collapsed' : '';
?>
<!doctype html>
<html lang="<?= e(\App\Core\Translator::getLocale()) ?>" data-theme="<?= e($theme) ?>" data-bs-theme="<?= e($theme) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <title><?= e($title ?? __('app.name')) ?> — <?= e($company['name'] ?? 'SmartCMS') ?></title>
    <link rel="stylesheet" href="/assets/vendor/bootstrap/css/bootstrap.min.css">
    <link rel="stylesheet" href="/assets/vendor/bootstrap-icons/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="/assets/css/app.css">
</head>
<body>
<div id="cmsShell" class="cms-shell <?= e($collapsed) ?>">
    <?= \App\Core\View::partial('partials/sidebar', get_defined_vars()) ?>
    <div class="cms-main">
        <?= \App\Core\View::partial('partials/topbar', get_defined_vars()) ?>
        <main class="cms-content">
            <?= \App\Core\View::partial('partials/alerts', get_defined_vars()) ?>
            <?= $content ?? '' ?>
        </main>
    </div>
</div>
<script src="/assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="/assets/vendor/chartjs/chart.umd.min.js"></script>
<script src="/assets/js/app.js"></script>
</body>
</html>
