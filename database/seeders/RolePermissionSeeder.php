<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Role & Permission seeder — implements ogami_role_permission_matrix.md v2.0
 *
 * ## Role hierarchy (11 roles)
 *   admin               — system custodian, zero business data access
 *   executive           — Chairman/President, read-only board observers
 *   vice_president      — VP, final approver of all financial requests (SOD-011–014)
 *   manager             — HR Manager only: full HR, payroll, employee management
 *   plant_manager       — Plant Manager only: oversees ALL plant operations (Production, QC, Maintenance, Mold, Delivery, ISO)
 *   production_manager  — Production Manager: supervises production activities only
 *   qc_manager          — QC/QA Manager: manages quality control and assurance only
 *   mold_manager        — Mold Manager: oversees mold department only
 *   officer             — Accounting Officer: full financial management (GL/AP/AR/Payroll/Banking)
 *   ga_officer          — GA Officer: HR admin support (HR, attendance, leave) — NO financials
 *   purchasing_officer  — Purchasing Officer: procurement + ordering management — NO financials
 *   impex_officer       — ImpEx Officer: delivery/shipments + import-export — NO financials
 *   head                — All Department Heads, Step 2 approver (renamed from supervisor)
 *   staff               — Rank-and-file, creates and submits requests
 *   super_admin         — Testing superuser: ALL permissions, bypasses Gate + SoD + dept-scope
 *
 * ## SoD rules encoded in permissions (enforced in Policies):
 *   SOD-001 → employees.activate
 *   SOD-002 → leaves.approve
 *   SOD-003 → overtime.approve
 *   SOD-004 → loans.hr_approve
 *   SOD-005/006 → payroll.hr_approve
 *   SOD-007 → payroll.acctg_approve
 *   SOD-008 → journal_entries.post
 *   SOD-009 → vendor_invoices.approve
 *   SOD-010 → customer_invoices.approve
 *   SOD-011 → loans.head_note (head ≠ requester)
 *   SOD-012 → loans.manager_check (manager ≠ head)
 *   SOD-013 → loans.officer_review (officer ≠ manager)
 *   SOD-014 → loans.vp_approve (vp ≠ officer)
 */
class RolePermissionSeeder extends Seeder
{
    private const GUARD = 'web';

