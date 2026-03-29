<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Role & Permission seeder — RBAC v2.0
 *
 * ## Simplified Role Hierarchy (7 core roles + 2 portal roles)
 *
 * Permissions are determined by: Role + Department Module
 *
 * ### Core Roles:
 *   super_admin         — System god mode: ALL permissions, bypasses all checks
 *   admin               — System custodian: users, settings, no business data
 *   executive           — Chairman/President: read-only board oversight
 *   vice_president      — VP: final approver for financial requests
 *   manager             — Full department module access (HR/Accounting/Production/etc)
 *   officer             — Department operations (create, process, but not final approve)
 *   head                — Team supervisor: first-level approvals
 *   staff               — Rank-and-file: self-service, create requests
 *
 * ### Portal Roles:
 *   vendor              — Vendor portal: view POs, update fulfillment
 *   client              — Client portal: view tickets, create support requests
 *
 * ### Department Modules (determine effective permissions):
 *   hr          → employees.*, attendance.*, payroll.*, leaves.*, loans.*
 *   accounting  → journal_entries.*, ap.*, ar.*, banking.*, reports.*
 *   production  → production.*, qc.*, maintenance.*, mold.*, inventory.view
 *   sales       → crm.*, customers.view
 *   warehouse   → inventory.*, mrq.*, delivery.view
 *   purchasing  → procurement.*, vendors.view
 *   operations  → limited access (IT, Executive, ISO)
 *
 * ## SoD rules encoded in permissions (enforced in Policies):
 *   SOD-001 → employees.activate (creator ≠ activator)
 *   SOD-002 → leaves.approve (supervisor ≠ requester)
 *   SOD-003 → overtime.approve (supervisor ≠ requester)
 *   SOD-004 → loans.hr_approve (HR ≠ requester)
 *   SOD-005/006 → payroll.hr_approve (HR prepares, Accounting approves)
 *   SOD-007 → payroll.acctg_approve (Accounting ≠ HR who prepared)
 *   SOD-008 → journal_entries.post (creator ≠ poster)
 *   SOD-009 → vendor_invoices.approve (creator ≠ approver)
 *   SOD-010 → customer_invoices.approve (creator ≠ approver)
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
        'procurement.purchase-request.create-dept',  // For department heads creating PRs for their own dept
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
        // Client Portal
        'client_portal.view_dashboard',
        'client_portal.view_shop',
        'client_portal.view_orders',
        // CRM
        'crm.tickets.view',
        'crm.tickets.create',
        'crm.tickets.reply',
        'crm.tickets.manage',
        'crm.tickets.assign',
        'crm.tickets.close',
        // CRM Leads & Opportunities (Phase 1)
        'crm.leads.view',
        'crm.leads.manage',
        'crm.leads.convert',
        'crm.opportunities.view',
        'crm.opportunities.manage',
        // CRM Client Orders (Sales)
        'sales.order_review',
        'sales.order_approve',
        'sales.order_reject',
        'sales.order_negotiate',
        'sales.order_vp_approve',
        // Sales Quotations & Orders (Phase 1)
        'sales.quotations.view',
        'sales.quotations.create',
        'sales.quotations.update',
        'sales.quotations.send',
        'sales.quotations.accept',
        'sales.quotations.manage',
        'sales.orders.view',
        'sales.orders.confirm',
        'sales.orders.cancel',
        'sales.orders.manage',
        'sales.pricing.view',
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
        // Inventory Physical Counts & Transfers (Phase 1)
        'inventory.physical_count.view',
        'inventory.physical_count.manage',
        'inventory.transfers.manage',
        // AP Payment Batches (Phase 2)
        'ap.payment_batches.view',
        'ap.payment_batches.create',
        'ap.payment_batches.manage',
        'ap.payment_batches.approve',
        // AR Dunning (Phase 2)
        'ar.dunning.view',
        'ar.dunning.create',
        'ar.dunning.send',
        'ar.dunning.manage',
        // Recruitment
        'recruitment.requisitions.view',
        'recruitment.requisitions.create',
        'recruitment.requisitions.edit',
        'recruitment.requisitions.submit',
        'recruitment.requisitions.approve',
        'recruitment.requisitions.reject',
        'recruitment.requisitions.cancel',
        'recruitment.postings.view',
        'recruitment.postings.create',
        'recruitment.postings.publish',
        'recruitment.postings.close',
        'recruitment.applications.view',
        'recruitment.applications.review',
        'recruitment.applications.shortlist',
        'recruitment.applications.reject',
        'recruitment.interviews.view',
        'recruitment.interviews.schedule',
        'recruitment.interviews.evaluate',
        'recruitment.offers.view',
        'recruitment.offers.create',
        'recruitment.offers.send',
        'recruitment.preemployment.view',
        'recruitment.preemployment.verify',
        'recruitment.hiring.execute',
        'recruitment.reports.view',
        'recruitment.candidates.view',
        'recruitment.candidates.manage',
        // HR Training & Competency (Phase 3)
        'hr.training.view',
        'hr.training.manage',
        'hr.competency.view',
        'hr.competency.manage',
        // Delivery Routes (Phase 3)
        'delivery.routes.view',
        'delivery.routes.manage',
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
        'fixed_assets.dispose',
        'fixed_assets.transfer',
        // Budget Forecast (Phase 4)
        'budget.forecast',
        // Payroll (SoD: finance_manager does NOT have initiate/hr_approve - those are HR Manager only)
        'payroll.view_own_payslip',
        'payroll.download_own_payslip',
        'payroll.view_runs',
        'payroll.manage_pay_periods',
        // 'payroll.initiate',       // REMOVED: SoD conflict with acctg_approve
        // 'payroll.pre_run_validate', // REMOVED: SoD conflict
        // 'payroll.compute',        // REMOVED: SoD conflict
        // 'payroll.review_breakdown', // REMOVED: SoD conflict
        // 'payroll.flag_employee',  // REMOVED: SoD conflict
        // 'payroll.submit_for_hr',  // REMOVED: SoD conflict
        'payroll.hr_approve',        // SoD-005/006: HR prepares, Accounting approves
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
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        foreach (self::PERMISSIONS as $name) {
            Permission::findOrCreate($name, self::GUARD);
        }

        // ── RBAC v2: 7 Core Roles + 2 Portal Roles ──────────────────────────
        $admin = Role::findOrCreate('admin', self::GUARD);
        $superAdmin = Role::findOrCreate('super_admin', self::GUARD);
        $executive = Role::findOrCreate('executive', self::GUARD);
        $vicePresident = Role::findOrCreate('vice_president', self::GUARD);
        $manager = Role::findOrCreate('manager', self::GUARD);
        $officer = Role::findOrCreate('officer', self::GUARD);
        $head = Role::findOrCreate('head', self::GUARD);
        $staff = Role::findOrCreate('staff', self::GUARD);
        $vendor = Role::findOrCreate('vendor', self::GUARD);
        $client = Role::findOrCreate('client', self::GUARD);

        // Note: Old specific roles (plant_manager, ga_officer, etc.) have been
        // removed in RBAC v2. Use generic roles + department modules instead.
        // Run: php artisan rbac:cleanup-old-roles to migrate existing users.

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
            'leaves.view_own', 'leaves.view_team', 'leaves.executive_approve',
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
            // Extended view access
            'bank_accounts.view', 'bank_reconciliations.view',
            'procurement.purchase-request.view', 'procurement.purchase-order.view',
            'inventory.items.view', 'inventory.stock.view',
            'production.orders.view', 'qc.inspections.view', 'qc.ncr.view',
            'maintenance.view', 'mold.view',
        ]);

        // ── Vice President — final approver with broad access
        $vicePresident->syncPermissions([
            // Employee view
            'employees.view', 'employees.view_team', 'employees.view_full_record',
            // Approvals
            'approvals.vp.view', 'approvals.vp.approve',
            // Payroll VP approval
            'payroll.view_runs', 'payroll.vp_approve',
            'payroll.view_own_payslip', 'payroll.download_own_payslip',
            // Procurement VP approval
            'procurement.purchase-request.view', 'procurement.purchase-request.review',
            'procurement.purchase-order.view',
            // MRQ VP approval
            'inventory.mrq.view', 'inventory.mrq.vp_approve',
            // Accounting view
            'journal_entries.view', 'chart_of_accounts.view', 'fiscal_periods.view',
            'vendors.view', 'vendor_invoices.view', 'vendor_invoices.approve',
            'customers.view', 'customer_invoices.view', 'customer_invoices.approve',
            'bank_accounts.view', 'bank_reconciliations.view',
            // AR/AP Aging
            'reports.ap_aging', 'reports.ar_aging',
            'reports.financial_statements', 'reports.gl', 'reports.trial_balance',
            'reports.vat', 'reports.bank_reconciliation',
            // Budget
            'budget.view', 'budget.approve',
            // Fixed Assets
            'fixed_assets.view',
            // Loans VP approval
            'loans.view_department', 'loans.vp_approve',
            // Inventory view (executive-level read access)
            'inventory.items.view', 'inventory.stock.view',
            // Procurement full view (includes goods receipts)
            'procurement.goods-receipt.view',
            // Production view
            'production.orders.view', 'production.delivery-schedule.view',
            // QC + Maintenance view
            'qc.inspections.view', 'qc.ncr.view',
            'maintenance.view',
            // Sales/Client Orders
            'sales.order_review', 'sales.order_approve', 'sales.order_reject', 'sales.order_negotiate',
            'sales.order_vp_approve',
            // New module permissions (Phase 1-4)
            'crm.leads.view', 'crm.opportunities.view',
            'sales.quotations.view', 'sales.orders.view', 'sales.pricing.view',
            'inventory.physical_count.view',
            'ap.payment_batches.view',
            'ar.dunning.view',
            'hr.training.view', 'hr.competency.view',
            'delivery.routes.view',
            'fixed_assets.transfer',
            'budget.forecast',
            // Self-service
            'self.view_profile', 'self.view_attendance',
            'leaves.view_own', 'leaves.view_team',
            'overtime.view',
        ]);

        // ── Manager — Department Managers (HR Manager, Accounting Manager, etc.)
        // Full access to their respective department modules
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
            'loans.hr_approve', 'loans.accounting_approve', 'loans.view_ln007_log', 'loans.configure_types',
            'loans.manager_check', 'loans.officer_review',
            // NOTE: loans.vp_approve is EXCLUSIVE to vice_president role (SoD-014)
            // Budget (view-only)
            'budget.view', 'budget.manage', 'budget.approve',
            // Fixed Assets
            'fixed_assets.view', 'fixed_assets.manage', 'fixed_assets.dispose',
            // Accounting / GL / AP / AR (for Accounting Manager)
            'journal_entries.view', 'journal_entries.create', 'journal_entries.update',
            'journal_entries.submit', 'journal_entries.post', 'journal_entries.reverse',
            'journal_entries.export',
            'chart_of_accounts.view', 'chart_of_accounts.manage',
            'fiscal_periods.view', 'fiscal_periods.manage',
            // Vendors/AP
            'vendors.view', 'vendors.manage', 'vendors.archive', 'vendors.accredit', 'vendors.suspend',
            'vendor_invoices.view', 'vendor_invoices.create', 'vendor_invoices.update',
            'vendor_invoices.submit', 'vendor_invoices.approve', 'vendor_invoices.reject',
            'vendor_invoices.record_payment', 'vendor_invoices.cancel', 'vendor_invoices.export',
            'vendor_payments.view', 'vendor_payments.create',
            'bir_2307.generate',
            // Customers/AR
            'customers.view', 'customers.manage', 'customers.archive',
            'customer_invoices.view', 'customer_invoices.create', 'customer_invoices.update',
            'customer_invoices.approve', 'customer_invoices.cancel',
            'customer_invoices.override_credit', 'customer_invoices.receive_payment',
            'customer_invoices.write_off', 'customer_invoices.apply_payment', 'customer_invoices.export',
            // Banking
            'bank_accounts.view', 'bank_accounts.create', 'bank_accounts.update', 'bank_accounts.delete',
            'bank_reconciliations.view', 'bank_reconciliations.create', 'bank_reconciliations.certify',
            // Tax
            'reports.vat',
            // Financial Reports
            'reports.financial_statements', 'reports.gl', 'reports.trial_balance',
            'reports.ap_aging', 'reports.ar_aging', 'reports.bank_reconciliation',
            // Procurement (full access for Procurement/Warehouse managers)
            'procurement.purchase-request.view', 'procurement.purchase-request.create',
            'procurement.purchase-order.view', 'procurement.purchase-order.create', 'procurement.purchase-order.manage',
            'procurement.goods-receipt.view', 'procurement.goods-receipt.create', 'procurement.goods-receipt.confirm',
            // Production/QC/Maintenance view access
            'production.bom.view', 'production.delivery-schedule.view', 'production.orders.view',
            'production.orders.create', 'production.orders.release', 'production.orders.complete', 'production.orders.log_output',
            'qc.inspections.view', 'qc.inspections.create', 'qc.ncr.view', 'qc.ncr.create', 'qc.ncr.close', 'qc.templates.view',
            'maintenance.view', 'maintenance.manage',
            'mold.view', 'mold.manage', 'mold.log_shots',
            'delivery.view', 'delivery.manage',
            // Inventory (full access for Warehouse/Procurement managers)
            'inventory.items.view', 'inventory.items.create', 'inventory.items.edit',
            'inventory.stock.view', 'inventory.locations.view', 'inventory.locations.manage',
            'inventory.adjustments.create',
            // New module permissions (Phase 1-4) — Manager gets full access
            'crm.leads.view', 'crm.leads.manage', 'crm.leads.convert',
            'crm.opportunities.view', 'crm.opportunities.manage',
            'sales.quotations.view', 'sales.quotations.create', 'sales.quotations.update', 'sales.quotations.send', 'sales.quotations.accept', 'sales.quotations.manage',
            'sales.orders.view', 'sales.orders.confirm', 'sales.orders.cancel', 'sales.orders.manage',
            'sales.pricing.view',
            'inventory.physical_count.view', 'inventory.physical_count.manage',
            'inventory.transfers.manage',
            'ap.payment_batches.view', 'ap.payment_batches.create', 'ap.payment_batches.manage', 'ap.payment_batches.approve',
            'ar.dunning.view', 'ar.dunning.create', 'ar.dunning.send', 'ar.dunning.manage',
            'hr.training.view', 'hr.training.manage',
            'hr.competency.view', 'hr.competency.manage',
            'delivery.routes.view', 'delivery.routes.manage',
            'fixed_assets.transfer',
            'budget.forecast',
            // Inventory (Step 3 checker for MRQ — view context only; no warehouse operations)
            'inventory.mrq.view', 'inventory.mrq.create', 'inventory.mrq.note', 'inventory.mrq.check', 'inventory.mrq.review', 'inventory.mrq.vp_approve', 'inventory.mrq.fulfill',
            // Payroll (Accounting Manager needs acctg_approve, not hr_approve)
            'payroll.view_own_payslip', 'payroll.download_own_payslip',
            'payroll.view_runs', 'payroll.manage_pay_periods',
            'payroll.initiate',        // Legacy alias for payroll creation
            'payroll.hr_approve',      // SoD-005/006: HR reviews payroll before Accounting
            'payroll.acctg_approve',   // SoD-007: Accounting approves after HR
            'payroll.acctg_reject',
            'payroll.hr_return',
            'payroll.disburse', 'payroll.download_bank_file',
            'payroll.publish', 'payroll.view_deduction_trace',
            'payroll.download_register', 'payroll.gov_reports',
            // NOTE: payroll.vp_approve is EXCLUSIVE to vice_president role (SoD — VP is final approver)
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

        // ── Officer (Accounting/Finance) ─────────────────────────────────────
        $officer->syncPermissions([
            // Accounting - full CRUD
            'journal_entries.view', 'journal_entries.create', 'journal_entries.update',
            'journal_entries.submit', 'journal_entries.post', 'journal_entries.reverse',
            'journal_entries.export',
            'chart_of_accounts.view', 'chart_of_accounts.manage',
            'fiscal_periods.view', 'fiscal_periods.manage',
            // Vendors/AP
            'vendors.view', 'vendors.manage', 'vendors.archive', 'vendors.accredit', 'vendors.suspend',
            'vendor_invoices.view', 'vendor_invoices.create', 'vendor_invoices.update', 'vendor_invoices.approve',
            'vendor_invoices.submit', 'vendor_invoices.reject', 'vendor_invoices.cancel',
            'vendor_invoices.record_payment', 'vendor_invoices.export',
            'vendor_payments.view', 'vendor_payments.create',
            'bir_2307.generate',
            // Customers/AR - full access including credit notes
            'customers.view', 'customers.manage', 'customers.archive',
            'customer_invoices.view', 'customer_invoices.create', 'customer_invoices.update', 'customer_invoices.approve',
            'customer_invoices.cancel', 'customer_invoices.override_credit', 'customer_invoices.receive_payment',
            'customer_invoices.write_off', 'customer_invoices.apply_payment', 'customer_invoices.export',
            // Banking
            'bank_accounts.view', 'bank_accounts.create', 'bank_accounts.update', 'bank_accounts.delete',
            'bank_reconciliations.view', 'bank_reconciliations.create', 'bank_reconciliations.certify',
            // Payroll view (for dashboard)
            'payroll.view_runs', 'payroll.view_own_payslip', 'payroll.download_own_payslip',
            // Loans view
            'loans.view_department',
            // Budget view
            'budget.view',
            // PR Budget verification (Accounting Officer commits funds)
            'procurement.purchase-request.budget-check',
            // Fixed Assets view
            'fixed_assets.view',
            // Tax
            'reports.vat',
            // BIR Reports
            'reports.bir_2316', 'reports.bir_alphalist', 'reports.bir_1601c',
            'reports.sss_sbr2', 'reports.philhealth_rf1', 'reports.pagibig_mc',
            // Reports
            'reports.financial_statements', 'reports.gl', 'reports.trial_balance',
            'reports.ap_aging', 'reports.ar_aging', 'reports.bank_reconciliation',
            // Self-service
            'self.view_profile', 'self.view_attendance',
            'overtime.view', 'overtime.submit',
            // HR view (for cross-reference)
            'employees.view',
            // Legacy aliases
            'payroll.view', 'loans.view',
            // ═══════════════════════════════════════════════════════════════════════
            // TEAM MANAGEMENT (for officers in ALL departments)
            // ═══════════════════════════════════════════════════════════════════════
            'employees.view_team', 'employees.view_full_record', 'employees.view_masked_gov_ids',
            'employees.upload_documents', 'employees.download_documents',
            'attendance.view_team', 'attendance.view_anomalies', 'attendance.manage_shifts',
            'overtime.view', 'overtime.submit', 'overtime.supervise', 'overtime.approve', 'overtime.reject',
            'leaves.view_team', 'leaves.file_on_behalf', 'leaves.approve', 'leaves.reject',
            'loans.view_department',
            // ═══════════════════════════════════════════════════════════════════════
            // PURCHASING OFFICER permissions (same role, PURCH department)
            // ═══════════════════════════════════════════════════════════════════════
            // Inventory view (to see items when creating PRs)
            'inventory.items.view', 'inventory.items.create', 'inventory.items.edit',
            'inventory.stock.view', 'inventory.locations.view',
            'inventory.mrq.view', 'inventory.mrq.create',
            'inventory.adjustments.create',
            // Procurement full access (Purchasing Officer does technical review)
            'procurement.purchase-request.view', 'procurement.purchase-request.create',
            'procurement.purchase-request.review',  // Technical review of PRs
            'procurement.purchase-order.view', 'procurement.purchase-order.create', 'procurement.purchase-order.manage',
            'procurement.goods-receipt.view', 'procurement.goods-receipt.create', 'procurement.goods-receipt.confirm',
            // Production view (for PPC officers)
            'production.bom.view', 'production.delivery-schedule.view', 'production.orders.view',
            // Vendors (Purchasing Officers can create/manage vendors)
            'vendors.view', 'vendors.manage', 'vendors.archive', 'vendors.accredit', 'vendors.suspend',
            'vendor_invoices.view',
            // New module permissions (Phase 1-4) — Officer level
            'crm.leads.view', 'crm.leads.manage',
            'crm.opportunities.view', 'crm.opportunities.manage',
            'sales.quotations.view', 'sales.quotations.create', 'sales.quotations.update', 'sales.quotations.send', 'sales.quotations.accept', 'sales.quotations.manage',
            'sales.orders.view', 'sales.orders.confirm', 'sales.orders.cancel', 'sales.orders.manage',
            'sales.pricing.view',
            'inventory.physical_count.view', 'inventory.physical_count.manage',
            'inventory.transfers.manage',
            'ap.payment_batches.view', 'ap.payment_batches.manage',
            'ar.dunning.view', 'ar.dunning.manage',
            'hr.training.view', 'hr.training.manage',
            'hr.competency.view',
            'delivery.routes.view',
            'fixed_assets.transfer',
            'budget.forecast',
        ]);

        // ── Head (Department Supervisor) ─────────────────────────────────────
        $head->syncPermissions([
            // Team view (full team management for supervisors)
            'employees.view', 'employees.view_team', 'employees.view_full_record', 'employees.view_masked_gov_ids',
            'employees.upload_documents', 'employees.download_documents',
            // Attendance - view team and endorse OT
            'attendance.view_team', 'attendance.view_anomalies', 'attendance.manage_shifts',
            'overtime.view', 'overtime.submit', 'overtime.supervise', 'overtime.approve', 'overtime.reject',
            // Leave - first-level approval
            'leaves.view_team', 'leaves.file_on_behalf', 'leaves.head_approve', 'leaves.approve', 'leaves.reject',
            // Loans - head note
            'loans.view_department', 'loans.head_note', 'loans.apply',
            // Payroll - view only
            'payroll.view_runs',
            // Inventory (for Warehouse Head, Production Head)
            'inventory.items.view', 'inventory.stock.view', 'inventory.locations.view',
            'inventory.mrq.view', 'inventory.mrq.create', 'inventory.mrq.note',
            'inventory.adjustments.create',
            // Procurement (for Purchasing Head, Production Head)
            'procurement.purchase-request.view', 'procurement.purchase-request.create',
            'procurement.purchase-request.create-dept',  // Dept heads create for own dept only
            'procurement.purchase-order.view', 'procurement.goods-receipt.view',
            // Production (for Production Head, Plant Head)
            'production.bom.view', 'production.delivery-schedule.view', 'production.orders.view',
            'production.orders.create', 'production.orders.release',
            // QC (for QC Head, Production Head)
            'qc.templates.view', 'qc.inspections.view', 'qc.inspections.create',
            'qc.ncr.view', 'qc.ncr.create',
            // Maintenance (for Maintenance Head, Plant Head)
            'maintenance.view', 'maintenance.manage',
            // Mold (for Production Head)
            'mold.view', 'mold.manage', 'mold.log_shots',
            // Delivery (for Warehouse Head)
            'delivery.view', 'delivery.manage',
            // ISO (for QC Head)
            'iso.view', 'iso.audit',
            // CRM (for Sales Head)
            'crm.tickets.view', 'crm.tickets.create', 'crm.tickets.reply', 'crm.tickets.manage',
            'crm.leads.view', 'crm.leads.manage', 'crm.leads.convert',
            'crm.opportunities.view', 'crm.opportunities.manage',
            // Sales/Client Orders — head can review only; approve/reject/negotiate is Sales Manager/VP scope
            'sales.order_review',
            'sales.quotations.view', 'sales.quotations.create', 'sales.quotations.update', 'sales.quotations.send', 'sales.quotations.accept', 'sales.quotations.manage',
            'sales.orders.view', 'sales.orders.confirm', 'sales.orders.cancel', 'sales.orders.manage',
            'sales.pricing.view',
            // Inventory physical counts & transfers (Warehouse Head)
            'inventory.physical_count.view', 'inventory.physical_count.manage',
            'inventory.transfers.manage',
            // AP Payment Batches (Accounting Head)
            'ap.payment_batches.view',
            // AR Dunning (Accounting/Sales Head)
            'ar.dunning.view', 'ar.dunning.manage',
            // HR Training (for all dept heads)
            'hr.training.view',
            'hr.competency.view',
            // Delivery Routes (Warehouse Head)
            'delivery.routes.view', 'delivery.routes.manage',
            // Fixed Assets (view + transfer)
            'fixed_assets.view', 'fixed_assets.transfer',
            // Budget (view + forecast)
            'budget.view', 'budget.forecast',
            // Accounting view (for cross-reference)
            'journal_entries.view', 'chart_of_accounts.view',
            'vendors.view', 'customers.view',
            // Self-service
            'self.view_profile', 'self.submit_profile_update', 'self.view_attendance',
            'leaves.view_own', 'leaves.file_own',
            'loans.view_own', 'loans.apply',
            'overtime.view', 'overtime.submit',
            'payroll.view_own_payslip', 'payroll.download_own_payslip',
            'employees.view_masked_gov_ids',
            // Legacy aliases
            'attendance.view', 'leaves.view', 'loans.view', 'payroll.view',
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
            // Inventory (Staff: view only — MRQ creation is Head/Manager responsibility)
            'inventory.items.view', 'inventory.stock.view', 'inventory.locations.view',
            'inventory.mrq.view',
            // Production (Staff: log output)
            'production.bom.view', 'production.orders.view', 'production.orders.log_output',
            'production.delivery-schedule.view',
            // QC (Staff: incoming/in-process inspection)
            'qc.inspections.view', 'qc.inspections.create',
            'qc.ncr.view', 'qc.ncr.create',
            // Maintenance (Staff: view equipment, create corrective WO)
            'maintenance.view',
            // Mold (Staff: view for navigation + log shots)
            'mold.view', 'mold.log_shots',
            // Delivery (Staff: view for warehouse staff)
            'delivery.view',
            // New module permissions (Phase 1-4) — Staff: view only
            'crm.leads.view', 'crm.opportunities.view',
            'sales.quotations.view', 'sales.orders.view', 'sales.pricing.view',
            'inventory.physical_count.view',
            'ar.dunning.view',
            'hr.training.view', 'hr.competency.view',
            'delivery.routes.view',
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
            'client_portal.view_dashboard',
            'client_portal.view_shop',
            'client_portal.view_orders',
            'crm.tickets.view',
            'crm.tickets.create',
            'crm.tickets.reply',
        ]);

        // ── Bootstrap admin user (only system account; no employee record needed) ──
        $adminUser = User::firstOrCreate(
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
        $superAdminUser = User::firstOrCreate(
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
