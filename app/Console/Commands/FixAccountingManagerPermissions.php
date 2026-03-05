<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class FixAccountingManagerPermissions extends Command
{
    protected $signature = 'permissions:fix-accounting-manager';

    protected $description = 'Remove HR permissions from Accounting Manager role';

    public function handle(): int
    {
        $role = Role::findByName('Accounting Manager');

        if (! $role) {
            $this->error('Accounting Manager role not found');

            return 1;
        }

        // HR permissions to remove
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

        $removed = 0;
        foreach ($hrPermissions as $permName) {
            $permission = Permission::findByName($permName);
            if ($permission && $role->hasPermissionTo($permission)) {
                $role->revokePermissionTo($permission);
                $this->info("Removed: {$permName}");
                $removed++;
            }
        }

        // Ensure required permissions are present
        $requiredPermissions = [
            'employees.view_team',
            'attendance.view_team',
            'leaves.view_team',
            'overtime.view',
            'loans.view_department',
            'payroll.view',
            'chart_of_accounts.view',
            'fiscal_periods.view',
            'journal_entries.view',
            'vendors.view',
            'vendor_invoices.view',
            'customers.view',
            'customer_invoices.view',
            'reports.vat',
            'bank_accounts.view',
            'bank_reconciliations.view',
        ];

        $added = 0;
        foreach ($requiredPermissions as $permName) {
            $permission = Permission::findByName($permName);
            if ($permission && ! $role->hasPermissionTo($permission)) {
                $role->givePermissionTo($permission);
                $this->info("Added: {$permName}");
                $added++;
            }
        }

        $this->newLine();
        $this->info("Done! Removed {$removed} HR permissions, added {$added} required permissions.");

        return 0;
    }
}
