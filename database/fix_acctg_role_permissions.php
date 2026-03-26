<?php

/**
 * Script to remove HR permissions from ACCTG department users' roles
 * Run: php database/fix_acctg_role_permissions.php
 */

require __DIR__.'/../vendor/autoload.php';

$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

echo "=== Fixing Accounting Department Role Permissions ===\n\n";

// Get ACCTG department users
$acctgUsers = DB::table('employees')
    ->where('department_id', function ($q) {
        $q->select('id')->from('departments')->where('code', 'ACCTG');
    })
    ->whereNotNull('user_id')
    ->pluck('user_id');

echo 'Found '.$acctgUsers->count()." ACCTG department users\n";

// Get all roles assigned to these users
$roleIds = DB::table('model_has_roles')
    ->whereIn('model_id', $acctgUsers)
    ->where('model_type', 'App\\Models\\User')
    ->pluck('role_id')
    ->unique();

echo 'Found '.$roleIds->count()." unique roles\n\n";

// HR permissions to remove
$hrPermissions = [
    'hr.full_access',
];

$totalRemoved = 0;

foreach ($roleIds as $roleId) {
    $role = Role::find($roleId);
    if (! $role) {
        continue;
    }

    echo "Role: {$role->name}\n";

    foreach ($hrPermissions as $permName) {
        try {
            $permission = Permission::findByName($permName);
            if ($permission && $role->hasPermissionTo($permission)) {
                $role->revokePermissionTo($permission);
                echo "  - Removed permission: {$permName}\n";
                $totalRemoved++;
            }
        } catch (Exception $e) {
            // Permission doesn't exist, skip
        }
    }
}

echo "\n=== Done! Removed {$totalRemoved} HR permissions from roles ===\n";
