<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\RBAC\ModulePermission;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

/**
 * Module Permission Seeder - Defines permissions for each role within each module.
 *
 * This is where the actual permission matrix is defined.
 * 7 modules × 4 core roles = 28 permission sets.
 */
class ModulePermissionSeeder extends Seeder
{
    /**
     * Staff is intentionally restricted to self-service only, regardless of
     * module-level definitions below.
     *
     * @var array<int, string>
     */
    private const STAFF_SELF_SERVICE_PERMISSIONS = [
        'self.view_profile', 'self.submit_profile_update', 'self.view_attendance',
        'employees.view_masked_gov_ids',
        'attendance.view_own', 'attendance.time_clock', 'attendance.corrections.submit',
        'overtime.view', 'overtime.submit',
        'leaves.view_own', 'leaves.file_own', 'leaves.cancel',
        'loans.view_own', 'loans.apply',
        'payroll.view_own_payslip', 'payroll.download_own_payslip',
        'payslips.view', 'payslips.download',
        'procurement.purchase-request.create', 'vendors.view', // General staff PR creation
    ];

    /**
     * Permission definitions for each module and role.
     */
    private const PERMISSIONS = [
        // =========================================================================
        // HR MODULE
        // =========================================================================
        'hr' => [
            'manager' => [
                // Self service
                'self.view_profile', 'self.submit_profile_update', 'self.view_attendance',
                'payroll.view_own_payslip', 'payroll.download_own_payslip',
                'payslips.view', 'payslips.download',
                'leaves.view_own', 'leaves.file_own', 'leaves.cancel',
                'loans.view_own', 'loans.apply', 'attendance.view_own',
                // Team management (sidebar access)
                'employees.view', 'employees.view_team', 'employees.view_full_record', 'employees.view_masked_gov_ids',
                'employees.upload_documents', 'employees.download_documents',
                'attendance.view_team', 'attendance.view_anomalies',
                'overtime.view', 'overtime.submit', 'overtime.approve', 'overtime.reject',
                'leaves.view_team', 'leaves.file_on_behalf', 'leaves.approve', 'leaves.reject',
                'loans.view_department', 'payroll.view_runs',
                // Sidebar access
                'hr.full_access',
                // HR-specific (manager only)
                'employees.view_salary', 'employees.view_unmasked_gov_ids',
                'employees.create', 'employees.update', 'employees.update_salary',
                'employees.activate', 'employees.suspend', 'employees.terminate',
                'employees.export', 'employees.manage_structure',
                'attendance.import_csv', 'attendance.resolve_anomalies',
                'attendance.manage_shifts', 'attendance.delete', 'attendance.lock', 'attendance.unlock',
                'leaves.adjust_balance', 'leaves.configure_types', 'leaves.trigger_sil_monetization',
                'leave_balances.adjust', 'leaves.manager_approve', 'leaves.hr_approve', 'leaves.vp_approve', 'loans.hr_approve', 'loans.view_ln007_log',
                'loans.configure_types', 'loans.create', 'loans.update', 'loans.approve', 'loans.reject',
                'loans.supervisor_review', 'loans.manager_check', 'loan_types.view',
                'payroll.manage_pay_periods', 'payroll.initiate', 'payroll.pre_run_validate',
                'payroll.compute', 'payroll.review_breakdown', 'payroll.flag_employee',
                'payroll.submit_for_hr', 'payroll.hr_approve', 'payroll.hr_return', 'payroll.publish',
                'payroll.disburse', 'payroll.download_bank_file',
                'payroll.view_deduction_trace', 'payroll.download_register', 'payroll.gov_reports',
                'reports.bir_2316', 'reports.bir_alphalist', 'reports.bir_1601c',
                'reports.sss_sbr2', 'reports.philhealth_rf1', 'reports.pagibig_mc',
                // Recruitment (HR Manager: Full Access)
                'recruitment.requisitions.view', 'recruitment.requisitions.create', 'recruitment.requisitions.edit',
                'recruitment.requisitions.submit', 'recruitment.requisitions.approve', 'recruitment.requisitions.reject',
                'recruitment.requisitions.cancel', 'recruitment.postings.view', 'recruitment.postings.create',
                'recruitment.postings.publish', 'recruitment.postings.close', 'recruitment.applications.view',
                'recruitment.applications.create', 'recruitment.applications.delete',
                'recruitment.applications.review', 'recruitment.applications.shortlist', 'recruitment.applications.reject',
                'recruitment.interviews.view', 'recruitment.interviews.schedule', 'recruitment.interviews.evaluate',
                'recruitment.offers.view', 'recruitment.offers.create', 'recruitment.offers.send',
                'recruitment.preemployment.view', 'recruitment.preemployment.verify', 'recruitment.hiring.execute',
                'recruitment.reports.view', 'recruitment.candidates.view', 'recruitment.candidates.manage',
            ],
            'officer' => [
                // Self service
                'self.view_profile', 'self.submit_profile_update', 'self.view_attendance',
                'payroll.view_own_payslip', 'payroll.download_own_payslip',
                'payslips.view', 'payslips.download',
                'leaves.view_own', 'leaves.file_own', 'leaves.cancel',
                'loans.view_own', 'loans.apply', 'attendance.view_own',
                // Team management (sidebar access + team views)
                'employees.view', 'employees.view_team', 'employees.view_full_record', 'employees.view_masked_gov_ids',
                'employees.upload_documents', 'employees.download_documents',
                'attendance.view_team', 'attendance.view_anomalies',
                'overtime.view', 'overtime.submit',
                'leaves.view_team', 'leaves.file_on_behalf',
                // Limited HR operations
                'employees.view_unmasked_gov_ids',
                'attendance.import_csv', 'attendance.resolve_anomalies',
                'attendance.manage_shifts',
                'loans.view_department',
                'payroll.view_runs',
                // Loan workflow
                'loans.manager_check', 'loans.officer_review', 'loans.accounting_approve',
                // Recruitment (HR Officer: Operational only)
                'recruitment.requisitions.view', 'recruitment.requisitions.create', 'recruitment.requisitions.submit',
                'recruitment.postings.view', 'recruitment.postings.create', 'recruitment.postings.publish',
                'recruitment.applications.view', 'recruitment.applications.review', 'recruitment.applications.shortlist',
                'recruitment.interviews.view', 'recruitment.interviews.schedule', 'recruitment.interviews.evaluate',
                'recruitment.candidates.view',
            ],
            'head' => [
                // Self service
                'self.view_profile', 'self.submit_profile_update', 'self.view_attendance',
                'payroll.view_own_payslip', 'payroll.download_own_payslip',
                'payslips.view', 'payslips.download',
                'leaves.view_own', 'leaves.file_own', 'leaves.cancel',
                'loans.view_own', 'loans.apply', 'attendance.view_own',
                // Team management (supervisor level)
                'employees.view', 'employees.view_team', 'employees.view_full_record', 'employees.view_masked_gov_ids',
                'attendance.view_team', 'overtime.view', 'overtime.submit', 'overtime.supervise',
                'leaves.view_team', 'leaves.file_on_behalf',
                // Approval authority
                'leaves.head_approve', 'leaves.reject',
                'overtime.approve', 'overtime.reject',
                'employees.upload_documents', 'employees.download_documents',
                'attendance.import_csv', 'attendance.view_anomalies', 'attendance.resolve_anomalies',
                'attendance.manage_shifts',
                // Loan workflow (head actions)
                'loans.head_note', 'loans.view_department',
                // HR module access (view only for heads)
                'hr.full_access',
                'payroll.view_runs',
                // Recruitment (HR Head: Same as Officer)
                'recruitment.requisitions.view', 'recruitment.requisitions.create', 'recruitment.requisitions.submit',
                'recruitment.postings.view', 'recruitment.postings.create', 'recruitment.postings.publish',
                'recruitment.applications.view', 'recruitment.applications.review', 'recruitment.applications.shortlist',
                'recruitment.interviews.view', 'recruitment.interviews.schedule', 'recruitment.interviews.evaluate',
                'recruitment.candidates.view',
                // Procurement: all dept heads can raise PRs for their own department
                'procurement.purchase-request.view', 'procurement.purchase-request.create-dept',
            ],
            'staff' => [
                // Self service only
                'self.view_profile', 'self.submit_profile_update', 'self.view_attendance',
                'payroll.view_own_payslip', 'payroll.download_own_payslip',
                'payslips.view', 'payslips.download',
                'leaves.view_own', 'leaves.file_own', 'leaves.cancel',
                'loans.view_own', 'loans.apply', 'attendance.view_own',
                // Payslip self-service (sidebar navigation)
                'payslips.view', 'payslips.download',
                // Limited work
                'inventory.mrq.create',
            ],
        ],

        // =========================================================================
        // ACCOUNTING MODULE
        // =========================================================================
        'accounting' => [
            'manager' => [
                // Self service
                'self.view_profile', 'self.submit_profile_update', 'self.view_attendance',
                'payroll.view_own_payslip', 'payroll.download_own_payslip',
                'payslips.view', 'payslips.download',
                'leaves.view_own', 'leaves.file_own', 'leaves.cancel',
                'loans.view_own', 'loans.apply', 'attendance.view_own',
                // Team management (sidebar access)
                'employees.view', 'employees.view_team', 'employees.view_full_record', 'employees.view_masked_gov_ids',
                'employees.upload_documents', 'employees.download_documents',
                'attendance.view_team', 'attendance.view_anomalies',
                'overtime.view', 'overtime.submit', 'overtime.approve', 'overtime.reject',
                'leaves.view_team', 'leaves.file_on_behalf', 'leaves.approve', 'leaves.reject',
                'loans.view_department', 'payroll.view_runs',
                // Cross-module access for managers
                'procurement.purchase-request.view', 'procurement.purchase-request.budget-check',
                'inventory.items.view',
                // Accounting-specific
                'journal_entries.view', 'journal_entries.create', 'journal_entries.update',
                'journal_entries.submit', 'journal_entries.post', 'journal_entries.reverse', 'journal_entries.export',
                'chart_of_accounts.view', 'chart_of_accounts.manage',
                'fiscal_periods.view', 'fiscal_periods.manage',
                'vendors.view',
                'vendor_invoices.view', 'vendor_invoices.create', 'vendor_invoices.update',
                'vendor_invoices.submit', 'vendor_invoices.approve', 'vendor_invoices.reject',
                'vendor_invoices.record_payment', 'vendor_invoices.cancel', 'vendor_invoices.export',
                'vendor_payments.view', 'vendor_payments.create',
                'bir_2307.generate',
                'customers.view', 'customers.manage', 'customers.archive',
                'customer_invoices.view', 'customer_invoices.create', 'customer_invoices.update',
                'customer_invoices.approve', 'customer_invoices.cancel', 'customer_invoices.override_credit',
                'customer_invoices.receive_payment', 'customer_invoices.write_off',
                'customer_invoices.apply_payment', 'customer_invoices.export',
                'bank_accounts.view', 'bank_accounts.create', 'bank_accounts.update', 'bank_accounts.delete',
                'bank_reconciliations.view', 'bank_reconciliations.create', 'bank_reconciliations.certify',
                'reports.financial_statements', 'reports.gl', 'reports.trial_balance',
                'reports.ap_aging', 'reports.ar_aging', 'reports.vat', 'reports.bank_reconciliation',
                // Payroll approval (accounting side)
                'payroll.acctg_approve', 'payroll.acctg_reject', 'payroll.disburse',
                'payroll.download_bank_file', 'payroll.post', 'payroll.publish',
                'payroll.review_breakdown', 'payroll.download_register',
                'payroll.view_deduction_trace', 'payroll.gov_reports',
                'loans.accounting_approve',
                // Fixed assets
                'fixed_assets.view', 'fixed_assets.manage', 'fixed_assets.depreciate',
                // Budget
                'budget.view', 'budget.manage', 'budget.approve',
            ],
            'officer' => [
                // Self service
                'self.view_profile', 'self.submit_profile_update', 'self.view_attendance',
                'payroll.view_own_payslip', 'payroll.download_own_payslip',
                'payslips.view', 'payslips.download',
                'leaves.view_own', 'leaves.file_own', 'leaves.cancel',
                'loans.view_own', 'loans.apply', 'attendance.view_own',
                // Team management
                'employees.view', 'employees.view_team', 'employees.view_full_record', 'employees.view_masked_gov_ids',
                'attendance.view_team', 'overtime.view', 'overtime.submit', 'overtime.supervise',
                'leaves.view_team', 'leaves.file_on_behalf',
                // Accounting operations
                'journal_entries.view', 'journal_entries.create', 'journal_entries.update', 'journal_entries.submit',
                'chart_of_accounts.view', 'fiscal_periods.view',
                'vendors.view', 'vendors.create', 'vendors.manage', 'vendor_invoices.view', 'vendor_invoices.create', 'vendor_invoices.update', 'vendor_invoices.export',
                'vendor_payments.view', 'customers.view', 'customers.manage', 'customer_invoices.view',
                'customer_invoices.create', 'customer_invoices.update', 'customer_invoices.export',
                'reports.gl', 'reports.ap_aging', 'reports.ar_aging',
                'bank_accounts.view', 'bank_accounts.create', 'bank_accounts.update',
                'bank_reconciliations.view', 'bank_reconciliations.create',
                'fixed_assets.view',
                'budget.view',
                // Procurement: Accounting Officer does budget verification on PRs
                'procurement.purchase-request.view', 'procurement.purchase-request.budget-check',
            ],
            'head' => [
                // Self service
                'self.view_profile', 'self.submit_profile_update', 'self.view_attendance',
                'payroll.view_own_payslip', 'payroll.download_own_payslip',
                'payslips.view', 'payslips.download',
                'leaves.view_own', 'leaves.file_own', 'leaves.cancel',
                'loans.view_own', 'loans.apply', 'attendance.view_own',
                // Team supervision
                'employees.view', 'employees.view_team', 'employees.view_full_record', 'employees.view_masked_gov_ids',
                'attendance.view_team', 'overtime.view', 'overtime.submit', 'overtime.supervise',
                'leaves.view_team', 'leaves.file_on_behalf',
                // Accounting view access
                'chart_of_accounts.view',
                'journal_entries.view',
                'vendors.view', 'vendor_invoices.view',
                'customers.view', 'customer_invoices.view',
                'bank_accounts.view', 'bank_reconciliations.view',
                // Reports and budgeting
                'reports.financial_statements', 'reports.gl', 'reports.ap_aging', 'reports.ar_aging', 'reports.vat',
                'budget.view',
                // Payroll view for heads
                'payroll.view_runs',
                // Procurement: all dept heads can raise PRs for their own department
                'procurement.purchase-request.view', 'procurement.purchase-request.create-dept',
            ],
            'staff' => [
                // Self service only
                'self.view_profile', 'self.submit_profile_update', 'self.view_attendance',
                'payroll.view_own_payslip', 'payroll.download_own_payslip',
                'payslips.view', 'payslips.download',
                'leaves.view_own', 'leaves.file_own', 'leaves.cancel',
                'loans.view_own', 'loans.apply', 'attendance.view_own',
                // Payslip self-service (sidebar navigation)
                'payslips.view', 'payslips.download',
            ],
        ],

        // =========================================================================
        // PRODUCTION MODULE
        // =========================================================================
        'production' => [
            'manager' => [
                // Self service
                'self.view_profile', 'self.submit_profile_update', 'self.view_attendance',
                'payroll.view_own_payslip', 'payroll.download_own_payslip',
                'payslips.view', 'payslips.download',
                'leaves.view_own', 'leaves.file_own', 'leaves.cancel',
                'loans.view_own', 'loans.apply', 'attendance.view_own',
                // Team management (sidebar access)
                'employees.view', 'employees.view_team', 'employees.view_full_record', 'employees.view_masked_gov_ids',
                'employees.upload_documents', 'employees.download_documents',
                'attendance.view_team', 'attendance.view_anomalies',
                'overtime.view', 'overtime.submit', 'overtime.approve', 'overtime.reject',
                'leaves.view_team', 'leaves.file_on_behalf', 'leaves.approve', 'leaves.reject',
                'loans.view_department',
                // Production operations - NO payroll access
                'production.bom.view', 'production.bom.manage',
                'production.delivery-schedule.view', 'production.delivery-schedule.manage',
                'production.orders.view', 'production.orders.create', 'production.orders.release', 'production.orders.complete',
                'production.orders.log_output',
                // QC
                'qc.templates.view', 'qc.inspections.view', 'qc.inspections.create',
                'qc.ncr.view', 'qc.ncr.create', 'qc.ncr.close',
                // Maintenance
                'maintenance.view', 'maintenance.manage',
                // Mold
                'mold.view', 'mold.manage', 'mold.log_shots',
                // Inventory - Material Requisitions and view access
                'inventory.items.view',
                'inventory.mrq.view', 'inventory.mrq.create', 'inventory.mrq.note',
                'inventory.mrq.check', 'inventory.mrq.review', 'inventory.mrq.fulfill',
                // Delivery
                'delivery.view', 'delivery.manage',
                // ISO
                'iso.view', 'iso.manage', 'iso.audit',
                // Procurement: Production Manager can create PRs for own department
                'procurement.purchase-request.view', 'procurement.purchase-request.create-dept',
                'vendors.view',
            ],
            'officer' => [
                // Self service
                'self.view_profile', 'self.submit_profile_update', 'self.view_attendance',
                'payroll.view_own_payslip', 'payroll.download_own_payslip',
                'payslips.view', 'payslips.download',
                'leaves.view_own', 'leaves.file_own', 'leaves.cancel',
                'loans.view_own', 'loans.apply', 'attendance.view_own',
                // Team management
                'employees.view', 'employees.view_team', 'employees.view_full_record', 'employees.view_masked_gov_ids',
                'attendance.view_team', 'overtime.view', 'overtime.submit', 'overtime.supervise',
                'leaves.view_team', 'leaves.file_on_behalf',
                // Production operations
                'production.bom.view',
                'production.delivery-schedule.view', 'production.delivery-schedule.manage',
                'production.orders.view', 'production.orders.create', 'production.orders.release',
                'production.orders.log_output',
                // QC (for QC department officers)
                'qc.inspections.view',
                // No inventory item view for officer
                'inventory.mrq.view', 'inventory.mrq.create',
            ],
            'head' => [
                // Self service
                'self.view_profile', 'self.submit_profile_update', 'self.view_attendance',
                'payroll.view_own_payslip', 'payroll.download_own_payslip',
                'payslips.view', 'payslips.download',
                'leaves.view_own', 'leaves.file_own', 'leaves.cancel',
                'loans.view_own', 'loans.apply', 'attendance.view_own',
                // Team management
                'employees.view', 'employees.view_team', 'employees.view_full_record', 'employees.view_masked_gov_ids',
                'attendance.view_team', 'overtime.view', 'overtime.submit', 'overtime.supervise',
                'leaves.view_team', 'leaves.file_on_behalf',
                // Production: heads create + release WOs and manage MRQs
                'production.bom.view',
                'production.delivery-schedule.view',
                'production.orders.view', 'production.orders.create', 'production.orders.release',
                'production.orders.log_output',
                // QC and Maintenance (for QC/MAINT departments using production module)
                'qc.inspections.view',
                'maintenance.view',
                // Inventory: heads create + note MRQs (but NOT fulfill — that's manager/WH)
                'inventory.items.view',
                'inventory.mrq.view', 'inventory.mrq.create', 'inventory.mrq.note',
                // Procurement: all dept heads can raise PRs for their own department
                'procurement.purchase-request.view', 'procurement.purchase-request.create-dept',
            ],
            'staff' => [
                // Self service
                'self.view_profile', 'self.submit_profile_update', 'self.view_attendance',
                'payroll.view_own_payslip', 'payroll.download_own_payslip',
                'payslips.view', 'payslips.download',
                'leaves.view_own', 'leaves.file_own', 'leaves.cancel',
                'loans.view_own', 'loans.apply', 'attendance.view_own',
                // Payslip self-service (sidebar navigation)
                'payslips.view', 'payslips.download',
                // Production floor
                'production.orders.view',
                'production.orders.log_output',
                'production.bom.view',
                'maintenance.view',
                // Staff: view MRQs only — creation is Head/Manager responsibility
                'inventory.mrq.view',
            ],
        ],

        // =========================================================================
        // SALES MODULE
        // =========================================================================
        'sales' => [
            'manager' => [
                // Self service
                'self.view_profile', 'self.submit_profile_update', 'self.view_attendance',
                'payroll.view_own_payslip', 'payroll.download_own_payslip',
                'payslips.view', 'payslips.download',
                'leaves.view_own', 'leaves.file_own', 'leaves.cancel',
                'loans.view_own', 'loans.apply', 'attendance.view_own',
                // Team management
                'employees.view', 'employees.view_team', 'employees.view_full_record', 'employees.view_masked_gov_ids',
                'employees.upload_documents', 'employees.download_documents',
                'attendance.view_team', 'attendance.view_anomalies',
                'overtime.view', 'overtime.submit', 'overtime.approve', 'overtime.reject',
                'leaves.view_team', 'leaves.file_on_behalf', 'leaves.approve', 'leaves.reject',
                'loans.view_department', 'payroll.view_runs',
                // CRM
                'crm.tickets.view', 'crm.tickets.create', 'crm.tickets.reply',
                'crm.tickets.manage', 'crm.tickets.assign', 'crm.tickets.close',
                // Sales
                'customers.view', 'customers.manage',
                'customer_invoices.view',
                'reports.sales',
                // Client Orders
                'sales.order_review', 'sales.order_approve', 'sales.order_reject', 'sales.order_negotiate',
                // Delivery (Sales manages customer deliveries)
                'delivery.view',
            ],
            'officer' => [
                // Self service
                'self.view_profile', 'self.submit_profile_update', 'self.view_attendance',
                'payroll.view_own_payslip', 'payroll.download_own_payslip',
                'payslips.view', 'payslips.download',
                'leaves.view_own', 'leaves.file_own', 'leaves.cancel',
                'loans.view_own', 'loans.apply', 'attendance.view_own',
                // Team management
                'employees.view', 'employees.view_team', 'employees.view_full_record', 'employees.view_masked_gov_ids',
                'attendance.view_team', 'overtime.view', 'overtime.submit', 'overtime.supervise',
                'leaves.view_team', 'leaves.file_on_behalf',
                // CRM
                'crm.tickets.view', 'crm.tickets.create', 'crm.tickets.reply',
                'crm.tickets.manage', 'crm.tickets.assign', 'crm.tickets.close',
                // Sales
                'customers.view', 'customers.manage',
                // Client Orders (view and negotiate only)
                'sales.order_review', 'sales.order_negotiate',
                // Delivery (Sales manages customer deliveries)
                'delivery.view',
            ],
            'head' => [
                // Self service
                'self.view_profile', 'self.submit_profile_update', 'self.view_attendance',
                'payroll.view_own_payslip', 'payroll.download_own_payslip',
                'payslips.view', 'payslips.download',
                'leaves.view_own', 'leaves.file_own', 'leaves.cancel',
                'loans.view_own', 'loans.apply', 'attendance.view_own',
                // Team management
                'employees.view', 'employees.view_team', 'employees.view_full_record', 'employees.view_masked_gov_ids',
                'attendance.view_team', 'overtime.view', 'overtime.submit', 'overtime.supervise',
                'leaves.view_team', 'leaves.file_on_behalf',
                // CRM
                'crm.tickets.view', 'crm.tickets.create', 'crm.tickets.reply',
                // Client Orders (full access for Sales Head)
                'sales.order_review', 'sales.order_approve', 'sales.order_reject', 'sales.order_negotiate',
                // Procurement: all dept heads can raise PRs for their own department
                'procurement.purchase-request.view', 'procurement.purchase-request.create-dept',
            ],
            'staff' => [
                // Self service
                'self.view_profile', 'self.submit_profile_update', 'self.view_attendance',
                'payroll.view_own_payslip', 'payroll.download_own_payslip',
                'payslips.view', 'payslips.download',
                'leaves.view_own', 'leaves.file_own', 'leaves.cancel',
                'loans.view_own', 'loans.apply', 'attendance.view_own',
                // Payslip self-service (sidebar navigation)
                'payslips.view', 'payslips.download',
                // CRM
                'crm.tickets.view', 'crm.tickets.create', 'crm.tickets.reply',
            ],
        ],

        // =========================================================================
        // WAREHOUSE MODULE
        // =========================================================================
        'warehouse' => [
            'manager' => [
                // Self service
                'self.view_profile', 'self.submit_profile_update', 'self.view_attendance',
                'payroll.view_own_payslip', 'payroll.download_own_payslip',
                'payslips.view', 'payslips.download',
                'leaves.view_own', 'leaves.file_own', 'leaves.cancel',
                'loans.view_own', 'loans.apply', 'attendance.view_own',
                // Team management
                'employees.view', 'employees.view_team', 'employees.view_full_record', 'employees.view_masked_gov_ids',
                'employees.upload_documents', 'employees.download_documents',
                'attendance.view_team', 'attendance.view_anomalies',
                'overtime.view', 'overtime.submit', 'overtime.approve', 'overtime.reject',
                'leaves.view_team', 'leaves.file_on_behalf', 'leaves.approve', 'leaves.reject',
                'loans.view_department', 'payroll.view_runs',
                // Warehouse operations
                'inventory.items.view', 'inventory.items.create', 'inventory.items.edit',
                'inventory.locations.view', 'inventory.locations.manage',
                'inventory.stock.view', 'inventory.adjustments.create',
                'inventory.mrq.view', 'inventory.mrq.create', 'inventory.mrq.check', 'inventory.mrq.review', 'inventory.mrq.fulfill',
                'delivery.view', 'delivery.manage',
                // Procurement: Warehouse Manager creates PRs (dept-scoped) and confirms GRs
                'procurement.purchase-request.view', 'procurement.purchase-request.create-dept',
                'procurement.goods-receipt.view', 'procurement.goods-receipt.create', 'procurement.goods-receipt.confirm',
                'vendors.view', // needed to populate vendor dropdown when creating PRs
            ],
            'officer' => [
                // Self service
                'self.view_profile', 'self.submit_profile_update', 'self.view_attendance',
                'payroll.view_own_payslip', 'payroll.download_own_payslip',
                'payslips.view', 'payslips.download',
                'leaves.view_own', 'leaves.file_own', 'leaves.cancel',
                'loans.view_own', 'loans.apply', 'attendance.view_own',
                // Team management
                'employees.view', 'employees.view_team', 'employees.view_full_record', 'employees.view_masked_gov_ids',
                'attendance.view_team', 'overtime.view', 'overtime.submit', 'overtime.supervise',
                'leaves.view_team', 'leaves.file_on_behalf',
                // Warehouse operations
                'inventory.items.view', 'inventory.items.edit',
                'inventory.locations.view', 'inventory.locations.manage',
                'inventory.stock.view', 'inventory.adjustments.create',
                'inventory.mrq.view', 'inventory.mrq.create', 'inventory.mrq.fulfill',
                'delivery.view',
                // Procurement: officer can view PRs (dept-scoped) and create/view GRs
                'procurement.purchase-request.view', 'procurement.purchase-request.create-dept',
                'procurement.goods-receipt.view', 'procurement.goods-receipt.create',
                'vendors.view',
            ],
            'head' => [
                // Self service
                'self.view_profile', 'self.submit_profile_update', 'self.view_attendance',
                'payroll.view_own_payslip', 'payroll.download_own_payslip',
                'payslips.view', 'payslips.download',
                'leaves.view_own', 'leaves.file_own', 'leaves.cancel',
                'loans.view_own', 'loans.apply', 'attendance.view_own',
                // Team management
                'employees.view', 'employees.view_team', 'employees.view_full_record', 'employees.view_masked_gov_ids',
                'attendance.view_team', 'overtime.view', 'overtime.submit', 'overtime.supervise',
                'leaves.view_team', 'leaves.file_on_behalf',
                // Warehouse FULL access including categories
                'inventory.items.view', 'inventory.items.create', 'inventory.items.edit',
                'inventory.locations.view', 'inventory.locations.manage',
                'inventory.stock.view', 'inventory.adjustments.create',
                'inventory.mrq.view', 'inventory.mrq.create', 'inventory.mrq.note', 'inventory.mrq.fulfill',
                'delivery.view',
                // Production view (for warehouse heads to see work orders)
                'production.orders.view',
                // Procurement: WH Head confirms Goods Receipts + can raise own dept PRs
                'procurement.goods-receipt.view', 'procurement.goods-receipt.confirm',
                'procurement.purchase-request.view', 'procurement.purchase-request.create', 'procurement.purchase-request.create-dept',
                'vendors.view',
            ],
            'staff' => [
                // Self service
                'self.view_profile', 'self.submit_profile_update', 'self.view_attendance',
                'payroll.view_own_payslip', 'payroll.download_own_payslip',
                'payslips.view', 'payslips.download',
                'leaves.view_own', 'leaves.file_own', 'leaves.cancel',
                'loans.view_own', 'loans.apply', 'attendance.view_own',
                // Payslip self-service (sidebar navigation)
                'payslips.view', 'payslips.download',
                // Warehouse operations
                'inventory.items.view',
                'inventory.stock.view',
                'inventory.mrq.create',
                'delivery.view',
            ],
        ],

        // =========================================================================
        // PURCHASING MODULE
        // =========================================================================
        'purchasing' => [
            'manager' => [
                // Self service
                'self.view_profile', 'self.submit_profile_update', 'self.view_attendance',
                'payroll.view_own_payslip', 'payroll.download_own_payslip',
                'payslips.view', 'payslips.download',
                'leaves.view_own', 'leaves.file_own', 'leaves.cancel',
                'loans.view_own', 'loans.apply', 'attendance.view_own',
                // Team management
                'employees.view', 'employees.view_team', 'employees.view_full_record', 'employees.view_masked_gov_ids',
                'employees.upload_documents', 'employees.download_documents',
                'attendance.view_team', 'attendance.view_anomalies',
                'overtime.view', 'overtime.submit', 'overtime.approve', 'overtime.reject',
                'leaves.view_team', 'leaves.file_on_behalf', 'leaves.approve', 'leaves.reject',
                'loans.view_department', 'payroll.view_runs',
                // Procurement
                'procurement.purchase-request.view', 'procurement.purchase-request.create',
                'procurement.purchase-request.note', 'procurement.purchase-request.check',
                'procurement.purchase-request.review',
                'procurement.purchase-order.view', 'procurement.purchase-order.create', 'procurement.purchase-order.manage',
                'procurement.goods-receipt.view', 'procurement.goods-receipt.create', 'procurement.goods-receipt.confirm',
                'vendors.view', 'vendors.create', 'vendors.manage', 'vendors.archive', 'vendors.accredit', 'vendors.suspend',
                'customers.view', 'customers.manage', 'customers.archive',
                'vendor_portal.view_orders', 'vendor_portal.update_fulfillment',
            ],
            'officer' => [
                // Self service
                'self.view_profile', 'self.submit_profile_update', 'self.view_attendance',
                'payroll.view_own_payslip', 'payroll.download_own_payslip',
                'payslips.view', 'payslips.download',
                'leaves.view_own', 'leaves.file_own', 'leaves.cancel',
                'loans.view_own', 'loans.apply', 'attendance.view_own',
                // Team management
                'employees.view', 'employees.view_team', 'employees.view_full_record', 'employees.view_masked_gov_ids',
                'attendance.view_team', 'overtime.view', 'overtime.submit', 'overtime.supervise',
                'leaves.view_team', 'leaves.file_on_behalf',
                // Procurement — full workflow for purchasing officers
                'procurement.purchase-request.view', 'procurement.purchase-request.create',
                'procurement.purchase-request.review',    // officer reviews PRs from other depts
                'procurement.purchase-request.note',      // officer can add notes
                'procurement.purchase-request.check',     // officer pre-checks
                'procurement.purchase-order.view', 'procurement.purchase-order.create',
                'procurement.purchase-order.manage',      // officer accepts/rejects vendor changes
                'procurement.goods-receipt.view', 'procurement.goods-receipt.create',
                'vendors.view',
                'vendor_portal.view_orders',
                // AR/Receivable - Purchasing Officers have preview-only customer access
                'customers.view',
                // Inventory: purchasing officers view items and can create/view MRQs for their dept
                'inventory.items.view',
                'inventory.mrq.view', 'inventory.mrq.create',
            ],
            'head' => [
                // Self service
                'self.view_profile', 'self.submit_profile_update', 'self.view_attendance',
                'payroll.view_own_payslip', 'payroll.download_own_payslip',
                'payslips.view', 'payslips.download',
                'leaves.view_own', 'leaves.file_own', 'leaves.cancel',
                'loans.view_own', 'loans.apply', 'attendance.view_own',
                // Team management
                'employees.view', 'employees.view_team', 'employees.view_full_record', 'employees.view_masked_gov_ids',
                'attendance.view_team', 'overtime.view', 'overtime.submit', 'overtime.supervise',
                'leaves.view_team', 'leaves.file_on_behalf',
                // Procurement: Purchasing Head views all + can raise PRs for own dept
                'procurement.purchase-request.view', 'procurement.purchase-request.create-dept',
                'procurement.purchase-order.view',
                'procurement.goods-receipt.view',
                'vendors.view',
            ],
            'staff' => [
                // Self service
                'self.view_profile', 'self.submit_profile_update', 'self.view_attendance',
                'payroll.view_own_payslip', 'payroll.download_own_payslip',
                'payslips.view', 'payslips.download',
                'leaves.view_own', 'leaves.file_own', 'leaves.cancel',
                'loans.view_own', 'loans.apply', 'attendance.view_own',
                // Payslip self-service (sidebar navigation)
                'payslips.view', 'payslips.download',
                // Procurement
                'procurement.purchase-request.view', 'procurement.purchase-request.create',
                'vendors.view',
            ],
        ],

        // =========================================================================
        // OPERATIONS MODULE (General/IT/Executive/etc)
        // =========================================================================
        'operations' => [
            'manager' => [
                // Self service
                'self.view_profile', 'self.submit_profile_update', 'self.view_attendance',
                'payroll.view_own_payslip', 'payroll.download_own_payslip',
                'payslips.view', 'payslips.download',
                'leaves.view_own', 'leaves.file_own', 'leaves.cancel',
                'loans.view_own', 'loans.apply', 'attendance.view_own',
                // Team management
                'employees.view', 'employees.view_full_record', 'employees.view_masked_gov_ids',
                'employees.upload_documents', 'employees.download_documents',
                'attendance.view_team', 'attendance.view_anomalies',
                'overtime.view', 'overtime.submit', 'overtime.approve', 'overtime.reject',
                'leaves.view_team', 'leaves.file_on_behalf', 'leaves.approve', 'leaves.reject',
                'loans.view_department', 'payroll.view_runs',
                // General operations
                'inventory.items.view', 'inventory.stock.view',
                'crm.tickets.view', 'crm.tickets.create', 'crm.tickets.reply',
                'iso.view', 'iso.manage', 'iso.audit',
                'reports.view',
                'vendors.view',
                // Recruitment (Dept Manager)
                'recruitment.requisitions.view', 'recruitment.requisitions.create', 'recruitment.requisitions.submit',
                'recruitment.requisitions.cancel', 'recruitment.applications.view', 'recruitment.interviews.evaluate',
            ],
            'officer' => [
                // Self service
                'self.view_profile', 'self.submit_profile_update', 'self.view_attendance',
                'payroll.view_own_payslip', 'payroll.download_own_payslip',
                'payslips.view', 'payslips.download',
                'leaves.view_own', 'leaves.file_own', 'leaves.cancel',
                'loans.view_own', 'loans.apply', 'attendance.view_own',
                // Team management
                'employees.view', 'employees.view_team', 'employees.view_full_record', 'employees.view_masked_gov_ids',
                'attendance.view_team', 'overtime.view', 'overtime.submit', 'overtime.supervise',
                'leaves.view_team', 'leaves.file_on_behalf',
                // General operations
                'inventory.items.view',
                'crm.tickets.view', 'crm.tickets.create', 'crm.tickets.reply',
            ],
            'head' => [
                // Self service
                'self.view_profile', 'self.submit_profile_update', 'self.view_attendance',
                'payroll.view_own_payslip', 'payroll.download_own_payslip',
                'payslips.view', 'payslips.download',
                'leaves.view_own', 'leaves.file_own', 'leaves.cancel',
                'loans.view_own', 'loans.apply', 'attendance.view_own',
                // Team management
                'employees.view', 'employees.view_team', 'employees.view_full_record', 'employees.view_masked_gov_ids',
                'attendance.view_team', 'overtime.view', 'overtime.submit', 'overtime.supervise',
                'leaves.view_team', 'leaves.file_on_behalf',
                // General operations
                'inventory.items.view',
                'crm.tickets.view', 'crm.tickets.create', 'crm.tickets.reply',
                // Procurement: all dept heads can raise PRs for their own department
                'procurement.purchase-request.view', 'procurement.purchase-request.create-dept',
            ],
            'staff' => [
                // Self service only
                'self.view_profile', 'self.submit_profile_update', 'self.view_attendance',
                'payroll.view_own_payslip', 'payroll.download_own_payslip',
                'payslips.view', 'payslips.download',
                'leaves.view_own', 'leaves.file_own', 'leaves.cancel',
                'loans.view_own', 'loans.apply', 'attendance.view_own',
                // Payslip self-service (sidebar navigation)
                'payslips.view', 'payslips.download',
                // General operations
                'crm.tickets.view', 'crm.tickets.create', 'crm.tickets.reply',
            ],
        ],

        // =========================================================================
        // EXECUTIVE MODULE (Chairman, President, VP)
        // =========================================================================
        'executive' => [
            'executive' => [
                'self.view_profile', 'self.view_attendance',
                'hr.full_access', 'employees.view', 'reports.view',
                'recruitment.requisitions.view', 'recruitment.applications.view',
                'recruitment.reports.view',
            ],
            'vice_president' => [
                'self.view_profile', 'self.view_attendance',
                'employees.view', 'reports.view', 'reports.financial_statements',
                'approvals.vp.approve',
                // VP approval queues + dashboard guards
                'loans.vp_approve',
                'procurement.purchase-request.view',
                'inventory.mrq.vp_approve',
                'payroll.vp_approve',
                'leaves.vp_approve',
                'overtime.executive_approve',
                // Recruitment Approval & Final Offers
                'recruitment.requisitions.view', 'recruitment.requisitions.approve', 'recruitment.requisitions.reject',
                'recruitment.offers.view', 'recruitment.offers.send',
                'recruitment.reports.view',
            ],
        ],
    ];

