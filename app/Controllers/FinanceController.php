<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;
use App\Models\Department;
use App\Models\Employee;
use App\Models\ExchangeRate;
use App\Models\ExpenseCategory;
use App\Models\IncomeCategory;
use App\Models\Transaction;
use App\Services\CurrencyService;
use App\Services\FileUploadService;
use App\Services\FinanceService;
use Throwable;

final class FinanceController extends BaseController
{
    private FinanceService $financeService;
    private Transaction $txModel;

    public function __construct()
    {
        $this->financeService = new FinanceService();
        $this->txModel = new Transaction();
    }

    public function index(Request $request): string
    {
        $companyId = Auth::companyId();
        $tab = (string) $request->query('tab', 'overview');
        $filters = [
            'type' => $request->query('type') ?: ($tab === 'income' ? 'income' : ($tab === 'expenses' ? 'expense' : null)),
            'income_category_id' => $request->query('income_category_id'),
            'expense_category_id' => $request->query('expense_category_id'),
            'employee_id' => $request->query('employee_id'),
            'payment_method' => $request->query('payment_method'),
            'status' => $request->query('status'),
            'date_from' => $request->query('date_from'),
            'date_to' => $request->query('date_to'),
            'search' => $request->query('search'),
            'is_recurring' => $tab === 'recurring' ? 1 : null,
        ];

        $summary = $this->financeService->getSummary($companyId, $filters['date_from'] ?: null, $filters['date_to'] ?: null);
        $transactions = $this->txModel->filter($companyId, $filters);
        $incomeCategories = (new IncomeCategory())->forCompany($companyId);
        $expenseCategories = (new ExpenseCategory())->forCompany($companyId);
        $employees = (new Employee())->filter($companyId);
        $departments = (new Department())->forCompany($companyId);
        $currencies = (new CurrencyService())->getAllActive();
        $exchangeRates = (new ExchangeRate())->latestRates();

        return $this->render('finance/index', [
            'title' => __('finance.title'),
            'tab' => $tab,
            'filters' => $filters,
            'summary' => $summary,
            'transactions' => $transactions,
            'incomeCategories' => $incomeCategories,
            'expenseCategories' => $expenseCategories,
            'employees' => $employees,
            'departments' => $departments,
            'currencies' => $currencies,
            'exchangeRates' => $exchangeRates,
        ]);
    }

    public function storeTransaction(Request $request): void
    {
        $data = $request->all();
        $v = Validator::make($data, [
            'type' => 'required|in:income,expense',
            'original_amount' => 'required|numeric|min:0.01',
            'currency_code' => 'required',
            'transaction_date' => 'required|date',
            'payment_method' => 'required|in:cash,bank_transfer,card,check,online,other',
        ]);
        if ($v->fails()) {
            $this->redirect('/finance?tab=' . ($data['type'] === 'income' ? 'income' : 'expenses'), ['error' => $v->firstError()]);
        }

        try {
            $this->financeService->createTransaction(Auth::companyId(), (int) Auth::id(), $data, $request->file('attachment'));
            $this->redirect('/finance?tab=' . ($data['type'] === 'income' ? 'income' : 'expenses'), ['success' => __('messages.saved')]);
        } catch (Throwable $e) {
            $this->redirect('/finance', ['error' => $e->getMessage()]);
        }
    }

    public function updateTransaction(Request $request, string $id): void
    {
        $data = $request->all();
        try {
            $this->financeService->updateTransaction((int) $id, Auth::companyId(), (int) Auth::id(), $data, $request->file('attachment'));
            $this->redirect('/finance?tab=' . (($data['type'] ?? 'income') === 'income' ? 'income' : 'expenses'), ['success' => __('messages.saved')]);
        } catch (Throwable $e) {
            $this->redirect('/finance', ['error' => $e->getMessage()]);
        }
    }

    public function approveTransaction(Request $request, string $id): void
    {
        try {
            $this->financeService->approveTransaction((int) $id, Auth::companyId(), (int) Auth::id());
            $this->redirect('/finance?tab=expenses', ['success' => __('messages.saved')]);
        } catch (Throwable $e) {
            $this->redirect('/finance?tab=expenses', ['error' => $e->getMessage()]);
        }
    }

    public function deleteTransaction(Request $request, string $id): void
    {
        try {
            $this->financeService->deleteTransaction((int) $id, Auth::companyId(), (int) Auth::id());
            $this->redirect($_SERVER['HTTP_REFERER'] ?? '/finance', ['success' => __('messages.deleted')]);
        } catch (Throwable $e) {
            $this->redirect('/finance', ['error' => $e->getMessage()]);
        }
    }

    public function processRecurring(Request $request): void
    {
        $count = $this->financeService->processDueRecurringExpenses(Auth::companyId(), (int) Auth::id());
        $this->redirect('/finance?tab=recurring', ['success' => "Processed {$count} recurring expense(s)."]);
    }

    public function storeCategory(Request $request): void
    {
        $kind = (string) $request->input('kind', 'expense');
        $name = trim((string) $request->input('name', ''));
        if ($name === '') {
            $this->redirect('/finance?tab=categories', ['error' => 'Category name is required.']);
        }
        $this->financeService->createCategory(
            $kind === 'income' ? 'income' : 'expense',
            Auth::companyId(),
            (int) Auth::id(),
            $name,
            (string) $request->input('color', '#6366f1'),
            (string) $request->input('description', '')
        );
        $this->redirect('/finance?tab=categories', ['success' => __('messages.saved')]);
    }

    public function saveExchangeRate(Request $request): void
    {
        $from = strtoupper(trim((string) $request->input('from_currency', '')));
        $to = strtoupper(trim((string) $request->input('to_currency', '')));
        $rate = (float) $request->input('rate', 0);
        $date = (string) $request->input('effective_date', date('Y-m-d'));

        if ($from === '' || $to === '' || $rate <= 0) {
            $this->redirect('/finance?tab=currencies', ['error' => 'Valid currencies and positive exchange rate required.']);
        }

        (new CurrencyService())->saveRate($from, $to, $rate, $date, Auth::id());
        $this->redirect('/finance?tab=currencies', ['success' => __('messages.saved')]);
    }

    public function downloadAttachment(Request $request, string $id): void
    {
        $tx = $this->txModel->find((int) $id);
        if ($tx === null || (int) $tx['company_id'] !== Auth::companyId() || empty($tx['attachment_path'])) {
            Response::html('Attachment not found', 404);
        }
        $path = (new FileUploadService())->resolveSafePath((string) $tx['attachment_path']);
        if ($path === null) {
            Response::html('File missing on disk', 404);
        }
        Response::downloadFile($path, (string) ($tx['attachment_name'] ?: basename($path)));
    }
}
