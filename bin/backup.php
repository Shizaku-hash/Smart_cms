#!/usr/bin/env php
<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';
require dirname(__DIR__) . '/app/Core/Helpers.php';

use App\Services\BackupService;

$res = (new BackupService())->createBackup(1, null);
echo "[backup] Created {$res['filename']} ({$res['size']} bytes) at {$res['path']}\n";
