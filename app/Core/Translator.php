<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Multi-language translator supporting en, uz, and ja.
 */
final class Translator
{
    private static string $locale = 'en';
    private static array $cache = [];

    public static function setLocale(string $locale): void
    {
        if (in_array($locale, ['en', 'uz', 'ja'], true)) {
            self::$locale = $locale;
        }
    }

    public static function getLocale(): string
    {
        return self::$locale;
    }

    public static function get(string $key, array $replace = [], ?string $locale = null): string
    {
        $lang = $locale ?? self::$locale;
        $translations = self::loadLocale($lang);

        $value = self::resolveDotKey($translations, $key);
        if ($value === null && $lang !== 'en') {
            $fallback = self::loadLocale('en');
            $value = self::resolveDotKey($fallback, $key);
        }

        if (!is_string($value)) {
            return $key;
        }

        foreach ($replace as $k => $v) {
            $value = str_replace(':' . $k, (string) $v, $value);
        }

        return $value;
    }

    private static function loadLocale(string $locale): array
    {
        if (isset(self::$cache[$locale])) {
            return self::$cache[$locale];
        }

        $file = dirname(__DIR__, 2) . '/resources/lang/' . $locale . '.php';
        if (is_file($file)) {
            $data = require $file;
            self::$cache[$locale] = is_array($data) ? $data : [];
        } else {
            self::$cache[$locale] = [];
        }

        return self::$cache[$locale];
    }

    private static function resolveDotKey(array $array, string $key): mixed
    {
        if (array_key_exists($key, $array)) {
            return $array[$key];
        }

        $segments = explode('.', $key);
        $current = $array;
        foreach ($segments as $segment) {
            if (!is_array($current) || !array_key_exists($segment, $current)) {
                return null;
            }
            $current = $current[$segment];
        }
        return $current;
    }
}
