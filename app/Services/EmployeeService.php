<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Models\Attendance;
use App\Models\Bonus;
use App\Models\Deduction;
use App\Models\Employee;
use App\Models\EmployeeDocument;
use App\Models\Payroll;
use App\Models\PerformanceRecord;
use RuntimeException;

final class EmployeeService
{
    private Employee $employeeModel;
    private EmployeeDocument $docModel;
    private FileUploadService $uploadService;
    private ActivityLogService $logger;
    private NotificationService $notifier;

    public function __construct()
    {
        $this->employeeModel = new Employee();
        $this->docModel = new EmployeeDocument();
        $this->uploadService = new FileUploadService();
        $this->logger = new ActivityLogService();
        $this->notifier = new NotificationService();
    }

    public function getProfileSections(int $employeeId, int $companyId): array
    {
        $employee = $this->employeeModel->findDetailed($employeeId);
        if ($employee === null || (int) $employee['company_id'] !== $companyId) {
            throw new RuntimeException('Employee not found.');
        }

        $attendance = (new Attendance())->filter($companyId, $employeeId);
        $performance = (new PerformanceRecord())->forCompany($companyId, $employeeId);
        $bonuses = (new Bonus())->forCompany($companyId, $employeeId);
        $deductions = (new Deduction())->forCompany($companyId, $employeeId);
        $payrolls = (new Payroll())->filter($companyId, ['employee_id' => $employeeId]);
        $documents = $this->docModel->forEmployee($employeeId);
        $tasks = Database::fetchAll(
            "SELECT t.*, ta.completion_status
             FROM tasks t
             INNER JOIN task_assignments ta ON ta.task_id = t.id
             WHERE ta.employee_id = :eid AND t.deleted_at IS NULL
             ORDER BY t.created_at DESC",
            ['eid' => $employeeId]
        );

        return [
            'employee' => $employee,
            'attendance' => $attendance,
            'performance' => $performance,
            'bonuses' => $bonuses,
            'deductions' => $deductions,
            'payrolls' => $payrolls,
            'documents' => $documents,
            'tasks' => $tasks,
        ];
    }

