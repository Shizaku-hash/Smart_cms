<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use Dompdf\Dompdf;
use Dompdf\Options;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Unified Export Service supporting:
 * - PhpSpreadsheet (XLSX)
 * - DomPDF (PDF)
 * - CSV
 * - Python FastAPI (OpenPyXL / ReportLab)
 */
final class ExportService
{
    public function export(array $report, string $format = 'xlsx', string $engine = 'php', ?int $companyId = null, ?int $userId = null): array
    {
        $company = Database::fetchOne('SELECT name, logo_path, default_currency_code FROM companies WHERE id = :id LIMIT 1', ['id' => $companyId ?? 1]);
        $companyName = $company['name'] ?? 'NovaTech Solutions';
        $currency = $company['default_currency_code'] ?? 'USD';
        $safeBase = preg_replace('/[^a-zA-Z0-9_-]+/', '_', strtolower((string) ($report['title'] ?? 'report'))) ?: 'report';

        if ($engine === 'python' && in_array($format, ['xlsx', 'pdf'], true)) {
            $pyClient = new PythonAnalyticsClient();
            $bytes = $pyClient->exportReportBytes([
                'title' => (string) ($report['title'] ?? 'Report'),
                'subtitle' => (string) ($report['subtitle'] ?? ''),
                'company_name' => $companyName,
                'columns' => array_values($report['columns'] ?? []),
                'rows' => array_values($report['rows'] ?? []),
                'totals_row' => $report['totals_row'] ?? null,
                'notes' => (string) ($report['notes'] ?? ''),
                'currency_code' => $currency,
                'export_format' => $format,
            ]);
            if ($bytes !== null) {
                $this->recordGenerated($companyId ?? 1, $userId, $report, $format, 'python');
                return [
                    'bytes' => $bytes,
                    'filename' => "{$safeBase}_python.{$format}",
                    'mime' => $format === 'pdf' ? 'application/pdf' : 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                ];
            }
        }

        if ($format === 'csv') {
            $bytes = $this->buildCsv($report);
            $this->recordGenerated($companyId ?? 1, $userId, $report, 'csv', 'php');
            return ['bytes' => $bytes, 'filename' => "{$safeBase}.csv", 'mime' => 'text/csv; charset=utf-8'];
        }

        if ($format === 'pdf') {
            $bytes = $this->buildDomPdf($report, $companyName, $company['logo_path'] ?? null);
            $this->recordGenerated($companyId ?? 1, $userId, $report, 'pdf', 'php');
            return ['bytes' => $bytes, 'filename' => "{$safeBase}.pdf", 'mime' => 'application/pdf'];
        }

        $bytes = $this->buildPhpSpreadsheet($report, $companyName);
        $this->recordGenerated($companyId ?? 1, $userId, $report, 'xlsx', 'php');
        return [
            'bytes' => $bytes,
            'filename' => "{$safeBase}.xlsx",
            'mime' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ];
    }

    public function buildPhpSpreadsheet(array $report, string $companyName): string
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Report');

        $columns = array_values($report['columns'] ?? []);
        $rows = array_values($report['rows'] ?? []);
        $colCount = max(1, count($columns));
        $lastCol = Coordinate::stringFromColumnIndex($colCount);

