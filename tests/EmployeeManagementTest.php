<?php

declare(strict_types=1);

namespace Tests;

use App\Core\Database;
use App\Services\EmployeeService;

final class EmployeeManagementTest extends TestCase
{
    public function run(): void
    {
        echo "\n=== EmployeeManagementTest ===\n";

        $svc = new EmployeeService();
        $empId = $svc->createEmployee(1, 1, [
            'full_name' => 'Test Engineer User',
            'position' => 'QA Automation Lead',
            'email' => 'qa.test@novatech.io',
            'phone' => '+998 90 123 45 67',
            'payment_type' => 'hourly',
            'hourly_rate' => 25.00,
            'base_salary' => 0,
            'currency_code' => 'USD',
        ]);

        $this->assertTrue($empId > 0, 'Creates new employee record');

        $svc->updateEmployee($empId, 1, 1, ['position' => 'Principal QA Lead', 'employment_status' => 'on_leave']);
        $profile = $svc->getProfileSections($empId, 1);

        $this->assertEquals('Principal QA Lead', $profile['employee']['position'], 'Updates employee position');
        $this->assertEquals('on_leave', $profile['employee']['employment_status'], 'Supports employment status updates');
        $this->assertTrue(array_key_exists('attendance', $profile) && array_key_exists('documents', $profile) && array_key_exists('payrolls', $profile), 'Loads all employee profile sections');

        // Soft delete check
        $svc->deleteEmployee($empId, 1, 1);
        $deletedAt = Database::fetchColumn('SELECT deleted_at FROM employees WHERE id = ?', [$empId]);
        $this->assertTrue(!empty($deletedAt), 'Soft deletes employee record instead of hard deleting');

        // Clean up test row
        Database::execute('DELETE FROM employees WHERE id = ?', [$empId]);
    }
}
