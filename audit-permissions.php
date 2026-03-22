<?php

/**
 * Permission Audit Script
 * 
 * Run: php audit-permissions.php
 */

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make('Illuminate\Contracts\Console\Kernel');
$kernel->bootstrap();

echo "═══════════════════════════════════════════════════════════════════════════════\n";
echo "                    🔐 RBAC PERMISSION AUDIT\n";
echo "═══════════════════════════════════════════════════════════════════════════════\n\n";

// Seed required data
echo "Seeding required data...\n";
Illuminate\Support\Facades\Artisan::call('db:seed', ['--class' => 'RolePermissionSeeder', '--force' => true]);
Illuminate\Support\Facades\Artisan::call('db:seed', ['--class' => 'DepartmentPositionSeeder', '--force' => true]);
Illuminate\Support\Facades\Artisan::call('db:seed', ['--class' => 'SalaryGradeSeeder', '--force' => true]);
Illuminate\Support\Facades\Artisan::call('db:seed', ['--class' => 'ComprehensiveTestAccountsSeeder', '--force' => true]);
Illuminate\Support\Facades\Artisan::call('db:seed', ['--class' => 'ModuleSeeder', '--force' => true]);
Illuminate\Support\Facades\Artisan::call('db:seed', ['--class' => 'ModulePermissionSeeder', '--force' => true]);
Illuminate\Support\Facades\Artisan::call('db:seed', ['--class' => 'DepartmentModuleAssignmentSeeder', '--force' => true]);

// Test users and their expected permissions
$audit = [
    // [email, permission, should_have, description]
    ['prod.manager@ogamierp.local', 'inventory.items.view', false, 'Production Manager → Inventory (BLOCKED)'],
    ['prod.manager@ogamierp.local', 'production.orders.view', true, 'Production Manager → Production (ALLOWED)'],
    ['prod.manager@ogamierp.local', 'employees.view', true, 'Production Manager → Employees (ALLOWED)'],
    
    ['warehouse.head@ogamierp.local', 'inventory.items.view', true, 'Warehouse Head → Inventory Items (ALLOWED)'],
    ['warehouse.head@ogamierp.local', 'inventory.items.create', true, 'Warehouse Head → Create Items (ALLOWED)'],
    ['warehouse.head@ogamierp.local', 'inventory.locations.manage', true, 'Warehouse Head → Manage Locations (ALLOWED)'],
    ['warehouse.head@ogamierp.local', 'production.orders.view', false, 'Warehouse Head → Production (BLOCKED)'],
    
    ['acctg.manager@ogamierp.local', 'bank_accounts.view', true, 'Accounting Manager → Bank Accounts (ALLOWED)'],
    ['acctg.manager@ogamierp.local', 'inventory.items.view', false, 'Accounting Manager → Inventory (BLOCKED)'],
    ['acctg.manager@ogamierp.local', 'journal_entries.view', true, 'Accounting Manager → Journal Entries (ALLOWED)'],
    
    ['acctg.officer@ogamierp.local', 'bank_accounts.view', true, 'Accounting Officer → Bank Accounts (ALLOWED)'],
    ['acctg.officer@ogamierp.local', 'bank_accounts.create', false, 'Accounting Officer → Create Bank (BLOCKED)'],
    
    ['hr.manager@ogamierp.local', 'payroll.view_runs', true, 'HR Manager → Payroll Runs (ALLOWED)'],
    ['hr.manager@ogamierp.local', 'bank_accounts.view', false, 'HR Manager → Banking (BLOCKED)'],
    
    ['ga.officer@ogamierp.local', 'employees.view', true, 'HR Officer → Employees (ALLOWED)'],
    ['ga.officer@ogamierp.local', 'payroll.view_runs', false, 'HR Officer → Payroll Runs (BLOCKED)'],
    
    ['crm.manager@ogamierp.local', 'crm.tickets.manage', true, 'Sales Manager → CRM Tickets (ALLOWED)'],
    ['crm.manager@ogamierp.local', 'accounting.journal_entries.view', false, 'Sales Manager → Accounting (BLOCKED)'],
    
    ['purchasing.officer@ogamierp.local', 'procurement.purchase-request.view', true, 'Purchasing Officer → PR View (ALLOWED)'],
    ['purchasing.officer@ogamierp.local', 'inventory.items.view', false, 'Purchasing Officer → Inventory (BLOCKED)'],
];

echo "\n";
echo "╔═══════════════════════════════════════════════════════════════════════════════╗\n";
echo "║                        PERMISSION VERIFICATION MATRIX                         ║\n";
echo "╚═══════════════════════════════════════════════════════════════════════════════╝\n\n";

$passed = 0;
$failed = 0;

foreach ($audit as [$email, $permission, $expected, $desc]) {
    $user = App\Models\User::where('email', $email)->first();
    
    if (!$user) {
        echo "⚠️  User not found: $email\n";
        continue;
    }
    
    $actual = $user->can($permission);
    $status = ($actual === $expected);
    
    $icon = $status ? '✅' : '❌';
    $color = $status ? "\033[0;32m" : "\033[0;31m";
    $reset = "\033[0m";
    
    printf("%s%s %s%s\n", $color, $icon, $desc, $reset);
    
    if (!$status) {
        printf("   Expected: %s, Got: %s\n", $expected ? 'YES' : 'NO', $actual ? 'YES' : 'NO');
        $failed++;
    } else {
        $passed++;
    }
}

echo "\n";
echo "═══════════════════════════════════════════════════════════════════════════════\n";
echo "                              AUDIT SUMMARY\n";
echo "═══════════════════════════════════════════════════════════════════════════════\n";
printf("  ✅ Passed: %d\n", $passed);
printf("  ❌ Failed: %d\n", $failed);
printf("  📊 Total:  %d\n", $passed + $failed);
echo "═══════════════════════════════════════════════════════════════════════════════\n";

if ($failed > 0) {
    echo "\n⚠️  Some permissions are not correctly configured!\n";
    exit(1);
} else {
    echo "\n🎉 All permissions are correctly configured!\n";
    exit(0);
}
