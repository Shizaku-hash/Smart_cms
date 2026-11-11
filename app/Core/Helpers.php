<?php

declare(strict_types=1);

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\Translator;

if (!function_exists('e')) {
    function e(mixed $value): string
    {
        return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('__')) {
    function __(string $key, array $replace = []): string
    {
        return Translator::get($key, $replace);
    }
}

if (!function_exists('config')) {
    function config(?string $key = null, mixed $default = null): mixed
    {
        static $cfg = null;
        if ($cfg === null) {
            $cfg = require dirname(__DIR__, 2) . '/config/app.php';
        }
        if ($key === null) {
            return $cfg;
        }
        return $cfg[$key] ?? $default;
    }
}

if (!function_exists('csrf_token')) {
    function csrf_token(): string
    {
        return Csrf::token();
    }
}

if (!function_exists('csrf_field')) {
    function csrf_field(): string
    {
        return Csrf::field();
    }
}

if (!function_exists('auth_user')) {
    function auth_user(): ?array
    {
        return Auth::user();
    }
}

if (!function_exists('can')) {
    function can(string $permission): bool
    {
        return Auth::can($permission);
    }
}

if (!function_exists('flash')) {
    function flash(string $key, mixed $default = null): mixed
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return $default;
        }
        $val = $_SESSION['_flash'][$key] ?? $default;
        unset($_SESSION['_flash'][$key]);
        return $val;
    }
}

if (!function_exists('currency_symbol')) {
    function currency_symbol(string $code = 'USD'): string
    {
        static $symbols = null;
        if ($symbols === null) {
            try {
                $rows = Database::fetchAll('SELECT code, symbol FROM currencies');
                $symbols = [];
                foreach ($rows as $r) {
                    $symbols[$r['code']] = $r['symbol'];
                }
            } catch (Throwable) {
                $symbols = ['USD' => '$', 'EUR' => '€', 'UZS' => "so'm", 'JPY' => '¥', 'GBP' => '£'];
            }
        }
        return $symbols[$code] ?? $code . ' ';
    }
}

if (!function_exists('format_num')) {
    function format_num(float|int|string $amount, int $decimals = 2): string
    {
        $prefs = Auth::preferences();
        $fmt = $prefs['number_format'] ?? 'comma_dot';
        $val = (float) $amount;

        return match ($fmt) {
            'space_comma' => number_format($val, $decimals, ',', ' '),
            'dot_comma' => number_format($val, $decimals, ',', '.'),
            default => number_format($val, $decimals, '.', ','),
        };
    }
}

if (!function_exists('format_money')) {
    function format_money(float|int|string $amount, string $currencyCode = 'USD'): string
    {
        $prefs = Auth::preferences();
        $decimals = $currencyCode === 'UZS' || $currencyCode === 'JPY' ? 0 : 2;
        $num = format_num($amount, $decimals);
        $sym = currency_symbol($currencyCode);
        $pos = $prefs['currency_format'] ?? 'symbol_before';
        $negative = (float) $amount < 0;
        if ($negative) {
            $num = ltrim($num, '-');
        }
        $formatted = match ($pos) {
            'symbol_after' => $num . ' ' . $sym,
            'code_before' => $currencyCode . ' ' . $num,
            default => (mb_strlen($sym) > 2 ? $num . ' ' . $sym : $sym . $num),
        };
        return $negative ? '-' . $formatted : $formatted;
    }
}

if (!function_exists('format_date')) {
    function format_date(?string $dateStr): string
    {
        if (!$dateStr) {
            return '—';
        }
        $ts = strtotime($dateStr);
        if ($ts === false) {
            return $dateStr;
        }
        $prefs = Auth::preferences();
        $fmt = $prefs['date_format'] ?? 'Y-m-d';
        return date($fmt, $ts);
    }
}
