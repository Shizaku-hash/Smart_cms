<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

final class CurrencyService
{
    public function getAllActive(): array
    {
        return Database::fetchAll('SELECT * FROM currencies WHERE is_active = 1 ORDER BY is_default DESC, code ASC');
    }

    public function getDefaultCode(int $companyId = 1): string
    {
        $code = Database::fetchColumn('SELECT default_currency_code FROM companies WHERE id = :id LIMIT 1', ['id' => $companyId]);
        return is_string($code) && $code !== '' ? $code : 'USD';
    }

    public function getRate(string $fromCurrency, string $toCurrency): float
    {
        $from = strtoupper(trim($fromCurrency));
        $to = strtoupper(trim($toCurrency));
        if ($from === $to) {
            return 1.0;
        }

        // Direct rate
        $direct = Database::fetchColumn(
            "SELECT rate FROM exchange_rates
             WHERE from_currency = :f AND to_currency = :t
             ORDER BY effective_date DESC, id DESC LIMIT 1",
            ['f' => $from, 't' => $to]
        );
        if ($direct !== false && (float) $direct > 0) {
            return (float) $direct;
        }

        // Inverse rate
        $inverse = Database::fetchColumn(
            "SELECT rate FROM exchange_rates
             WHERE from_currency = :t AND to_currency = :f
             ORDER BY effective_date DESC, id DESC LIMIT 1",
            ['f' => $from, 't' => $to]
        );
        if ($inverse !== false && (float) $inverse > 0) {
            return round(1.0 / (float) $inverse, 8);
        }

        // Triangulate via USD
        if ($from !== 'USD' && $to !== 'USD') {
            $toUsd = $this->getRate($from, 'USD');
            $usdToTarget = $this->getRate('USD', $to);
            if ($toUsd > 0 && $usdToTarget > 0) {
                return round($toUsd * $usdToTarget, 8);
            }
        }

        return 1.0;
    }

    public function convert(float $amount, string $fromCurrency, string $toCurrency): array
    {
        $rate = $this->getRate($fromCurrency, $toCurrency);
        return [
            'original_amount' => round($amount, 2),
            'rate' => $rate,
            'converted_amount' => round($amount * $rate, 2),
        ];
    }

    public function saveRate(string $from, string $to, float $rate, string $effectiveDate, ?int $userId = null): void
    {
        $from = strtoupper(trim($from));
        $to = strtoupper(trim($to));
        Database::execute(
            "INSERT INTO exchange_rates (from_currency, to_currency, rate, effective_date, created_by)
             VALUES (:f, :t, :r, :d, :uid)
             ON DUPLICATE KEY UPDATE rate = VALUES(rate), created_by = VALUES(created_by)",
            ['f' => $from, 't' => $to, 'r' => $rate, 'd' => $effectiveDate, 'uid' => $userId]
        );
    }
}
