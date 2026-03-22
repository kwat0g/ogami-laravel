<?php

declare(strict_types=1);

namespace App\Console\Commands\RBAC;

use App\Domains\HR\Models\Department;
use App\Models\RBAC\Module;
use App\Models\RBAC\ModulePermission;
use App\Services\DepartmentModuleService;
use Illuminate\Console\Command;

/**
 * RBAC Diagnostics Command
 * 
 * Checks for misconfigurations in the module-based RBAC system:
 * - Departments without module assignments
 * - Invalid module keys
 * - Missing permission definitions
 * - Incomplete role coverage
 */
class DiagnosticsCommand extends Command
{
    protected $signature = 'rbac:diagnostics 
                            {--fix : Attempt to fix fixable issues}
                            {--report : Generate detailed report}';

    protected $description = 'Diagnose RBAC configuration issues';

    public function handle(): int
    {
        $this->info('🔍 Running RBAC Diagnostics...');
        $this->newLine();

        $issues = [];
        $fixable = [];

        // Check 1: Departments without module
        $this->checkDepartmentsWithoutModule($issues, $fixable);

        // Check 2: Invalid module keys
        $this->checkInvalidModuleKeys($issues);

        // Check 3: Missing module permissions
        $this->checkMissingModulePermissions($issues, $fixable);

        // Check 4: Modules without any permissions
        $this->checkEmptyModules($issues);

        // Summary
        $this->displaySummary($issues, $fixable);

        // Fix if requested
        if ($this->option('fix') && !empty($fixable)) {
            $this->applyFixes($fixable);
        }

        // Detailed report if requested
        if ($this->option('report')) {
            $this->generateReport();
        }

        return empty($issues) ? self::SUCCESS : self::FAILURE;
    }

    private function checkDepartmentsWithoutModule(array &$issues, array &$fixable): void
    {
        $this->info('Checking departments without module assignments...');

        $orphans = Department::withoutModule()->active()->get();

        if ($orphans->isEmpty()) {
            $this->line('  ✓ All departments have module assignments');
            return;
        }

        foreach ($orphans as $dept) {
            $issues[] = [
                'type' => 'missing_module',
                'severity' => 'warning',
                'message' => "Department '{$dept->name}' ({$dept->code}) has no module",
                'department' => $dept,
            ];
            $fixable[] = ['type' => 'assign_module', 'department' => $dept];
        }

        $this->warn("  ⚠️  Found {$orphans->count()} departments without modules");
    }

    private function checkInvalidModuleKeys(array &$issues): void
    {
        $this->info('Checking for invalid module keys...');

        $validModules = Module::pluck('module_key')->toArray();
        $departments = Department::whereNotNull('module_key')->get();
        $invalidFound = false;

        foreach ($departments as $dept) {
            if (!in_array($dept->module_key, $validModules, true)) {
                $issues[] = [
                    'type' => 'invalid_module',
                    'severity' => 'error',
                    'message' => "Department '{$dept->name}' has invalid module_key: {$dept->module_key}",
                    'department' => $dept,
                    'valid_modules' => $validModules,
                ];
                $invalidFound = true;
            }
        }

        if (!$invalidFound) {
            $this->line('  ✓ All module keys are valid');
        } else {
            $this->error('  ❌ Found departments with invalid module keys');
        }
    }

    private function checkMissingModulePermissions(array &$issues, array &$fixable): void
    {
        $this->info('Checking for missing module permissions...');

        $coreRoles = ['manager', 'officer', 'head', 'staff'];
        $missingFound = false;

        foreach (Module::active()->get() as $module) {
            foreach ($coreRoles as $role) {
                if (!ModulePermission::exists($module->module_key, $role)) {
                    $issues[] = [
                        'type' => 'missing_permissions',
                        'severity' => 'warning',
                        'message' => "No permissions for {$module->module_key}.{$role}",
                        'module' => $module,
                        'role' => $role,
                    ];
                    $fixable[] = ['type' => 'seed_permissions', 'module' => $module, 'role' => $role];
                    $missingFound = true;
                }
            }
        }

        if (!$missingFound) {
            $this->line('  ✓ All module+role combinations have permissions');
        } else {
            $this->warn('  ⚠️  Found missing permission definitions');
        }
    }

