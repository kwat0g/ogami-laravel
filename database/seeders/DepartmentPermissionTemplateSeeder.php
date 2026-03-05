<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\DepartmentPermissionTemplate;
use Illuminate\Database\Seeder;

/**
 * Permission templates that can be assigned to ANY department regardless of code.
 * This decouples permissions from hardcoded department codes.
 */
class DepartmentPermissionTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $templates = [
            [
                'template_key' => 'hr_full',
                'label' => 'HR & Payroll Full Access',
                'manager_permissions' => [
                    // Self service
                    'self.view_profile', 'self.submit_profile_update', 'self.view_attendance',
                    'payroll.view_own_payslip', 'payroll.download_own_payslip',
                    'leaves.view_own', 'leaves.file_own', 'leaves.cancel',
                    'loans.view_own', 'loans.apply', 'attendance.view_own',
                    // Team management
                    'employees.view', 'employees.view_full_record', 'employees.view_masked_gov_ids',
                    'employees.upload_documents', 'employees.download_documents',
                    'attendance.view_team', 'attendance.view_anomalies',
                    'overtime.view', 'overtime.submit', 'overtime.approve', 'overtime.reject',
                    'leaves.view_team', 'leaves.file_on_behalf', 'leaves.approve', 'leaves.reject',
                    'loans.view_department', 'payroll.view_runs',
                    // Legacy aliases
                    'payslips.view', 'payslips.download', 'leaves.view', 'leaves.create',
                    'leave_balances.view', 'loans.view', 'attendance.view',
                    'attendance.create', 'attendance.update', 'attendance.export',
                    'overtime.create', 'overtime.update', 'payroll.view',
                    // HR-specific
                    'employees.view_salary', 'employees.view_unmasked_gov_ids',
                    'employees.create', 'employees.update', 'employees.update_salary',
                    'employees.activate', 'employees.suspend', 'employees.terminate',
                    'employees.export', 'employees.manage_structure',
                    'attendance.import_csv', 'attendance.resolve_anomalies',
                    'attendance.manage_shifts', 'attendance.delete', 'attendance.lock', 'attendance.unlock',
                    'leaves.adjust_balance', 'leaves.configure_types', 'leaves.trigger_sil_monetization',
                    'leave_balances.adjust', 'loans.hr_approve', 'loans.view_ln007_log',
                    'loans.configure_types', 'loans.create', 'loans.update', 'loans.approve', 'loans.reject',
                    'loans.supervisor_review', 'loan_types.view',
                    'payroll.manage_pay_periods', 'payroll.initiate', 'payroll.pre_run_validate',
                    'payroll.compute', 'payroll.review_breakdown', 'payroll.flag_employee',
                    'payroll.submit_for_hr', 'payroll.hr_approve', 'payroll.hr_return', 'payroll.publish',
                    'payroll.view_deduction_trace', 'payroll.download_register', 'payroll.gov_reports',
                    'payroll.submit', 'payroll.approve', 'payroll.recall',
                    'reports.bir_2316', 'reports.bir_alphalist', 'reports.bir_1601c',
                    'reports.sss_sbr2', 'reports.philhealth_rf1', 'reports.pagibig_mc',
                ],
                'supervisor_permissions' => [
                    // Self service
                    'self.view_profile', 'self.submit_profile_update', 'self.view_attendance',
                    'payroll.view_own_payslip', 'payroll.download_own_payslip',
                    'leaves.view_own', 'leaves.file_own', 'leaves.cancel',
                    'loans.view_own', 'loans.apply', 'loans.supervisor_review',
                    'attendance.view_own',
                    // Team management
                    'employees.view', 'employees.view_team', 'employees.view_full_record', 'employees.view_masked_gov_ids',
                    'attendance.view_team', 'overtime.view', 'overtime.submit', 'overtime.supervise',
                    'leaves.view_team', 'leaves.file_on_behalf',
                    // Legacy
                    'payslips.view', 'payslips.download', 'leaves.view', 'leaves.create',
                    'leave_balances.view', 'loans.view', 'attendance.view',
                    'attendance.create', 'attendance.update', 'attendance.export',
                    'overtime.create', 'overtime.update',
                    // HR-specific (supervisor)
                    'employees.view_unmasked_gov_ids', 'employees.create',
                    'employees.upload_documents', 'employees.download_documents',
                    'attendance.import_csv', 'attendance.view_anomalies', 'attendance.resolve_anomalies',
                    'attendance.manage_shifts',
                ],
            ],
            [
                'template_key' => 'accounting_full',
                'label' => 'Accounting & Finance Full Access',
                'manager_permissions' => [
                    // Self service
                    'self.view_profile', 'self.submit_profile_update', 'self.view_attendance',
                    'payroll.view_own_payslip', 'payroll.download_own_payslip',
                    'leaves.view_own', 'leaves.file_own', 'leaves.cancel',
                    'loans.view_own', 'loans.apply', 'attendance.view_own',
                    // Team management
                    'employees.view', 'employees.view_full_record', 'employees.view_masked_gov_ids',
                    'employees.upload_documents', 'employees.download_documents',
                    'attendance.view_team', 'attendance.view_anomalies',
                    'overtime.view', 'overtime.submit', 'overtime.approve', 'overtime.reject',
                    'leaves.view_team', 'leaves.file_on_behalf', 'leaves.approve', 'leaves.reject',
                    'loans.view_department', 'payroll.view_runs',
                    // Legacy
                    'payslips.view', 'payslips.download', 'leaves.view', 'leaves.create',
                    'leave_balances.view', 'loans.view', 'attendance.view',
                    'attendance.create', 'attendance.update', 'attendance.export',
                    'overtime.create', 'overtime.update', 'payroll.view',
                    // ACCTG-specific
                    'journal_entries.view', 'journal_entries.create', 'journal_entries.update',
                    'journal_entries.submit', 'journal_entries.post', 'journal_entries.reverse', 'journal_entries.export',
                    'chart_of_accounts.view', 'chart_of_accounts.manage',
                    'fiscal_periods.view', 'fiscal_periods.manage',
                    'vendors.view', 'vendors.manage', 'vendors.archive',
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
                    'payroll.acctg_approve', 'payroll.acctg_reject', 'payroll.disburse',
                    'payroll.download_bank_file', 'payroll.post', 'payroll.publish',
                    'payroll.review_breakdown', 'payroll.download_register',
                    'loans.accounting_approve',
                ],
                'supervisor_permissions' => [
                    // Self service
                    'self.view_profile', 'self.submit_profile_update', 'self.view_attendance',
                    'payroll.view_own_payslip', 'payroll.download_own_payslip',
                    'leaves.view_own', 'leaves.file_own', 'leaves.cancel',
                    'loans.view_own', 'loans.apply', 'loans.supervisor_review',
                    'attendance.view_own',
                    // Team management
                    'employees.view', 'employees.view_team', 'employees.view_full_record', 'employees.view_masked_gov_ids',
                    'attendance.view_team', 'overtime.view', 'overtime.submit', 'overtime.supervise',
                    'leaves.view_team', 'leaves.file_on_behalf',
                    // Legacy
                    'payslips.view', 'payslips.download', 'leaves.view', 'leaves.create',
                    'leave_balances.view', 'loans.view', 'attendance.view',
                    'attendance.create', 'attendance.update', 'attendance.export',
                    'overtime.create', 'overtime.update',
                    // ACCTG-specific (supervisor)
                    'journal_entries.view', 'journal_entries.create', 'journal_entries.update', 'journal_entries.submit',
                    'chart_of_accounts.view', 'fiscal_periods.view',
                    'vendors.view', 'vendor_invoices.view', 'vendor_invoices.create', 'vendor_invoices.update', 'vendor_invoices.export',
                    'vendor_payments.view', 'customers.view', 'customer_invoices.view',
                    'customer_invoices.create', 'customer_invoices.update', 'customer_invoices.export',
                    'reports.gl', 'reports.ap_aging',
                ],
            ],
            [
                'template_key' => 'operations_standard',
                'label' => 'Operations (Production/Sales/IT/etc.)',
                'manager_permissions' => [
                    // Self service
                    'self.view_profile', 'self.submit_profile_update', 'self.view_attendance',
                    'payroll.view_own_payslip', 'payroll.download_own_payslip',
                    'leaves.view_own', 'leaves.file_own', 'leaves.cancel',
                    'loans.view_own', 'loans.apply', 'attendance.view_own',
                    // Team management
                    'employees.view', 'employees.view_full_record', 'employees.view_masked_gov_ids',
                    'employees.upload_documents', 'employees.download_documents',
                    'attendance.view_team', 'attendance.view_anomalies',
                    'overtime.view', 'overtime.submit',
                    'leaves.view_team', 'leaves.file_on_behalf',
                    // Legacy
                    'payslips.view', 'payslips.download', 'leaves.view', 'leaves.create',
                    'leave_balances.view', 'loans.view', 'attendance.view',
                    'attendance.create', 'attendance.update', 'attendance.export',
                    'overtime.create', 'overtime.update', 'payroll.view',
                    'payroll.view_runs', 'loans.view_department',
                ],
                'supervisor_permissions' => [
                    // Self service
                    'self.view_profile', 'self.submit_profile_update', 'self.view_attendance',
                    'payroll.view_own_payslip', 'payroll.download_own_payslip',
                    'leaves.view_own', 'leaves.file_own', 'leaves.cancel',
                    'loans.view_own', 'loans.apply', 'loans.supervisor_review',
                    'attendance.view_own',
                    // Team management
                    'employees.view', 'employees.view_team', 'employees.view_full_record', 'employees.view_masked_gov_ids',
                    'employees.upload_documents', 'employees.download_documents',
                    'attendance.view_team', 'attendance.view_anomalies',
                    'overtime.view', 'overtime.submit', 'overtime.supervise',
                    'leaves.view_team', 'leaves.file_on_behalf',
                    // Legacy
                    'payslips.view', 'payslips.download', 'leaves.view', 'leaves.create',
                    'leave_balances.view', 'loans.view', 'attendance.view',
                    'attendance.create', 'attendance.update', 'attendance.export',
                    'overtime.create', 'overtime.update',
                ],
            ],
        ];

        foreach ($templates as $template) {
            DepartmentPermissionTemplate::updateOrCreate(
                ['template_key' => $template['template_key']],
                [
                    'label' => $template['label'],
                    'manager_permissions' => $template['manager_permissions'],
                    'supervisor_permissions' => $template['supervisor_permissions'],
                    'is_active' => true,
                ]
            );
        }

        $this->command->info('✓ Department permission templates seeded: '.count($templates).' templates.');
        $this->command->info('  - hr_full: Full HR & Payroll access');
        $this->command->info('  - accounting_full: Full Accounting & Finance access');
        $this->command->info('  - operations_standard: Standard operations access');
    }
}
