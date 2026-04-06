<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domains\HR\Models\Department;
use App\Models\DepartmentPermissionProfile;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;

/**
 * Department Permission Profile Seeder — v2
 *
 * Seeds the `department_permission_profiles` table with one row per
 * (department × role) combination.  Permission names are the EXISTING
 * Spatie permission slugs used throughout all policies and tests.
 *
 * Architecture:
 *   - hr_manager & supervisor in HR      get HR-specific permissions
 *   - accounting_manager & supervisor in ACCTG  get Accounting-specific permissions
 *   - supervisor in PROD/PURCH/IT/SALES/QC/MAINT/WH get self-service + team management only
 *
 * Run via:
 *   php artisan db:seed --class=DepartmentPermissionProfileSeeder
 */
class DepartmentPermissionProfileSeeder extends Seeder
{
    public function run(): void
    {
        // ─── Resolve department IDs ───────────────────────────────────────
        // Using actual department codes from the system
        $depts = Department::whereIn('code', ['HR', 'ACCTG', 'PROD', 'PURCH', 'IT', 'SALES', 'QC', 'MAINT', 'WH'])
            ->pluck('id', 'code');

        // ─── Profile definitions ──────────────────────────────────────────
        $profiles = [

            // ═══════════════════════════════════════════════════════════════
            // HRD HR_MANAGER — common + full HR/Payroll-HR module permissions
            // ═══════════════════════════════════════════════════════════════
            [
                'dept_code' => 'HR',
                'role' => 'manager',
                'profile_label' => 'HR Manager',
                'permissions' => [
                    // ── Self-service ─────────────────────────────────────
                    'self.view_profile',
                    'self.submit_profile_update',
                    'self.view_attendance',

                    // ── Own payslips ─────────────────────────────────────
                    'payroll.view_own_payslip',
                    'payroll.download_own_payslip',

                    // ── Own leaves ───────────────────────────────────────
                    'leaves.view_own',
                    'leaves.file_own',
                    'leaves.cancel',

                    // ── Own loans ────────────────────────────────────────
                    'loans.view_own',
                    'loans.apply',

                    // ── Own attendance ───────────────────────────────────
                    'attendance.view_own',

                    // ── Team management (shared across all managers) ─────
                    'employees.view',
                    'employees.view_team',
                    'employees.view_full_record',
                    'employees.view_masked_gov_ids',
                    'employees.upload_documents',
                    'employees.download_documents',
                    'attendance.view_team',
                    'attendance.view_anomalies',
                    'overtime.view',
                    'overtime.submit',
                    'overtime.approve',
                    'overtime.reject',
                    'leaves.view_team',
                    'leaves.file_on_behalf',
                    'leaves.approve',
                    'leaves.reject',
                    'loans.view_department',
                    'payroll.view_runs',
                    'hr.full_access',

                    // ── Legacy aliases ───────────────────────────────────
                    'payslips.view',
                    'payslips.download',
                    'leaves.view',
                    'leaves.create',
                    'leave_balances.view',
                    'loans.view',
                    'attendance.view',
                    'attendance.create',
                    'attendance.update',
                    'attendance.export',
                    'overtime.create',
                    'overtime.update',
                    'payroll.view',

                    // ── HR-specific: Full employee management ───────────
                    'employees.view_salary',
                    'employees.view_unmasked_gov_ids',
                    'employees.create',
                    'employees.update',
                    'employees.update_salary',
                    'employees.activate',
                    'employees.suspend',
                    'employees.terminate',
                    'employees.export',
                    'employees.manage_structure',

                    // ── HR-specific: Attendance management ─────────────
                    'attendance.import_csv',
                    'attendance.resolve_anomalies',
                    'attendance.manage_shifts',
                    'attendance.delete',
                    'attendance.lock',
                    'attendance.unlock',

                    // ── HR-specific: Leave management ───────────────────
                    'leaves.adjust_balance',
                    'leaves.configure_types',
                    'leaves.trigger_sil_monetization',
                    'leave_balances.view',
                    'leave_balances.adjust',
                    'leave_balances.manage',

                    // ── HR-specific: Loan HR approval ───────────────────
                    'loans.hr_approve',
                    'loans.view_ln007_log',
                    'loans.configure_types',
                    'loans.create',
                    'loans.update',
                    'loans.approve',
                    'loans.reject',
                    'loans.supervisor_review',
                    'loan_types.view',

                    // ── HR-specific: Payroll HR workflow ────────────────
                    'payroll.manage_pay_periods',
                    'payroll.initiate',
                    'payroll.pre_run_validate',
                    'payroll.compute',
                    'payroll.review_breakdown',
                    'payroll.flag_employee',
                    'payroll.submit_for_hr',
                    'payroll.hr_approve',
                    'payroll.hr_return',
                    'payroll.disburse',
                    'payroll.download_bank_file',
                    'payroll.publish',
                    'payroll.view_deduction_trace',
                    'payroll.download_register',
                    'payroll.gov_reports',
                    'payroll.submit',
                    'payroll.approve',
                    'payroll.recall',

                    // ── HR-specific: BIR & Gov Reports ─────────────────
                    'reports.bir_2316',
                    'reports.bir_alphalist',
                    'reports.bir_1601c',
                    'reports.sss_sbr2',
                    'reports.philhealth_rf1',
                    'reports.pagibig_mc',
                ],
            ],

            // ═══════════════════════════════════════════════════════════════
            // ACCTG MANAGER — common + full Accounting/Payroll-ACCTG permissions
            // ═══════════════════════════════════════════════════════════════
            [
                'dept_code' => 'ACCTG',
                'role' => 'manager',
                'profile_label' => 'Accounting Manager',
                'permissions' => [
                    // ── Self-service ─────────────────────────────────────
                    'self.view_profile',
                    'self.submit_profile_update',
                    'self.view_attendance',

                    // ── Own payslips ─────────────────────────────────────
                    'payroll.view_own_payslip',
                    'payroll.download_own_payslip',

                    // ── Own leaves ───────────────────────────────────────
                    'leaves.view_own',
                    'leaves.file_own',
                    'leaves.cancel',

                    // ── Own loans ────────────────────────────────────────
                    'loans.view_own',
                    'loans.apply',

                    // ── Own attendance ───────────────────────────────────
                    'attendance.view_own',

                    // ── Team management (shared across all managers) ─────
                    'employees.view',
                    'employees.view_team',
                    'employees.view_full_record',
                    'employees.view_masked_gov_ids',
                    'employees.upload_documents',
                    'employees.download_documents',
                    'attendance.view_team',
                    'attendance.view_anomalies',
                    'overtime.view',
                    'overtime.submit',
                    'overtime.approve',
                    'overtime.reject',
                    'leaves.view_team',
                    'leaves.file_on_behalf',
                    'leaves.approve',
                    'leaves.reject',
                    'loans.view_department',
                    'payroll.view_runs',

                    // ── Legacy aliases ───────────────────────────────────
                    'payslips.view',
                    'payslips.download',
                    'leaves.view',
                    'leaves.create',
                    'leave_balances.view',
                    'loans.view',
                    'attendance.view',
                    'attendance.create',
                    'attendance.update',
                    'attendance.export',
                    'overtime.create',
                    'overtime.update',
                    'payroll.view',

                    // ── ACCTG-specific: GL & Journal Entries ─────────────
                    'journal_entries.view',
                    'journal_entries.create',
                    'journal_entries.update',
                    'journal_entries.submit',
                    'journal_entries.post',
                    'journal_entries.reverse',
                    'journal_entries.export',

                    // ── ACCTG-specific: Chart of Accounts ────────────────
                    'chart_of_accounts.view',
                    'chart_of_accounts.manage',

                    // ── ACCTG-specific: Fiscal Periods ───────────────────
                    'fiscal_periods.view',
                    'fiscal_periods.manage',

                    // ── ACCTG-specific: AP — Vendors ──────────────────────
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

                    // ── ACCTG-specific: AR — Customers ───────────────────
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

                    // ── ACCTG-specific: Banking ───────────────────────────
                    'bank_accounts.view',
                    'bank_accounts.create',
                    'bank_accounts.update',
                    'bank_accounts.delete',
                    'bank_reconciliations.view',
                    'bank_reconciliations.create',
                    'bank_reconciliations.certify',

                    // ── ACCTG-specific: Financial Reports ────────────────
                    'reports.financial_statements',
                    'reports.gl',
                    'reports.trial_balance',
                    'reports.ap_aging',
                    'reports.ar_aging',
                    'reports.vat',
                    'reports.bank_reconciliation',

                    // ── ACCTG-specific: Payroll accounting workflow ───────
                    'payroll.acctg_approve',
                    'payroll.acctg_reject',
                    'payroll.disburse',
                    'payroll.download_bank_file',
                    'payroll.post',
                    'payroll.publish',
                    'payroll.view_deduction_trace',
                    'payroll.download_register',
                    'payroll.gov_reports',
                    'payroll.approve',

                    // ── ACCTG-specific: BIR & Gov Reports ────────────────
                    'reports.bir_2316',
                    'reports.bir_alphalist',
                    'reports.bir_1601c',
                    'reports.sss_sbr2',
                    'reports.philhealth_rf1',
                    'reports.pagibig_mc',

                    // ── ACCTG-specific: Loan accounting approval ──────────
                    'loans.accounting_approve',

                    // ── ACCTG-specific: Budget ────────────────────────────
                    'budget.view',
                    'budget.manage',
                    'budget.approve',

                    // ── ACCTG-specific: Fixed Assets ─────────────────────
                    'fixed_assets.view',
                    'fixed_assets.manage',
                    'fixed_assets.dispose',
                    'fixed_assets.transfer',
                ],
            ],

            // ═══════════════════════════════════════════════════════════════
            // HRD HEAD — common + limited HR permissions (no approve)
            // ═══════════════════════════════════════════════════════════════
            [
                'dept_code' => 'HR',
                'role' => 'head',
                'profile_label' => 'HR Head',
                'permissions' => [
                    // ── Self-service ─────────────────────────────────────
                    'self.view_profile',
                    'self.submit_profile_update',
                    'self.view_attendance',

                    // ── Own payslips ─────────────────────────────────────
                    'payroll.view_own_payslip',
                    'payroll.download_own_payslip',

                    // ── Own leaves ───────────────────────────────────────
                    'leaves.view_own',
                    'leaves.file_own',
                    'leaves.cancel',

                    // ── Own loans ────────────────────────────────────────
                    'loans.view_own',
                    'loans.apply',
                    'loans.supervisor_review',

                    // ── Own attendance ───────────────────────────────────
                    'attendance.view_own',

                    // ── Team management ──────────────────────────────────
                    'employees.view',
                    'employees.view_full_record',
                    'employees.view_masked_gov_ids',
                    'attendance.view_team',
                    'overtime.view',
                    'overtime.submit',
                    'overtime.supervise',
                    'leaves.view_team',
                    'leaves.supervise',
                    'leaves.file_on_behalf',

                    // ── Legacy aliases ───────────────────────────────────
                    'payslips.view',
                    'payslips.download',
                    'leaves.view',
                    'leaves.create',
                    'leave_balances.view',
                    'loans.view',
                    'attendance.view',
                    'attendance.create',
                    'attendance.update',
                    'attendance.export',
                    'overtime.create',
                    'overtime.update',

                    // ── HR-specific (supervisor): employee ──────────────
                    'employees.view_unmasked_gov_ids',
                    'employees.create',
                    'employees.upload_documents',
                    'employees.download_documents',

                    // ── HR-specific (supervisor): attendance ─────────────
                    'attendance.import_csv',
                    'attendance.view_anomalies',
                    'attendance.resolve_anomalies',
                    'attendance.manage_shifts',
                ],
            ],

            // ═══════════════════════════════════════════════════════════════
            // ACCTG OFFICER — common + full Accounting module permissions
            // ═══════════════════════════════════════════════════════════════
            [
                'dept_code' => 'ACCTG',
                'role' => 'officer',
                'profile_label' => 'Accounting Officer',
                'permissions' => [
                    // ── Self-service ─────────────────────────────────────
                    'self.view_profile',
                    'self.submit_profile_update',
                    'self.view_attendance',

                    // ── Own payslips ─────────────────────────────────────
                    'payroll.view_own_payslip',
                    'payroll.download_own_payslip',

                    // ── Own leaves ───────────────────────────────────────
                    'leaves.view_own',
                    'leaves.file_own',
                    'leaves.cancel',

                    // ── Own loans ────────────────────────────────────────
                    'loans.view_own',
                    'loans.apply',

                    // ── Own attendance ───────────────────────────────────
                    'attendance.view_own',

                    // ── Team management (shared across all managers) ─────
                    'employees.view',
                    'employees.view_team',
                    'employees.view_full_record',
                    'employees.view_masked_gov_ids',
                    'employees.upload_documents',
                    'employees.download_documents',
                    'attendance.view_team',
                    'attendance.view_anomalies',
                    'overtime.view',
                    'overtime.submit',
                    'overtime.approve',
                    'overtime.reject',
                    'leaves.view_team',
                    'leaves.file_on_behalf',
                    'leaves.approve',
                    'leaves.reject',
                    'loans.view_department',
                    'payroll.view_runs',

                    // ── Legacy aliases ───────────────────────────────────
                    'payslips.view',
                    'payslips.download',
                    'leaves.view',
                    'leaves.create',
                    'leave_balances.view',
                    'loans.view',
                    'attendance.view',
                    'attendance.create',
                    'attendance.update',
                    'attendance.export',
                    'overtime.create',
                    'overtime.update',
                    'payroll.view',

                    // ── ACCTG-specific: GL & Journal Entries ─────────────
                    'journal_entries.view',
                    'journal_entries.create',
                    'journal_entries.update',
                    'journal_entries.submit',
                    'journal_entries.post',
                    'journal_entries.reverse',
                    'journal_entries.export',

                    // ── ACCTG-specific: Chart of Accounts ────────────────
                    'chart_of_accounts.view',
                    'chart_of_accounts.manage',

                    // ── ACCTG-specific: Fiscal Periods ───────────────────
                    'fiscal_periods.view',
                    'fiscal_periods.manage',

                    // ── ACCTG-specific: AP — Vendors ──────────────────────
                    'vendors.view',
                    'vendors.manage',
                    'vendors.archive',
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

                    // ── ACCTG-specific: AR — Customers ───────────────────
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

                    // ── ACCTG-specific: Banking ───────────────────────────
                    'bank_accounts.view',
                    'bank_accounts.create',
                    'bank_accounts.update',
                    'bank_accounts.delete',
                    'bank_reconciliations.view',
                    'bank_reconciliations.create',
                    'bank_reconciliations.certify',

                    // ── ACCTG-specific: Financial Reports ────────────────
                    'reports.financial_statements',
                    'reports.gl',
                    'reports.trial_balance',
                    'reports.ap_aging',
                    'reports.ar_aging',
                    'reports.vat',
                    'reports.bank_reconciliation',

                    // ── ACCTG-specific: Payroll accounting workflow ───────
                    'payroll.acctg_approve',
                    'payroll.acctg_reject',
                    'payroll.disburse',
                    'payroll.download_bank_file',
                    'payroll.post',
                    'payroll.publish',

                    // ── ACCTG-specific: Loan accounting approval ──────────
                    'loans.accounting_approve',
                ],
            ],

            // ═══════════════════════════════════════════════════════════════
            // ACCTG SUPERVISOR — common + limited accounting (no post/approve)
            // ═══════════════════════════════════════════════════════════════
            [
                'dept_code' => 'ACCTG',
                'role' => 'head',
                'profile_label' => 'Accounting Head',
                'permissions' => [
                    // ── Self-service ─────────────────────────────────────
                    'self.view_profile',
                    'self.submit_profile_update',
                    'self.view_attendance',

                    // ── Own payslips ─────────────────────────────────────
                    'payroll.view_own_payslip',
                    'payroll.download_own_payslip',

                    // ── Own leaves ───────────────────────────────────────
                    'leaves.view_own',
                    'leaves.file_own',
                    'leaves.cancel',

                    // ── Own loans ────────────────────────────────────────
                    'loans.view_own',
                    'loans.apply',
                    'loans.supervisor_review',

                    // ── Own attendance ───────────────────────────────────
                    'attendance.view_own',

                    // ── Team management ──────────────────────────────────
                    'employees.view',
                    'employees.view_full_record',
                    'employees.view_masked_gov_ids',
                    'attendance.view_team',
                    'overtime.view',
                    'overtime.submit',
                    'overtime.supervise',
                    'leaves.view_team',
                    'leaves.file_on_behalf',

                    // ── Legacy aliases ───────────────────────────────────
                    'payslips.view',
                    'payslips.download',
                    'leaves.view',
                    'leaves.create',
                    'leave_balances.view',
                    'loans.view',
                    'attendance.view',
                    'attendance.create',
                    'attendance.update',
                    'attendance.export',
                    'overtime.create',
                    'overtime.update',

                    // ── ACCTG-specific (supervisor): GL — draft & submit ──
                    'journal_entries.view',
                    'journal_entries.create',
                    'journal_entries.update',
                    'journal_entries.submit',
                    'chart_of_accounts.view',
                    'fiscal_periods.view',

                    // ── ACCTG-specific (supervisor): AP — view + draft ────
                    'vendors.view',
                    'vendor_invoices.view',
                    'vendor_invoices.create',
                    'vendor_invoices.update',
                    'vendor_invoices.export',
                    'vendor_payments.view',

                    // ── ACCTG-specific (supervisor): AR — view + draft ────
                    'customers.view',
                    'customer_invoices.view',
                    'customer_invoices.create',
                    'customer_invoices.update',
                    'customer_invoices.export',

                    // ── ACCTG-specific (supervisor): Reports (read-only) ──
                    'reports.gl',
                    'reports.ap_aging',
                ],
            ],

            // ═══════════════════════════════════════════════════════════════
            // PROD SUPERVISOR — self-service + basic team mgmt only
            // (No dedicated manager role for ops depts; supervisor handles team lead duties)
            // ═══════════════════════════════════════════════════════════════
            [
                'dept_code' => 'PROD',
                'role' => 'head',
                'profile_label' => 'Production Head',
                'permissions' => self::commonSupervisorPermissions(),
            ],

            // ═══════════════════════════════════════════════════════════════
            // SALES HEAD
            // ═══════════════════════════════════════════════════════════════
            [
                'dept_code' => 'SALES',
                'role' => 'head',
                'profile_label' => 'Sales Head',
                'permissions' => self::commonSupervisorPermissions(),
            ],

            // ═══════════════════════════════════════════════════════════════
            // QC HEAD
            // ═══════════════════════════════════════════════════════════════
            [
                'dept_code' => 'QC',
                'role' => 'head',
                'profile_label' => 'QC Head',
                'permissions' => self::commonSupervisorPermissions(),
            ],

            // ═══════════════════════════════════════════════════════════════
            // MAINTENANCE HEAD
            // ═══════════════════════════════════════════════════════════════
            [
                'dept_code' => 'MAINT',
                'role' => 'head',
                'profile_label' => 'Maintenance Head',
                'permissions' => self::commonSupervisorPermissions(),
            ],

            // ═══════════════════════════════════════════════════════════════
            // WAREHOUSE HEAD
            // ═══════════════════════════════════════════════════════════════
            [
                'dept_code' => 'WH',
                'role' => 'head',
                'profile_label' => 'Warehouse Head',
                'permissions' => self::commonSupervisorPermissions(),
            ],

            // ═══════════════════════════════════════════════════════════════
            // IT SUPERVISOR
            // ═══════════════════════════════════════════════════════════════
            [
                'dept_code' => 'IT',
                'role' => 'head',
                'profile_label' => 'IT Head',
                'permissions' => self::commonSupervisorPermissions(),
            ],

            // ═══════════════════════════════════════════════════════════════
            // SALES SUPERVISOR
            // ═══════════════════════════════════════════════════════════════
            [
                'dept_code' => 'SALES',
                'role' => 'head',
                'profile_label' => 'Sales Head',
                'permissions' => self::commonSupervisorPermissions(),
            ],

            // ═══════════════════════════════════════════════════════════════
            // OPERATIONS DEPARTMENTS HEADS — WH, MAINT, MOLD, QC, PLANT, PPC, ISO
            // ═══════════════════════════════════════════════════════════════
            [
                'dept_code' => 'WH',
                'role' => 'head',
                'profile_label' => 'Warehouse Head',
                'permissions' => array_merge(self::commonSupervisorPermissions(), [
                    'inventory.items.view', 'inventory.stock.view', 'inventory.locations.view',
                    'inventory.mrq.view', 'inventory.mrq.create', 'inventory.mrq.note', 'inventory.mrq.fulfill',
                    'procurement.purchase-request.note', // Step 2 note
                    'procurement.goods-receipt.view', 'procurement.goods-receipt.create', 'procurement.goods-receipt.confirm',
                    'delivery.view', // view receipts/shipments
                    'reports.gl', // view-only
                ]),
            ],
            [
                'dept_code' => 'MAINT',
                'role' => 'head',
                'profile_label' => 'Maintenance Head',
                'permissions' => array_merge(self::commonSupervisorPermissions(), [
                    'maintenance.view', 'maintenance.manage',
                    'inventory.items.view', // for parts
                    'inventory.stock.view', // for parts availability
                    'procurement.purchase-request.note',
                ]),
            ],
            [
                'dept_code' => 'MOLD',
                'role' => 'head',
                'profile_label' => 'Mold Head',
                'permissions' => array_merge(self::commonSupervisorPermissions(), [
                    'mold.view', 'mold.manage', 'mold.log_shots',
                    'inventory.items.view', // for mold parts?
                    'procurement.purchase-request.note',
                ]),
            ],
            [
                'dept_code' => 'QC',
                'role' => 'head', // QC Manager usually handles this, but if head role used
                'profile_label' => 'QC Head',
                'permissions' => array_merge(self::commonSupervisorPermissions(), [
                    'qc.templates.view', 'qc.inspections.view', 'qc.inspections.create',
                    'qc.ncr.view',
                    'inventory.items.view', // access to item specs
                    'procurement.goods-receipt.view', // inspect incoming
                    'procurement.purchase-request.note',
                ]),
            ],
            [
                'dept_code' => 'PLANT',
                'role' => 'head', // General Plant Ops admin?
                'profile_label' => 'Plant Ops Head',
                'permissions' => array_merge(self::commonSupervisorPermissions(), [
                    'production.orders.view', // overview
                    'maintenance.view', // overview
                    'mold.view', // overview
                    'qc.inspections.view', // overview
                    'procurement.purchase-request.note',
                ]),
            ],
            [
                'dept_code' => 'PPC',
                'role' => 'head',
                'profile_label' => 'PPC Head',
                'permissions' => array_merge(self::commonSupervisorPermissions(), [
                    'production.bom.view', 'production.delivery-schedule.view',
                    'production.orders.view',
                    'inventory.stock.view', // check material availability
                    'procurement.purchase-request.note',
                ]),
            ],
            [
                'dept_code' => 'ISO',
                'role' => 'head',
                'profile_label' => 'ISO Head',
                'permissions' => array_merge(self::commonSupervisorPermissions(), [
                    'iso.view', 'iso.audit',
                    'procurement.purchase-request.note',
                ]),
            ],
            [
                'dept_code' => 'EXEC',
                'role' => 'head', // usually executive/vp, but if head assigned
                'profile_label' => 'Executive Dept Head',
                'permissions' => self::commonSupervisorPermissions(),
            ],
        ];

        // ─── Insert/update profiles ───────────────────────────────────────
        foreach ($profiles as $data) {
            $deptCode = $data['dept_code'];

            // Reload dept ID map to ensure we catch new ones if needed, but the top-level $depts usage prevents strict ordering issues if executed inside transaction.
            // Better to rely on DB lookup for new codes not in top list.
            $deptId = Department::where('code', $deptCode)->value('id');

            if (! $deptId) {
                // Try resolving by name if code fails (fallback logic for robustness)
                // However, top list defines scope. We should expand scope.

                // Let's just log and skip IF truly missing
                $this->command->warn("Department '{$deptCode}' not found — skipping profile '{$data['profile_label']}'.");

                continue;
            }

            DepartmentPermissionProfile::updateOrCreate(
                [
                    'department_id' => $deptId,
                    'role' => $data['role'],
                ],
                [
                    'permissions' => $data['permissions'],
                    'profile_label' => $data['profile_label'],
                    'is_active' => true,
                ]
            );
        }

        $this->command->info('Department permission profiles seeded: '.count($profiles).' profiles.');

        // Flush the dept-scoped permission cache so the new profiles take
        // effect immediately — prevents stale 'false' hits after a fresh seed.
        foreach (['manager', 'officer', 'head', 'vice_president'] as $role) {
            Cache::forget("dept_scoped_v2:{$role}");
        }
        $this->command->info('✓ Department permission cache flushed.');
    }