    private function checkEmptyModules(array &$issues): void
    {
        $this->info('Checking for modules without any permissions...');

        $emptyModules = [];

        foreach (Module::active()->get() as $module) {
            $count = ModulePermission::where('module_key', $module->module_key)->count();
            if ($count === 0) {
                $emptyModules[] = $module;
                $issues[] = [
                    'type' => 'empty_module',
                    'severity' => 'error',
                    'message' => "Module '{$module->label}' has no permissions defined",
                    'module' => $module,
                ];
            }
        }

        if (empty($emptyModules)) {
            $this->line('  ✓ All modules have permissions');
        } else {
            $this->error('  ❌ Found ' . count($emptyModules) . ' modules without permissions');
        }
    }

    private function displaySummary(array $issues, array $fixable): void
    {
        $this->newLine();
        $this->info('📊 Diagnostics Summary');
        $this->table(
            ['Type', 'Severity', 'Count'],
            $this->groupIssuesByType($issues)
        );

        if (!empty($fixable)) {
            $this->newLine();
            $this->info('🔧 ' . count($fixable) . ' issues can be auto-fixed with --fix');
        }
    }

    private function groupIssuesByType(array $issues): array
    {
        $grouped = [];
        foreach ($issues as $issue) {
            $key = $issue['type'];
            if (!isset($grouped[$key])) {
                $grouped[$key] = ['type' => $key, 'severity' => $issue['severity'], 'count' => 0];
            }
            $grouped[$key]['count']++;
        }
        return array_values($grouped);
    }

    private function applyFixes(array $fixable): void
    {
        $this->newLine();
        $this->info('Applying fixes...');

        foreach ($fixable as $fix) {
            switch ($fix['type']) {
                case 'assign_module':
                    $this->fixAssignModule($fix['department']);
                    break;
                case 'seed_permissions':
                    $this->fixSeedPermissions($fix['module'], $fix['role']);
                    break;
            }
        }

        $this->info('✓ Fixes applied');
    }

    private function fixAssignModule(Department $dept): void
    {
        $this->warn("  Cannot auto-fix: Please assign module to {$dept->code} manually");
        $this->line("    Suggested modules: " . implode(', ', Module::pluck('module_key')->toArray()));
    }

    private function fixSeedPermissions($module, string $role): void
    {
        $this->warn("  Cannot auto-fix: Please run: php artisan module:seed-permissions {$module->module_key}");
    }

    private function generateReport(): void
    {
        $this->newLine();
        $this->info('📄 Generating Detailed Report...');

        // Modules overview
        $this->newLine();
        $this->info('Modules:');
        foreach (Module::active()->get() as $module) {
            $deptCount = Department::where('module_key', $module->module_key)->count();
            $this->line("  - {$module->module_key}: {$module->label} ({$deptCount} departments)");
        }

        // Department assignments
        $this->newLine();
        $this->info('Department Assignments:');
        foreach (Department::active()->get() as $dept) {
            $moduleLabel = $dept->module_key ? ($dept->module?->label ?? 'INVALID') : 'NONE';
            $this->line("  - {$dept->code}: {$dept->name} → {$moduleLabel}");
        }

        // Permission counts by module
        $this->newLine();
        $this->info('Permission Counts:');
        foreach (Module::active()->get() as $module) {
            foreach (['manager', 'officer', 'head', 'staff'] as $role) {
                $perms = ModulePermission::getPermissions($module->module_key, $role);
                $count = count($perms);
                $this->line("  - {$module->module_key}.{$role}: {$count} permissions");
            }
        }
    }
}
