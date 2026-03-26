<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\RBAC\Module;
use Illuminate\Database\Seeder;

/**
 * Module Seeder - Creates the permission modules.
 *
 * Modules decouple permissions from hardcoded department codes.
 * A department is assigned a module (e.g., HR dept → 'hr' module).
 * Permissions are then determined by: Role + Module
 */
class ModuleSeeder extends Seeder
{
    public function run(): void
    {
        $modules = [
            [
                'module_key' => 'hr',
                'label' => 'Human Resources',
                'description' => 'HR, Payroll, Attendance, Leave, Loans',
                'default_permissions' => ['self.*'],
                'permission_groups' => ['hr', 'payroll', 'attendance'],
            ],
            [
                'module_key' => 'accounting',
                'label' => 'Accounting & Finance',
                'description' => 'GL, AP, AR, Banking, Tax, Financial Reports',
                'default_permissions' => ['self.*'],
                'permission_groups' => ['accounting', 'finance', 'banking'],
            ],
            [
                'module_key' => 'production',
                'label' => 'Production & Operations',
                'description' => 'Production, QC, Maintenance, Mold, Inventory, Delivery, ISO',
                'default_permissions' => ['self.*', 'inventory.view'],
                'permission_groups' => ['production', 'qc', 'maintenance', 'mold', 'inventory', 'delivery', 'iso'],
            ],
            [
                'module_key' => 'sales',
                'label' => 'Sales & CRM',
                'description' => 'CRM, Customer Portal, Sales Reports, Customer Management',
                'default_permissions' => ['self.*', 'crm.*'],
                'permission_groups' => ['sales', 'crm'],
            ],
            [
                'module_key' => 'warehouse',
                'label' => 'Warehouse & Logistics',
                'description' => 'Inventory Management, Material Requisitions, Shipping',
                'default_permissions' => ['self.*', 'inventory.view'],
                'permission_groups' => ['warehouse', 'inventory', 'logistics'],
            ],
            [
                'module_key' => 'purchasing',
                'label' => 'Purchasing & Procurement',
                'description' => 'Purchase Requests, Purchase Orders, Vendor Management, RFQ',
                'default_permissions' => ['self.*', 'procurement.view'],
                'permission_groups' => ['procurement', 'purchasing', 'vendor'],
            ],
            [
                'module_key' => 'operations',
                'label' => 'General Operations',
                'description' => 'Standard operations with limited module access (IT, Executive, etc.)',
                'default_permissions' => ['self.*'],
                'permission_groups' => ['operations'],
            ],
        ];

        foreach ($modules as $module) {
            Module::updateOrCreate(
                ['module_key' => $module['module_key']],
                [
                    'label' => $module['label'],
                    'description' => $module['description'],
                    'default_permissions' => $module['default_permissions'],
                    'permission_groups' => $module['permission_groups'],
                    'is_active' => true,
                ]
            );
        }

        $this->command->info('✓ Modules seeded: '.count($modules));
        foreach ($modules as $m) {
            $this->command->info("  - {$m['module_key']}: {$m['label']}");
        }
    }
}