    // ──────────────────────────────────────────────────────────────────────
    // Shared permission sets
    // ──────────────────────────────────────────────────────────────────────

    /**
     * Common permissions available to ALL managers regardless of department.
     * Used for ops departments (PROD, PURCH, IT, SALES, QC, MAINT, WH) that have no module-specific access.
     *
     * @return list<string>
     */
    private static function commonManagerPermissions(): array
    {
        return [
            // Self-service
            'self.view_profile',
            'self.submit_profile_update',
            'self.view_attendance',

            // Own payslips
            'payroll.view_own_payslip',
            'payroll.download_own_payslip',

            // Own leaves
            'leaves.view_own',
            'leaves.file_own',
            'leaves.cancel',

            // Own loans
            'loans.view_own',
            'loans.apply',

            // Own attendance
            'attendance.view_own',

            // Team management
            'employees.view',
            'employees.view_team',
            'employees.view_full_record',
            'employees.view_masked_gov_ids',
            'employees.upload_documents',
            'employees.download_documents',
            'attendance.view_team',
            'attendance.view_anomalies',
            // Leave/OT approval intentionally excluded for ops depts.
            // Per v2 doc: HR Manager is approval authority for ALL depts currently.
            // Unlock for ops managers in a future phase via a profile row update.
            'overtime.view',
            'overtime.submit',
            'leaves.view_team',
            'leaves.file_on_behalf',

            // Legacy aliases
            'payslips.view',
            'payslips.download',
            'leaves.view',
            'leaves.create',
            'leave_balances.view',
            'loans.view',
            'attendance.view',
            'attendance.create',
            'attendance.update',
            'attendance.export',
            'overtime.create',
            'overtime.update',
            'payroll.view',

            // Ops managers can see payroll run status for their team
            // and view their department's loan applications.
            'payroll.view_runs',
            'loans.view_department',
        ];
    }

