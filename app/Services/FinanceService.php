<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Models\ExpenseCategory;
use App\Models\IncomeCategory;
use App\Models\Transaction;
use RuntimeException;

final class FinanceService
{
    private Transaction $txModel;
    private IncomeCategory $incCatModel;
    private ExpenseCategory $expCatModel;
    private CurrencyService $currencyService;
    private FileUploadService $uploadService;
    private ActivityLogService $logger;
    private NotificationService $notifier;

    public function __construct()
    {
        $this->txModel = new Transaction();
        $this->incCatModel = new IncomeCategory();
        $this->expCatModel = new ExpenseCategory();
        $this->currencyService = new CurrencyService();
        $this->uploadService = new FileUploadService();
        $this->logger = new ActivityLogService();
        $this->notifier = new NotificationService();
    }

    public function getSummary(int $companyId, ?string $dateFrom = null, ?string $dateTo = null): array
    {
        $where = "company_id = :cid AND deleted_at IS NULL AND status IN ('completed', 'approved')";
        $params = ['cid' => $companyId];

        if ($dateFrom) {
            $where .= " AND transaction_date >= :df";
            $params['df'] = $dateFrom;
        }
        if ($dateTo) {
            $where .= " AND transaction_date <= :dt";
            $params['dt'] = $dateTo;
        }

        $totalIncome = (float) Database::fetchColumn(
            "SELECT COALESCE(SUM(base_amount), 0) FROM transactions WHERE {$where} AND type = 'income'",
            $params
        );
        $totalExpenses = (float) Database::fetchColumn(
            "SELECT COALESCE(SUM(base_amount), 0) FROM transactions WHERE {$where} AND type = 'expense'",
            $params
        );

        // Current month figures
        $mStart = date('Y-m-01');
        $mEnd = date('Y-m-t');
        $monthIncome = (float) Database::fetchColumn(
            "SELECT COALESCE(SUM(base_amount), 0) FROM transactions
             WHERE company_id = :cid AND deleted_at IS NULL AND status IN ('completed','approved')
               AND type = 'income' AND transaction_date BETWEEN :ms AND :me",
            ['cid' => $companyId, 'ms' => $mStart, 'me' => $mEnd]
        );
        $monthExpenses = (float) Database::fetchColumn(
            "SELECT COALESCE(SUM(base_amount), 0) FROM transactions
             WHERE company_id = :cid AND deleted_at IS NULL AND status IN ('completed','approved')
               AND type = 'expense' AND transaction_date BETWEEN :ms AND :me",
            ['cid' => $companyId, 'ms' => $mStart, 'me' => $mEnd]
        );

        $pendingExpenses = Database::fetchOne(
            "SELECT COUNT(*) AS cnt, COALESCE(SUM(base_amount), 0) AS total
             FROM transactions WHERE company_id = :cid AND deleted_at IS NULL AND type = 'expense' AND status = 'pending'",
            ['cid' => $companyId]
        );

        return [
            'base_currency' => $this->currencyService->getDefaultCode($companyId),
            'total_income' => round($totalIncome, 2),
            'total_expenses' => round($totalExpenses, 2),
            'net_balance' => round($totalIncome - $totalExpenses, 2),
            'monthly_income' => round($monthIncome, 2),
            'monthly_expenses' => round($monthExpenses, 2),
            'pending_expenses_count' => (int) ($pendingExpenses['cnt'] ?? 0),
            'pending_expenses_total' => round((float) ($pendingExpenses['total'] ?? 0), 2),
        ];
    }

