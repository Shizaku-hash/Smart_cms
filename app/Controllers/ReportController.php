<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Models\Employee;
use App\Models\ExpenseCategory;
use App\Models\GeneratedReport;
use App\Models\IncomeCategory;
use App\Models\ReportTemplate;
use App\Services\ActivityLogService;
use App\Services\ExportService;
use App\Services\ReportService;

final class ReportController extends BaseController
{
    private ReportService $reportService;
    private ReportTemplate $templateModel;

    public function __construct()
    {
        $this->reportService = new ReportService();
        $this->templateModel = new ReportTemplate();
    }

    public function index(Request $request): string
    {
        $companyId = Auth::companyId();
        $templates = $this->templateModel->accessibleBy($companyId, (int) Auth::id());
        $history = (new GeneratedReport())->recent($companyId, 30);

        return $this->render('reports/index', [
            'title' => __('reports.title'),
            'reportTypes' => ReportService::REPORT_TYPES,
            'templates' => $templates,
            'history' => $history,
        ]);
    }

    public function builder(Request $request): string
    {
        $companyId = Auth::companyId();
        $templateId = $request->query('template_id') ? (int) $request->query('template_id') : null;
        $loadedTemplate = $templateId ? $this->templateModel->find($templateId) : null;
        $method = (string) $request->query('method', $loadedTemplate['creation_method'] ?? 'automatic');
        $reportType = (string) $request->query('report_type', $loadedTemplate['report_type'] ?? 'complete_financial');

        $templateConfig = [];
        if ($loadedTemplate && !empty($loadedTemplate['config_json'])) {
            $templateConfig = json_decode((string) $loadedTemplate['config_json'], true) ?: [];
        }

        // Generate initial preview automatically for fast UX
        $previewData = $method === 'manual'
            ? $this->reportService->buildManualReport($templateConfig ?: [
                'title' => 'Custom Executive Summary Report',
                'subtitle' => 'Prepared via Manual Report Builder',
                'include_logo' => true,
                'columns' => ['Line Item', 'Q1 Budget', 'Q1 Actual', 'Variance'],
                'rows' => [
                    ['Cloud Infrastructure', '4000', '3450', '=B1-C1'],
                    ['Marketing Campaigns', '6000', '5120', '=B2-C2'],
                    ['Team Operations', '8500', '8200', '=B3-C3'],
                ],
                'show_totals_row' => true,
                'notes' => 'Formulas like =B1-C1 or =SUM(B1:B3) are automatically calculated.',
            ])
            : $this->reportService->buildAutomaticReport($companyId, array_merge(['report_type' => $reportType], $templateConfig));

        return $this->render('reports/builder', [
            'title' => __('reports.builder'),
            'method' => $method,
            'reportType' => $reportType,
            'reportTypes' => ReportService::REPORT_TYPES,
            'availableColumns' => $this->reportService->getAvailableColumns($reportType),
            'allColumnsByType' => $this->getAllColumnsMap(),
            'employees' => (new Employee())->filter($companyId),
            'incomeCategories' => (new IncomeCategory())->forCompany($companyId),
            'expenseCategories' => (new ExpenseCategory())->forCompany($companyId),
            'loadedTemplate' => $loadedTemplate,
            'templateConfig' => $templateConfig,
            'previewData' => $previewData,
        ]);
    }

    public function preview(Request $request): void
    {
        $payload = $request->all();
        $method = (string) ($payload['creation_method'] ?? 'automatic');
        $report = $method === 'manual'
            ? $this->reportService->buildManualReport($payload)
            : $this->reportService->buildAutomaticReport(Auth::companyId(), $payload);

        $this->json(['ok' => true, 'report' => $report]);
    }

    public function export(Request $request): void
    {
        $payload = $request->all();
        $method = (string) ($payload['creation_method'] ?? 'automatic');
        $format = (string) ($payload['export_format'] ?? 'xlsx');
        $engine = (string) ($payload['engine'] ?? 'php');

        if ($method === 'manual') {
            if (is_string($payload['columns'] ?? null)) {
                $payload['columns'] = json_decode($payload['columns'], true) ?: [];
            }
            if (is_string($payload['rows'] ?? null)) {
                $payload['rows'] = json_decode($payload['rows'], true) ?: [];
            }
            $report = $this->reportService->buildManualReport($payload);
        } else {
            if (is_string($payload['columns'] ?? null)) {
                $payload['columns'] = json_decode($payload['columns'], true) ?: [];
            }
            $report = $this->reportService->buildAutomaticReport(Auth::companyId(), $payload);
        }

        if (!empty($payload['template_id'])) {
            $report['template_id'] = (int) $payload['template_id'];
        }

        $out = (new ExportService())->export($report, $format, $engine, Auth::companyId(), Auth::id());
        (new ActivityLogService())->log('export', 'report', null, "Exported {$report['title']} ({$format}, engine={$engine})", null, null, Auth::companyId(), Auth::id());
        Response::streamDownload($out['bytes'], $out['filename'], $out['mime']);
    }

    public function saveTemplate(Request $request): void
    {
        $payload = $request->all();
        $name = trim((string) ($payload['template_name'] ?? 'Untitled Template'));
        $method = (string) ($payload['creation_method'] ?? 'automatic');
        $reportType = (string) ($payload['report_type'] ?? ($method === 'manual' ? 'custom' : 'complete_financial'));
        $config = is_string($payload['config_json'] ?? null)
            ? $payload['config_json']
            : json_encode($payload['config'] ?? $payload, JSON_UNESCAPED_UNICODE);

        $id = $this->templateModel->create([
            'company_id' => Auth::companyId(),
            'name' => $name,
            'description' => (string) ($payload['template_description'] ?? ''),
            'report_type' => $reportType,
            'creation_method' => $method === 'manual' ? 'manual' : 'automatic',
            'config_json' => $config,
            'is_shared' => !empty($payload['is_shared']) ? 1 : 0,
            'created_by' => Auth::id(),
        ]);

        (new ActivityLogService())->log('create', 'report_template', $id, "Saved report template '{$name}'", null, null, Auth::companyId(), Auth::id());

        if ($request->wantsJson()) {
            $this->json(['ok' => true, 'id' => $id]);
        }
        $this->redirect('/reports/builder?template_id=' . $id, ['success' => __('messages.saved')]);
    }

    public function deleteTemplate(Request $request, string $id): void
    {
        $this->templateModel->delete((int) $id);
        $this->redirect('/reports', ['success' => __('messages.deleted')]);
    }

    private function getAllColumnsMap(): array
    {
        $map = [];
        foreach (array_keys(ReportService::REPORT_TYPES) as $type) {
            $map[$type] = $this->reportService->getAvailableColumns($type);
        }
        return $map;
    }
}
