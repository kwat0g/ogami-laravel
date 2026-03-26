<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\RBAC\ModulePermission;
use Illuminate\Database\Seeder;

/**
 * Standard Hierarchy Permission Seeder
 *
 * Hierarchy: Manager (full) → Officer (operational) → Head (team lead) → Staff (minimal)
 *
 * Each role's permission set is a STRICT SUPERSET of the role below it.
 * If Manager is absent → Officer can cover most work.
 * If Officer is absent → Head can cover team-level work.
 *
 * Role-specific approval-stage permissions (loans.head_note, leaves.head_approve, etc.)
 * are exceptions — they belong to a specific approval stage and do NOT cascade up.
 */
class ReversedHierarchyPermissionSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('═══════════════════════════════════════════════════════════════');
        $this->command->info('  STANDARD HIERARCHY: Manager → Officer → Head → Staff');
        $this->command->info('  Each role is a strict superset of the role below it.');
        $this->command->info('═══════════════════════════════════════════════════════════════');

        $this->seedHRPermissions();
        $this->seedAccountingPermissions();
        $this->seedProductionPermissions();
        $this->seedPurchasingPermissions();
        $this->seedWarehousePermissions();
        $this->seedSalesPermissions();
        $this->seedOperationsPermissions();

        $this->command->info('');
        $this->command->info('✓ All module permissions set with proper cascading hierarchy');
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // HR MODULE
    // Manager → Officer → Head → Staff (each is superset of the one below)
    // ═══════════════════════════════════════════════════════════════════════════
    private function seedHRPermissions(): void
    {
        $this->command->info('─ HR Module ───────────────────────────────────────────────────');

        $staffBase = [
            'self.view_profile', 'self.submit_profile_update', 'self.view_attendance',
            'payroll.view_own_payslip', 'payroll.download_own_payslip',
            'payslips.view', 'payslips.download',
            'leaves.view_own', 'leaves.file_own', 'leaves.cancel',
            'loans.view_own', 'loans.apply',
            'attendance.view_own',
            'employees.view',
        ];

        $headAdds = [
            'employees.view_team', 'employees.view_full_record', 'employees.view_masked_gov_ids',
            'attendance.view_team', 'attendance.manage_shifts',
            'overtime.view', 'overtime.submit', 'overtime.supervise',
            'leaves.view_team', 'leaves.file_on_behalf',
            'leaves.head_approve',  // stage-specific: head's first-level leave approval
            'loans.view_department',
        ];

        $officerAdds = [
            'attendance.view_anomalies', 'attendance.import_csv', 'attendance.resolve_anomalies',
            'overtime.approve', 'overtime.reject',
            'leaves.approve', 'leaves.reject',
            'loans.head_note',  // stage-specific: officer covers head_note step when head is absent
            'payroll.view_runs', 'payroll.approve',
            'hr.full_access',
        ];

        $managerAdds = [
            'employees.create', 'employees.edit',
            'employees.upload_documents', 'employees.download_documents',
            'payroll.initiate_run', 'payroll.compute', 'payroll.publish', 'payroll.download_reports',
        ];

        $head = array_merge($staffBase, $headAdds);
        $officer = array_merge($head, $officerAdds);
        $manager = array_merge($officer, $managerAdds);

        $this->updateModulePermission('hr', 'staff', $staffBase);
        $this->updateModulePermission('hr', 'head', $head);
        $this->updateModulePermission('hr', 'officer', $officer);
        $this->updateModulePermission('hr', 'manager', $manager);

        $this->command->info('  ✓ HR permissions updated');
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // ACCOUNTING MODULE
    // ═══════════════════════════════════════════════════════════════════════════
    private function seedAccountingPermissions(): void
    {
        $this->command->info('─ Accounting Module ───────────────────────────────────────────');

        $staffBase = [
            'self.view_profile', 'self.submit_profile_update', 'self.view_attendance',
            'payroll.view_own_payslip', 'payroll.download_own_payslip',
            'payroll.view_runs', 'payroll.review_breakdown',
            'payslips.view', 'payslips.download',
            'leaves.view_own', 'leaves.file_own', 'leaves.cancel',
            'loans.view_own', 'loans.apply',
            'attendance.view_own',
            'employees.view',
            'chart_of_accounts.view',
            'journal_entries.view',
            'bank_accounts.view',
            'vendors.view',
            'vendor_invoices.view',
            'customers.view',
            'customer_invoices.view',
            'tax.vat.view',
            'budget.view',
        ];

        $headAdds = [
            'employees.view_team',
            'attendance.view_team', 'attendance.manage_shifts',
            'overtime.view', 'overtime.submit', 'overtime.supervise',
            'leaves.view_team', 'leaves.file_on_behalf',
            'leaves.head_approve',
            'loans.view_department',
            'bank_reconciliations.view',
            'vendor_payments.view',
            'customer_payments.view',
            'reports.gl', 'reports.financial_statements',
        ];

        $officerAdds = [
            'attendance.view_anomalies', 'attendance.import_csv', 'attendance.resolve_anomalies',
            'overtime.approve', 'overtime.reject',
            'leaves.approve', 'leaves.reject',
            'loans.head_note',
            'payroll.post',
            'journal_entries.approve',
            'vendor_invoices.approve',
            'vendor_payments.approve',
            'customer_invoices.approve',
            'tax.vat.file', 'tax.withholding.view',
            'budget.approve',
            'reports.ap_aging', 'reports.ar_aging',
            'procurement.purchase-request.view',
            'procurement.purchase-request.budget-check',
        ];

        $managerAdds = [
            'employees.view_full_record', 'employees.view_masked_gov_ids',
            'chart_of_accounts.manage',
            'journal_entries.create', 'journal_entries.post',
            'bank_accounts.manage',
            'bank_reconciliations.create',
            'vendors.create', 'vendors.edit',
            'vendor_invoices.create', 'vendor_invoices.post',
            'vendor_payments.create',
            'customers.create', 'customers.edit',
            'customer_invoices.create', 'customer_invoices.post',
            'customer_payments.create',
            'budget.create', 'budget.edit',
            'accounting.full_access',
        ];

        $head = array_merge($staffBase, $headAdds);
        $officer = array_merge($head, $officerAdds);
        $manager = array_merge($officer, $managerAdds);

        $this->updateModulePermission('accounting', 'staff', $staffBase);
        $this->updateModulePermission('accounting', 'head', $head);
        $this->updateModulePermission('accounting', 'officer', $officer);
        $this->updateModulePermission('accounting', 'manager', $manager);

        $this->command->info('  ✓ Accounting permissions updated');
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // PRODUCTION MODULE
    // ═══════════════════════════════════════════════════════════════════════════
    private function seedProductionPermissions(): void
    {
        $this->command->info('─ Production Module ───────────────────────────────────────────');

        $staffBase = [
            'self.view_profile', 'self.submit_profile_update', 'self.view_attendance',
            'payroll.view_own_payslip', 'payroll.download_own_payslip',
            'payslips.view', 'payslips.download',
            'leaves.view_own', 'leaves.file_own', 'leaves.cancel',
            'loans.view_own', 'loans.apply',
            'attendance.view_own',
            'employees.view',
            'production.bom.view',
            'production.delivery-schedule.view',
            'production.orders.view', 'production.orders.log_output',
            'qc.inspections.view',
            'maintenance.view',
            'inventory.items.view',
            'inventory.mrq.view',
            'inventory.stock.view',
        ];

        $headAdds = [
            'employees.view_team',
            'attendance.view_team', 'attendance.manage_shifts',
            'overtime.view', 'overtime.submit', 'overtime.supervise',
            'leaves.view_team', 'leaves.file_on_behalf',
            'leaves.head_approve',
            'loans.view_department',
            'inventory.mrq.create',
            'procurement.purchase-request.view',
            'procurement.purchase-request.create-dept',
            'delivery.view',
        ];

        $officerAdds = [
            'attendance.view_anomalies', 'attendance.import_csv', 'attendance.resolve_anomalies',
            'overtime.approve', 'overtime.reject',
            'leaves.approve', 'leaves.reject',
            'loans.head_note',
            'production.orders.approve',
            'qc.ncr.view',
            'mold.view',
            'iso.view',
            'reports.production',
        ];

        $managerAdds = [
            'employees.view_full_record', 'employees.view_masked_gov_ids',
            'production.bom.manage',
            'production.delivery-schedule.manage',
            'production.orders.create', 'production.orders.release', 'production.orders.complete',
            'production.work-center.view', 'production.work-center.manage',
            'qc.inspections.create',
            'qc.ncr.create', 'qc.ncr.close',
            'maintenance.manage',
            'mold.manage', 'mold.log_shots',
            'inventory.items.edit',
            'inventory.adjustments.create',
            'procurement.purchase-request.create',
            'procurement.goods-receipt.view', 'procurement.goods-receipt.create',
            'delivery.manage',
        ];

        $head = array_merge($staffBase, $headAdds);
        $officer = array_merge($head, $officerAdds);
        $manager = array_merge($officer, $managerAdds);

        $this->updateModulePermission('production', 'staff', $staffBase);
        $this->updateModulePermission('production', 'head', $head);
        $this->updateModulePermission('production', 'officer', $officer);
        $this->updateModulePermission('production', 'manager', $manager);

        $this->command->info('  ✓ Production permissions updated');
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // PURCHASING MODULE
    // ═══════════════════════════════════════════════════════════════════════════
    private function seedPurchasingPermissions(): void
    {
        $this->command->info('─ Purchasing Module ───────────────────────────────────────────');

        $staffBase = [
            'self.view_profile', 'self.submit_profile_update', 'self.view_attendance',
            'payroll.view_own_payslip', 'payroll.download_own_payslip',
            'payslips.view', 'payslips.download',
            'leaves.view_own', 'leaves.file_own', 'leaves.cancel',
            'loans.view_own', 'loans.apply',
            'attendance.view_own',
            'employees.view',
            'vendors.view',
            'procurement.purchase-request.view',
            'inventory.items.view',
        ];

        $headAdds = [
            'employees.view_team',
            'attendance.view_team', 'attendance.manage_shifts',
            'overtime.view', 'overtime.submit', 'overtime.supervise',
            'leaves.view_team', 'leaves.file_on_behalf',
            'leaves.head_approve',
            'loans.view_department',
            'procurement.goods-receipt.view',
        ];

        $officerAdds = [
            'attendance.view_anomalies', 'attendance.import_csv', 'attendance.resolve_anomalies',
            'overtime.approve', 'overtime.reject',
            'leaves.approve', 'leaves.reject',
            'loans.head_note',
            'vendors.approve',
            // Officers perform technical review of PRs (SoD: cannot review own submissions)
            'procurement.purchase-request.create',
            'procurement.purchase-request.review',
            'procurement.purchase-order.view', 'procurement.purchase-order.create', 'procurement.purchase-order.manage',
            'procurement.goods-receipt.view', 'procurement.goods-receipt.create',
            'procurement.rfq.view', 'procurement.rfq.create',
            'reports.procurement',
        ];

        $managerAdds = [
            'employees.view_full_record', 'employees.view_masked_gov_ids',
            'vendors.create', 'vendors.edit', 'vendors.manage',
            'vendor_items.view', 'vendor_items.manage',
            'vendor_invoices.view', 'vendor_invoices.create',
            'vendor_payments.view', 'vendor_payments.create',
            'customers.view', 'customers.create', 'customers.edit',
            'customer_invoices.view', 'customer_invoices.create',
            'customer_payments.view',
            'procurement.purchase-request.budget-check',
            'procurement.purchase-order.create',
            'procurement.goods-receipt.post',
            'procurement.rfq.send',
        ];

        $head = array_merge($staffBase, $headAdds);
        $officer = array_merge($head, $officerAdds);
        $manager = array_merge($officer, $managerAdds);

        $this->updateModulePermission('purchasing', 'staff', $staffBase);
        $this->updateModulePermission('purchasing', 'head', $head);
        $this->updateModulePermission('purchasing', 'officer', $officer);
        $this->updateModulePermission('purchasing', 'manager', $manager);

        $this->command->info('  ✓ Purchasing permissions updated');
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // WAREHOUSE MODULE
    // ═══════════════════════════════════════════════════════════════════════════
    private function seedWarehousePermissions(): void
    {
        $this->command->info('─ Warehouse Module ────────────────────────────────────────────');

        $staffBase = [
            'self.view_profile', 'self.submit_profile_update', 'self.view_attendance',
            'payroll.view_own_payslip', 'payroll.download_own_payslip',
            'payslips.view', 'payslips.download',
            'leaves.view_own', 'leaves.file_own', 'leaves.cancel',
            'loans.view_own', 'loans.apply',
            'attendance.view_own',
            'employees.view',
            'inventory.items.view',
            'inventory.stock.view',
            'delivery.view',
        ];

        $headAdds = [
            'employees.view_team',
            'attendance.view_team', 'attendance.manage_shifts',
            'overtime.view', 'overtime.submit', 'overtime.supervise',
            'leaves.view_team', 'leaves.file_on_behalf',
            'leaves.head_approve',
            'loans.view_department',
            'inventory.locations.view',
            'inventory.mrq.view', 'inventory.mrq.fulfill',
            'procurement.goods-receipt.view',
            'procurement.purchase-order.view',
        ];

        $officerAdds = [
            'attendance.view_anomalies', 'attendance.import_csv', 'attendance.resolve_anomalies',
            'overtime.approve', 'overtime.reject',
            'leaves.approve', 'leaves.reject',
            'loans.head_note',
            'inventory.categories.view',
            'reports.inventory',
        ];

        $managerAdds = [
            'employees.view_full_record', 'employees.view_masked_gov_ids',
            'inventory.items.create', 'inventory.items.edit',
            'inventory.categories.manage',
            'inventory.locations.manage',
            'inventory.adjustments.create',
            'procurement.goods-receipt.post',
            'delivery.manage',
        ];

        $head = array_merge($staffBase, $headAdds);
        $officer = array_merge($head, $officerAdds);
        $manager = array_merge($officer, $managerAdds);

        $this->updateModulePermission('warehouse', 'staff', $staffBase);
        $this->updateModulePermission('warehouse', 'head', $head);
        $this->updateModulePermission('warehouse', 'officer', $officer);
        $this->updateModulePermission('warehouse', 'manager', $manager);

        $this->command->info('  ✓ Warehouse permissions updated');
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // SALES MODULE
    // ═══════════════════════════════════════════════════════════════════════════
    private function seedSalesPermissions(): void
    {
        $this->command->info('─ Sales Module ────────────────────────────────────────────────');

        $staffBase = [
            'self.view_profile', 'self.submit_profile_update', 'self.view_attendance',
            'payroll.view_own_payslip', 'payroll.download_own_payslip',
            'payslips.view', 'payslips.download',
            'leaves.view_own', 'leaves.file_own', 'leaves.cancel',
            'loans.view_own', 'loans.apply',
            'attendance.view_own',
            'employees.view',
            'customers.view',
            'crm.tickets.view',
        ];

        $headAdds = [
            'employees.view_team',
            'attendance.view_team', 'attendance.manage_shifts',
            'overtime.view', 'overtime.submit', 'overtime.supervise',
            'leaves.view_team', 'leaves.file_on_behalf',
            'leaves.head_approve',
            'loans.view_department',
            'customer_invoices.view',
            'crm.tickets.create',
            'delivery.view',
        ];

        $officerAdds = [
            'attendance.view_anomalies', 'attendance.import_csv', 'attendance.resolve_anomalies',
            'overtime.approve', 'overtime.reject',
            'leaves.approve', 'leaves.reject',
            'loans.head_note',
            'customer_invoices.approve',
            'crm.tickets.reply', 'crm.tickets.manage',
            'production.delivery-schedule.view',
            'reports.sales',
        ];

        $managerAdds = [
            'employees.view_full_record', 'employees.view_masked_gov_ids',
            'customers.create', 'customers.edit',
            'customer_invoices.create',
            'crm.tickets.assign', 'crm.tickets.close',
        ];

        $head = array_merge($staffBase, $headAdds);
        $officer = array_merge($head, $officerAdds);
        $manager = array_merge($officer, $managerAdds);

        $this->updateModulePermission('sales', 'staff', $staffBase);
        $this->updateModulePermission('sales', 'head', $head);
        $this->updateModulePermission('sales', 'officer', $officer);
        $this->updateModulePermission('sales', 'manager', $manager);

        $this->command->info('  ✓ Sales permissions updated');
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // OPERATIONS MODULE (IT, ISO, Maintenance, etc.)
    // ═══════════════════════════════════════════════════════════════════════════
    private function seedOperationsPermissions(): void
    {
        $this->command->info('─ Operations Module ───────────────────────────────────────────');

        $staffBase = [
            'self.view_profile', 'self.submit_profile_update', 'self.view_attendance',
            'payroll.view_own_payslip', 'payroll.download_own_payslip',
            'payslips.view', 'payslips.download',
            'leaves.view_own', 'leaves.file_own', 'leaves.cancel',
            'loans.view_own', 'loans.apply',
            'attendance.view_own',
            'employees.view',
            'iso.view',
            'maintenance.view',
        ];

        $headAdds = [
            'employees.view_team',
            'attendance.view_team', 'attendance.manage_shifts',
            'overtime.view', 'overtime.submit', 'overtime.supervise',
            'leaves.view_team', 'leaves.file_on_behalf',
            'leaves.head_approve',
            'loans.view_department',
            'system.settings.view',
        ];

        $officerAdds = [
            'attendance.view_anomalies', 'attendance.import_csv', 'attendance.resolve_anomalies',
            'overtime.approve', 'overtime.reject',
            'leaves.approve', 'leaves.reject',
            'loans.head_note',
            'iso.manage',
        ];

        $managerAdds = [
            'employees.view_full_record', 'employees.view_masked_gov_ids',
            'iso.audit',
            'maintenance.manage',
            'system.settings.manage',
        ];

        $head = array_merge($staffBase, $headAdds);
        $officer = array_merge($head, $officerAdds);
        $manager = array_merge($officer, $managerAdds);

        $this->updateModulePermission('operations', 'staff', $staffBase);
        $this->updateModulePermission('operations', 'head', $head);
        $this->updateModulePermission('operations', 'officer', $officer);
        $this->updateModulePermission('operations', 'manager', $manager);

        $this->command->info('  ✓ Operations permissions updated');
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // HELPER
    // ═══════════════════════════════════════════════════════════════════════════

    private function updateModulePermission(string $module, string $role, array $permissions): void
    {
        $mp = ModulePermission::firstOrCreate(
            ['module_key' => $module, 'role' => $role],
            ['is_active' => true, 'created_by' => 1]
        );

        $mp->permissions = array_values(array_unique($permissions));
        $mp->save();
    }
}