    public function createTransaction(int $companyId, int $userId, array $data, ?array $file = null): int
    {
        $type = $data['type'] === 'income' ? 'income' : 'expense';
        $baseCurrency = $this->currencyService->getDefaultCode($companyId);
        $currency = strtoupper((string) ($data['currency_code'] ?? $baseCurrency));
        $originalAmount = round((float) ($data['original_amount'] ?? 0), 2);
        if ($originalAmount <= 0) {
            throw new RuntimeException('Transaction amount must be greater than zero.');
        }

        $conv = $this->currencyService->convert($originalAmount, $currency, $baseCurrency);
        $attachmentPath = null;
        $attachmentName = null;
        if ($file !== null) {
            $stored = $this->uploadService->store($file, 'receipts');
            $attachmentPath = $stored['relative_path'];
            $attachmentName = $stored['original_name'];
        }

        $isRecurring = !empty($data['is_recurring']) ? 1 : 0;
        $freq = $isRecurring ? ($data['recurring_frequency'] ?? 'monthly') : null;
        $txDate = (string) ($data['transaction_date'] ?? date('Y-m-d'));
        $nextRecDate = $isRecurring ? $this->computeNextRecurringDate($txDate, (string) $freq) : null;

        return Database::transaction(function () use (
            $companyId, $userId, $type, $data, $originalAmount, $currency, $conv,
            $txDate, $attachmentPath, $attachmentName, $isRecurring, $freq, $nextRecDate
        ): int {
            $code = $this->txModel->generateNextCode($type);
            $status = (string) ($data['status'] ?? 'completed');

            $id = $this->txModel->create([
                'company_id' => $companyId,
                'transaction_code' => $code,
                'type' => $type,
                'income_category_id' => $type === 'income' && !empty($data['income_category_id']) ? (int) $data['income_category_id'] : null,
                'expense_category_id' => $type === 'expense' && !empty($data['expense_category_id']) ? (int) $data['expense_category_id'] : null,
                'employee_id' => !empty($data['employee_id']) ? (int) $data['employee_id'] : null,
                'department_id' => !empty($data['department_id']) ? (int) $data['department_id'] : null,
                'original_amount' => $originalAmount,
                'currency_code' => $currency,
                'exchange_rate' => $conv['rate'],
                'base_amount' => $conv['converted_amount'],
                'transaction_date' => $txDate,
                'due_date' => !empty($data['due_date']) ? $data['due_date'] : null,
                'payment_method' => (string) ($data['payment_method'] ?? 'bank_transfer'),
                'status' => $status,
                'reference_number' => $data['reference_number'] ?? null,
                'vendor_or_client' => $data['vendor_or_client'] ?? null,
                'description' => $data['description'] ?? null,
                'attachment_path' => $attachmentPath,
                'attachment_name' => $attachmentName,
                'is_recurring' => $isRecurring,
                'recurring_frequency' => $freq,
                'next_recurring_date' => $nextRecDate,
                'created_by' => $userId,
            ]);

            $this->logger->log('create', 'transaction', $id, "Created {$type} {$code} ({$conv['converted_amount']})", null, [
                'code' => $code,
                'type' => $type,
                'base_amount' => $conv['converted_amount'],
                'currency' => $currency,
            ], $companyId, $userId);

            if ($type === 'expense' && $status === 'pending') {
                $this->notifier->notifyRole($companyId, 'accountant', 'expense_pending', 'Pending Expense Approval', "Expense {$code} (\${$conv['converted_amount']}) requires approval.", '/finance?tab=expenses', 'warning');
                $this->notifier->notifyRole($companyId, 'super_admin', 'expense_pending', 'Pending Expense Approval', "Expense {$code} (\${$conv['converted_amount']}) requires approval.", '/finance?tab=expenses', 'warning');
            } elseif ($conv['converted_amount'] >= 5000) {
                $this->notifier->notifyRole($companyId, 'company_owner', 'financial_alert', 'Significant Financial Movement', ucfirst($type) . " {$code} of \${$conv['converted_amount']} recorded.", '/finance', 'info');
            }

            return $id;
        });
    }

    public function updateTransaction(int $id, int $companyId, int $userId, array $data, ?array $file = null): void
    {
        $existing = $this->txModel->find($id);
        if ($existing === null || (int) $existing['company_id'] !== $companyId) {
            throw new RuntimeException('Transaction not found.');
        }

        $type = $existing['type'];
        $baseCurrency = $this->currencyService->getDefaultCode($companyId);
        $currency = strtoupper((string) ($data['currency_code'] ?? $existing['currency_code']));
        $originalAmount = isset($data['original_amount']) ? round((float) $data['original_amount'], 2) : (float) $existing['original_amount'];
        $conv = $this->currencyService->convert($originalAmount, $currency, $baseCurrency);

        $updateData = [
            'income_category_id' => $type === 'income' ? (!empty($data['income_category_id']) ? (int) $data['income_category_id'] : null) : null,
            'expense_category_id' => $type === 'expense' ? (!empty($data['expense_category_id']) ? (int) $data['expense_category_id'] : null) : null,
            'employee_id' => !empty($data['employee_id']) ? (int) $data['employee_id'] : null,
            'department_id' => !empty($data['department_id']) ? (int) $data['department_id'] : null,
            'original_amount' => $originalAmount,
            'currency_code' => $currency,
            'exchange_rate' => $conv['rate'],
            'base_amount' => $conv['converted_amount'],
            'transaction_date' => (string) ($data['transaction_date'] ?? $existing['transaction_date']),
            'payment_method' => (string) ($data['payment_method'] ?? $existing['payment_method']),
            'status' => (string) ($data['status'] ?? $existing['status']),
            'reference_number' => $data['reference_number'] ?? $existing['reference_number'],
            'vendor_or_client' => $data['vendor_or_client'] ?? $existing['vendor_or_client'],
            'description' => $data['description'] ?? $existing['description'],
        ];

        if ($file !== null) {
            $stored = $this->uploadService->store($file, 'receipts');
            $updateData['attachment_path'] = $stored['relative_path'];
            $updateData['attachment_name'] = $stored['original_name'];
        }

        Database::transaction(function () use ($id, $updateData, $existing, $companyId, $userId): void {
            $this->txModel->update($id, $updateData);
            $this->logger->log('update', 'transaction', $id, "Updated transaction {$existing['transaction_code']}", $existing, $updateData, $companyId, $userId);
        });
    }

