<?php

declare(strict_types=1);

namespace Tests;

use App\Core\Database;
use App\Services\CurrencyService;
use App\Services\FinanceService;

final class FinanceCalculationsTest extends TestCase
{
    public function run(): void
    {
        echo "\n=== FinanceCalculationsTest ===\n";

        $finance = new FinanceService();
        $curr = new CurrencyService();

        // 1. Multi-currency conversion test
        $conv = $curr->convert(1000.00, 'EUR', 'USD');
        $this->assertFloatEquals(1080.00, $conv['converted_amount'], 'EUR to USD currency conversion uses configured rate (1000 EUR -> 1080 USD)');

        // 2. Balance invariant: Net Balance = Total Income - Total Expenses
        $before = $finance->getSummary(1);
        $this->assertFloatEquals($before['total_income'] - $before['total_expenses'], $before['net_balance'], 'Initial Net Balance equals Income - Expenses');

        // 3. Create Income + Expense inside test and verify delta
        $incId = $finance->createTransaction(1, 1, [
            'type' => 'income',
            'original_amount' => 1000.00,
            'currency_code' => 'EUR', // converts to 1080.00 USD
            'transaction_date' => date('Y-m-d'),
            'payment_method' => 'bank_transfer',
            'status' => 'completed',
            'description' => 'Automated Test EUR Income',
        ]);
        $expId = $finance->createTransaction(1, 1, [
            'type' => 'expense',
            'original_amount' => 300.00,
            'currency_code' => 'USD',
            'transaction_date' => date('Y-m-d'),
            'payment_method' => 'card',
            'status' => 'completed',
            'description' => 'Automated Test Expense',
        ]);

        $after = $finance->getSummary(1);
        $this->assertFloatEquals($before['total_income'] + 1080.00, $after['total_income'], 'Total Income increases by converted EUR base amount');
        $this->assertFloatEquals($before['total_expenses'] + 300.00, $after['total_expenses'], 'Total Expenses increases by expense base amount');
        $this->assertFloatEquals($before['net_balance'] + 780.00, $after['net_balance'], 'Net Balance increases by exactly 1080 - 300 = 780 USD');

        // Clean up test transactions via hard delete so seeded demo totals stay clean
        Database::execute('DELETE FROM transactions WHERE id IN (?, ?)', [$incId, $expId]);
    }
}
