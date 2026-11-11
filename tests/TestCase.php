<?php

declare(strict_types=1);

namespace Tests;

use App\Core\Database;

abstract class TestCase
{
    protected int $passed = 0;
    protected int $failed = 0;

    protected function assertTrue(bool $cond, string $message): void
    {
        if ($cond) {
            $this->passed++;
            echo "  [PASS] {$message}\n";
        } else {
            $this->failed++;
            echo "  [FAIL] {$message}\n";
        }
    }

    protected function assertEquals(mixed $expected, mixed $actual, string $message): void
    {
        if ($expected == $actual) {
            $this->passed++;
            echo "  [PASS] {$message}\n";
        } else {
            $this->failed++;
            echo "  [FAIL] {$message} (Expected: " . var_export($expected, true) . ", Actual: " . var_export($actual, true) . ")\n";
        }
    }

    protected function assertFloatEquals(float $expected, float $actual, string $message, float $delta = 0.01): void
    {
        if (abs($expected - $actual) <= $delta) {
            $this->passed++;
            echo "  [PASS] {$message}\n";
        } else {
            $this->failed++;
            echo "  [FAIL] {$message} (Expected: {$expected}, Actual: {$actual})\n";
        }
    }

    public function getStats(): array
    {
        return ['passed' => $this->passed, 'failed' => $this->failed];
    }

    abstract public function run(): void;
}