    public function approveTransaction(int $id, int $companyId, int $userId): void
    {
        $existing = $this->txModel->find($id);
        if ($existing === null || (int) $existing['company_id'] !== $companyId) {
            throw new RuntimeException('Transaction not found.');
        }

        Database::transaction(function () use ($id, $existing, $companyId, $userId): void {
            $this->txModel->update($id, [
                'status' => 'completed',
                'approved_by' => $userId,
                'approved_at' => date('Y-m-d H:i:s'),
            ]);
            $this->logger->log('approve', 'transaction', $id, "Approved transaction {$existing['transaction_code']}", ['status' => $existing['status']], ['status' => 'completed'], $companyId, $userId);
        });
    }

    public function deleteTransaction(int $id, int $companyId, int $userId): void
    {
        $existing = $this->txModel->find($id);
        if ($existing === null || (int) $existing['company_id'] !== $companyId) {
            throw new RuntimeException('Transaction not found.');
        }
        Database::transaction(function () use ($id, $existing, $companyId, $userId): void {
            $this->txModel->delete($id);
            $this->logger->log('delete', 'transaction', $id, "Archived transaction {$existing['transaction_code']}", $existing, null, $companyId, $userId);
        });
    }

    public function processDueRecurringExpenses(int $companyId, int $userId): int
    {
        $dueRows = Database::fetchAll(
            "SELECT * FROM transactions
             WHERE company_id = :cid AND deleted_at IS NULL AND type = 'expense'
               AND is_recurring = 1 AND next_recurring_date IS NOT NULL AND next_recurring_date <= CURDATE()",
            ['cid' => $companyId]
        );

        $count = 0;
        foreach ($dueRows as $row) {
            Database::transaction(function () use ($row, $companyId, $userId, &$count): void {
                $newCode = $this->txModel->generateNextCode('expense');
                $this->txModel->create([
                    'company_id' => $companyId,
                    'transaction_code' => $newCode,
                    'type' => 'expense',
                    'expense_category_id' => $row['expense_category_id'],
                    'employee_id' => $row['employee_id'],
                    'department_id' => $row['department_id'],
                    'original_amount' => $row['original_amount'],
                    'currency_code' => $row['currency_code'],
                    'exchange_rate' => $row['exchange_rate'],
                    'base_amount' => $row['base_amount'],
                    'transaction_date' => $row['next_recurring_date'],
                    'payment_method' => $row['payment_method'],
                    'status' => 'completed',
                    'vendor_or_client' => $row['vendor_or_client'],
                    'description' => '[Recurring] ' . ($row['description'] ?? ''),
                    'is_recurring' => 0,
                    'parent_transaction_id' => (int) $row['id'],
                    'created_by' => $userId,
                ]);

                $nextDate = $this->computeNextRecurringDate((string) $row['next_recurring_date'], (string) ($row['recurring_frequency'] ?? 'monthly'));
                $this->txModel->update((int) $row['id'], ['next_recurring_date' => $nextDate]);
                $this->logger->log('recurring_process', 'transaction', (int) $row['id'], "Generated recurring expense {$newCode} from {$row['transaction_code']}", null, ['new_code' => $newCode, 'next_date' => $nextDate], $companyId, $userId);
                $count++;
            });
        }

        return $count;
    }

    public function createCategory(string $kind, int $companyId, int $userId, string $name, string $color = '#6366f1', string $description = ''): int
    {
        $slug = strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '-', $name) ?: 'cat', '-')) . '-' . random_int(10, 99);
        if ($kind === 'income') {
            $id = $this->incCatModel->create([
                'company_id' => $companyId,
                'name' => $name,
                'slug' => $slug,
                'color' => $color,
                'description' => $description,
                'is_system' => 0,
            ]);
            $this->logger->log('create', 'income_category', $id, "Created income category '{$name}'", null, ['name' => $name], $companyId, $userId);
            return $id;
        }

        $id = $this->expCatModel->create([
            'company_id' => $companyId,
            'name' => $name,
            'slug' => $slug,
            'color' => $color,
            'description' => $description,
            'is_system' => 0,
        ]);
        $this->logger->log('create', 'expense_category', $id, "Created expense category '{$name}'", null, ['name' => $name], $companyId, $userId);
        return $id;
    }

    private function computeNextRecurringDate(string $baseDate, string $frequency): string
    {
        $offset = match ($frequency) {
            'weekly' => '+1 week',
            'quarterly' => '+3 months',
            'yearly' => '+1 year',
            default => '+1 month',
        };
        return date('Y-m-d', strtotime($offset, strtotime($baseDate)));
    }
}
