<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeds all operational parameters into system_settings.
 *
 * These values must NEVER be hardcoded in PHP or TypeScript.
 * Any change to these values is made through the Settings UI by an Admin,
 * creating an audit trail entry automatically.
 *
 * Key naming convention: {group}.{parameter_name}
 */
class SystemSettingsSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            // ─── Payroll ─────────────────────────────────────────────────────
            [
                'key' => 'payroll.standard_working_days_per_month',
                'label' => 'Standard Working Days Per Month',
                'value' => json_encode(26),
                'data_type' => 'integer',
                'group' => 'payroll',
                'editable_by_role' => 'hr_manager',
            ],
            [
                'key' => 'payroll.annual_payroll_periods_count',
                'label' => 'Annual Payroll Periods Count',
                'value' => json_encode(24),
                'data_type' => 'integer',
                'group' => 'payroll',
                'editable_by_role' => 'hr_manager',
            ],
            [
                'key' => 'payroll.sss_deduction_period',
                'label' => 'SSS Deduction Period (first_half | second_half)',
                'value' => json_encode('second_half'),
                'data_type' => 'string',
                'group' => 'payroll',
                'editable_by_role' => 'hr_manager',
            ],
            [
                'key' => 'payroll.working_days_divisor',
                'label' => 'Monthly Working Days Divisor (for hourly rate computation)',
                'value' => json_encode(26),
                'data_type' => 'integer',
                'group' => 'payroll',
                'editable_by_role' => 'hr_manager',
            ],

            // ─── Tax ─────────────────────────────────────────────────────────
            [
                'key' => 'tax.night_differential_rate',
                'label' => 'Night Differential Rate (22:00–06:00)',
                'value' => json_encode(0.10),
                'data_type' => 'decimal',
                'group' => 'tax',
                'editable_by_role' => 'admin',
            ],
            [
                'key' => 'tax.thirteenth_month_tax_exempt_cap',
                'label' => '13th Month Pay Tax-Exempt Cap (₱)',
                'value' => json_encode(90000.00),
                'data_type' => 'decimal',
                'group' => 'tax',
                'editable_by_role' => 'admin',
            ],
            [
                'key' => 'tax.de_minimis_cap',
                'label' => 'De Minimis Benefits Annual Cap (₱)',
                'value' => json_encode(90000.00),
                'data_type' => 'decimal',
                'group' => 'tax',
                'editable_by_role' => 'admin',
            ],
            // ─── Attendance ───────────────────────────────────────────────────
            [
                'key' => 'attendance.grace_period_minutes',
                'label' => 'Tardiness Grace Period (minutes)',
                'value' => json_encode(0),
                'data_type' => 'integer',
                'group' => 'attendance',
                'editable_by_role' => 'hr_manager',
            ],

            // ─── Leave ────────────────────────────────────────────────────────
            [
                'key' => 'leave.backdating_days_allowed',
                'label' => 'Leave Backdating Days Allowed',
                'value' => json_encode(3),
                'data_type' => 'integer',
                'group' => 'leave',
                'editable_by_role' => 'hr_manager',
            ],
            [
                'key' => 'leave.coverage_minimum_pct',
                'label' => 'Minimum Department Coverage Percentage for Leave Approval (%)',
                'value' => json_encode(60),
                'data_type' => 'integer',
                'group' => 'leave',
                'editable_by_role' => 'hr_manager',
            ],
            [
                'key' => 'leave.sil_conversion_divisor',
                'label' => 'SIL Cash Conversion Divisor',
                'value' => json_encode(26),
                'data_type' => 'integer',
                'group' => 'leave',
                'editable_by_role' => 'hr_manager',
            ],

            // ─── Security ─────────────────────────────────────────────────────
            [
                'key' => 'security.session_timeout_minutes',
                'label' => 'Session Timeout (minutes)',
                'value' => json_encode(30),
                'data_type' => 'integer',
                'group' => 'security',
                'editable_by_role' => 'admin',
            ],
            [
                'key' => 'security.max_failed_login_attempts',
                'label' => 'Max Failed Login Attempts Before Lockout',
                'value' => json_encode(5),
                'data_type' => 'integer',
                'group' => 'security',
                'editable_by_role' => 'admin',
            ],
            [
                'key' => 'security.account_lockout_minutes',
                'label' => 'Account Lockout Duration (minutes)',
                'value' => json_encode(15),
                'data_type' => 'integer',
                'group' => 'security',
                'editable_by_role' => 'admin',
            ],
            [
                'key' => 'security.password_expiry_days',
                'label' => 'Password Expiry (days)',
                'value' => json_encode(90),
                'data_type' => 'integer',
                'group' => 'security',
                'editable_by_role' => 'admin',
            ],

            // ─── SoD Conflict Matrix (v1.0 — Feb 2026) ──────────────────────────
            // Keyed by permission-namespace prefix (matches {process}.{action}).
            // SodMiddleware reads this with key = 'sod_conflict_matrix'.
            // Format: { "process": { "action_being_taken": ["permissions_user_must_NOT_hold"] } }
            // Route usage: ->middleware('sod:process,action')
            // Record-level SoD (same-record checks) are enforced additionally in Policies.
            [
                'key' => 'sod_conflict_matrix',
                'label' => 'Separation of Duties Conflict Matrix',
                'value' => json_encode([

                    // ── SOD-001 ── Employees: creator cannot activate ─────────
                    'employees' => [
                        'activate' => ['create'],
                        'create' => ['activate'],
                    ],

                    // ── SOD-002 ── Leaves: approver cannot file own leave ─────
                    // Record-level (approver ≠ employee) also checked in LeaveRequestPolicy.
                    'leaves' => [
                        'approve' => ['file_own'],
                        'file_own' => ['approve'],
                    ],

                    // ── SOD-003 ── Overtime: approver cannot be submitter ─────
                    'overtime' => [
                        'approve' => ['submit'],
                        'submit' => ['approve'],
                    ],

                    // ── SOD-004 ── Loans: record-level only ───────────────────
                    // Approver ≠ requester of the SAME loan is enforced in
                    // LoanPolicy::approve() and LoanRequestService::approve().
                    // HR Managers legitimately hold both loans.apply (on-behalf filing)
                    // and loans.hr_approve, so NO route-level permission conflict is
                    // possible here without blocking every HR Manager.
                    // The sod:loans,approve middleware has been removed from the route.

                    // ── SOD-005/006/007 ── Payroll workflow ───────────────────
                    // SOD-005: hr_approver ≠ initiator
                    // SOD-006: acctg_approver ≠ initiator
                    // SOD-007: generic approver ≠ initiator
                    // Route middleware: sod:payroll,{action} → builds payroll.{conflict}.
                    // Disburser ≠ hr_approver also checked record-level in PayrollRunPolicy.
                    'payroll' => [
                        'initiate' => ['hr_approve', 'acctg_approve', 'approve'],
                        'hr_approve' => ['initiate'],
                        'acctg_approve' => ['initiate'],
                        'approve' => ['initiate'],   // SOD-007b
                    ],

                    // ── SOD-008 ── Vendor invoices: recorder ≠ approver ───────
                    // Route-level OK: finance_supervisor has create; finance_manager has approve
                    'vendor_invoices' => [
                        'create' => ['approve'],
                        'approve' => ['create'],
                    ],

                    // NOTE: journal_entries and bank_reconciliations SoD is enforced at
                    // service/policy level (record-level check: same user cannot draft+post
                    // or prepare+certify the SAME record). Route-level permission cross-check
                    // would incorrectly block finance_manager who legitimately holds both
                    // permissions for oversight. See JournalEntryService::post() and
                    // BankReconciliationPolicy::certify() for enforcement.

                    // ── AR ── Customer invoices: biller ≠ approver ────────────
                    'customer_invoices' => [
                        'create' => ['approve'],
                        'approve' => ['create'],
                    ],
                ]),
                'data_type' => 'json',
                'group' => 'security',
                'editable_by_role' => 'admin',
                'is_sensitive' => true,
            ],

            // ─── Accounting Module Settings ───────────────────────────────────
            // Account codes pointing to specific COA entries used for auto-posting.
            // Set these BEFORE approving payroll runs. Blank = not yet configured.
            [
                'key' => 'accounting.salaries_expense_code',
                'label' => 'Salaries Expense Account Code',
                'value' => json_encode(''),
                'data_type' => 'string',
                'group' => 'accounting',
                'editable_by_role' => 'accounting_manager',
            ],
            [
                'key' => 'accounting.sss_er_expense_code',
                'label' => 'SSS Contribution Expense (ER) Account Code',
                'value' => json_encode(''),
                'data_type' => 'string',
                'group' => 'accounting',
                'editable_by_role' => 'accounting_manager',
            ],
            [
                'key' => 'accounting.philhealth_er_expense_code',
                'label' => 'PhilHealth Contribution Expense (ER) Account Code',
                'value' => json_encode(''),
                'data_type' => 'string',
                'group' => 'accounting',
                'editable_by_role' => 'accounting_manager',
            ],
            [
                'key' => 'accounting.pagibig_er_expense_code',
                'label' => 'Pag-IBIG Contribution Expense (ER) Account Code',
                'value' => json_encode(''),
                'data_type' => 'string',
                'group' => 'accounting',
                'editable_by_role' => 'accounting_manager',
            ],
            [
                'key' => 'accounting.cash_in_bank_code',
                'label' => 'Cash in Bank Account Code',
                'value' => json_encode(''),
                'data_type' => 'string',
                'group' => 'accounting',
                'editable_by_role' => 'accounting_manager',
            ],
            [
                'key' => 'accounting.sss_payable_code',
                'label' => 'SSS Payable Account Code',
                'value' => json_encode(''),
                'data_type' => 'string',
                'group' => 'accounting',
                'editable_by_role' => 'accounting_manager',
            ],
            [
                'key' => 'accounting.philhealth_payable_code',
                'label' => 'PhilHealth Payable Account Code',
                'value' => json_encode(''),
                'data_type' => 'string',
                'group' => 'accounting',
                'editable_by_role' => 'accounting_manager',
            ],
            [
                'key' => 'accounting.pagibig_payable_code',
                'label' => 'Pag-IBIG Payable Account Code',
                'value' => json_encode(''),
                'data_type' => 'string',
                'group' => 'accounting',
                'editable_by_role' => 'accounting_manager',
            ],
            [
                'key' => 'accounting.withholding_tax_payable_code',
                'label' => 'Withholding Tax Payable Account Code',
                'value' => json_encode(''),
                'data_type' => 'string',
                'group' => 'accounting',
                'editable_by_role' => 'accounting_manager',
            ],
            [
                'key' => 'accounting.loans_payable_code',
                'label' => 'Loans Payable Account Code',
                'value' => json_encode(''),
                'data_type' => 'string',
                'group' => 'accounting',
                'editable_by_role' => 'accounting_manager',
            ],
            [
                'key' => 'accounting.other_deductions_payable_code',
                'label' => 'Other Deductions Payable Account Code',
                'value' => json_encode(''),
                'data_type' => 'string',
                'group' => 'accounting',
                'editable_by_role' => 'accounting_manager',
            ],
            [
                'key' => 'accounting.thirteenth_month_expense_code',
                'label' => '13th Month Pay Expense Account Code',
                'value' => json_encode(''),
                'data_type' => 'string',
                'group' => 'accounting',
                'editable_by_role' => 'accounting_manager',
            ],
            [
                'key' => 'accounting.allow_future_period_posting',
                'label' => 'Allow posting to future fiscal periods',
                'value' => json_encode(false),
                'data_type' => 'boolean',
                'group' => 'accounting',
                'editable_by_role' => 'accounting_manager',
            ],
            [
                'key' => 'accounting.stale_draft_days',
                'label' => 'Days before draft JE is flagged as stale',
                'value' => json_encode(30),
                'data_type' => 'integer',
                'group' => 'accounting',
                'editable_by_role' => 'accounting_manager',
            ],
            [
                'key' => 'accounting.je_cancel_days',
                'label' => 'Days before stale JE is auto-cancelled',
                'value' => json_encode(60),
                'data_type' => 'integer',
                'group' => 'accounting',
                'editable_by_role' => 'accounting_manager',
            ],

            // ─── AP (Accounts Payable) ────────────────────────────────────────
            [
                'key' => 'ap.due_date_alert_days',
                'label' => 'AP Due Date Alert Window (days)',
                'value' => json_encode(7),
                'data_type' => 'integer',
                'group' => 'ap',
                'editable_by_role' => 'accounting_manager',
            ],
            [
                'key' => 'ap.notification_time',
                'label' => 'AP Alert Job Daily Run Time (HH:MM)',
                'value' => json_encode('08:00'),
                'data_type' => 'string',
                'group' => 'ap',
                'editable_by_role' => 'admin',
            ],
            [
                'key' => 'ap.default_ap_account_code',
                'label' => 'Default Accounts Payable GL Account Code',
                'value' => json_encode(''),
                'data_type' => 'string',
                'group' => 'ap',
                'editable_by_role' => 'accounting_manager',
            ],

            // ─── VAT / Tax ────────────────────────────────────────────────────
            [
                'key' => 'tax.vat_rate',
                'label' => 'Standard Output/Input VAT Rate',
                'value' => json_encode(0.12),
                'data_type' => 'decimal',
                'group' => 'tax',
                'editable_by_role' => 'admin',
            ],
            [
                'key' => 'tax.vat_exemption_reasons',
                'label' => 'Allowed VAT Exemption Reasons (JSON array)',
                'value' => json_encode(['zero-rated export', 'government entity', 'PWD/SC discount', 'VAT-exempt sale']),
                'data_type' => 'json',
                'group' => 'tax',
                'editable_by_role' => 'admin',
            ],
            [
                'key' => 'tax.vat_output_account_id',
                'label' => 'VAT Output Clearing GL Account ID',
                'value' => json_encode(null),
                'data_type' => 'integer',
                'group' => 'tax',
                'editable_by_role' => 'accounting_manager',
            ],
            [
                'key' => 'tax.vat_input_account_id',
                'label' => 'VAT Input Clearing GL Account ID',
                'value' => json_encode(null),
                'data_type' => 'integer',
                'group' => 'tax',
                'editable_by_role' => 'accounting_manager',
            ],

            // ─── AR (Accounts Receivable) ────────────────────────────────────
            [
                'key' => 'ar.default_ar_account_code',
                'label' => 'Default Accounts Receivable GL Account Code',
                'value' => json_encode(''),
                'data_type' => 'string',
                'group' => 'ar',
                'editable_by_role' => 'accounting_manager',
            ],
            [
                'key' => 'ar.overdue_alert_days',
                'label' => 'AR Overdue Alert Window (days ahead of due date)',
                'value' => json_encode(7),
                'data_type' => 'integer',
                'group' => 'ar',
                'editable_by_role' => 'accounting_manager',
            ],
            [
                'key' => 'ar.bad_debt_expense_account_code',
                'label' => 'Bad Debt Expense GL Account Code (AR-006)',
                'value' => json_encode(''),
                'data_type' => 'string',
                'group' => 'ar',
                'editable_by_role' => 'accounting_manager',
            ],
        ];

        foreach ($settings as $setting) {
            DB::table('system_settings')->upsert(
                array_merge($setting, [
                    'is_sensitive' => $setting['is_sensitive'] ?? false,
                    'updated_by' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]),
                ['key'],
                ['label', 'value', 'data_type', 'group', 'editable_by_role', 'is_sensitive', 'updated_at'],
            );
        }
    }
}
