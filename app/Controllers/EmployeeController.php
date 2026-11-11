<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;
use App\Core\View;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmployeeDocument;
use App\Services\CurrencyService;
use App\Services\EmployeeService;
use App\Services\FileUploadService;
use Throwable;

final class EmployeeController extends BaseController
{
    private Employee $employeeModel;
    private EmployeeService $employeeService;

    public function __construct()
    {
        $this->employeeModel = new Employee();
        $this->employeeService = new EmployeeService();
    }

    public function index(Request $request): string
    {
        $companyId = Auth::companyId();
        $canViewAll = Auth::can('employees.view_all');
        $ownEmployeeId = Auth::employeeId();

        if (!$canViewAll) {
            if ($ownEmployeeId !== null) {
                $this->redirect('/employees/' . $ownEmployeeId);
            }
            Response::html(View::render('errors/403', ['requiredPermission' => 'employees.view_all']), 403);
        }

        $filters = [
            'department_id' => $request->query('department_id'),
            'status' => $request->query('status'),
            'payment_type' => $request->query('payment_type'),
            'search' => $request->query('search'),
        ];

        $employees = $this->employeeModel->filter($companyId, $filters);
        $departments = (new Department())->forCompany($companyId);
        $currencies = (new CurrencyService())->getAllActive();

        return $this->render('employees/index', [
            'title' => __('employees.title'),
            'employees' => $employees,
            'departments' => $departments,
            'currencies' => $currencies,
            'filters' => $filters,
        ]);
    }

    public function store(Request $request): void
    {
        $data = $request->all();
        $v = Validator::make($data, [
            'full_name' => 'required|min:2',
            'position' => 'required',
            'base_salary' => 'numeric|min:0',
        ]);
        if ($v->fails()) {
            $this->redirect('/employees', ['error' => $v->firstError()]);
        }

        try {
            $id = $this->employeeService->createEmployee(Auth::companyId(), (int) Auth::id(), $data, $request->file('photo'));
            $this->redirect('/employees/' . $id, ['success' => __('messages.saved')]);
        } catch (Throwable $e) {
            $this->redirect('/employees', ['error' => $e->getMessage()]);
        }
    }

    public function show(Request $request, string $id): string
    {
        $empId = (int) $id;
        $canViewAll = Auth::can('employees.view_all');
        $ownEmployeeId = Auth::employeeId();

        // STRICT PRIVACY ENFORCEMENT: employees cannot access other employees' profiles
        if (!$canViewAll && $ownEmployeeId !== $empId) {
            Response::html(View::render('errors/403', ['requiredPermission' => 'employees.view_all']), 403);
        }

        $companyId = Auth::companyId();
        $sections = $this->employeeService->getProfileSections($empId, $companyId);
        $departments = (new Department())->forCompany($companyId);
        $currencies = (new CurrencyService())->getAllActive();

        return $this->render('employees/show', [
            'title' => $sections['employee']['full_name'] . ' — ' . __('employees.profile'),
            'profile' => $sections,
            'departments' => $departments,
            'currencies' => $currencies,
            'activeTab' => (string) $request->query('tab', 'personal'),
            'canEditAll' => Auth::can('employees.edit'),
        ]);
    }

    public function update(Request $request, string $id): void
    {
        $empId = (int) $id;
        $canEditAll = Auth::can('employees.edit');
        $ownEmployeeId = Auth::employeeId();

        if (!$canEditAll && $ownEmployeeId !== $empId) {
            Response::html(View::render('errors/403', ['requiredPermission' => 'employees.edit']), 403);
        }

        try {
            $this->employeeService->updateEmployee(
                $empId,
                Auth::companyId(),
                (int) Auth::id(),
                $request->all(),
                $request->file('photo'),
                !$canEditAll
            );
            $this->redirect('/employees/' . $empId, ['success' => __('messages.saved')]);
        } catch (Throwable $e) {
            $this->redirect('/employees/' . $empId, ['error' => $e->getMessage()]);
        }
    }

    public function uploadDocument(Request $request, string $id): void
    {
        $file = $request->file('document');
        if ($file === null) {
            $this->redirect('/employees/' . $id . '?tab=documents', ['error' => 'Please select a file to upload.']);
        }
        try {
            $this->employeeService->uploadDocument(
                (int) $id,
                Auth::companyId(),
                (int) Auth::id(),
                $file,
                trim((string) $request->input('title', '')),
                (string) $request->input('document_type', 'contract'),
                (string) $request->input('notes', '')
            );
            $this->redirect('/employees/' . $id . '?tab=documents', ['success' => __('messages.saved')]);
        } catch (Throwable $e) {
            $this->redirect('/employees/' . $id . '?tab=documents', ['error' => $e->getMessage()]);
        }
    }

    public function downloadDocument(Request $request, string $docId): void
    {
        $doc = Database::fetchOne(
            "SELECT d.*, e.company_id FROM employee_documents d
             INNER JOIN employees e ON e.id = d.employee_id
             WHERE d.id = :id AND d.deleted_at IS NULL LIMIT 1",
            ['id' => (int) $docId]
        );
        if ($doc === null || (int) $doc['company_id'] !== Auth::companyId()) {
            Response::html('Document not found', 404);
        }
        if (!Auth::can('employees.view_all') && Auth::employeeId() !== (int) $doc['employee_id']) {
            Response::html(View::render('errors/403', ['requiredPermission' => 'employees.view_all']), 403);
        }

        $full = (new FileUploadService())->resolveSafePath((string) $doc['file_path']);
        if ($full === null) {
            Response::html('File missing', 404);
        }
        Response::downloadFile($full, (string) $doc['original_filename'], (string) $doc['mime_type']);
    }

    public function deleteDocument(Request $request, string $docId): void
    {
        $doc = (new EmployeeDocument())->find((int) $docId);
        if ($doc !== null) {
            (new EmployeeDocument())->delete((int) $docId);
            $this->redirect('/employees/' . $doc['employee_id'] . '?tab=documents', ['success' => __('messages.deleted')]);
        }
        $this->redirect('/employees');
    }

    public function delete(Request $request, string $id): void
    {
        try {
            $this->employeeService->deleteEmployee((int) $id, Auth::companyId(), (int) Auth::id());
            $this->redirect('/employees', ['success' => __('messages.deleted')]);
        } catch (Throwable $e) {
            $this->redirect('/employees', ['error' => $e->getMessage()]);
        }
    }

    public function servePhoto(Request $request, string $id): void
    {
        $emp = $this->employeeModel->find((int) $id);
        if ($emp === null || empty($emp['photo_path'])) {
            Response::html('Not found', 404);
        }
        $full = (new FileUploadService())->resolveSafePath((string) $emp['photo_path']);
        if ($full === null) {
            Response::html('Not found', 404);
        }
        $mime = mime_content_type($full) ?: 'image/png';
        http_response_code(200);
        header('Content-Type: ' . $mime);
        readfile($full);
        exit;
    }
}
