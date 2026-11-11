<?php

declare(strict_types=1);

namespace Tests;

use App\Core\Auth;
use App\Core\Translator;

final class AuthAndPermissionsTest extends TestCase
{
    public function run(): void
    {
        echo "\n=== AuthAndPermissionsTest ===\n";

        // 1. Valid & invalid password verification
        $badUser = Auth::attempt('admin@novatech.io', 'WrongPassword');
        $this->assertTrue($badUser === null, 'Rejects invalid login password');

        $admin = Auth::attempt('admin@novatech.io', 'Admin123!');
        $this->assertTrue($admin !== null && $admin['role_slug'] === 'super_admin', 'Authenticates Super Admin');
        $this->assertTrue(Auth::can('settings.permissions'), 'Super Admin has settings.permissions');
        $this->assertTrue(Auth::can('payroll.authorize_deductions'), 'Super Admin can authorize deductions');
        Auth::clearCache();

        // 2. Accountant permissions check
        $acc = Auth::attempt('accountant@novatech.io', 'Accountant123!');
        $this->assertTrue($acc !== null && $acc['role_slug'] === 'accountant', 'Authenticates Accountant');
        $this->assertTrue(Auth::can('finance.create'), 'Accountant can create finance records');
        $this->assertTrue(!Auth::can('settings.permissions'), 'Accountant cannot manage role permissions');
        $this->assertTrue(!Auth::can('employees.delete'), 'Accountant cannot delete employees');
        Auth::clearCache();

        // 3. Employee privacy isolation check
        $emp = Auth::attempt('employee@novatech.io', 'Employee123!');
        $this->assertTrue($emp !== null && $emp['role_slug'] === 'employee', 'Authenticates Employee');
        $this->assertTrue(Auth::can('employees.view_own'), 'Employee can view own profile');
        $this->assertTrue(!Auth::can('employees.view_all'), 'Employee cannot view all other employees');
        $this->assertTrue(!Auth::can('payroll.view_all'), 'Employee cannot view other employees payrolls');
        Auth::clearCache();

        // 4. Multilingual translations check
        Translator::setLocale('uz');
        $this->assertEquals('Moliya', __('nav.finance'), 'Uzbek translation resolves nav.finance');
        Translator::setLocale('ja');
        $this->assertEquals('財務管理', __('nav.finance'), 'Japanese translation resolves nav.finance');
        Translator::setLocale('en');
        $this->assertEquals('Finance', __('nav.finance'), 'English translation resolves nav.finance');
    }
}
