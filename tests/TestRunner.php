#!/usr/bin/env php
<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';
require dirname(__DIR__) . '/app/Core/Helpers.php';
require __DIR__ . '/TestCase.php';
require __DIR__ . '/AuthAndPermissionsTest.php';
require __DIR__ . '/FinanceCalculationsTest.php';
require __DIR__ . '/EmployeeManagementTest.php';
require __DIR__ . '/PayrollAndBonusTest.php';
require __DIR__ . '/ReportAndExportTest.php';
require __DIR__ . '/ApiIntegrationTest.php';

use Tests\ApiIntegrationTest;
use Tests\AuthAndPermissionsTest;
use Tests\EmployeeManagementTest;
use Tests\FinanceCalculationsTest;
use Tests\PayrollAndBonusTest;
use Tests\ReportAndExportTest;

$suites = [
    new AuthAndPermissionsTest(),
    new FinanceCalculationsTest(),
    new EmployeeManagementTest(),
    new PayrollAndBonusTest(),
    new ReportAndExportTest(),
    new ApiIntegrationTest(),
];

$totalPassed = 0;
$totalFailed = 0;

foreach ($suites as $suite) {
    $suite->run();
    $stats = $suite->getStats();
    $totalPassed += $stats['passed'];
    $totalFailed += $stats['failed'];
}

echo "\n========================================\n";
echo "TOTAL PASSED: {$totalPassed} | TOTAL FAILED: {$totalFailed}\n";
echo "========================================\n";

exit($totalFailed > 0 ? 1 : 0);
