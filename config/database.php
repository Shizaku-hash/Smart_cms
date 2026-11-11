<?php

declare(strict_types=1);

use App\Core\Env;

$basePath = dirname(__DIR__);
Env::load($basePath . '/.env');

return [
    'driver' => 'mysql',
    'host' => (string) Env::get('DB_HOST', '127.0.0.1'),
    'port' => (int) Env::get('DB_PORT', 3306),
    'database' => (string) Env::get('DB_DATABASE', 'smart_cms'),
    'username' => (string) Env::get('DB_USERNAME', 'cms_user'),
    'password' => (string) Env::get('DB_PASSWORD', 'cms_dev_pass'),
    'charset' => (string) Env::get('DB_CHARSET', 'utf8mb4'),
];
