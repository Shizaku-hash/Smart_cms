<?php

declare(strict_types=1);

namespace App\Core;

/**
 * HTTP Request abstraction handling query, form, JSON, headers, and uploaded files.
 */
final class Request
{
    private string $method;
    private string $path;
    private array $query;
    private array $post;
    private array $json;
    private array $files;
    private array $headers;

    public function __construct()
    {
        $rawMethod = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $this->query = $_GET ?? [];
        $this->post = $_POST ?? [];
        $this->files = $_FILES ?? [];
        $this->headers = $this->parseHeaders();

        $rawBody = file_get_contents('php://input');
        $decodedJson = [];
        if (is_string($rawBody) && $rawBody !== '' && str_contains(strtolower($this->header('Content-Type', '')), 'application/json')) {
            $parsed = json_decode($rawBody, true);
            if (is_array($parsed)) {
                $decodedJson = $parsed;
            }
        }
        $this->json = $decodedJson;

        if ($rawMethod === 'POST' && isset($this->post['_method'])) {
            $override = strtoupper((string) $this->post['_method']);
            if (in_array($override, ['PUT', 'PATCH', 'DELETE'], true)) {
                $rawMethod = $override;
            }
        }
        $this->method = $rawMethod;

        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $path = parse_url($uri, PHP_URL_PATH);
        $this->path = '/' . trim((string) ($path ?: '/'), '/');
        if ($this->path !== '/') {
            $this->path = rtrim($this->path, '/');
        }
    }

    public function method(): string
    {
        return $this->method;
    }

    public function path(): string
    {
        return $this->path;
    }

    public function isApi(): bool
    {
        return str_starts_with($this->path, '/api/') || $this->wantsJson();
    }

    public function wantsJson(): bool
    {
        $accept = strtolower($this->header('Accept', ''));
        $xhr = strtolower($this->header('X-Requested-With', ''));
        return str_contains($accept, 'application/json') || $xhr === 'xmlhttprequest';
    }

    public function query(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->query;
        }
        return $this->query[$key] ?? $default;
    }

    public function input(?string $key = null, mixed $default = null): mixed
    {
        $merged = array_merge($this->query, $this->post, $this->json);
        if ($key === null) {
            return $merged;
        }
        return $merged[$key] ?? $default;
    }

    public function all(): array
    {
        return array_merge($this->query, $this->post, $this->json);
    }

    public function file(string $key): ?array
    {
        if (!isset($this->files[$key]) || !is_array($this->files[$key])) {
            return null;
        }
        if (($this->files[$key]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        return $this->files[$key];
    }

    public function header(string $name, ?string $default = null): ?string
    {
        $normalized = strtolower($name);
        return $this->headers[$normalized] ?? $default;
    }

    public function ip(): string
    {
        return (string) ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1');
    }

    public function userAgent(): string
    {
        return substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? 'Unknown'), 0, 250);
    }

    private function parseHeaders(): array
    {
        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $name = strtolower(str_replace('_', '-', substr($key, 5)));
                $headers[$name] = (string) $value;
            } elseif (in_array($key, ['CONTENT_TYPE', 'CONTENT_LENGTH'], true)) {
                $name = strtolower(str_replace('_', '-', $key));
                $headers[$name] = (string) $value;
            }
        }
        return $headers;
    }
}
