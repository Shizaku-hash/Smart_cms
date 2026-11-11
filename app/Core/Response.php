<?php

declare(strict_types=1);

namespace App\Core;

/**
 * HTTP Response helper supporting HTML, JSON, redirects, and binary file downloads.
 */
final class Response
{
    public static function json(array $payload, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    public static function html(string $content, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: text/html; charset=utf-8');
        echo $content;
        exit;
    }

    public static function redirect(string $url, array $flash = []): never
    {
        if (session_status() === PHP_SESSION_ACTIVE && $flash !== []) {
            foreach ($flash as $k => $v) {
                $_SESSION['_flash'][$k] = $v;
            }
        }
        header('Location: ' . $url, true, 302);
        exit;
    }

    public static function streamDownload(string $bytes, string $filename, string $mimeType): never
    {
        $safeName = preg_replace('/[^A-Za-z0-9._-]/', '_', $filename) ?: 'export.bin';
        http_response_code(200);
        header('Content-Type: ' . $mimeType);
        header('Content-Disposition: attachment; filename="' . $safeName . '"');
        header('Content-Length: ' . strlen($bytes));
        header('Cache-Control: private, max-age=0, must-revalidate');
        echo $bytes;
        exit;
    }

    public static function downloadFile(string $path, string $filename, string $mimeType = 'application/octet-stream'): never
    {
        if (!is_file($path)) {
            self::html('File not found.', 404);
        }
        $safeName = preg_replace('/[^A-Za-z0-9._-]/', '_', $filename) ?: basename($path);
        http_response_code(200);
        header('Content-Type: ' . $mimeType);
        header('Content-Disposition: attachment; filename="' . $safeName . '"');
        header('Content-Length: ' . filesize($path));
        readfile($path);
        exit;
    }
}
