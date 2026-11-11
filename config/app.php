<?php

declare(strict_types=1);

use App\Core\Env;

$basePath = dirname(__DIR__);
Env::load($basePath . '/.env');

return [
    'name' => Env::get('APP_NAME', 'Smart Company Management System'),
    'env' => Env::get('APP_ENV', 'local'),
    'debug' => filter_var(Env::get('APP_DEBUG', 'true'), FILTER_VALIDATE_BOOLEAN),
    'url' => rtrim((string) Env::get('APP_URL', 'http://127.0.0.1:8080'), '/'),
    'timezone' => (string) Env::get('APP_TIMEZONE', 'Asia/Tashkent'),
    'key' => (string) Env::get('APP_KEY', 'smart_cms_dev_secret_key'),
    'base_path' => $basePath,
    'storage_path' => $basePath . '/storage',
    'upload_path' => $basePath . '/storage/uploads',
    'backup_path' => $basePath . '/storage/backups',
    'export_path' => $basePath . '/storage/exports',
    'log_path' => $basePath . '/storage/logs',
    'python_analytics_url' => rtrim((string) Env::get('PYTHON_ANALYTICS_URL', 'http://127.0.0.1:8001'), '/'),
    'python_api_key' => (string) Env::get('PYTHON_API_KEY', 'smart_cms_internal_analytics_key_2026'),
    'api_public_token' => (string) Env::get('API_PUBLIC_TOKEN', 'smart_cms_rest_api_token_2026'),
    'supported_languages' => ['en', 'uz', 'ja'],
    'max_upload_bytes' => 10 * 1024 * 1024, // 10 MB
];