    public function run(): void
    {
        $count = 0;

        foreach (self::PERMISSIONS as $moduleKey => $roles) {
            foreach ($roles as $role => $permissions) {
                ModulePermission::updateOrCreate(
                    [
                        'module_key' => $moduleKey,
                        'role' => $role,
                    ],
                    [
                        'permissions' => $permissions,
                        'is_active' => true,
                    ]
                );
                $count++;
            }
        }

        $this->command->info("✓ Module permissions seeded: {$count} role+module combinations");

        // Summary by module
        foreach (self::PERMISSIONS as $moduleKey => $roles) {
            $roleCount = count($roles);
            $this->command->info("  - {$moduleKey}: {$roleCount} roles");
        }

        // Sync permissions to roles via Spatie
        $this->syncPermissionsToRoles();
    }

    /**
     * Sync all permissions from module_permissions table to actual Spatie roles.
     * This ensures users get the correct permissions when assigned to roles.
     * Permissions are MERGED across modules for each role.
     */
    private function syncPermissionsToRoles(): void
    {
        $this->command->info("\nSyncing permissions to Spatie roles...");

        // First, collect all permissions per role across all modules
        $rolePermissions = [];
        foreach (self::PERMISSIONS as $moduleKey => $roles) {
            foreach ($roles as $roleName => $permissions) {
                if (! isset($rolePermissions[$roleName])) {
                    $rolePermissions[$roleName] = [];
                }
                // Merge permissions, avoiding duplicates
                $rolePermissions[$roleName] = array_unique(array_merge($rolePermissions[$roleName], $permissions));
            }
        }

        // Hard override: staff must remain self-service only.
        $rolePermissions['staff'] = self::STAFF_SELF_SERVICE_PERMISSIONS;

        // Now sync the merged permissions to each role
        $now = now();
        $permissionRows = collect($rolePermissions)
            ->flatten()
            ->unique()
            ->values()
            ->map(fn (string $permissionName): array => [
                'name' => $permissionName,
                'guard_name' => 'web',
                'created_at' => $now,
                'updated_at' => $now,
            ])
            ->all();

        DB::table('permissions')->insertOrIgnore($permissionRows);

        $totalSynced = 0;
        foreach ($rolePermissions as $roleName => $permissions) {
            $role = Role::findByName($roleName, 'web');
            if (! $role) {
                $this->command->warn("  Role '{$roleName}' not found, skipping.");

                continue;
            }

            // Sync permissions to role
            $role->syncPermissions($permissions);
            $totalSynced += count($permissions);
            $this->command->info("  ✓ {$roleName}: ".count($permissions).' permissions synced');
        }

        $this->command->info("✓ Total permissions synced: {$totalSynced}");
    }
}
