#!/usr/bin/env php
<?php
/**
 * Test Account Verification Script
 * 
 * Verifies that all test accounts from seeders exist in the database
 * and have the correct roles and employee links.
 */

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Domains\HR\Models\Employee;
use App\Models\User;
use Spatie\Permission\Models\Role;

$exitCode = 0;

echo "========================================\n";
echo "Ogami ERP - Test Account Verification\n";
echo "========================================\n\n";

// Define all expected test accounts with their employee codes
$expectedAccounts = [
    // HR Department
    ['email' => 'hr.manager@ogamierp.local', 'role' => 'manager', 'employee_code' => 'EMP-HR-001'],
    ['email' => 'ga.officer@ogamierp.local', 'role' => 'ga_officer', 'employee_code' => 'EMP-HR-002'],
    ['email' => 'hr.head@ogamierp.local', 'role' => 'head', 'employee_code' => 'EMP-HR-003'],
    ['email' => 'hr.staff@ogamierp.local', 'role' => 'staff', 'employee_code' => 'EMP-HR-004'],
    
    // ACCTG Department
    ['email' => 'acctg.manager@ogamierp.local', 'role' => 'officer', 'employee_code' => 'EMP-ACCT-001'],
    ['email' => 'acctg.officer@ogamierp.local', 'role' => 'officer', 'employee_code' => 'EMP-ACCT-002'],
    ['email' => 'acctg.head@ogamierp.local', 'role' => 'head', 'employee_code' => 'EMP-ACCT-003'],
    ['email' => 'acctg.staff@ogamierp.local', 'role' => 'staff', 'employee_code' => 'EMP-ACCT-004'],
    
    // PROD Department
    ['email' => 'prod.manager@ogamierp.local', 'role' => 'production_manager', 'employee_code' => 'EMP-PROD-001'],
    ['email' => 'prod.head@ogamierp.local', 'role' => 'head', 'employee_code' => 'EMP-PROD-002'],
    ['email' => 'prod.staff@ogamierp.local', 'role' => 'staff', 'employee_code' => 'EMP-PROD-003'],
    
    // QC Department
    ['email' => 'qc.manager@ogamierp.local', 'role' => 'qc_manager', 'employee_code' => 'EMP-QC-001'],
    ['email' => 'qc.head@ogamierp.local', 'role' => 'head', 'employee_code' => 'EMP-QC-002'],
    ['email' => 'qc.staff@ogamierp.local', 'role' => 'staff', 'employee_code' => 'EMP-QC-003'],
    
    // MOLD Department
    ['email' => 'mold.manager@ogamierp.local', 'role' => 'mold_manager', 'employee_code' => 'EMP-MOLD-001'],
    ['email' => 'mold.head@ogamierp.local', 'role' => 'head', 'employee_code' => 'EMP-MOLD-002'],
    ['email' => 'mold.staff@ogamierp.local', 'role' => 'staff', 'employee_code' => 'EMP-MOLD-003'],
    
    // PLANT Department
    ['email' => 'plant.manager@ogamierp.local', 'role' => 'plant_manager', 'employee_code' => 'EMP-PLANT-001'],
    ['email' => 'plant.head@ogamierp.local', 'role' => 'head', 'employee_code' => 'EMP-PLANT-002'],
    
    // SALES Department
    ['email' => 'crm.manager@ogamierp.local', 'role' => 'crm_manager', 'employee_code' => 'EMP-SALES-001'],
    ['email' => 'sales.staff@ogamierp.local', 'role' => 'staff', 'employee_code' => 'EMP-SALES-002'],
    
    // IT Department
    ['email' => 'it.admin@ogamierp.local', 'role' => 'admin', 'employee_code' => 'EMP-IT-001'],
    
    // EXEC Department
    ['email' => 'executive@ogamierp.local', 'role' => 'executive', 'employee_code' => 'EMP-EXEC-001'],
    ['email' => 'vp@ogamierp.local', 'role' => 'vice_president', 'employee_code' => 'EMP-EXEC-002'],
    
    // External Portals (no employee record)
    ['email' => 'vendor@ogamierp.local', 'role' => 'vendor', 'employee_code' => null],
    ['email' => 'client@ogamierp.local', 'role' => 'client', 'employee_code' => null],
];

$verified = 0;
$missing = 0;
$wrongRole = 0;
$wrongEmployeeLink = 0;

foreach ($expectedAccounts as $account) {
    $user = User::where('email', $account['email'])->first();
    
    if (!$user) {
        echo "❌ MISSING: {$account['email']}\n";
        $missing++;
        $exitCode = 1;
        continue;
    }
    
    $hasRole = $user->hasRole($account['role']);
    
    if (!$hasRole) {
        $actualRoles = $user->roles->pluck('name')->implode(', ');
        echo "⚠️  WRONG ROLE: {$account['email']} (expected: {$account['role']}, has: {$actualRoles})\n";
        $wrongRole++;
        $exitCode = 1;
        continue;
    }
    
    // Check employee link if expected
    if ($account['employee_code']) {
        $employee = Employee::where('employee_code', $account['employee_code'])->first();
        if (!$employee) {
            echo "⚠️  MISSING EMPLOYEE: {$account['email']} (expected: {$account['employee_code']})\n";
            $wrongEmployeeLink++;
            $exitCode = 1;
            continue;
        }
        if ($employee->user_id !== $user->id) {
            echo "⚠️  WRONG EMPLOYEE LINK: {$account['email']} (expected: {$account['employee_code']})\n";
            $wrongEmployeeLink++;
            $exitCode = 1;
            continue;
        }
        echo "✅ VERIFIED: {$account['email']} ({$account['role']}) ({$account['employee_code']})\n";
    } else {
        echo "✅ VERIFIED: {$account['email']} ({$account['role']}) (no employee)\n";
    }
    
    $verified++;
}

echo "\n========================================\n";
echo "Summary:\n";
echo "  ✅ Verified:  {$verified}\n";
echo "  ❌ Missing:   {$missing}\n";
echo "  ⚠️  Wrong Role: {$wrongRole}\n";
echo "  ⚠️  Employee Link: {$wrongEmployeeLink}\n";
echo "========================================\n";

if ($missing > 0) {
    echo "\nTo fix missing accounts, run:\n";
    echo "  php artisan db:seed --class=RolePermissionSeeder\n";
    echo "  php artisan db:seed --class=ComprehensiveTestAccountsSeeder\n";
}

exit($exitCode);