    /**
     * Common permissions available to ALL supervisors regardless of department.
     * Used for ops departments (PROD, MKT, ADMON, IT) that have no module-specific access.
     *
     * @return list<string>
     */
    private static function commonSupervisorPermissions(): array
    {
        return [
            // Self-service
            'self.view_profile',
            'self.submit_profile_update',
            'self.view_attendance',

            // Own payslips
            'payroll.view_own_payslip',
            'payroll.download_own_payslip',

            // Own leaves
            'leaves.view_own',
            'leaves.file_own',
            'leaves.cancel',

            // Own loans
            'loans.view_own',
            'loans.apply',
            'loans.supervisor_review',

            // Own attendance
            'attendance.view_own',

            // Team management
            'employees.view',
            'employees.view_team',
            'employees.view_full_record',
            'employees.view_masked_gov_ids',
            'employees.upload_documents',    // supervisors file HR docs for their team
            'employees.download_documents',
            'attendance.view_team',
            'attendance.view_anomalies',     // supervisors flag anomalies before manager resolves
            'overtime.view',
            'overtime.submit',
            'overtime.supervise',
            'leaves.view_team',
            'leaves.supervise',
            'leaves.file_on_behalf',

            // Common Approvals / Notes
            'procurement.purchase-request.note',
            'inventory.mrq.create', // Heads can request materials
            'inventory.mrq.note',   // Heads note staff MRQs

            // Common Visibility
            'iso.view',             // All heads can view ISO docs
            'reports.gl',           // View financial impact

            // Legacy aliases
            'payslips.view',
            'payslips.download',
            'leaves.view',
            'leaves.create',
            'leave_balances.view',
            'loans.view',
            'attendance.view',
            'attendance.create',
            'attendance.update',
            'attendance.export',
            'overtime.create',
            'overtime.update',
        ];
    }
}
