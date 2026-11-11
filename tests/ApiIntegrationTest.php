<?php

declare(strict_types=1);

namespace Tests;

use App\Services\ExportService;
use App\Services\PythonAnalyticsClient;

final class ApiIntegrationTest extends TestCase
{
    public function run(): void
    {
        echo "\n=== ApiIntegrationTest ===\n";

        $py = new PythonAnalyticsClient();
        $healthy = $py->isHealthy();
        $this->assertTrue($healthy, 'Python FastAPI /health endpoint responds OK');

        if ($healthy) {
            $fin = $py->getFinancialAnalytics(1);
            $this->assertTrue(is_array($fin) && ($fin['total_income'] ?? 0) > 0, 'PHP PythonAnalyticsClient retrieves Pandas financial analytics');
            $this->assertTrue(!empty($fin['forecast_next_3_months']), 'Python FastAPI returns 3-month Pandas forecast');

            $pyPayroll = $py->getPayrollAnalytics(1);
            $this->assertTrue(is_array($pyPayroll) && !empty($pyPayroll['by_department']), 'Python FastAPI returns department payroll distribution');

            // Test Python ReportLab PDF & OpenPyXL XLSX via PHP ExportService
            $sampleReport = [
                'title' => 'API Integration Report',
                'subtitle' => 'Python Export Verification',
                'columns' => ['Metric', 'Value'],
                'rows' => [['Income', '10000'], ['Expenses', '4000']],
                'totals_row' => ['Net', '6000'],
            ];
            $exp = new ExportService();
            $pyPdf = $exp->export($sampleReport, 'pdf', 'python', 1, 1);
            $this->assertTrue(str_starts_with($pyPdf['bytes'], '%PDF'), 'PHP->Python FastAPI ReportLab generates %PDF');

            $pyXlsx = $exp->export($sampleReport, 'xlsx', 'python', 1, 1);
            $this->assertTrue(str_starts_with($pyXlsx['bytes'], 'PK'), 'PHP->Python FastAPI OpenPyXL generates XLSX');
        }

        // Test PHP REST API (/api/v1/health and /api/v1/transactions) if web server on 8080 is up
        $ch = curl_init('http://127.0.0.1:8080/api/v1/health');
        if ($ch !== false) {
            curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 3]);
            $body = curl_exec($ch);
            $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            if ($code === 200) {
                $json = json_decode((string) $body, true);
                $this->assertTrue(($json['status'] ?? '') === 'ok', 'PHP REST API /api/v1/health responds 200 OK');

                // Authenticated REST API call with X-API-Token
                $ch2 = curl_init('http://127.0.0.1:8080/api/v1/transactions');
                curl_setopt_array($ch2, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_TIMEOUT => 3,
                    CURLOPT_HTTPHEADER => ['X-API-Token: ' . config('api_public_token')],
                ]);
                $txBody = curl_exec($ch2);
                $txCode = (int) curl_getinfo($ch2, CURLINFO_HTTP_CODE);
                curl_close($ch2);
                $txJson = json_decode((string) $txBody, true);
                $this->assertTrue($txCode === 200 && ($txJson['ok'] ?? false) === true && !empty($txJson['transactions']), 'Authenticated PHP REST API /api/v1/transactions returns live DB records');
            }
        }
    }
}
