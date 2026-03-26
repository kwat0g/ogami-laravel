<?php

declare(strict_types=1);

namespace App\Console\Commands\RBAC;

use App\Domains\HR\Models\Department;
use App\Models\RBAC\Module;
use App\Services\DepartmentModuleService;
use Illuminate\Console\Command;

/**
 * Assign Module to Department Command
 *
 * Links a department to a permission module.
 * Usage: php artisan rbac:assign-module HR hr
 */
class AssignModuleCommand extends Command
{
    protected $signature = 'rbac:assign-module 
                            {department : Department code (e.g., HR, ACCTG)}
                            {module : Module key (e.g., hr, accounting)}
                            {--dry-run : Preview changes without applying}';

    protected $description = 'Assign a permission module to a department';

    public function handle(): int
    {
        $deptCode = strtoupper($this->argument('department'));
        $moduleKey = strtolower($this->argument('module'));
        $dryRun = $this->option('dry-run');

        // Validate department
        $department = Department::where('code', $deptCode)->first();
        if (! $department) {
            $this->error("❌ Department '{$deptCode}' not found");
            $this->listAvailableDepartments();

            return self::FAILURE;
        }

        // Validate module
        $module = Module::findByKey($moduleKey);
        if (! $module) {
            $this->error("❌ Module '{$moduleKey}' not found");
            $this->listAvailableModules();

            return self::FAILURE;
        }

        // Display current and new state
        $this->info("Department: {$department->name} ({$department->code})");
        $this->info('Current module: '.($department->module_key ?? 'NONE'));
        $this->info("New module: {$module->label} ({$module->module_key})");

        if ($dryRun) {
            $this->warn('🧪 Dry run - no changes made');

            return self::SUCCESS;
        }

        // Confirm if overwriting
        if ($department->module_key !== null) {
            if (! $this->confirm("Department already has module '{$department->module_key}'. Overwrite?")) {
                $this->info('Cancelled');

                return self::SUCCESS;
            }
        }

        // Apply change
        try {
            $department->update(['module_key' => $moduleKey]);

            // Clear permission caches
            DepartmentModuleService::clearAllCaches();

            $this->info("✅ Module '{$moduleKey}' assigned to department '{$deptCode}'");

            // Show what permissions this grants
            $this->showPermissionSummary($moduleKey);

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error("❌ Failed to assign module: {$e->getMessage()}");

            return self::FAILURE;
        }
    }

    private function listAvailableDepartments(): void
    {
        $this->newLine();
        $this->info('Available departments:');
        $departments = Department::active()->orderBy('code')->get(['code', 'name']);
        foreach ($departments as $dept) {
            $this->line("  - {$dept->code}: {$dept->name}");
        }
    }

    private function listAvailableModules(): void
    {
        $this->newLine();
        $this->info('Available modules:');
        $modules = Module::active()->get(['module_key', 'label']);
        foreach ($modules as $mod) {
            $this->line("  - {$mod->module_key}: {$mod->label}");
        }
    }

    private function showPermissionSummary(string $moduleKey): void
    {
        $this->newLine();
        $this->info('Permission Summary:');

        foreach (['manager', 'officer', 'head', 'staff'] as $role) {
            $perms = DepartmentModuleService::getModulePermissions($role, $moduleKey);
            $count = count($perms);
            $this->line("  - {$role}: {$count} permissions");
        }
    }
}
