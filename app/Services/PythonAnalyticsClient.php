<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Secure REST API client communicating with the Python FastAPI analytics & export microservice.
 */
final class PythonAnalyticsClient
{
    private string $baseUrl;
    private string $apiKey;

    public function __construct()
    {
        $this->baseUrl = rtrim((string) config('python_analytics_url', 'http://127.0.0.1:8001'), '/');
        $this->apiKey = (string) config('python_api_key', '');
    }

    public function isHealthy(): bool
    {
        $res = $this->request('GET', '/health', null, 2);
        return is_array($res) && (($res['status'] ?? '') === 'ok');
    }

    public function getFinancialAnalytics(int $companyId, ?string $dateFrom = null, ?string $dateTo = null): ?array
    {
        $qs = http_build_query(array_filter([
            'company_id' => $companyId,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
        ], static fn ($v) => $v !== null && $v !== ''));
        return $this->request('GET', '/api/v1/analytics/financial?' . $qs);
    }

    public function getPayrollAnalytics(int $companyId): ?array
    {
        return $this->request('GET', '/api/v1/analytics/payroll?company_id=' . $companyId);
    }

    public function evaluatePerformance(array $payload): ?array
    {
        return $this->request('POST', '/api/v1/analytics/performance/evaluate', $payload);
    }

    public function exportReportBytes(array $payload): ?string
    {
        return $this->requestRaw('POST', '/api/v1/reports/export', $payload, 15);
    }

    private function request(string $method, string $path, ?array $body = null, int $timeout = 5): ?array
    {
        $raw = $this->requestRaw($method, $path, $body, $timeout);
        if ($raw === null) {
            return null;
        }
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : null;
    }

    private function requestRaw(string $method, string $path, ?array $body = null, int $timeout = 5): ?string
    {
        if (!function_exists('curl_init')) {
            return null;
        }

        $ch = curl_init($this->baseUrl . $path);
        if ($ch === false) {
            return null;
        }

        $headers = [
            'X-API-Key: ' . $this->apiKey,
            'Accept: application/json',
        ];
        $opts = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_CONNECTTIMEOUT => 2,
            CURLOPT_CUSTOMREQUEST => $method,
        ];

        if ($body !== null) {
            $json = json_encode($body, JSON_UNESCAPED_UNICODE);
            $headers[] = 'Content-Type: application/json';
            $opts[CURLOPT_POSTFIELDS] = $json;
        }
        $opts[CURLOPT_HTTPHEADER] = $headers;

        curl_setopt_array($ch, $opts);
        $response = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false || $httpCode < 200 || $httpCode >= 300) {
            return null;
        }

        return (string) $response;
    }
}
