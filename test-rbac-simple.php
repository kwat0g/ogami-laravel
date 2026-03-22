#!/usr/bin/env php
<?php

/**
 * Simple RBAC Test Script
 * Run: php test-rbac-simple.php
 */

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make('Illuminate\Contracts\Console\Kernel');
$kernel->bootstrap();

echo "═══════════════════════════════════════════════════════════════════\n";
echo "              RBAC CRITICAL FIXES - SIMPLE TEST\n";
echo "═══════════════════════════════════════════════════════════════════\n\n";

// Seed the database
echo "Seeding database...\n";
Illuminate\Support\Facades\Artisan::call('db:seed', ['--force' => true]);
app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
echo "Done.\n\n";

// Test cases
$tests = [
    [
        'email' => 'prod.manager@ogamierp.local',
        'perm' => 'inventory.items.view',
        'expected' => false,
        'desc' => 'Production Manager CANNOT view Inventory'
    ],
    [
        'email' => 'warehouse.head@ogamierp.local',
        'perm' => 'inventory.items.view',
        'expected' => true,
        'desc' => 'Warehouse Head CAN view Inventory'
    ],
    [
        'email' => 'prod.manager@ogamierp.local',
        'perm' => 'production.orders.view',
        'expected' => true,
        'desc' => 'Production Manager CAN view Production'
    ],
    [
        'email' => 'warehouse.head@ogamierp.local',
        'perm' => 'inventory.items.create',
        'expected' => true,
        'desc' => 'Warehouse Head CAN create Inventory items'
    ],
    [
        'email' => 'acctg.manager@ogamierp.local',
        'perm' => 'inventory.items.view',
        'expected' => false,
        'desc' => 'Accounting Manager CANNOT view Inventory'
    ],
];

$passed = 0;
$failed = 0;

echo "Running tests...\n";
echo "───────────────────────────────────────────────────────────────────\n";

foreach ($tests as $test) {
    $email = $test['email'];
    $perm = $test['perm'];
    $expected = $test['expected'];
    $desc = $test['desc'];
    
    $user = App\Models\User::where('email', $email)->first();
    if (!$user) {
        echo "❌ FAIL: $desc\n";
        echo "   User not found: $email\n";
        $failed++;
        continue;
    }
    
    $actual = $user->can($perm);
    
    if ($actual === $expected) {
        echo "✅ PASS: $desc\n";
        echo "   Result: " . ($actual ? 'ALLOWED' : 'BLOCKED') . "\n";
        $passed++;
    } else {
        echo "❌ FAIL: $desc\n";
        echo "   Expected: " . ($expected ? 'ALLOWED' : 'BLOCKED') . "\n";
        echo "   Actual: " . ($actual ? 'ALLOWED' : 'BLOCKED') . "\n";
        $failed++;
    }
}

echo "───────────────────────────────────────────────────────────────────\n";
echo "Results: $passed passed, $failed failed\n";
echo "═══════════════════════════════════════════════════════════════════\n";

exit($failed > 0 ? 1 : 0);
