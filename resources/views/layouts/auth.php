<!doctype html>
<html lang="<?= e(\App\Core\Translator::getLocale()) ?>" data-theme="light" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <title><?= e($title ?? __('app.name')) ?></title>
    <link rel="stylesheet" href="/assets/vendor/bootstrap/css/bootstrap.min.css">
    <link rel="stylesheet" href="/assets/vendor/bootstrap-icons/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="/assets/css/app.css">
</head>
<body class="d-flex align-items-center justify-content-center min-vh-100" style="background: radial-gradient(circle at top left, #1e293b 0%, #0f172a 60%, #020617 100%);">
<main class="container py-4">
    <?= $content ?? '' ?>
</main>
<script src="/assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
</body>
</html>