    // All canonical permission names per the matrix
    private const PERMISSIONS = [
        // System Administration (Admin only)
        'system.manage_users',
        'system.assign_roles',
        'system.assign_departments',
        'system.unlock_accounts',
        'system.edit_settings',
        'system.manage_rate_tables',
        'system.manage_holidays',
        'system.manage_ewt_atc',
        'system.reopen_fiscal_period',
        'system.view_audit_log',
        'system.view_horizon',
        'system.view_pulse',
        'system.manage_backups',
        // HR Employee
        'employees.view',
        'employees.view_team',
        'employees.view_full_record',
        'employees.view_salary',
        'employees.view_unmasked_gov_ids',
        'employees.view_masked_gov_ids',
        'employees.create',
        'employees.update',
        'employees.update_salary',
        'employees.activate',
        'employees.suspend',
        'employees.terminate',
        'employees.upload_documents',
        'employees.download_documents',
        'employees.export',
        'employees.manage_structure',
        'hr.full_access',
        // Attendance
        'attendance.view_own',
        'attendance.view_team',
        'attendance.import_csv',
        'attendance.view_anomalies',
        'attendance.resolve_anomalies',
        'attendance.manage_shifts',
        'overtime.view',
        'overtime.submit',
        'overtime.approve',
        'overtime.reject',
        'overtime.supervise',
        'overtime.executive_approve',
        // Leave
        'leaves.view_own',
        'leaves.view_team',
        'leaves.file_own',
        'leaves.file_on_behalf',
        'leaves.cancel',
        'leaves.head_approve',
        'leaves.manager_check',
        'leaves.ga_process',
        'leaves.vp_note',
        'leaves.reject',
        'leaves.adjust_balance',
        'leaves.configure_types',
        'leaves.trigger_sil_monetization',
        // (Legacy — kept for backward compat with older tests)
        'leaves.supervise',
        'leaves.approve',
        'leaves.executive_approve',
        // Loans — v1 (legacy)
        'loans.view_own',
        'loans.view_department',
        'loans.apply',
        'loans.supervisor_review',
        'loans.hr_approve',
        'loans.accounting_approve',
        'loans.view_ln007_log',
        'loans.configure_types',
        // Loans — v2 5-stage approval chain
        'loans.head_note',
        'loans.manager_check',
        'loans.officer_review',
        'loans.vp_approve',
        // Procurement
        'procurement.purchase-request.view',
        'procurement.purchase-request.create',
        'procurement.purchase-request.note',
        'procurement.purchase-request.check',
        'procurement.purchase-request.review',
        'procurement.purchase-request.budget-check',
        'procurement.purchase-order.view',
        'procurement.purchase-order.create',
        'procurement.purchase-order.manage',
        'procurement.goods-receipt.view',
        'procurement.goods-receipt.create',
        'procurement.goods-receipt.confirm',
        // Vendor Portal
        'vendor_portal.view_orders',
        'vendor_portal.update_fulfillment',
        'vendor_portal.manage_items',
        'vendor_portal.view_receipts',
        // CRM
        'crm.tickets.view',
        'crm.tickets.create',
        'crm.tickets.reply',
        'crm.tickets.manage',
        'crm.tickets.assign',
        'crm.tickets.close',
        // Inventory / Warehouse
        'inventory.items.view',
        'inventory.items.create',
        'inventory.items.edit',
        'inventory.locations.view',
        'inventory.locations.manage',
        'inventory.stock.view',
        'inventory.adjustments.create',
        'inventory.mrq.view',
        'inventory.mrq.create',
        'inventory.mrq.note',
        'inventory.mrq.check',
        'inventory.mrq.review',
        'inventory.mrq.vp_approve',
        'inventory.mrq.fulfill',
        // Production / PPC
        'production.bom.view',
        'production.bom.manage',
        'production.delivery-schedule.view',
        'production.delivery-schedule.manage',
        'production.orders.view',
        'production.orders.create',
        'production.orders.release',
        'production.orders.complete',
        'production.orders.log_output',
        // QC / QA
        'qc.templates.view',
        'qc.templates.manage',
        'qc.inspections.view',
        'qc.inspections.create',
        'qc.ncr.view',
        'qc.ncr.create',
        'qc.ncr.close',
        // Maintenance
        'maintenance.view',
        'maintenance.manage',
        // Mold
        'mold.view',
        'mold.manage',
        'mold.log_shots',
        // Delivery / Logistics
        'delivery.view',
        'delivery.manage',
        // ISO / IATF
        'iso.view',
        'iso.manage',
        'iso.audit',
        'approvals.vp.view',
        'approvals.vp.approve',
        // Budget
        'budget.view',
        'budget.manage',
        'budget.approve',
        // Fixed Assets
        'fixed_assets.view',
        'fixed_assets.manage',
        // Payroll
        'payroll.view_own_payslip',
        'payroll.download_own_payslip',
        'payroll.view_runs',
        'payroll.manage_pay_periods',
        'payroll.initiate',
        'payroll.pre_run_validate',
        'payroll.compute',
        'payroll.review_breakdown',
        'payroll.flag_employee',
        'payroll.submit_for_hr',
        'payroll.hr_approve',
        'payroll.hr_return',
        'payroll.acctg_approve',
        'payroll.acctg_reject',
        'payroll.disburse',
        'payroll.download_bank_file',
        'payroll.publish',
        'payroll.view_deduction_trace',
        'payroll.download_register',
        'payroll.gov_reports',
        // BIR Reports
        'reports.bir_2316',
        'reports.bir_alphalist',
        'reports.bir_1601c',
        'reports.sss_sbr2',
        'reports.philhealth_rf1',
        'reports.pagibig_mc',
        // GL & Journal Entries
        'journal_entries.view',
        'journal_entries.create',
        'journal_entries.update',
        'journal_entries.submit',
        'journal_entries.post',
        'journal_entries.reverse',
        'journal_entries.export',
        'chart_of_accounts.view',
        'chart_of_accounts.manage',
        'fiscal_periods.view',
        'fiscal_periods.manage',
        // AP
        'vendors.view',
        'vendors.manage',
        'vendors.archive',
        'vendors.accredit',
        'vendors.suspend',
        'vendor_invoices.view',
        'vendor_invoices.create',
        'vendor_invoices.update',
        'vendor_invoices.submit',
        'vendor_invoices.approve',
        'vendor_invoices.reject',
        'vendor_invoices.record_payment',
        'vendor_invoices.cancel',
        'vendor_invoices.export',
        'vendor_payments.view',
        'vendor_payments.create',
        'bir_2307.generate',
        // AR
        'customers.view',
        'customers.manage',
        'customers.archive',
        'customer_invoices.view',
        'customer_invoices.create',
        'customer_invoices.update',
        'customer_invoices.approve',
        'customer_invoices.cancel',
        'customer_invoices.override_credit',
        'customer_invoices.receive_payment',
        'customer_invoices.write_off',
        'customer_invoices.apply_payment',
        'customer_invoices.export',
        // Bank Accounts & Reconciliations
        'bank_accounts.view',
        'bank_accounts.create',
        'bank_accounts.update',
        'bank_accounts.delete',
        'bank_reconciliations.view',
        'bank_reconciliations.create',
        'bank_reconciliations.certify',
        // Financial Reports
        'reports.financial_statements',
        'reports.gl',
        'reports.trial_balance',
        'reports.ap_aging',
        'reports.ar_aging',
        'reports.vat',
        'reports.bank_reconciliation',
        // Payroll VP
        'payroll.vp_approve',
        // Production QC
        'production.qc-override',
        // Self-service
        'self.view_profile',
        'self.submit_profile_update',
        'self.view_attendance',
        // Legacy aliases (keep for backward compat with old tests)
        'payroll.view',
        'payroll.initiate',
        'payroll.submit',
        'payroll.approve',
        'payroll.post',
        'payroll.recall',
        'payslips.view',
        'payslips.download',
        'leaves.view',
        'leaves.create',
        'leave_balances.view',
        'leave_balances.adjust',
        'leave_balances.manage',
        'loans.view',
        'loans.create',
        'loans.update',
        'loans.approve',
        'loans.reject',
        'loan_types.view',
        'attendance.view',
        'attendance.create',
        'attendance.update',
        'attendance.delete',
        'attendance.export',
        'attendance.lock',
        'attendance.unlock',
        'overtime.create',
        'overtime.update',
    ];