    public function createEmployee(int $companyId, int $userId, array $data, ?array $photoFile = null): int
    {
        $photoPath = null;
        if ($photoFile !== null) {
            $stored = $this->uploadService->store($photoFile, 'employees');
            $photoPath = $stored['relative_path'];
        }

        return Database::transaction(function () use ($companyId, $userId, $data, $photoPath): int {
            $code = !empty($data['employee_code']) ? trim((string) $data['employee_code']) : $this->employeeModel->generateNextCode($companyId);

            $id = $this->employeeModel->create([
                'company_id' => $companyId,
                'department_id' => !empty($data['department_id']) ? (int) $data['department_id'] : null,
                'employee_code' => $code,
                'full_name' => trim((string) $data['full_name']),
                'photo_path' => $photoPath,
                'date_of_birth' => !empty($data['date_of_birth']) ? $data['date_of_birth'] : null,
                'gender' => $data['gender'] ?? 'other',
                'phone' => $data['phone'] ?? null,
                'email' => $data['email'] ?? null,
                'home_address' => $data['home_address'] ?? null,
                'position' => trim((string) $data['position']),
                'employment_date' => !empty($data['employment_date']) ? $data['employment_date'] : date('Y-m-d'),
                'employment_status' => $data['employment_status'] ?? 'active',
                'contract_type' => $data['contract_type'] ?? 'full_time',
                'contract_start_date' => !empty($data['contract_start_date']) ? $data['contract_start_date'] : date('Y-m-d'),
                'contract_end_date' => !empty($data['contract_end_date']) ? $data['contract_end_date'] : null,
                'contract_notes' => $data['contract_notes'] ?? null,
                'payment_type' => $data['payment_type'] ?? 'fixed',
                'base_salary' => (float) ($data['base_salary'] ?? 0),
                'hourly_rate' => (float) ($data['hourly_rate'] ?? 0),
                'daily_rate' => (float) ($data['daily_rate'] ?? 0),
                'currency_code' => $data['currency_code'] ?? 'USD',
                'bank_name' => $data['bank_name'] ?? null,
                'bank_account_number' => $data['bank_account_number'] ?? null,
                'bank_routing_code' => $data['bank_routing_code'] ?? null,
                'payment_notes' => $data['payment_notes'] ?? null,
                'emergency_contact_name' => $data['emergency_contact_name'] ?? null,
                'emergency_contact_phone' => $data['emergency_contact_phone'] ?? null,
                'emergency_contact_relation' => $data['emergency_contact_relation'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);

            $this->logger->log('create', 'employee', $id, "Created employee {$code} ({$data['full_name']})", null, ['code' => $code, 'position' => $data['position']], $companyId, $userId);
            $this->notifier->notifyRole($companyId, 'hr_manager', 'employee_update', 'New Employee Onboarded', "{$data['full_name']} ({$code}) added to team.", "/employees/{$id}", 'success');
            return $id;
        });
    }

    public function updateEmployee(int $id, int $companyId, int $userId, array $data, ?array $photoFile = null, bool $limitedSelfUpdate = false): void
    {
        $existing = $this->employeeModel->find($id);
        if ($existing === null || (int) $existing['company_id'] !== $companyId) {
            throw new RuntimeException('Employee not found.');
        }

        if ($limitedSelfUpdate) {
            // Employees updating their own profile may only edit safe personal contact fields
            $update = array_intersect_key($data, array_flip([
                'phone', 'home_address', 'emergency_contact_name', 'emergency_contact_phone', 'emergency_contact_relation',
            ]));
        } else {
            $update = [
                'department_id' => !empty($data['department_id']) ? (int) $data['department_id'] : $existing['department_id'],
                'full_name' => trim((string) ($data['full_name'] ?? $existing['full_name'])),
                'date_of_birth' => !empty($data['date_of_birth']) ? $data['date_of_birth'] : $existing['date_of_birth'],
                'gender' => $data['gender'] ?? $existing['gender'],
                'phone' => $data['phone'] ?? $existing['phone'],
                'email' => $data['email'] ?? $existing['email'],
                'home_address' => $data['home_address'] ?? $existing['home_address'],
                'position' => trim((string) ($data['position'] ?? $existing['position'])),
                'employment_date' => !empty($data['employment_date']) ? $data['employment_date'] : $existing['employment_date'],
                'employment_status' => $data['employment_status'] ?? $existing['employment_status'],
                'contract_type' => $data['contract_type'] ?? $existing['contract_type'],
                'contract_start_date' => !empty($data['contract_start_date']) ? $data['contract_start_date'] : $existing['contract_start_date'],
                'contract_end_date' => !empty($data['contract_end_date']) ? $data['contract_end_date'] : $existing['contract_end_date'],
                'contract_notes' => $data['contract_notes'] ?? $existing['contract_notes'],
                'payment_type' => $data['payment_type'] ?? $existing['payment_type'],
                'base_salary' => isset($data['base_salary']) ? (float) $data['base_salary'] : (float) $existing['base_salary'],
                'hourly_rate' => isset($data['hourly_rate']) ? (float) $data['hourly_rate'] : (float) $existing['hourly_rate'],
                'daily_rate' => isset($data['daily_rate']) ? (float) $data['daily_rate'] : (float) $existing['daily_rate'],
                'currency_code' => $data['currency_code'] ?? $existing['currency_code'],
                'bank_name' => $data['bank_name'] ?? $existing['bank_name'],
                'bank_account_number' => $data['bank_account_number'] ?? $existing['bank_account_number'],
                'bank_routing_code' => $data['bank_routing_code'] ?? $existing['bank_routing_code'],
                'payment_notes' => $data['payment_notes'] ?? $existing['payment_notes'],
                'emergency_contact_name' => $data['emergency_contact_name'] ?? $existing['emergency_contact_name'],
                'emergency_contact_phone' => $data['emergency_contact_phone'] ?? $existing['emergency_contact_phone'],
                'emergency_contact_relation' => $data['emergency_contact_relation'] ?? $existing['emergency_contact_relation'],
                'notes' => $data['notes'] ?? $existing['notes'],
            ];
        }

        if ($photoFile !== null) {
            $stored = $this->uploadService->store($photoFile, 'employees');
            $update['photo_path'] = $stored['relative_path'];
        }

        Database::transaction(function () use ($id, $update, $existing, $companyId, $userId): void {
            $this->employeeModel->update($id, $update);
            $this->logger->log('update', 'employee', $id, "Updated employee {$existing['employee_code']}", $existing, $update, $companyId, $userId);
        });
    }

    public function uploadDocument(int $employeeId, int $companyId, int $userId, array $file, string $title, string $docType, string $notes = ''): int
    {
        $emp = $this->employeeModel->find($employeeId);
        if ($emp === null || (int) $emp['company_id'] !== $companyId) {
            throw new RuntimeException('Employee not found.');
        }

        $stored = $this->uploadService->store($file, 'documents');
        $docId = $this->docModel->create([
            'employee_id' => $employeeId,
            'title' => $title !== '' ? $title : $stored['original_name'],
            'document_type' => $docType,
            'file_path' => $stored['relative_path'],
            'original_filename' => $stored['original_name'],
            'mime_type' => $stored['mime_type'],
            'file_size' => $stored['size'],
            'uploaded_by' => $userId,
            'notes' => $notes,
        ]);

        $this->logger->log('upload_document', 'employee', $employeeId, "Uploaded document '{$title}' for {$emp['employee_code']}", null, ['doc_id' => $docId, 'file' => $stored['original_name']], $companyId, $userId);
        return $docId;
    }

    public function deleteEmployee(int $id, int $companyId, int $userId): void
    {
        $existing = $this->employeeModel->find($id);
        if ($existing === null || (int) $existing['company_id'] !== $companyId) {
            throw new RuntimeException('Employee not found.');
        }
        Database::transaction(function () use ($id, $existing, $companyId, $userId): void {
            $this->employeeModel->delete($id);
            $this->logger->log('delete', 'employee', $id, "Archived employee {$existing['employee_code']}", $existing, null, $companyId, $userId);
        });
    }
}
