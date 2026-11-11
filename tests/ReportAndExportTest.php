<?php

declare(strict_types=1);

namespace Tests;

use App\Core\Database;
use App\Services\ExportService;
use App\Services\ReportService;

final class ReportAndExportTest extends TestCase
{
    public function run(): void
    {
        echo "\n=== ReportAndExportTest ===\n";

        $reportSvc = new ReportService();
        $exportSvc = new ExportService();

        // 1. Method A: Automatic Financial Report
        $autoReport = $reportSvc->buildAutomaticReport(1, ['report_type' => 'complete_financial']);
        $this->assertTrue(count($autoReport['rows']) > 0, 'Method A generates non-empty financial report from database');
        $this->assertTrue(!empty($autoReport['totals_row']), 'Automatic report calculates totals row');

        // 2. Method B: Manual Report Creation with Formulas
        $manualReport = $reportSvc->buildManualReport([
            'title' => 'Manual Budget Audit',
            'columns' => ['Department', 'Budget', 'Actual', 'Variance'],
            'rows' => [
                ['Engineering', '1000', '750', '=B1-C1'],
                ['Marketing', '500', '400', '=B2-C2'],
                ['Sum Check', '=SUM(B1:B2)', '=SUM(C1:C2)', '=SUM(D1:D2)'],
            ],
            'show_totals_row' => true,
        ]);
        $this->assertFloatEquals(250.00, (float) $manualReport['rows'][0][3], 'Method B formula =B1-C1 evaluates to 250');
        $this->assertFloatEquals(1500.00, (float) $manualReport['rows'][2][1], 'Method B formula =SUM(B1:B2) evaluates to 1500');

        // 3. DomPDF Export
        $pdfOut = $exportSvc->export($autoReport, 'pdf', 'php', 1, 1);
        $this->assertTrue(str_starts_with($pdfOut['bytes'], '%PDF'), 'DomPDF generates valid %PDF binary bytes');

        // 4. PhpSpreadsheet XLSX Export
        $xlsxOut = $exportSvc->export($manualReport, 'xlsx', 'php', 1, 1);
        $this->assertTrue(str_starts_with($xlsxOut['bytes'], 'PK'), 'PhpSpreadsheet generates valid XLSX ZIP archive bytes');

        // 5. CSV Export
        $csvOut = $exportSvc->export($autoReport, 'csv', 'php', 1, 1);
        $this->assertTrue(str_contains($csvOut['bytes'], 'Code') || str_contains($csvOut['bytes'], 'INC-'), 'CSV export outputs valid CSV rows');

        // Clean up generated_reports test log entries
        Database::execute("DELETE FROM generated_reports WHERE title IN ('Complete Financial Report', 'Manual Budget Audit')");
    }
}
