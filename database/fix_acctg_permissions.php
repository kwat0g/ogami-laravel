<?php

/**
 * Script to remove HR permissions from ACCTG department users
 * Run: php database/fix_acctg_permissions.php
 */

require __DIR__.'/../vendor/autoload.php';

$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;

echo "=== Fixing Accounting Department Permissions ===\n\n";

// HR permissions to remove from ACCTG users
$hrPermissions = [
    'hr.full_access',
    'employees.view',
    'employees.create',
    'employees.update',
    'employees.delete',
    'attendance.view',
    'attendance.manage',
    'leaves.view',
    'leaves.manage',
    'leave_balances.manage',
    'overtime.manage',
    'loans.view',
    'loans.manage',
    'employees.manage_structure',
    'attendance.manage_shifts',
];

// Get ACCTG department users
$acctgUsers = DB::table('employees')
    ->where('department_id', function ($q) {
        $q->select('id')->from('departments')->where('code', 'ACCTG');
    })
    ->whereNotNull('user_id')
    ->pluck('user_id');

echo 'Found '.$acctgUsers->count()." ACCTG department users\n\n";

$totalRemoved = 0;

foreach ($acctgUsers as $userId) {
    $user = \App\Models\User::find($userId);
    if (! $user) {
        continue;
    }

    echo "User: {$user->email}\n";

    foreach ($hrPermissions as $permName) {
        try {
            $permission = Permission::findByName($permName);
            if ($permission && $user->hasDirectPermission($permission)) {
                $user->revokePermissionTo($permission);
                echo "  - Removed direct permission: {$permName}\n";
                $totalRemoved++;
            }
        } catch (\Exception $e) {
            // Permission doesn't exist, skip
        }
    }
}

echo "\n=== Done! Removed {$totalRemoved} HR permissions from ACCTG users ===\n";
echo "\nNOTE: If permissions are assigned via roles, you need to remove them from the role.\n";
