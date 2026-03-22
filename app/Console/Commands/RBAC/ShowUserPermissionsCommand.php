<?php

declare(strict_types=1);

namespace App\Console\Commands\RBAC;

use App\Models\User;
use App\Services\DepartmentModuleService;
use Illuminate\Console\Command;

/**
 * Show User Permissions Command
 * 
 * Displays a user's effective permissions based on role + department modules.
 * Usage: php artisan rbac:show-user user@example.com
 */
class ShowUserPermissionsCommand extends Command
{
    protected $signature = 'rbac:show-user 
                            {user : User email or ID}
                            {--count-only : Show only permission count}
                            {--group : Group permissions by category}';

    protected $description = 'Show effective permissions for a user';

    public function handle(): int
    {
        $userIdentifier = $this->argument('user');

        // Find user
        $user = is_numeric($userIdentifier)
            ? User::find($userIdentifier)
            : User::where('email', $userIdentifier)->first();

        if (!$user) {
            $this->error("❌ User '{$userIdentifier}' not found");
            return self::FAILURE;
        }

        $this->info("User: {$user->name} ({$user->email})");
        $this->info("ID: {$user->id}");

        // Show roles
        $roles = $user->getRoleNames()->implode(', ');
        $this->info("Roles: {$roles}");

        // Show departments
        $departments = $user->departments->map(fn($d) => "{$d->code}" . ($d->module_key ? " ({$d->module_key})" : ''))->implode(', ');
        $this->info("Departments: {$departments}");

        // Get effective permissions
        $permissions = DepartmentModuleService::getUserPermissions($user);
        $count = count($permissions);

        $this->newLine();
        $this->info("Effective Permissions: {$count}");

        if ($this->option('count-only')) {
            return self::SUCCESS;
        }

        if ($count === 0) {
            $this->warn('  No permissions assigned');
            return self::SUCCESS;
        }

        // Check for universal access
        if (in_array('*', $permissions, true)) {
            $this->warn('  🌟 Universal access (superadmin)');
            return self::SUCCESS;
        }

        // Display permissions
        if ($this->option('group')) {
            $this->displayGroupedPermissions($permissions);
        } else {
            $this->displayPermissionList($permissions);
        }

        return self::SUCCESS;
    }

    private function displayPermissionList(array $permissions): void
    {
        sort($permissions);
        foreach ($permissions as $perm) {
            $this->line("  - {$perm}");
        }
    }

    private function displayGroupedPermissions(array $permissions): void
    {
        $groups = [];
        
        foreach ($permissions as $perm) {
            $parts = explode('.', $perm);
            $group = $parts[0] ?? 'other';
            $groups[$group][] = $perm;
        }

        ksort($groups);

        foreach ($groups as $group => $perms) {
            $this->newLine();
            $this->info("  📁 {$group} (" . count($perms) . ")");
            sort($perms);
            foreach (array_slice($perms, 0, 10) as $perm) {
                $this->line("    - {$perm}");
            }
            if (count($perms) > 10) {
                $this->line("    ... and " . (count($perms) - 10) . " more");
            }
        }
    }
}
