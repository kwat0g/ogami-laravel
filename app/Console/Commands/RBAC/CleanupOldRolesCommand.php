<?php

declare(strict_types=1);

namespace App\Console\Commands\RBAC;

use App\Domains\HR\Models\Department;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

/**
 * Cleanup Old Roles Command
 * 
 * Migrates from 18+ specific roles to 7 generic roles + department modules.
 * 
 * Old roles being removed:
 *   - plant_manager → manager (with PLANT/MOLD/QC/MAINT/PROD department)
 *   - production_manager → manager (with PROD department)
 *   - qc_manager → manager (with QC department)
 *   - mold_manager → manager (with MOLD department)
 *   - ga_officer → officer (with HR department)
 *   - purchasing_officer → officer (with PPC department)
 *   - impex_officer → officer (with SALES/WH department)
 *   - warehouse_head → head (with WH department)
 *   - ppc_head → head (with PPC department)
 *   - crm_manager → manager (with SALES department)
 */
class CleanupOldRolesCommand extends Command
{
    protected $signature = 'rbac:cleanup-old-roles
                            {--dry-run : Preview changes without applying}
                            {--force : Skip confirmation prompt}';

    protected $description = 'Migrate from old specific roles to new generic roles';

    /**
     * Mapping of old roles to new roles.
     * Users will be assigned the new role based on their department.
     */
    private const ROLE_MIGRATION_MAP = [
        // Old specific manager roles → generic manager
        'plant_manager' => 'manager',
        'production_manager' => 'manager',
        'qc_manager' => 'manager',
        'mold_manager' => 'manager',
        'crm_manager' => 'manager',

        // Old specific officer roles → generic officer
        'ga_officer' => 'officer',
        'purchasing_officer' => 'officer',
        'impex_officer' => 'officer',

        // Old specific head roles → generic head
        'warehouse_head' => 'head',
        'ppc_head' => 'head',
    ];

    /**
     * Roles that are kept as-is (new 7-role system + portals).
     */
    private const KEEP_ROLES = [
        'super_admin',
        'admin',
        'executive',
        'vice_president',
        'manager',
        'officer',
        'head',
        'staff',
        'vendor',
        'client',
    ];

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $force = $this->option('force');

        $this->info('🔧 RBAC Role Cleanup');
        $this->newLine();

        if ($dryRun) {
            $this->warn('🧪 DRY RUN MODE - No changes will be made');
            $this->newLine();
        }

        // Get all roles in the system
        $allRoles = Role::all()->pluck('name')->toArray();
        $oldRoles = array_keys(self::ROLE_MIGRATION_MAP);
        $existingOldRoles = array_intersect($allRoles, $oldRoles);

        if (empty($existingOldRoles)) {
            $this->info('✅ No old roles found. System is already using new 7-role system.');
            return self::SUCCESS;
        }

        $this->info('Found old roles to migrate:');
        foreach ($existingOldRoles as $role) {
            $newRole = self::ROLE_MIGRATION_MAP[$role];
            $count = User::role($role)->count();
            $this->line("  - {$role} → {$newRole} ({$count} users)");
        }

        if (!$force && !$dryRun) {
            if (!$this->confirm('Do you want to proceed with the migration?')) {
                $this->info('Cancelled.');
                return self::SUCCESS;
            }
        }

        // Perform migration
        $this->newLine();
        $this->info('Migrating users...');

        DB::beginTransaction();

        try {
            foreach ($existingOldRoles as $oldRoleName) {
                $newRoleName = self::ROLE_MIGRATION_MAP[$oldRoleName];
                $this->migrateRole($oldRoleName, $newRoleName, $dryRun);
            }

            // Delete old roles
            if (!$dryRun) {
                $this->newLine();
                $this->info('Removing old roles...');
                foreach ($existingOldRoles as $oldRoleName) {
                    $role = Role::findByName($oldRoleName, 'web');
                    if ($role) {
                        $role->delete();
                        $this->line("  ✓ Deleted role: {$oldRoleName}");
                    }
                }
            }

            if ($dryRun) {
                $this->newLine();
                $this->warn('🧪 Dry run complete. No changes were made.');
            } else {
                DB::commit();
                $this->newLine();
                $this->info('✅ Migration complete!');
            }

            return self::SUCCESS;
        } catch (\Exception $e) {
            DB::rollBack();
            $this->error('❌ Migration failed: ' . $e->getMessage());
            return self::FAILURE;
        }
    }

    private function migrateRole(string $oldRole, string $newRole, bool $dryRun): void
    {
        $users = User::role($oldRole)->get();
        
        if ($users->isEmpty()) {
            $this->line("  • No users with role: {$oldRole}");
            return;
        }

        foreach ($users as $user) {
            if ($dryRun) {
                $this->line("  Would migrate: {$user->email} ({$oldRole} → {$newRole})");
                continue;
            }

            // Remove old role
            $user->removeRole($oldRole);
            
            // Add new role
            $user->assignRole($newRole);

            $this->line("  ✓ Migrated: {$user->email} ({$oldRole} → {$newRole})");
        }
    }

    /**
     * Get role display name for UI.
     */
    public static function getRoleDisplayName(string $role): string
    {
        return match($role) {
            'super_admin' => 'Super Admin',
            'admin' => 'System Admin',
            'executive' => 'Executive',
            'vice_president' => 'Vice President',
            'manager' => 'Manager',
            'officer' => 'Officer',
            'head' => 'Department Head',
            'staff' => 'Staff',
            'vendor' => 'Vendor Portal',
            'client' => 'Client Portal',
            default => ucfirst(str_replace('_', ' ', $role)),
        };
    }
}