    public function run(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        foreach (self::PERMISSIONS as $name) {
            Permission::findOrCreate($name, self::GUARD);
        }

        $admin         = Role::findOrCreate('admin',          self::GUARD);
        $superAdmin    = Role::findOrCreate('super_admin',    self::GUARD);
        $executive     = Role::findOrCreate('executive',      self::GUARD);
        $vicePresident = Role::findOrCreate('vice_president', self::GUARD);
        $manager       = Role::findOrCreate('manager',        self::GUARD);
        $plantManager       = Role::findOrCreate('plant_manager',       self::GUARD);
        $productionManager  = Role::findOrCreate('production_manager',  self::GUARD);
        $qcManager          = Role::findOrCreate('qc_manager',          self::GUARD);
        $moldManager        = Role::findOrCreate('mold_manager',        self::GUARD);
        $officer            = Role::findOrCreate('officer',             self::GUARD);
        $gaOfficer          = Role::findOrCreate('ga_officer',           self::GUARD);
        $purchasingOfficer  = Role::findOrCreate('purchasing_officer',   self::GUARD);
        $impexOfficer       = Role::findOrCreate('impex_officer',        self::GUARD);
        $head          = Role::findOrCreate('head',           self::GUARD);
        $staff         = Role::findOrCreate('staff',          self::GUARD);
        $vendor        = Role::findOrCreate('vendor',         self::GUARD);
        $client        = Role::findOrCreate('client',         self::GUARD);
        $crmManager    = Role::findOrCreate('crm_manager',    self::GUARD);

        // Note: The rename migration (2026_03_05_000001) already renamed the
        //   old hr_manager → manager, accounting_manager → officer, supervisor → head.
        //   The old delete guard for 'manager' is intentionally removed — manager is now a real role.

        // ── Admin — system management only; zero business data ───────────────
        $admin->syncPermissions([
            'system.manage_users', 'system.assign_roles', 'system.assign_departments',
            'system.unlock_accounts', 'system.edit_settings',
            'system.manage_rate_tables', 'system.manage_holidays', 'system.manage_ewt_atc',
            'system.reopen_fiscal_period', 'system.view_audit_log',
            'system.view_horizon', 'system.view_pulse', 'system.manage_backups',
            'vendors.view',   // Added so Admin can access the Vendors page to provision portal accounts
            'customers.view', // Added so Admin can access the Customers page to provision portal accounts
        ]);

        // ── Executive — read-only across all modules + executive approvals ─────────────────────────
        $executive->syncPermissions([
            'employees.view',
            'attendance.view_team', 'overtime.view', 'overtime.executive_approve',
            'leaves.view_own', 'leaves.view_team',
            'loans.view_own', 'loans.view_department',
            'payroll.view_runs', 'payroll.view_own_payslip', 'payroll.download_own_payslip', 'payroll.view',
            'journal_entries.view', 'chart_of_accounts.view', 'fiscal_periods.view',
            'vendors.view', 'vendor_invoices.view', 'customers.view', 'customer_invoices.view',
            'reports.financial_statements', 'reports.gl', 'reports.trial_balance',
            'reports.ap_aging', 'reports.ar_aging', 'reports.bank_reconciliation',
            // Budget (view-only)
            'budget.view',
            // Fixed Assets (view-only)
            'fixed_assets.view',
            // Self-service (executive can file own overtime)
            'overtime.submit',
            'self.view_profile', 'self.view_attendance',
        ]);

        // ── Manager (HR Manager only) — full HR and Payroll access ──
        $manager->syncPermissions([
            // HR Employee
            'employees.view', 'employees.view_team', 'employees.view_full_record', 'employees.view_salary',
            'employees.view_unmasked_gov_ids', 'employees.view_masked_gov_ids',
            'employees.create', 'employees.update', 'employees.update_salary',
            'employees.activate', 'employees.suspend', 'employees.terminate',
            'employees.upload_documents', 'employees.download_documents',
            'employees.export', 'employees.manage_structure',
            'hr.full_access',
            // Attendance
            'attendance.view_own', 'attendance.view_team', 'attendance.import_csv',
            'attendance.view_anomalies', 'attendance.resolve_anomalies', 'attendance.manage_shifts',
            'overtime.view', 'overtime.submit', 'overtime.approve', 'overtime.reject', 'overtime.supervise',
            // Leave
            'leaves.view_own', 'leaves.view_team', 'leaves.file_own', 'leaves.file_on_behalf',
            'leaves.cancel', 'leaves.head_approve', 'leaves.manager_check', 'leaves.ga_process', 'leaves.vp_note',
            'leaves.reject', 'leaves.adjust_balance', 'leaves.configure_types', 'leaves.trigger_sil_monetization',
            'leave_balances.view', 'leave_balances.adjust', 'leave_balances.manage',
            // Loans (v1 + v2 Step 2 checker)
            'loans.view_own', 'loans.view_department', 'loans.apply',
            'loans.hr_approve', 'loans.view_ln007_log', 'loans.configure_types',
            'loans.manager_check',
            // Budget (view-only)
            'budget.view',
            // Procurement (Step 2 checker)
            'procurement.purchase-request.view', 'procurement.purchase-request.create',
            'procurement.purchase-request.check',
            'procurement.purchase-order.view',
            'vendors.view',
            // Inventory (Step 3 checker for MRQ — view context only; no warehouse operations)
            'inventory.items.view', 'inventory.locations.view', 'inventory.stock.view',
            'inventory.mrq.view', 'inventory.mrq.check',
            // Payroll
            'payroll.view_own_payslip', 'payroll.download_own_payslip',
            'payroll.view_runs', 'payroll.manage_pay_periods',
            'payroll.initiate', 'payroll.pre_run_validate', 'payroll.compute',
            'payroll.review_breakdown', 'payroll.flag_employee',
            'payroll.submit_for_hr', 'payroll.hr_approve', 'payroll.hr_return',
            'payroll.disburse', 'payroll.download_bank_file',
            'payroll.publish', 'payroll.view_deduction_trace',
            'payroll.download_register', 'payroll.gov_reports',
            // BIR & Gov Reports
            'reports.bir_2316', 'reports.bir_alphalist', 'reports.bir_1601c',
            'reports.sss_sbr2', 'reports.philhealth_rf1', 'reports.pagibig_mc',
            // Self-service
            'self.view_profile', 'self.submit_profile_update', 'self.view_attendance',
            // Legacy
            'payroll.view', 'payroll.initiate', 'payroll.submit', 'payroll.approve',
            'payroll.post', 'payroll.recall', 'payslips.view', 'payslips.download',
            'leaves.view', 'leaves.create', 'leave_balances.view', 'leave_balances.adjust',
            'loans.view', 'loans.create', 'loans.update', 'loans.approve', 'loans.reject',
            'loan_types.view', 'attendance.view', 'attendance.create', 'attendance.update',
            'attendance.delete', 'attendance.export', 'attendance.lock', 'attendance.unlock',
            'overtime.create', 'overtime.update',
        ]);

        // ── Plant Manager — ALL plant operations ─────────────────────────────
        // Responsibility: "Oversees all plant operations and activities."
        // Oversees Production, QC, Maintenance, Mold, Delivery, ISO teams.
        $plantManager->syncPermissions([
            // Production / PPC (full control)
            'production.bom.view', 'production.bom.manage',
            'production.delivery-schedule.view', 'production.delivery-schedule.manage',
            'production.orders.view', 'production.orders.create',
            'production.orders.release', 'production.orders.complete', 'production.orders.log_output',
            'production.qc-override',
            // QC / QA (full)
            'qc.templates.view', 'qc.templates.manage',
            'qc.inspections.view', 'qc.inspections.create',
            'qc.ncr.view', 'qc.ncr.create', 'qc.ncr.close',
            // Maintenance (full)
            'maintenance.view', 'maintenance.manage',
            // Mold (full)
            'mold.view', 'mold.manage', 'mold.log_shots',
            // Delivery / Logistics (full)
            'delivery.view', 'delivery.manage',
            // ISO / IATF (full)
            'iso.view', 'iso.manage', 'iso.audit',
            // Inventory — view stock for plant operations visibility + create MRQs
            'inventory.items.view',
            'inventory.stock.view',
            'inventory.locations.view',
            'inventory.mrq.view',
            'inventory.mrq.create',
            // Self-service only
            'attendance.view_own',
            'overtime.view', 'overtime.submit',
            'leaves.view_own', 'leaves.view_team', 'leaves.file_own', 'leaves.cancel',
            'leaves.manager_check',
            'loans.view_own', 'loans.apply',
            'payroll.view_own_payslip', 'payroll.download_own_payslip',
            'self.view_profile', 'self.submit_profile_update', 'self.view_attendance',
        ]);

        // ── Production Manager — supervises production activities only ──────────
        // Responsibility: "Supervises overall production activities."
        $productionManager->syncPermissions([
            // Production / PPC (full control)
            'production.bom.view', 'production.bom.manage',
            'production.delivery-schedule.view', 'production.delivery-schedule.manage',
            'production.orders.view', 'production.orders.create',
            'production.orders.release', 'production.orders.complete', 'production.orders.log_output',
            // Inventory — view only (need to know material availability)
            'inventory.items.view', 'inventory.stock.view', 'inventory.locations.view', 'inventory.mrq.view',
            // Self-service
            'attendance.view_own',
            'overtime.view', 'overtime.submit',
            'leaves.view_own', 'leaves.file_own', 'leaves.cancel',
            'loans.view_own', 'loans.apply',
            'payroll.view_own_payslip', 'payroll.download_own_payslip',
            'self.view_profile', 'self.submit_profile_update', 'self.view_attendance',
        ]);

        // ── QC/QA Manager — quality control and assurance only ──────────────────
        // Responsibility: "Manages quality control and quality assurance operations."
        $qcManager->syncPermissions([
            // QC / QA (full)
            'qc.templates.view', 'qc.templates.manage',
            'qc.inspections.view', 'qc.inspections.create',
            'qc.ncr.view', 'qc.ncr.create', 'qc.ncr.close',
            'production.qc-override',
            // Inventory — view only (items & stock for QC context)
            'inventory.items.view', 'inventory.stock.view',
            // Self-service
            'attendance.view_own',
            'overtime.view', 'overtime.submit',
            'leaves.view_own', 'leaves.file_own', 'leaves.cancel',
            'loans.view_own', 'loans.apply',
            'payroll.view_own_payslip', 'payroll.download_own_payslip',
            'self.view_profile', 'self.submit_profile_update', 'self.view_attendance',
        ]);

        // ── Mold Manager — mold department only ─────────────────────────────────
        // Responsibility: "Oversees the mold department and related operations."
        $moldManager->syncPermissions([
            // Mold (full)
            'mold.view', 'mold.manage', 'mold.log_shots',
            // Inventory — view only
            'inventory.items.view', 'inventory.stock.view',
            // Self-service
            'attendance.view_own',
            'overtime.view', 'overtime.submit',
            'leaves.view_own', 'leaves.file_own', 'leaves.cancel',
            'loans.view_own', 'loans.apply',
            'payroll.view_own_payslip', 'payroll.download_own_payslip',
            'self.view_profile', 'self.submit_profile_update', 'self.view_attendance',
        ]);

        // ── Officer (Accounting Officer) — Financial management only ────────────
        // Responsibility: "Manages all accounting and financial operations of the company."
        $officer->syncPermissions([
            // GL & Journal Entries
            'journal_entries.view', 'journal_entries.create', 'journal_entries.update',
            'journal_entries.submit', 'journal_entries.post', 'journal_entries.reverse', 'journal_entries.export',
            'chart_of_accounts.view', 'chart_of_accounts.manage',
            'fiscal_periods.view', 'fiscal_periods.manage',
            // AP
            'vendors.view', // Can view but cannot create/manage (SoD: purchasing_officer creates)
            'vendor_invoices.view', 'vendor_invoices.create', 'vendor_invoices.update',
            'vendor_invoices.submit', 'vendor_invoices.approve', 'vendor_invoices.reject',
            'vendor_invoices.record_payment', 'vendor_invoices.cancel', 'vendor_invoices.export',
            'vendor_payments.view', 'vendor_payments.create', 'bir_2307.generate',
            // AR
            'customers.view', // Can view but cannot create/manage (SoD: purchasing_officer creates)
            'customer_invoices.view', 'customer_invoices.create', 'customer_invoices.update',
            'customer_invoices.approve', 'customer_invoices.cancel', 'customer_invoices.override_credit',
            'customer_invoices.receive_payment', 'customer_invoices.write_off',
            'customer_invoices.apply_payment', 'customer_invoices.export',
            // Banking
            'bank_accounts.view', 'bank_accounts.create', 'bank_accounts.update', 'bank_accounts.delete',            'bank_reconciliations.view', 'bank_reconciliations.create', 'bank_reconciliations.certify',
            // Financial Reports
            'reports.financial_statements', 'reports.gl', 'reports.trial_balance',
            'reports.ap_aging', 'reports.ar_aging', 'reports.vat', 'reports.bank_reconciliation',
            // Payroll (approve + disburse + publish)
            'payroll.view_own_payslip', 'payroll.download_own_payslip',
            'payroll.view_runs', 'payroll.manage_pay_periods',
            'payroll.review_breakdown', 'payroll.acctg_approve', 'payroll.acctg_reject',
            'payroll.disburse', 'payroll.download_bank_file', 'payroll.publish', 'payroll.post',
            'payroll.download_register', 'payroll.gov_reports',
            // Loans (v1 accounting approval + v2 Step 3 reviewer)
            'loans.view_own', 'loans.view_department', 'loans.accounting_approve',
            'loans.officer_review',
            // Budget (full management)
            'budget.view', 'budget.manage',
            // Procurement (Step 3 reviewer + budget check + PO management)
            'procurement.purchase-request.view', 'procurement.purchase-request.review',
            'procurement.purchase-request.budget-check',
            'procurement.purchase-order.view', 'procurement.purchase-order.create', 'procurement.purchase-order.manage',
            'procurement.goods-receipt.view',
            // Inventory (Step 4 reviewer for MRQ + stock management)
            'inventory.items.view', 'inventory.items.create', 'inventory.items.edit',
            'inventory.locations.view', 'inventory.locations.manage',
            'inventory.stock.view', 'inventory.adjustments.create',
            'inventory.mrq.view', 'inventory.mrq.review',
            // Fixed Assets (full management)
            'fixed_assets.view', 'fixed_assets.manage',
            // Self-service
            'self.view_profile', 'self.submit_profile_update', 'self.view_attendance',
            // Basic employee/team view
            'employees.view', 'employees.view_full_record',
            'attendance.view_own',
            'leaves.view_own',
            'overtime.view', 'overtime.submit',
        ]);

        // ── GA Officer — General Affairs / HR Administrative Support ──────────
        // Responsibility: "Supports HR and administrative operations of the company."
        $gaOfficer->syncPermissions([
            // HR — team visibility for admin support
            'employees.view', 'employees.view_team', 'employees.view_full_record',
            'employees.upload_documents', 'employees.download_documents',
            // Attendance management
            'attendance.view_own', 'attendance.view_team', 'attendance.import_csv',
            'attendance.view_anomalies', 'attendance.resolve_anomalies', 'attendance.manage_shifts',
            // Overtime
            'overtime.view', 'overtime.submit', 'overtime.supervise',
            // Leave
            'leaves.view_own', 'leaves.view_team', 'leaves.file_own', 'leaves.file_on_behalf',
            'leaves.cancel', 'leaves.ga_process',
            'leave_balances.view',
            // Loans (own only)
            'loans.view_own', 'loans.apply',
            // Self-service
            'self.view_profile', 'self.submit_profile_update', 'self.view_attendance',
            'payroll.view_own_payslip', 'payroll.download_own_payslip',
        ]);

        // ── Purchasing Officer — Procurement and Materials Ordering ───────────
        // Responsibility: "Responsible for ordering all materials required by the company."
        $purchasingOfficer->syncPermissions([
            // Procurement (full cycle)
            'procurement.purchase-request.view', 'procurement.purchase-request.create',
            'procurement.purchase-request.review',
            'procurement.purchase-order.view', 'procurement.purchase-order.create', 'procurement.purchase-order.manage',
            'procurement.goods-receipt.view', 'procurement.goods-receipt.create', 'procurement.goods-receipt.confirm',
            // Vendors (manage + accredit + suspend + archive)
            'vendors.view', 'vendors.manage', 'vendors.accredit', 'vendors.suspend', 'vendors.archive',
            // Customers (manage - for AR master data setup)
            'customers.view', 'customers.manage',
            // Inventory (view for sourcing context)
            'inventory.items.view', 'inventory.stock.view', 'inventory.locations.view',
            'inventory.mrq.view', 'inventory.mrq.review',
            // Delivery (view — inbound receipts)
            'delivery.view',
            // Loans (own only — PO does not review loans)
            'loans.view_own', 'loans.apply',
            // Employee view
            'employees.view',
            // Self-service
            'self.view_profile', 'self.submit_profile_update', 'self.view_attendance',
            'attendance.view_own', 'overtime.view', 'overtime.submit',
            'leaves.view_own', 'leaves.file_own', 'leaves.cancel',
            'payroll.view_own_payslip', 'payroll.download_own_payslip',
        ]);

        // ── ImpEx Officer — Import / Export and Delivery Management ──────────
        // Responsibility: "Manages import/export shipments and delivery documentation."
        $impexOfficer->syncPermissions([
            // Delivery (full — primary responsibility)
            'delivery.view', 'delivery.manage',
            // Procurement (view for shipment context)
            'procurement.purchase-request.view',
            'procurement.purchase-order.view',
            'procurement.goods-receipt.view', 'procurement.goods-receipt.create', 'procurement.goods-receipt.confirm',
            // Vendors (view only)
            'vendors.view',
            // Inventory (view for shipment context)
            'inventory.items.view', 'inventory.stock.view', 'inventory.locations.view',
            // Loans (own only — per company flow, ImpEx Officer is not a loan reviewer)
            'loans.view_own', 'loans.apply',
            // Employee view
            'employees.view',
            // Self-service
            'self.view_profile', 'self.submit_profile_update', 'self.view_attendance',
            'attendance.view_own', 'overtime.view', 'overtime.submit',
            'leaves.view_own', 'leaves.file_own', 'leaves.cancel',
            'payroll.view_own_payslip', 'payroll.download_own_payslip',
        ]);

        // ── Head (renamed from Supervisor) — dept heads, Step 2 approver ───
        $head->syncPermissions([
            'employees.view', 'employees.view_team', 'employees.view_full_record',
            'employees.view_unmasked_gov_ids', 'employees.view_masked_gov_ids',
            'employees.create', 'employees.upload_documents', 'employees.download_documents',
            // Attendance
            'attendance.view_own', 'attendance.view_team', 'attendance.import_csv',
            'attendance.view_anomalies', 'attendance.resolve_anomalies', 'attendance.manage_shifts',
            'overtime.view', 'overtime.submit', 'overtime.supervise',
            // Leave
            'leaves.view_own', 'leaves.view_team', 'leaves.file_own',
            'leaves.file_on_behalf', 'leaves.cancel', 'leaves.head_approve',
            // Loans (v1 + v2)
            'loans.view_own', 'loans.apply', 'loans.supervisor_review', 'loans.head_note',
            // Procurement
            'procurement.purchase-request.view', 'procurement.purchase-request.note',
            'procurement.goods-receipt.view', 'procurement.goods-receipt.create', 'procurement.goods-receipt.confirm',
            // Inventory (Step 2 noter + Warehouse Head fulfills MRQ)
            'inventory.items.view', 'inventory.stock.view', 'inventory.locations.view',
            'inventory.mrq.view', 'inventory.mrq.create', 'inventory.mrq.note', 'inventory.mrq.fulfill',
            // Production / PPC (Head: log output + view)
            'production.bom.view', 'production.delivery-schedule.view',
            'production.orders.view', 'production.orders.log_output',
            // QC / QA (Head: inspections + NCR view)
            'qc.templates.view', 'qc.inspections.view', 'qc.inspections.create',
            'qc.ncr.view',
            // Maintenance (Head: full)
            'maintenance.view', 'maintenance.manage',
            // Mold (Head: full + log shots)
            'mold.view', 'mold.manage', 'mold.log_shots',
            // Delivery (Head: view)
            'delivery.view',
            // ISO (Head: view + audit)
            'iso.view', 'iso.audit',
            // Payroll
            'payroll.view_own_payslip', 'payroll.download_own_payslip',
            // GL (view only — dept heads do not create accounting entries)
            'journal_entries.view',
            'chart_of_accounts.view', 'fiscal_periods.view',
            // AP (view only)
            'vendors.view', 'vendor_invoices.view', 'vendor_invoices.export', 'vendor_payments.view',
            // AR (view only)
            'customers.view', 'customer_invoices.view', 'customer_invoices.export',
            // Reports (read)
            'reports.gl', 'reports.ap_aging',
            // Fixed Assets (view-only)
            'fixed_assets.view',
            // Self-service
            'self.view_profile', 'self.submit_profile_update', 'self.view_attendance',
            // Legacy
            'payslips.view', 'payslips.download', 'leaves.view', 'leaves.create',
            'leave_balances.view', 'loans.view', 'attendance.view', 'attendance.create',
            'attendance.update', 'attendance.export', 'overtime.create', 'overtime.update',
        ]);

        // ── Vice President — final financial approver, cross-department visibility
        $vicePresident->syncPermissions([
            // Final approval across all financial modules
            'approvals.vp.view', 'approvals.vp.approve',
            // Loans v2 final approval
            'loans.vp_approve',
            // Budget (approve only)
            'budget.view', 'budget.approve',
            // Payroll VP approval
            'payroll.vp_approve',
            // Procurement VP approval
            'procurement.purchase-request.view',
            'procurement.purchase-order.view',
            'procurement.goods-receipt.view',
            // Inventory VP approval for MRQ + read-only
            'inventory.items.view', 'inventory.stock.view',
            'inventory.mrq.view', 'inventory.mrq.vp_approve',
            // Production / PPC (VP: view-only)
            'production.bom.view', 'production.delivery-schedule.view', 'production.orders.view',
            // Read-only across all modules (context for approvals)
            'employees.view',
            'loans.view_own', 'loans.view_department',
            'payroll.view_runs', 'payroll.view_own_payslip', 'payroll.download_own_payslip',
            'reports.financial_statements', 'reports.trial_balance', 'reports.gl',
            'reports.ap_aging', 'reports.ar_aging',
            'journal_entries.view', 'chart_of_accounts.view', 'fiscal_periods.view',
            'vendors.view', 'vendor_invoices.view', 'customers.view', 'customer_invoices.view',
            // Fixed Assets (view-only)
            'fixed_assets.view',
            // Self-service
            'self.view_profile', 'self.submit_profile_update', 'self.view_attendance',
            'leaves.view_own', 'leaves.view_team', 'leaves.file_own', 'leaves.cancel', 'leaves.vp_note',
            'attendance.view_own', 'overtime.view', 'overtime.submit',
        ]);

        // ── Staff ────────────────────────────────────────────────────────────
        $staff->syncPermissions([
            'payroll.view_own_payslip', 'payroll.download_own_payslip',
            'leaves.view_own', 'leaves.file_own', 'leaves.cancel',
            'loans.view_own', 'loans.apply',
            'employees.view_masked_gov_ids',
            'self.view_profile', 'self.submit_profile_update', 'self.view_attendance',
            'attendance.view_own',
            'overtime.view', 'overtime.submit',
            // Inventory (Staff creates MRQ + view for sidebar navigation)
            'inventory.items.view', 'inventory.stock.view',
            'inventory.mrq.view', 'inventory.mrq.create',
            // Production (Staff: log output)
            'production.orders.view', 'production.orders.log_output',
            // Mold (Staff: view for navigation + log shots)
            'mold.view', 'mold.log_shots',
            // Legacy
            'payslips.view', 'payslips.download', 'leaves.view', 'leaves.create',
            'leave_balances.view', 'loans.view', 'attendance.view',
        ]);

        // ── Super Admin — ALL permissions (for full-system testing) ──────────
        $superAdmin->syncPermissions(Permission::all());

        // ── Vendor Portal — external vendor user access ───────────────────────
        $vendor->syncPermissions([
            'vendor_portal.view_orders',
            'vendor_portal.update_fulfillment',
            'vendor_portal.manage_items',
            'vendor_portal.view_receipts',
        ]);

        // ── Client Portal — external client/customer user access ─────────────
        $client->syncPermissions([
            'crm.tickets.view',
            'crm.tickets.create',
            'crm.tickets.reply',
        ]);

        // ── CRM Manager — manages support tickets, assigns agents, closes tickets ──
        $crmManager->syncPermissions([
            'crm.tickets.view', 'crm.tickets.create', 'crm.tickets.reply',
            'crm.tickets.manage', 'crm.tickets.assign', 'crm.tickets.close',
            // Self-service
            'self.view_profile', 'self.submit_profile_update', 'self.view_attendance',
            'attendance.view_own', 'overtime.view', 'overtime.submit',
            'leaves.view_own', 'leaves.file_own', 'leaves.cancel',
            'loans.view_own', 'loans.apply',
            'payroll.view_own_payslip', 'payroll.download_own_payslip',
        ]);

        // ── Bootstrap admin user (only system account; no employee record needed) ──
        $adminUser = \App\Models\User::firstOrCreate(
            ['email' => 'admin@ogamierp.local'],
            [
                'name' => 'System Administrator',
                'password' => 'Admin@1234567890!', // Auto-hashed by model cast
                'email_verified_at' => now(),
                'password_changed_at' => now(),
            ]
        );
        $adminUser->syncRoles(['admin']);

        // ── Bootstrap superadmin user (testing account — all modules) ─────────
        $superAdminUser = \App\Models\User::firstOrCreate(
            ['email' => 'superadmin@ogamierp.local'],
            [
                'name' => 'Super Admin',
                'password' => 'SuperAdmin@12345!', // Auto-hashed by model cast
                'email_verified_at' => now(),
                'password_changed_at' => now(),
            ]
        );
        $superAdminUser->syncRoles(['super_admin']);

        $this->command->info('✓ Roles and permissions seeded (v1.0 matrix).');
        $this->command->info('  admin@ogamierp.local      / Admin@1234567890!');
        $this->command->info('  superadmin@ogamierp.local / SuperAdmin@12345!  (all modules + SoD bypass)');
    }
}
