<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Core\Database;
use PDO;

/**
 * Seeds realistic company, RBAC, HR, multi-currency finance, payroll, tasks,
 * performance, report templates, and system settings data.
 */
final class DatabaseSeeder
{
    public function run(): void
    {
        $pdo = Database::pdo();
        $existingCompanies = (int) Database::fetchColumn('SELECT COUNT(*) FROM companies');
        if ($existingCompanies > 0) {
            echo "[seed] Database already contains data. Skipping (run migrate.php --fresh first to reset).\n";
            return;
        }

        Database::transaction(function (PDO $pdo): void {
            // 1. Company
            $companyId = Database::insert(
                "INSERT INTO companies (name, legal_name, email, phone, website, address, tax_id, tax_rate, default_currency_code, financial_year_start_month)
                 VALUES (:name, :legal_name, :email, :phone, :website, :address, :tax_id, :tax_rate, :curr, :fy)",
                [
                    'name' => 'NovaTech Solutions',
                    'legal_name' => 'NovaTech Solutions LLC',
                    'email' => 'contact@novatech.io',
                    'phone' => '+998 71 200 45 90',
                    'website' => 'https://novatech.io',
                    'address' => '42 Amir Temur Avenue, Tashkent 100000, Uzbekistan',
                    'tax_id' => 'TIN-309485712',
                    'tax_rate' => 12.00,
                    'curr' => 'USD',
                    'fy' => 1,
                ]
            );

            // 2. Currencies & Exchange Rates
            $currencies = [
                ['USD', 'US Dollar', '$', 2, 1],
                ['UZS', 'Uzbekistan Som', "so'm", 0, 0],
                ['EUR', 'Euro', '€', 2, 0],
                ['JPY', 'Japanese Yen', '¥', 0, 0],
                ['GBP', 'British Pound', '£', 2, 0],
            ];
            foreach ($currencies as [$code, $name, $sym, $dec, $isDefault]) {
                Database::execute(
                    'INSERT INTO currencies (code, name, symbol, decimal_places, is_default, is_active) VALUES (?, ?, ?, ?, ?, 1)',
                    [$code, $name, $sym, $dec, $isDefault]
                );
            }

            // 3. Roles & Permissions
            $roles = [
                ['Super Admin', 'super_admin', 'Full unrestricted system access'],
                ['Company Owner', 'company_owner', 'Executive financial overview, company settings, and permissions'],
                ['Administrator', 'administrator', 'Operational administration across modules'],
                ['Accountant', 'accountant', 'Finance, transactions, approvals, and financial reports'],
                ['HR Manager', 'hr_manager', 'Employees, attendance, payroll, bonuses, and performance'],
                ['Department Manager', 'department_manager', 'Department tasks, team performance, and department reports'],
                ['Employee', 'employee', 'Self-service profile, own payroll statements, and assigned tasks'],
            ];
            $roleIds = [];
            foreach ($roles as [$name, $slug, $desc]) {
                $roleIds[$slug] = Database::insert(
                    'INSERT INTO roles (name, slug, description, is_system) VALUES (?, ?, ?, 1)',
                    [$name, $slug, $desc]
                );
            }

            $permissions = [
                // Dashboard
                ['View Dashboard', 'dashboard.view', 'dashboard', 'Access main analytics dashboard'],
                // Finance
                ['View Finance', 'finance.view', 'finance', 'View income, expenses, and financial balance'],
                ['Create Finance', 'finance.create', 'finance', 'Create income and expense records'],
                ['Edit Finance', 'finance.edit', 'finance', 'Edit financial transactions and categories'],
                ['Delete Finance', 'finance.delete', 'finance', 'Soft delete financial transactions'],
                ['Approve Expenses', 'finance.approve', 'finance', 'Approve pending expense records'],
                ['Manage Currencies', 'finance.currencies', 'finance', 'Configure currencies and exchange rates'],
                // Employees
                ['View All Employees', 'employees.view_all', 'employees', 'View all employee profiles and private details'],
                ['View Own Profile', 'employees.view_own', 'employees', 'View own employee profile'],
                ['Create Employee', 'employees.create', 'employees', 'Create new employee records'],
                ['Edit Employee', 'employees.edit', 'employees', 'Update employee records and documents'],
                ['Delete Employee', 'employees.delete', 'employees', 'Soft delete employee records'],
                // Payroll
                ['View All Payroll', 'payroll.view_all', 'payroll', 'View and manage company payroll'],
                ['View Own Payroll', 'payroll.view_own', 'payroll', 'View own salary statements'],
                ['Calculate Payroll', 'payroll.calculate', 'payroll', 'Calculate and edit payroll components'],
                ['Authorize Deductions', 'payroll.authorize_deductions', 'payroll', 'Confirm and authorize salary deductions/penalties'],
                ['Pay Payroll', 'payroll.pay', 'payroll', 'Mark salaries as paid and post expense'],
                // Tasks & Performance
                ['View Tasks', 'tasks.view', 'tasks', 'View assigned or department tasks'],
                ['Manage Tasks', 'tasks.manage', 'tasks', 'Create, assign, edit, and complete tasks'],
                ['View Performance', 'performance.view', 'performance', 'View employee performance evaluations'],
                ['Manage Performance', 'performance.manage', 'performance', 'Evaluate performance and configure bonus rules'],
                // Reports
                ['View Reports', 'reports.view', 'reports', 'Access report builder and saved templates'],
                ['Export Reports', 'reports.export', 'reports', 'Export PDF, Excel, and CSV reports'],
                ['Manage Templates', 'reports.templates', 'reports', 'Create and save manual or automatic report templates'],
                // Settings & Security
                ['Manage Company Settings', 'settings.company', 'settings', 'Update company info, tax, and departments'],
                ['Manage Permissions', 'settings.permissions', 'settings', 'Edit role permissions and user accounts'],
                ['Personalize Workspace', 'settings.personalize', 'settings', 'Customize personal dashboard, theme, language, shortcuts'],
                ['View Activity Logs', 'logs.view', 'security', 'View audit trail and activity logs'],
                ['Manage Backups', 'backup.manage', 'security', 'Create and download database backups'],
            ];

            $permIds = [];
            foreach ($permissions as [$name, $slug, $module, $desc]) {
                $permIds[$slug] = Database::insert(
                    'INSERT INTO permissions (name, slug, module, description) VALUES (?, ?, ?, ?)',
                    [$name, $slug, $module, $desc]
                );
            }

            $rolePermMap = [
                'super_admin' => array_keys($permIds),
                'company_owner' => array_keys($permIds),
                'administrator' => [
                    'dashboard.view', 'finance.view', 'finance.create', 'finance.edit', 'finance.approve', 'finance.currencies',
                    'employees.view_all', 'employees.view_own', 'employees.create', 'employees.edit',
                    'payroll.view_all', 'payroll.view_own', 'payroll.calculate', 'payroll.authorize_deductions',
                    'tasks.view', 'tasks.manage', 'performance.view', 'performance.manage',
                    'reports.view', 'reports.export', 'reports.templates',
                    'settings.company', 'settings.personalize', 'logs.view', 'backup.manage',
                ],
                'accountant' => [
                    'dashboard.view', 'finance.view', 'finance.create', 'finance.edit', 'finance.approve', 'finance.currencies',
                    'employees.view_own', 'payroll.view_all', 'payroll.view_own', 'payroll.pay',
                    'reports.view', 'reports.export', 'reports.templates', 'settings.personalize',
                ],
                'hr_manager' => [
                    'dashboard.view', 'employees.view_all', 'employees.view_own', 'employees.create', 'employees.edit',
                    'payroll.view_all', 'payroll.view_own', 'payroll.calculate', 'payroll.authorize_deductions',
                    'tasks.view', 'tasks.manage', 'performance.view', 'performance.manage',
                    'reports.view', 'reports.export', 'settings.personalize',
                ],
                'department_manager' => [
                    'dashboard.view', 'employees.view_own', 'payroll.view_own',
                    'tasks.view', 'tasks.manage', 'performance.view', 'performance.manage',
                    'reports.view', 'reports.export', 'settings.personalize',
                ],
                'employee' => [
                    'dashboard.view', 'employees.view_own', 'payroll.view_own',
                    'tasks.view', 'performance.view', 'settings.personalize',
                ],
            ];

            foreach ($rolePermMap as $rSlug => $pSlugs) {
                $rId = $roleIds[$rSlug];
                foreach ($pSlugs as $pSlug) {
                    if (isset($permIds[$pSlug])) {
                        Database::execute(
                            'INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?)',
                            [$rId, $permIds[$pSlug]]
                        );
                    }
                }
            }

            // 4. Departments
            $departmentsData = [
                ['Engineering', 'ENG', 'Software architecture, backend, frontend, and QA', 35000.00],
                ['Finance & Accounting', 'FIN', 'Financial planning, bookkeeping, and compliance', 15000.00],
                ['Human Resources', 'HR', 'Talent acquisition, payroll, and people operations', 12000.00],
                ['Marketing & Sales', 'MKT', 'Growth, brand campaigns, and client acquisition', 20000.00],
                ['Operations', 'OPS', 'Facilities, IT infrastructure, and vendor management', 14000.00],
            ];
            $deptIds = [];
            foreach ($departmentsData as [$dName, $dCode, $dDesc, $dBudget]) {
                $deptIds[$dCode] = Database::insert(
                    'INSERT INTO departments (company_id, name, code, description, budget) VALUES (?, ?, ?, ?, ?)',
                    [$companyId, $dName, $dCode, $dDesc, $dBudget]
                );
            }

            // 5. Users
            $usersData = [
                ['super_admin', 'admin', 'admin@novatech.io', 'Admin123!', 'Azizbek Karimov'],
                ['company_owner', 'owner', 'owner@novatech.io', 'Owner123!', 'Shohruh Mirzayev'],
                ['accountant', 'accountant', 'accountant@novatech.io', 'Accountant123!', 'Malika Tursunova'],
                ['hr_manager', 'hr', 'hr@novatech.io', 'Hr123456!', 'Dilnoza Rahimova'],
                ['department_manager', 'manager', 'manager@novatech.io', 'Manager123!', 'Kenji Takahashi'],
                ['employee', 'employee', 'employee@novatech.io', 'Employee123!', 'Sardor Alimov'],
                ['employee', 'jamshid', 'jamshid@novatech.io', 'Employee123!', 'Jamshid Nazarov'],
                ['employee', 'ayako', 'ayako@novatech.io', 'Employee123!', 'Ayako Sato'],
            ];
            $userIds = [];
            foreach ($usersData as [$rSlug, $uname, $email, $pwd, $fullName]) {
                $userIds[$uname] = Database::insert(
                    'INSERT INTO users (company_id, role_id, username, email, password_hash, full_name, is_active) VALUES (?, ?, ?, ?, ?, ?, 1)',
                    [$companyId, $roleIds[$rSlug], $uname, $email, password_hash($pwd, PASSWORD_DEFAULT), $fullName]
                );
                Database::execute(
                    'INSERT INTO user_preferences (user_id, language, theme) VALUES (?, ?, ?)',
                    [$userIds[$uname], 'en', 'light']
                );
            }

            // 6. Exchange Rates (seeded after admin user exists)
            $rates = [
                ['UZS', 'USD', 0.00007813],
                ['USD', 'UZS', 12800.00000000],
                ['EUR', 'USD', 1.08000000],
                ['USD', 'EUR', 0.92592593],
                ['JPY', 'USD', 0.00657895],
                ['USD', 'JPY', 152.00000000],
                ['GBP', 'USD', 1.27000000],
                ['USD', 'GBP', 0.78740157],
            ];
            $today = date('Y-m-d');
            foreach ($rates as [$from, $to, $rate]) {
                Database::execute(
                    'INSERT INTO exchange_rates (from_currency, to_currency, rate, effective_date, created_by) VALUES (?, ?, ?, ?, ?)',
                    [$from, $to, $rate, $today, $userIds['admin']]
                );
            }

            // 7. Employees
            $employeesData = [
                [$userIds['admin'], $deptIds['OPS'], 'EMP-001', 'Azizbek Karimov', '1991-04-14', 'male', '+998 90 111 22 33', 'admin@novatech.io', 'Tashkent, Yunusabad 14', 'Chief Systems Administrator', '2022-01-10', 'active', 'full_time', 'fixed', 3200.00, 20.00, 150.00, 'USD'],
                [$userIds['owner'], $deptIds['ENG'], 'EMP-002', 'Shohruh Mirzayev', '1989-08-22', 'male', '+998 90 222 33 44', 'owner@novatech.io', 'Tashkent, Mirzo Ulugbek 7', 'Founder & CEO', '2021-03-01', 'active', 'full_time', 'fixed', 4800.00, 30.00, 220.00, 'USD'],
                [$userIds['accountant'], $deptIds['FIN'], 'EMP-003', 'Malika Tursunova', '1993-11-05', 'female', '+998 91 333 44 55', 'accountant@novatech.io', 'Tashkent, Chilonzor 9', 'Senior Accountant', '2022-06-15', 'active', 'full_time', 'fixed', 2400.00, 15.00, 110.00, 'USD'],
                [$userIds['hr'], $deptIds['HR'], 'EMP-004', 'Dilnoza Rahimova', '1994-02-19', 'female', '+998 93 444 55 66', 'hr@novatech.io', 'Tashkent, Shayxontohur 21', 'HR Manager', '2023-02-01', 'active', 'full_time', 'fixed', 2200.00, 14.00, 100.00, 'USD'],
                [$userIds['manager'], $deptIds['ENG'], 'EMP-005', 'Kenji Takahashi', '1990-07-03', 'male', '+998 94 555 66 77', 'manager@novatech.io', 'Tashkent, Yakkasaroy 18', 'Engineering Lead', '2022-09-01', 'active', 'full_time', 'fixed', 3400.00, 22.00, 160.00, 'USD'],
                [$userIds['employee'], $deptIds['ENG'], 'EMP-006', 'Sardor Alimov', '1997-05-12', 'male', '+998 97 666 77 88', 'employee@novatech.io', 'Tashkent, Sergeli 4', 'Full-Stack Developer', '2024-01-15', 'active', 'full_time', 'fixed', 1800.00, 12.00, 85.00, 'USD'],
                [$userIds['jamshid'], $deptIds['MKT'], 'EMP-007', 'Jamshid Nazarov', '1996-09-28', 'male', '+998 99 777 88 99', 'jamshid@novatech.io', 'Samarkand St 112, Tashkent', 'Growth Specialist', '2024-04-10', 'active', 'part_time', 'hourly', 0.00, 18.00, 0.00, 'USD'],
                [$userIds['ayako'], $deptIds['MKT'], 'EMP-008', 'Ayako Sato', '1995-12-10', 'female', '+998 90 888 99 00', 'ayako@novatech.io', 'Tashkent, Mirobod 33', 'UI/UX Contractor', '2024-07-01', 'on_leave', 'contract', 'task', 500.00, 16.00, 110.00, 'USD'],
            ];

            $empIds = [];
            foreach ($employeesData as $idx => $emp) {
                $empId = Database::insert(
                    "INSERT INTO employees (
                        company_id, user_id, department_id, employee_code, full_name, date_of_birth, gender,
                        phone, email, home_address, position, employment_date, employment_status,
                        contract_type, contract_start_date, payment_type, base_salary, hourly_rate, daily_rate,
                        currency_code, bank_name, bank_account_number, emergency_contact_name, emergency_contact_phone, emergency_contact_relation
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                    [
                        $companyId, $emp[0], $emp[1], $emp[2], $emp[3], $emp[4], $emp[5],
                        $emp[6], $emp[7], $emp[8], $emp[9], $emp[10], $emp[11],
                        $emp[12], $emp[10], $emp[13], $emp[14], $emp[15], $emp[16],
                        $emp[17], 'Kapitalbank', '4008 9000 0000 0' . ($idx + 101), 'Family Contact', '+998 90 000 11 0' . ($idx + 1), 'Spouse/Parent',
                    ]
                );
                $empIds[$emp[2]] = $empId;
            }

            // Link department managers
            Database::execute('UPDATE departments SET manager_employee_id = ? WHERE id = ?', [$empIds['EMP-005'], $deptIds['ENG']]);
            Database::execute('UPDATE departments SET manager_employee_id = ? WHERE id = ?', [$empIds['EMP-003'], $deptIds['FIN']]);
            Database::execute('UPDATE departments SET manager_employee_id = ? WHERE id = ?', [$empIds['EMP-004'], $deptIds['HR']]);

            // 8. Income & Expense Categories
            $incCats = [
                ['Client Retainers', 'client-retainers', '#10b981', 'Recurring monthly B2B client contracts', 1],
                ['Software Licensing', 'software-licensing', '#0ea5e9', 'SaaS subscriptions and product licenses', 1],
                ['Consulting Services', 'consulting-services', '#6366f1', 'Architecture, audit, and advisory projects', 1],
                ['Product Sales', 'product-sales', '#14b8a6', 'One-time software deliverables and add-ons', 0],
                ['Other Income', 'other-income', '#8b5cf6', 'Miscellaneous company revenue', 1],
            ];
            $incCatIds = [];
            foreach ($incCats as [$cName, $cSlug, $cColor, $cDesc, $isSys]) {
                $incCatIds[$cSlug] = Database::insert(
                    'INSERT INTO income_categories (company_id, name, slug, color, description, is_system) VALUES (?, ?, ?, ?, ?, ?)',
                    [$companyId, $cName, $cSlug, $cColor, $cDesc, $isSys]
                );
            }

            $expCats = [
                ['Office rent', 'office-rent', '#ef4444', 'Monthly office space and co-working leases', 1],
                ['Employee salaries', 'employee-salaries', '#f97316', 'Monthly payroll and salary disbursements', 1],
                ['Equipment', 'equipment', '#eab308', 'Laptops, monitors, servers, and office hardware', 1],
                ['Transportation', 'transportation', '#84cc16', 'Business travel, fuel, and logistics', 1],
                ['Marketing', 'marketing', '#ec4899', 'Paid ads, events, and promotional campaigns', 1],
                ['Utilities', 'utilities', '#06b6d4', 'Electricity, internet, water, and telecom', 1],
                ['Software and subscriptions', 'software-subscriptions', '#6366f1', 'Cloud hosting, SaaS licenses, and dev tools', 1],
                ['Taxes', 'taxes', '#64748b', 'Corporate taxes, social contributions, and fees', 1],
                ['Other expenses', 'other-expenses', '#94a3b8', 'Miscellaneous operational expenses', 1],
            ];
            $expCatIds = [];
            foreach ($expCats as [$cName, $cSlug, $cColor, $cDesc, $isSys]) {
                $expCatIds[$cSlug] = Database::insert(
                    'INSERT INTO expense_categories (company_id, name, slug, color, description, is_system) VALUES (?, ?, ?, ?, ?, ?)',
                    [$companyId, $cName, $cSlug, $cColor, $cDesc, $isSys]
                );
            }

            // 9. Transactions across the last 6 months (including current month)
            $txnCounter = 1;
            for ($m = 5; $m >= 0; $m--) {
                $monthDate = date('Y-m', strtotime("-{$m} months"));
                $d05 = $monthDate . '-05';
                $d10 = $monthDate . '-10';
                $d14 = $monthDate . '-14';
                $d18 = $monthDate . '-18';
                $d22 = $monthDate . '-22';
                $d25 = $monthDate . '-25';

                $retainerAmt = 24500 + (5 - $m) * 1400;
                $consultingAmt = 8200 + (($m % 3) * 1800);
                $licenseEur = 4200 + (5 - $m) * 250;

                // Income 1: USD Client Retainer
                Database::execute(
                    "INSERT INTO transactions (company_id, transaction_code, type, income_category_id, employee_id, department_id, original_amount, currency_code, exchange_rate, base_amount, transaction_date, payment_method, status, vendor_or_client, description, created_by)
                     VALUES (?, ?, 'income', ?, ?, ?, ?, 'USD', 1.00000000, ?, ?, 'bank_transfer', 'completed', 'Apex Global Corp', 'Monthly enterprise SaaS retainer', ?)",
                    [$companyId, sprintf('INC-%s-%04d', date('Y'), $txnCounter++), $incCatIds['client-retainers'], $empIds['EMP-002'], $deptIds['MKT'], $retainerAmt, $retainerAmt, $d05, $userIds['accountant']]
                );

                // Income 2: EUR Software Licensing (multi-currency!)
                $licenseUsd = round($licenseEur * 1.08, 2);
                Database::execute(
                    "INSERT INTO transactions (company_id, transaction_code, type, income_category_id, employee_id, department_id, original_amount, currency_code, exchange_rate, base_amount, transaction_date, payment_method, status, vendor_or_client, description, created_by)
                     VALUES (?, ?, 'income', ?, ?, ?, ?, 'EUR', 1.08000000, ?, ?, 'online', 'completed', 'EuroSoft GmbH', 'Annual/Monthly EU platform licensing', ?)",
                    [$companyId, sprintf('INC-%s-%04d', date('Y'), $txnCounter++), $incCatIds['software-licensing'], $empIds['EMP-007'], $deptIds['MKT'], $licenseEur, $licenseUsd, $d14, $userIds['accountant']]
                );

                // Income 3: Consulting
                Database::execute(
                    "INSERT INTO transactions (company_id, transaction_code, type, income_category_id, employee_id, department_id, original_amount, currency_code, exchange_rate, base_amount, transaction_date, payment_method, status, vendor_or_client, description, created_by)
                     VALUES (?, ?, 'income', ?, ?, ?, ?, 'USD', 1.00000000, ?, ?, 'bank_transfer', 'completed', 'SilkRoad Logistics', 'Custom ERP integration milestone', ?)",
                    [$companyId, sprintf('INC-%s-%04d', date('Y'), $txnCounter++), $incCatIds['consulting-services'], $empIds['EMP-005'], $deptIds['ENG'], $consultingAmt, $consultingAmt, $d22, $userIds['accountant']]
                );

                // Expense 1: Office Rent (Recurring)
                $isLatest = ($m === 0) ? 1 : 0;
                $nextRec = date('Y-m-d', strtotime('+1 month', strtotime($d05)));
                Database::execute(
                    "INSERT INTO transactions (company_id, transaction_code, type, expense_category_id, employee_id, department_id, original_amount, currency_code, exchange_rate, base_amount, transaction_date, payment_method, status, vendor_or_client, description, is_recurring, recurring_frequency, next_recurring_date, created_by)
                     VALUES (?, ?, 'expense', ?, ?, ?, 2800.00, 'USD', 1.00000000, 2800.00, ?, 'bank_transfer', 'completed', 'Tashkent Business Center', 'Headquarters office lease', ?, ?, ?, ?)",
                    [$companyId, sprintf('EXP-%s-%04d', date('Y'), $txnCounter++), $expCatIds['office-rent'], $empIds['EMP-001'], $deptIds['OPS'], $d05, $isLatest, $isLatest ? 'monthly' : null, $isLatest ? $nextRec : null, $userIds['accountant']]
                );

                // Expense 2: Software & Subscriptions (Recurring)
                Database::execute(
                    "INSERT INTO transactions (company_id, transaction_code, type, expense_category_id, employee_id, department_id, original_amount, currency_code, exchange_rate, base_amount, transaction_date, payment_method, status, vendor_or_client, description, is_recurring, recurring_frequency, next_recurring_date, created_by)
                     VALUES (?, ?, 'expense', ?, ?, ?, 1150.00, 'USD', 1.00000000, 1150.00, ?, 'card', 'completed', 'AWS & GitHub Enterprise', 'Cloud servers and CI/CD subscriptions', ?, ?, ?, ?)",
                    [$companyId, sprintf('EXP-%s-%04d', date('Y'), $txnCounter++), $expCatIds['software-subscriptions'], $empIds['EMP-005'], $deptIds['ENG'], $d10, $isLatest, $isLatest ? 'monthly' : null, $isLatest ? date('Y-m-d', strtotime('+1 month', strtotime($d10))) : null, $userIds['accountant']]
                );

                // Expense 3: Marketing
                $mktAmt = 1450 + ($m * 120);
                Database::execute(
                    "INSERT INTO transactions (company_id, transaction_code, type, expense_category_id, employee_id, department_id, original_amount, currency_code, exchange_rate, base_amount, transaction_date, payment_method, status, vendor_or_client, description, created_by)
                     VALUES (?, ?, 'expense', ?, ?, ?, ?, 'USD', 1.00000000, ?, ?, 'card', 'completed', 'LinkedIn & Google Ads', 'B2B lead generation campaign', ?)",
                    [$companyId, sprintf('EXP-%s-%04d', date('Y'), $txnCounter++), $expCatIds['marketing'], $empIds['EMP-007'], $deptIds['MKT'], $mktAmt, $mktAmt, $d18, $userIds['accountant']]
                );

                // Expense 4: Utilities in UZS (multi-currency!)
                $uzsAmt = 6400000;
                $uzsToUsd = round($uzsAmt * 0.00007813, 2); // $500.03
                Database::execute(
                    "INSERT INTO transactions (company_id, transaction_code, type, expense_category_id, employee_id, department_id, original_amount, currency_code, exchange_rate, base_amount, transaction_date, payment_method, status, vendor_or_client, description, created_by)
                     VALUES (?, ?, 'expense', ?, ?, ?, ?, 'UZS', 0.00007813, ?, ?, 'bank_transfer', 'completed', 'Uzbektelekom & Power Grid', 'Fiber internet & electricity utilities', ?)",
                    [$companyId, sprintf('EXP-%s-%04d', date('Y'), $txnCounter++), $expCatIds['utilities'], $empIds['EMP-001'], $deptIds['OPS'], $uzsAmt, $uzsToUsd, $d22, $userIds['accountant']]
                );

                // Expense 5: Taxes
                Database::execute(
                    "INSERT INTO transactions (company_id, transaction_code, type, expense_category_id, employee_id, department_id, original_amount, currency_code, exchange_rate, base_amount, transaction_date, payment_method, status, vendor_or_client, description, created_by)
                     VALUES (?, ?, 'expense', ?, ?, ?, 2100.00, 'USD', 1.00000000, 2100.00, ?, 'bank_transfer', 'completed', 'State Tax Committee', 'Monthly corporate & payroll tax remittance', ?)",
                    [$companyId, sprintf('EXP-%s-%04d', date('Y'), $txnCounter++), $expCatIds['taxes'], $empIds['EMP-003'], $deptIds['FIN'], $d25, $userIds['accountant']]
                );
            }

            // Add 2 pending expense approvals + 1 equipment expense this month
            Database::execute(
                "INSERT INTO transactions (company_id, transaction_code, type, expense_category_id, employee_id, department_id, original_amount, currency_code, exchange_rate, base_amount, transaction_date, due_date, payment_method, status, vendor_or_client, description, created_by)
                 VALUES (?, ?, 'expense', ?, ?, ?, 2450.00, 'USD', 1.00000000, 2450.00, ?, ?, 'bank_transfer', 'pending', 'Dell Enterprise Partner', '2x Developer workstations (awaiting approval)', ?)",
                [$companyId, sprintf('EXP-%s-%04d', date('Y'), $txnCounter++), $expCatIds['equipment'], $empIds['EMP-005'], $deptIds['ENG'], date('Y-m-d'), date('Y-m-d', strtotime('+5 days')), $userIds['manager']]
            );
            Database::execute(
                "INSERT INTO transactions (company_id, transaction_code, type, expense_category_id, employee_id, department_id, original_amount, currency_code, exchange_rate, base_amount, transaction_date, due_date, payment_method, status, vendor_or_client, description, created_by)
                 VALUES (?, ?, 'expense', ?, ?, ?, 380.00, 'USD', 1.00000000, 380.00, ?, ?, 'card', 'pending', 'UzAirways Corporate', 'Client onsite transport reimbursement', ?)",
                [$companyId, sprintf('EXP-%s-%04d', date('Y'), $txnCounter++), $expCatIds['transportation'], $empIds['EMP-007'], $deptIds['MKT'], date('Y-m-d'), date('Y-m-d', strtotime('+3 days')), $userIds['accountant']]
            );

            // 10. Tasks & Task Assignments
            $tasksData = [
                ['TSK-101', $deptIds['ENG'], 'Implement FastAPI Cashflow Forecast', 'Build Pandas time-series revenue projection service', 'high', 'completed', 1, 'Analytics v2', 100.00, date('Y-m-d', strtotime('-10 days')), date('Y-m-d H:i:s', strtotime('-9 days')), 100, $empIds['EMP-006']],
                ['TSK-102', $deptIds['ENG'], 'Database Indexing & PDO Optimization', 'Optimize payroll queries and add composite indexes', 'medium', 'completed', 0, null, 75.00, date('Y-m-d', strtotime('-5 days')), date('Y-m-d H:i:s', strtotime('-4 days')), 100, $empIds['EMP-006']],
                ['TSK-103', $deptIds['MKT'], 'Q4 Enterprise Outreach Deck', 'Prepare bilingual presentation and ROI calculator', 'high', 'completed', 1, 'Enterprise Q4', 150.00, date('Y-m-d', strtotime('-3 days')), date('Y-m-d H:i:s', strtotime('-2 days')), 100, $empIds['EMP-008']],
                ['TSK-104', $deptIds['ENG'], 'Multi-Currency Reconciliation Audit', 'Verify UZS/EUR exchange rate precision across ledgers', 'urgent', 'in_progress', 0, null, 120.00, date('Y-m-d', strtotime('+2 days')), null, 65, $empIds['EMP-005']],
                ['TSK-105', $deptIds['HR'], 'Annual Employee Contract Renewal', 'Review and update 2026 employment contracts', 'medium', 'pending', 0, null, 60.00, date('Y-m-d', strtotime('+6 days')), null, 15, $empIds['EMP-004']],
                ['TSK-106', $deptIds['MKT'], 'Partner Portal UI Prototype', 'Design onboarding flow for partner accounts', 'medium', 'in_progress', 1, 'Partner Portal', 200.00, date('Y-m-d', strtotime('+4 days')), null, 50, $empIds['EMP-007']],
            ];
            foreach ($tasksData as $t) {
                $taskId = Database::insert(
                    "INSERT INTO tasks (company_id, department_id, task_code, title, description, priority, status, is_project_task, project_name, reward_amount, currency_code, deadline, completed_at, progress_percent, created_by)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'USD', ?, ?, ?, ?)",
                    [$companyId, $t[1], $t[0], $t[2], $t[3], $t[4], $t[5], $t[6], $t[7], $t[8], $t[9], $t[10], $t[11], $userIds['manager']]
                );
                Database::execute(
                    "INSERT INTO task_assignments (task_id, employee_id, assigned_by, completion_status) VALUES (?, ?, ?, ?)",
                    [$taskId, $t[12], $userIds['manager'], $t[5] === 'completed' ? 'verified' : ($t[5] === 'in_progress' ? 'working' : 'assigned')]
                );
            }

            // 11. Attendance (last 10 weekdays for active employees)
            $allEmpList = array_values($empIds);
            for ($d = 12; $d >= 1; $d--) {
                $dayStr = date('Y-m-d', strtotime("-{$d} days"));
                $dow = (int) date('N', strtotime($dayStr));
                if ($dow >= 6) {
                    continue;
                }
                foreach ($allEmpList as $eIdx => $eId) {
                    $status = ($eIdx === 7 && $d <= 3) ? 'on_leave' : (($eIdx === 5 && $d === 4) ? 'late' : 'present');
                    $worked = $status === 'on_leave' ? 0.00 : ($eIdx === 6 ? 5.00 : 8.00);
                    $overtime = ($eIdx === 5 && $d <= 3) ? 1.50 : 0.00;
                    Database::execute(
                        "INSERT INTO attendance (company_id, employee_id, attendance_date, status, check_in, check_out, worked_hours, overtime_hours, recorded_by)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
                        [$companyId, $eId, $dayStr, $status, '09:00:00', $overtime > 0 ? '18:30:00' : '18:00:00', $worked, $overtime, $userIds['hr']]
                    );
                }
            }

            // 12. Historical Paid Payrolls (previous month) + Current Month Pending Payrolls
            $prevMonthTs = strtotime('-1 month');
            $prevYear = (int) date('Y', $prevMonthTs);
            $prevMonth = (int) date('n', $prevMonthTs);
            $prevStart = date('Y-m-01', $prevMonthTs);
            $prevEnd = date('Y-m-t', $prevMonthTs);
            $prevPayDate = date('Y-m-28', $prevMonthTs);

            $currYear = (int) date('Y');
            $currMonth = (int) date('n');
            $currStart = date('Y-m-01');
            $currEnd = date('Y-m-t');

            $empRows = Database::fetchAll('SELECT * FROM employees WHERE company_id = ? ORDER BY id ASC', [$companyId]);
            $payIdx = 1;
            foreach ($empRows as $empRow) {
                $base = match ($empRow['payment_type']) {
                    'hourly' => round((float) $empRow['hourly_rate'] * 88, 2),
                    'daily' => round((float) $empRow['daily_rate'] * 22, 2),
                    default => (float) $empRow['base_salary'],
                };
                $pBonus = $empRow['employee_code'] === 'EMP-006' ? 150.00 : 100.00;
                $tBonus = $empRow['employee_code'] === 'EMP-006' ? 100.00 : 0.00;
                $gross = $base + $pBonus + $tBonus;
                $ded = 0.00;
                $net = $gross - $ded;

                // Create paid payroll expense transaction for previous month
                $txCode = sprintf('EXP-%d-%04d', $prevYear, $txnCounter++);
                $txId = Database::insert(
                    "INSERT INTO transactions (company_id, transaction_code, type, expense_category_id, employee_id, department_id, original_amount, currency_code, exchange_rate, base_amount, transaction_date, payment_method, status, vendor_or_client, description, created_by)
                     VALUES (?, ?, 'expense', ?, ?, ?, ?, 'USD', 1.00000000, ?, ?, 'bank_transfer', 'completed', ?, ?, ?)",
                    [$companyId, $txCode, $expCatIds['employee-salaries'], $empRow['id'], $empRow['department_id'], $net, $net, $prevPayDate, $empRow['full_name'], "Salary payment {$prevYear}-" . str_pad((string) $prevMonth, 2, '0', STR_PAD_LEFT), $userIds['accountant']]
                );

                $prId = Database::insert(
                    "INSERT INTO payrolls (company_id, employee_id, payroll_code, period_year, period_month, period_start, period_end, payment_type, base_salary, working_days, worked_days, working_hours, overtime_hours, overtime_pay, completed_tasks_count, task_bonus, performance_bonus, other_bonuses, authorized_deductions, adjustments, gross_salary, net_salary, currency_code, status, payment_date, payment_method, transaction_id, calculated_by, paid_by)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 22, 22, 176, 0, 0, ?, ?, ?, 0, 0, 0, ?, ?, 'USD', 'paid', ?, 'bank_transfer', ?, ?, ?)",
                    [$companyId, $empRow['id'], sprintf('PAY-%d%02d-%03d', $prevYear, $prevMonth, $payIdx), $prevYear, $prevMonth, $prevStart, $prevEnd, $empRow['payment_type'], $base, $tBonus > 0 ? 1 : 0, $tBonus, $pBonus, $gross, $net, $prevPayDate, $txId, $userIds['hr'], $userIds['accountant']]
                );
                Database::execute('UPDATE transactions SET payroll_id = ? WHERE id = ?', [$prId, $txId]);

                Database::execute(
                    "INSERT INTO salary_components (payroll_id, component_type, code, label, quantity, rate, amount, is_authorized, authorized_by) VALUES (?, 'earning', 'BASE', 'Base Salary', 1, ?, ?, 1, ?)",
                    [$prId, $base, $base, $userIds['hr']]
                );
                if ($pBonus > 0) {
                    Database::execute(
                        "INSERT INTO salary_components (payroll_id, component_type, code, label, quantity, rate, amount, is_authorized, authorized_by) VALUES (?, 'bonus', 'PERF', 'Performance Bonus', 1, ?, ?, 1, ?)",
                        [$prId, $pBonus, $pBonus, $userIds['hr']]
                    );
                }

                // Current month pending payroll (so upcoming salary payments show immediately)
                $currOvertimePay = $empRow['employee_code'] === 'EMP-006' ? 60.00 : 0.00;
                $currTaskBonus = $empRow['employee_code'] === 'EMP-006' ? 175.00 : ($empRow['employee_code'] === 'EMP-008' ? 150.00 : 0.00);
                $currGross = $base + $currOvertimePay + $currTaskBonus;
                $currNet = $currGross;
                $pendingPrId = Database::insert(
                    "INSERT INTO payrolls (company_id, employee_id, payroll_code, period_year, period_month, period_start, period_end, payment_type, base_salary, working_days, worked_days, working_hours, overtime_hours, overtime_pay, completed_tasks_count, task_bonus, performance_bonus, other_bonuses, authorized_deductions, adjustments, gross_salary, net_salary, currency_code, status, payment_date, payment_method, calculated_by)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 22, 20, 160, ?, ?, ?, ?, 0, 0, 0, 0, ?, ?, 'USD', 'pending', ?, 'bank_transfer', ?)",
                    [$companyId, $empRow['id'], sprintf('PAY-%d%02d-%03d', $currYear, $currMonth, $payIdx), $currYear, $currMonth, $currStart, $currEnd, $empRow['payment_type'], $base, $currOvertimePay > 0 ? 4 : 0, $currOvertimePay, $currTaskBonus > 0 ? 2 : 0, $currTaskBonus, $currGross, $currNet, date('Y-m-t'), $userIds['hr']]
                );
                Database::execute(
                    "INSERT INTO salary_components (payroll_id, component_type, code, label, quantity, rate, amount, is_authorized, authorized_by) VALUES (?, 'earning', 'BASE', 'Base Salary', 1, ?, ?, 1, ?)",
                    [$pendingPrId, $base, $base, $userIds['hr']]
                );
                $payIdx++;
            }

            // Sample Bonuses & Deductions (including 1 unauthorized deduction awaiting HR confirmation!)
            Database::execute(
                "INSERT INTO bonuses (company_id, employee_id, bonus_type, title, amount, currency_code, awarded_date, status, approved_by, created_by)
                 VALUES (?, ?, 'performance', 'Q4 Delivery Excellence', 150.00, 'USD', ?, 'approved', ?, ?)",
                [$companyId, $empIds['EMP-006'], date('Y-m-d'), $userIds['hr'], $userIds['manager']]
            );
            Database::execute(
                "INSERT INTO deductions (company_id, employee_id, deduction_type, title, amount, currency_code, deduction_date, is_authorized, authorized_by, authorized_at, status, notes, created_by)
                 VALUES (?, ?, 'advance', 'Salary Advance Repayment', 100.00, 'USD', ?, 1, ?, NOW(), 'authorized', 'Signed advance agreement', ?)",
                [$companyId, $empIds['EMP-007'], date('Y-m-d'), $userIds['hr'], $userIds['accountant']]
            );
            Database::execute(
                "INSERT INTO deductions (company_id, employee_id, deduction_type, title, amount, currency_code, deduction_date, is_authorized, status, notes, created_by)
                 VALUES (?, ?, 'penalty', 'Late Arrival Review (Unconfirmed)', 25.00, 'USD', ?, 0, 'pending_confirmation', 'Requires HR confirmation before deduction applies', ?)",
                [$companyId, $empIds['EMP-006'], date('Y-m-d'), $userIds['manager']]
            );

            // 13. Performance Records
            $perfSample = [
                [$empIds['EMP-005'], 8, 7, 7, 98.5, 92.0, 93.4, 250.00],
                [$empIds['EMP-006'], 10, 9, 8, 95.0, 90.0, 89.5, 150.00],
                [$empIds['EMP-007'], 6, 5, 5, 92.0, 86.0, 86.4, 100.00],
                [$empIds['EMP-008'], 5, 5, 5, 100.0, 94.0, 96.4, 180.00],
            ];
            foreach ($perfSample as [$eId, $ta, $tc, $tot, $att, $mgr, $score, $bonus]) {
                Database::execute(
                    "INSERT INTO performance_records (company_id, employee_id, period_label, period_start, period_end, tasks_assigned, tasks_completed, tasks_on_time, attendance_rate, manager_rating, computed_score, recommended_bonus, approved_bonus, evaluation_rule_snapshot, feedback, evaluated_by)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                    [
                        $companyId, $eId, date('Y-m'), date('Y-m-01'), date('Y-m-t'),
                        $ta, $tc, $tot, $att, $mgr, $score, $bonus, $bonus,
                        json_encode(['task_completion_weight' => 40, 'on_time_weight' => 25, 'attendance_weight' => 15, 'manager_weight' => 20]),
                        'Consistent execution and high reliability.',
                        $userIds['hr'],
                    ]
                );
            }

            // 14. Report Templates (Automatic + Manual)
            Database::execute(
                "INSERT INTO report_templates (company_id, name, description, report_type, creation_method, config_json, is_shared, created_by)
                 VALUES (?, ?, ?, ?, 'automatic', ?, 1, ?)",
                [
                    $companyId,
                    'Monthly Executive P&L Summary',
                    'Automatic financial report comparing income, expenses, and category breakdowns.',
                    'complete_financial',
                    json_encode([
                        'report_type' => 'complete_financial',
                        'columns' => ['transaction_code', 'type', 'category_name', 'transaction_date', 'payment_method', 'base_amount', 'status'],
                        'sort_by' => 'transaction_date',
                        'sort_dir' => 'DESC',
                    ]),
                    $userIds['accountant'],
                ]
            );
            Database::execute(
                "INSERT INTO report_templates (company_id, name, description, report_type, creation_method, config_json, is_shared, created_by)
                 VALUES (?, ?, ?, ?, 'manual', ?, 1, ?)",
                [
                    $companyId,
                    'Board Budget Forecast Sheet (Manual Template)',
                    'Manual customizable report template with formulas and executive commentary.',
                    'custom',
                    json_encode([
                        'title' => 'Quarterly Department Budget vs Actual',
                        'subtitle' => 'Prepared for Executive Board Review',
                        'include_logo' => true,
                        'notes' => 'All amounts are expressed in USD base currency. Engineering expansion includes FastAPI microservice launch.',
                        'columns' => ['Department', 'Allocated Budget', 'Actual Spend', 'Variance'],
                        'rows' => [
                            ['Engineering', '35000', '29400', '=B1-C1'],
                            ['Marketing & Sales', '20000', '16200', '=B2-C2'],
                            ['Operations', '14000', '11800', '=B3-C3'],
                        ],
                        'show_totals_row' => true,
                    ]),
                    $userIds['owner'],
                ]
            );

            // 15. Application Settings (Performance Rules & System Defaults)
            $appSettings = [
                ['perf_weight_task_completion', '40', 'float', 'performance'],
                ['perf_weight_on_time', '25', 'float', 'performance'],
                ['perf_weight_attendance', '15', 'float', 'performance'],
                ['perf_weight_manager_rating', '20', 'float', 'performance'],
                ['perf_bonus_tier_gold_min', '90', 'float', 'performance'],
                ['perf_bonus_tier_gold_percent', '15', 'float', 'performance'],
                ['perf_bonus_tier_silver_min', '75', 'float', 'performance'],
                ['perf_bonus_tier_silver_percent', '8', 'float', 'performance'],
                ['task_completion_auto_bonus_enabled', '1', 'boolean', 'performance'],
                ['payroll_default_working_days', '22', 'integer', 'payroll'],
                ['payroll_overtime_multiplier', '1.5', 'float', 'payroll'],
                ['payroll_auto_post_expense_on_pay', '1', 'boolean', 'payroll'],
            ];
            foreach ($appSettings as [$key, $val, $type, $group]) {
                Database::execute(
                    'INSERT INTO application_settings (company_id, setting_key, setting_value, setting_type, group_name, updated_by) VALUES (?, ?, ?, ?, ?, ?)',
                    [$companyId, $key, $val, $type, $group, $userIds['admin']]
                );
            }

            // 16. Notifications & Activity Logs
            Database::execute(
                "INSERT INTO notifications (company_id, user_id, type, title, message, link_url, severity) VALUES
                 (?, ?, 'expense_pending', '2 Expenses Awaiting Approval', 'Hardware workstation ($2,450) and travel ($380) requests need review.', '/finance?tab=expenses', 'warning'),
                 (?, ?, 'salary_upcoming', 'Monthly Payroll Ready for Review', 'Current month salaries are calculated and awaiting payment confirmation.', '/payroll', 'info'),
                 (?, ?, 'task_deadline', 'Task Deadline Approaching', 'TSK-104 Multi-Currency Reconciliation Audit is due in 2 days.', '/tasks', 'warning'),
                 (?, ?, 'system', 'Deduction Confirmation Required', '1 salary deduction is pending HR confirmation before payroll application.', '/payroll', 'danger')",
                [$companyId, $userIds['admin'], $companyId, $userIds['admin'], $companyId, $userIds['admin'], $companyId, $userIds['admin']]
            );

            Database::execute(
                "INSERT INTO activity_logs (company_id, user_id, action, entity_type, entity_id, description, ip_address)
                 VALUES (?, ?, 'seed', 'system', ?, 'Initial demo database seeded with company, RBAC, finance, HR, payroll, and reports data', '127.0.0.1')",
                [$companyId, $userIds['admin'], $companyId]
            );
        });

        echo "[seed] Complete! Demo data successfully populated.\n";
    }
}