        // Title Row
        $sheet->mergeCells("A1:{$lastCol}1");
        $sheet->setCellValue('A1', "{$companyName} — " . ($report['title'] ?? 'Report'));
        $sheet->getStyle("A1:{$lastCol}1")->applyFromArray([
            'font' => ['bold' => true, 'size' => 14, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '0F172A']],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(28);

        // Subtitle Row
        $sheet->mergeCells("A2:{$lastCol}2");
        $sheet->setCellValue('A2', ($report['subtitle'] ?? '') . ' | Generated: ' . date('Y-m-d H:i'));
        $sheet->getStyle("A2")->getFont()->setItalic(true)->setSize(10);

        // Table Header
        $headerRow = 4;
        foreach ($columns as $cIdx => $colTitle) {
            $colLetter = Coordinate::stringFromColumnIndex($cIdx + 1);
            $sheet->setCellValue("{$colLetter}{$headerRow}", $colTitle);
        }
        $sheet->getStyle("A{$headerRow}:{$lastCol}{$headerRow}")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '2563EB']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'CBD5E1']]],
        ]);
        $sheet->getRowDimension($headerRow)->setRowHeight(22);

        // Data Rows
        $rNum = $headerRow + 1;
        foreach ($rows as $idx => $row) {
            for ($cIdx = 0; $cIdx < $colCount; $cIdx++) {
                $colLetter = Coordinate::stringFromColumnIndex($cIdx + 1);
                $sheet->setCellValue("{$colLetter}{$rNum}", $row[$cIdx] ?? '');
            }
            $bg = $idx % 2 === 1 ? 'F8FAFC' : 'FFFFFF';
            $sheet->getStyle("A{$rNum}:{$lastCol}{$rNum}")->applyFromArray([
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $bg]],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'E2E8F0']]],
            ]);
            $rNum++;
        }

        // Totals Row
        if (!empty($report['totals_row'])) {
            for ($cIdx = 0; $cIdx < $colCount; $cIdx++) {
                $colLetter = Coordinate::stringFromColumnIndex($cIdx + 1);
                $sheet->setCellValue("{$colLetter}{$rNum}", $report['totals_row'][$cIdx] ?? '');
            }
            $sheet->getStyle("A{$rNum}:{$lastCol}{$rNum}")->applyFromArray([
                'font' => ['bold' => true],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E2E8F0']],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '94A3B8']]],
            ]);
            $rNum++;
        }

        if (!empty($report['notes'])) {
            $rNum++;
            $sheet->mergeCells("A{$rNum}:{$lastCol}{$rNum}");
            $sheet->setCellValue("A{$rNum}", 'Notes: ' . $report['notes']);
            $sheet->getStyle("A{$rNum}")->getFont()->setItalic(true);
        }

        for ($cIdx = 1; $cIdx <= $colCount; $cIdx++) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($cIdx))->setAutoSize(true);
        }

        ob_start();
        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');
        return (string) ob_get_clean();
    }

    public function buildDomPdf(array $report, string $companyName, ?string $logoPath = null): string
    {
        $columns = array_values($report['columns'] ?? []);
        $rows = array_values($report['rows'] ?? []);
        $orientation = count($columns) > 5 ? 'landscape' : 'portrait';

        $logoHtml = '';
        if ((!isset($report['include_logo']) || !empty($report['include_logo'])) && $logoPath) {
            $fullLogo = (new FileUploadService())->resolveSafePath($logoPath);
            if ($fullLogo && is_file($fullLogo)) {
                $mime = mime_content_type($fullLogo) ?: 'image/png';
                $b64 = base64_encode((string) file_get_contents($fullLogo));
                $logoHtml = '<img src="data:' . $mime . ';base64,' . $b64 . '" style="height:36px;vertical-align:middle;margin-right:10px;">';
            }
        }

        $html = '<!doctype html><html><head><meta charset="utf-8"><style>
            body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #0f172a; margin: 18px; }
            .hdr { border-bottom: 2px solid #2563eb; padding-bottom: 10px; margin-bottom: 14px; }
            .hdr h1 { font-size: 16px; margin: 0 0 4px 0; color: #0f172a; }
            .hdr .sub { font-size: 10px; color: #475569; }
            table { width: 100%; border-collapse: collapse; margin-top: 8px; }
            th { background: #2563eb; color: #ffffff; text-align: left; padding: 6px 7px; font-size: 9px; border: 1px solid #1d4ed8; }
            td { padding: 5px 7px; border: 1px solid #cbd5e1; font-size: 9px; }
            tr:nth-child(even) td { background: #f8fafc; }
            tr.totals td { background: #e2e8f0; font-weight: bold; }
            .notes { margin-top: 12px; padding: 8px; background: #f1f5f9; border-left: 3px solid #2563eb; font-size: 9.5px; }
        </style></head><body>';

        $html .= '<div class="hdr">' . $logoHtml . '<h1>' . e($companyName) . ' — ' . e($report['title'] ?? 'Report') . '</h1>';
        $html .= '<div class="sub">' . e($report['subtitle'] ?? '') . ' &bull; Generated: ' . date('Y-m-d H:i') . '</div></div>';

        $html .= '<table><thead><tr>';
        foreach ($columns as $col) {
            $html .= '<th>' . e($col) . '</th>';
        }
        $html .= '</tr></thead><tbody>';

        foreach ($rows as $row) {
            $html .= '<tr>';
            for ($i = 0; $i < count($columns); $i++) {
                $val = $row[$i] ?? '';
                $html .= '<td>' . e((string) $val) . '</td>';
            }
            $html .= '</tr>';
        }

        if (!empty($report['totals_row'])) {
            $html .= '<tr class="totals">';
            for ($i = 0; $i < count($columns); $i++) {
                $html .= '<td>' . e((string) ($report['totals_row'][$i] ?? '')) . '</td>';
            }
            $html .= '</tr>';
        }
        $html .= '</tbody></table>';

        if (!empty($report['notes'])) {
            $html .= '<div class="notes"><strong>Notes:</strong> ' . nl2br(e((string) $report['notes'])) . '</div>';
        }
        $html .= '</body></html>';

        $options = new Options();
        $options->set('isRemoteEnabled', false);
        $options->set('defaultFont', 'DejaVu Sans');
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', $orientation);
        $dompdf->render();

        return (string) $dompdf->output();
    }

    public function buildCsv(array $report): string
    {
        $fp = fopen('php://temp', 'r+');
        if ($fp === false) {
            return '';
        }
        // UTF-8 BOM for clean Excel opening
        fwrite($fp, "\xEF\xBB\xBF");
        fputcsv($fp, $report['columns'] ?? []);
        foreach ($report['rows'] ?? [] as $row) {
            fputcsv($fp, $row);
        }
        if (!empty($report['totals_row'])) {
            fputcsv($fp, $report['totals_row']);
        }
        rewind($fp);
        $csv = (string) stream_get_contents($fp);
        fclose($fp);
        return $csv;
    }

    private function recordGenerated(int $companyId, ?int $userId, array $report, string $format, string $engine): void
    {
        try {
            Database::execute(
                "INSERT INTO generated_reports (company_id, template_id, title, report_type, creation_method, export_format, engine, generated_by)
                 VALUES (:cid, :tid, :title, :rtype, :method, :fmt, :eng, :uid)",
                [
                    'cid' => $companyId,
                    'tid' => $report['template_id'] ?? null,
                    'title' => mb_substr((string) ($report['title'] ?? 'Report'), 0, 190),
                    'rtype' => (string) ($report['report_type'] ?? 'custom'),
                    'method' => (string) ($report['creation_method'] ?? 'automatic'),
                    'fmt' => $format,
                    'eng' => $engine,
                    'uid' => $userId,
                ]
            );
        } catch (\Throwable) {
            // Safe ignore
        }
    }
}
