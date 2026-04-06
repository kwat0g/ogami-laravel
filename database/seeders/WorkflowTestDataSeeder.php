<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domains\Accounting\Models\ChartOfAccount;
use App\Domains\Accounting\Models\FiscalPeriod;
use App\Domains\Accounting\Models\JournalEntry;
use App\Domains\AP\Models\Vendor;
use App\Domains\AP\Models\VendorInvoice;
use App\Domains\HR\Models\Department;
use App\Domains\HR\Models\Employee;
use App\Domains\Inventory\Models\MaterialRequisition;
use App\Domains\Leave\Models\LeaveRequest;
use App\Domains\Leave\Models\LeaveType;
use App\Domains\Loan\Models\Loan;
use App\Domains\Loan\Models\LoanType;
use App\Domains\Payroll\Models\PayrollRun;
use App\Domains\Procurement\Models\PurchaseRequest;
use App\Domains\Procurement\Models\PurchaseRequestItem;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Workflow Test Data Seeder
 *
 * Creates test data specifically for workflow, approval hierarchy,
 * cross-module integration, and automation testing.
 */
class WorkflowTestDataSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('Seeding workflow test data...');

        // Clean up existing workflow test data (to allow re-running)
        $this->cleanupExistingData();

        // Get reference data
        $departments = Department::all()->keyBy('code');
        $leaveTypes = LeaveType::all()->keyBy('code');
        $loanTypes = LoanType::all()->keyBy('code');
        $users = User::all()->keyBy('email');

        // 1. LEAVE REQUESTS - Various stages for workflow testing
        $this->seedLeaveRequests($users, $leaveTypes, $departments);

        // 2. LOANS - Various stages for approval testing
        $this->seedLoans($users, $loanTypes);

        // 3. PURCHASE REQUESTS - Cross-department workflow testing
        $this->seedPurchaseRequests($users, $departments);

        // 4. PAYROLL RUN - Skipped: user will demo payroll creation manually
        // $this->seedPayrollRuns($users);

        // 5. JOURNAL ENTRIES - Accounting workflow
        $this->seedJournalEntries($users);

        // 6. VENDOR INVOICES - AP workflow
        $this->seedVendorInvoices($users);

        // 7. MATERIAL REQUISITIONS - Inventory workflow
        $this->seedMaterialRequisitions($users, $departments);

        // 8. BOM COMPONENTS - Link raw materials to BOMs
        $this->seedBomComponents();

        // 9. AUTOMATION TEST DATA
        $this->seedAutomationTestData();

        $this->command->info('✓ Workflow test data seeded successfully!');
        $this->command->info('');
        $this->command->info('Test Data Summary:');
        $this->command->info('  - Leave Requests: 12 (various stages)');
        $this->command->info('  - Loans: 8 (various stages)');
        $this->command->info('  - Purchase Requests: 10 (various stages)');
        $this->command->info('  - Payroll Runs: 3 (various stages)');
        $this->command->info('  - Journal Entries: 8 (various stages)');
        $this->command->info('  - Vendor Invoices: 6 (various stages)');
        $this->command->info('  - Material Requisitions: 8 (various stages)');
    }

    /**
     * Seed leave requests at various workflow stages
     */
    private function seedLeaveRequests($users, $leaveTypes, $departments): void
    {
        $this->command->info('  Creating leave requests...');

        $staff = $users->get('prod.staff@ogamierp.local');
        $head = $users->get('production.head@ogamierp.local');
        $manager = $users->get('prod.manager@ogamierp.local');
        $gaOfficer = $users->get('hr.officer@ogamierp.local');
        $vp = $users->get('vp@ogamierp.local');

        if (! $staff || ! $head || ! $manager || ! $gaOfficer || ! $vp) {
            $this->command->warn('    Some users not found, skipping leave requests');

            return;
        }

        $employee = Employee::where('user_id', $staff->id)->first();
        if (! $employee) {
            $this->command->warn('    Staff employee not found');

            return;
        }

        $vacationType = $leaveTypes->get('VACATION') ?? LeaveType::first();

        // Stage 1: Draft (Staff created, not submitted)
        LeaveRequest::create([
            'employee_id' => $employee->id,
            'leave_type_id' => $vacationType->id,
            'submitted_by' => $staff->id,
            'requester_type' => 'staff',
            'date_from' => '2026-04-01',
            'date_to' => '2026-04-02',
            'total_days' => 2,
            'is_half_day' => false,
            'reason' => 'Family vacation - DRAFT STAGE',
            'status' => 'draft',
        ]);

        // Stage 2: Submitted (Pending head approval)
        LeaveRequest::create([
            'employee_id' => $employee->id,
            'leave_type_id' => $vacationType->id,
            'submitted_by' => $staff->id,
            'requester_type' => 'staff',
            'date_from' => '2026-04-05',
            'date_to' => '2026-04-07',
            'total_days' => 3,
            'is_half_day' => false,
            'reason' => 'Personal matters - PENDING HEAD APPROVAL',
            'status' => 'submitted',
        ]);

        // Stage 3: Submitted (for Head approval workflow testing)
        LeaveRequest::create([
            'employee_id' => $employee->id,
            'leave_type_id' => $vacationType->id,
            'submitted_by' => $staff->id,
            'requester_type' => 'staff',
            'date_from' => '2026-04-10',
            'date_to' => '2026-04-11',
            'total_days' => 2,
            'is_half_day' => false,
            'reason' => 'Medical appointment - PENDING APPROVAL',
            'status' => 'submitted',
        ]);

        // Stage 4: Another Submitted (for workflow testing)
        LeaveRequest::create([
            'employee_id' => $employee->id,
            'leave_type_id' => $vacationType->id,
            'submitted_by' => $staff->id,
            'requester_type' => 'staff',
            'date_from' => '2026-04-15',
            'date_to' => '2026-04-16',
            'total_days' => 2,
            'is_half_day' => false,
            'reason' => 'Family emergency - PENDING APPROVAL',
            'status' => 'submitted',
        ]);

        // Stage 5: Another Submitted
        LeaveRequest::create([
            'employee_id' => $employee->id,
            'leave_type_id' => $vacationType->id,
            'submitted_by' => $staff->id,
            'requester_type' => 'staff',
            'date_from' => '2026-04-20',
            'date_to' => '2026-04-21',
            'total_days' => 2,
            'is_half_day' => false,
            'reason' => 'Wedding attendance - PENDING APPROVAL',
            'status' => 'submitted',
        ]);

        // Stage 6: Approved (Complete)
        LeaveRequest::create([
            'employee_id' => $employee->id,
            'leave_type_id' => $vacationType->id,
            'submitted_by' => $staff->id,
            'requester_type' => 'staff',
            'date_from' => '2026-03-01',
            'date_to' => '2026-03-02',
            'total_days' => 2,
            'is_half_day' => false,
            'reason' => 'Completed leave - APPROVED',
            'status' => 'approved',
        ]);

        // Stage 7: Rejected
        LeaveRequest::create([
            'employee_id' => $employee->id,
            'leave_type_id' => $vacationType->id,
            'submitted_by' => $staff->id,
            'requester_type' => 'staff',
            'date_from' => '2026-05-01',
            'date_to' => '2026-05-05',
            'total_days' => 5,
            'is_half_day' => false,
            'reason' => 'Extended vacation - REJECTED',
            'status' => 'rejected',
        ]);

        // Create head's own leave (for SoD testing - head cannot approve own)
        $headEmployee = Employee::where('user_id', $head->id)->first();
        if ($headEmployee) {
            LeaveRequest::create([
                'employee_id' => $headEmployee->id,
                'leave_type_id' => $vacationType->id,
                'submitted_by' => $head->id,
                'requester_type' => 'head_officer',
                'date_from' => '2026-04-25',
                'date_to' => '2026-04-26',
                'total_days' => 2,
                'is_half_day' => false,
                'reason' => 'Head own leave - SoD TEST (head cannot approve)',
                'status' => 'submitted',
            ]);
        }

        $this->command->info('    ✓ 8 leave requests created');
    }

    /**
     * Seed loans at various workflow stages
     */
    private function seedLoans($users, $loanTypes): void
    {
        $this->command->info('  Creating loans...');

        $staff = $users->get('prod.staff@ogamierp.local');
        $head = $users->get('production.head@ogamierp.local');
        $manager = $users->get('prod.manager@ogamierp.local');
        $officer = $users->get('acctg.officer@ogamierp.local');
        $vp = $users->get('vp@ogamierp.local');

        if (! $staff) {
            $this->command->warn('    Staff user not found');

            return;
        }

        $employee = Employee::where('user_id', $staff->id)->first();
        if (! $employee) {
            $this->command->warn('    Staff employee not found');

            return;
        }

        $sssLoan = $loanTypes->get('SSS') ?? LoanType::first();

        // Stage 1: Pending (New application)
        Loan::create([
            'reference_no' => 'LOAN-WF-001',
            'employee_id' => $employee->id,
            'loan_type_id' => $sssLoan->id,
            'requested_by' => $staff->id,
            'principal_centavos' => 5000000, // ₱50,000
            'term_months' => 24,
            'interest_rate_annual' => 10,
            'monthly_amortization_centavos' => 229167, // ~₱2,291.67
            'total_payable_centavos' => 5500000,
            'outstanding_balance_centavos' => 5500000,
            'loan_date' => '2026-03-15',
            'deduction_cutoff' => '1st',
            'first_deduction_date' => '2026-04-01',
            'status' => 'pending',
            'purpose' => 'Medical expenses - PENDING',
            'workflow_version' => 2,
        ]);

        // Stage 2: Pending (for workflow testing)
        Loan::create([
            'reference_no' => 'LOAN-WF-002',
            'employee_id' => $employee->id,
            'loan_type_id' => $sssLoan->id,
            'requested_by' => $staff->id,
            'principal_centavos' => 3000000,
            'term_months' => 12,
            'interest_rate_annual' => 10,
            'monthly_amortization_centavos' => 275000,
            'total_payable_centavos' => 3300000,
            'outstanding_balance_centavos' => 3300000,
            'loan_date' => '2026-03-10',
            'deduction_cutoff' => '2nd',
            'first_deduction_date' => '2026-04-15',
            'status' => 'pending',
            'purpose' => 'Education - PENDING',
            'workflow_version' => 2,
        ]);

        // Stage 3: Another Pending
        Loan::create([
            'reference_no' => 'LOAN-WF-003',
            'employee_id' => $employee->id,
            'loan_type_id' => $sssLoan->id,
            'requested_by' => $staff->id,
            'principal_centavos' => 10000000,
            'term_months' => 36,
            'interest_rate_annual' => 10,
            'monthly_amortization_centavos' => 361111,
            'total_payable_centavos' => 11000000,
            'outstanding_balance_centavos' => 11000000,
            'loan_date' => '2026-03-05',
            'deduction_cutoff' => '1st',
            'first_deduction_date' => '2026-04-01',
            'status' => 'pending',
            'purpose' => 'Home improvement - PENDING',
            'workflow_version' => 2,
        ]);

        // Stage 4: Approved
        Loan::create([
            'reference_no' => 'LOAN-WF-004',
            'employee_id' => $employee->id,
            'loan_type_id' => $sssLoan->id,
            'requested_by' => $staff->id,
            'principal_centavos' => 2500000,
            'term_months' => 6,
            'interest_rate_annual' => 10,
            'monthly_amortization_centavos' => 437500,
            'total_payable_centavos' => 2625000,
            'outstanding_balance_centavos' => 2625000,
            'loan_date' => '2026-02-20',
            'deduction_cutoff' => '2nd',
            'first_deduction_date' => '2026-03-15',
            'status' => 'approved',
            'purpose' => 'Emergency - APPROVED',
            'workflow_version' => 2,
        ]);

        // Stage 5: Active
        Loan::create([
            'reference_no' => 'LOAN-WF-005',
            'employee_id' => $employee->id,
            'loan_type_id' => $sssLoan->id,
            'requested_by' => $staff->id,
            'principal_centavos' => 4000000,
            'term_months' => 18,
            'interest_rate_annual' => 10,
            'monthly_amortization_centavos' => 233333,
            'total_payable_centavos' => 4400000,
            'outstanding_balance_centavos' => 4400000,
            'loan_date' => '2026-02-01',
            'deduction_cutoff' => '1st',
            'first_deduction_date' => '2026-03-01',
            'status' => 'active',
            'purpose' => 'Debt consolidation - ACTIVE',
            'workflow_version' => 2,
        ]);

        $this->command->info('    ✓ 5 loans created');
    }

    /**
     * Seed purchase requests at various stages
     */
    private function seedPurchaseRequests($users, $departments): void
    {
        $this->command->info('  Creating purchase requests...');

        $prodHead = $users->get('production.head@ogamierp.local');
        $purchasingOfficer = $users->get('purchasing.officer@ogamierp.local');
        $plantMgr = $users->get('prod.manager@ogamierp.local');
        $vp = $users->get('vp@ogamierp.local');

        if (! $prodHead) {
            $this->command->warn('    Production head not found');

            return;
        }

        $prodDept = $departments->get('PROD');

        // Stage 1: Draft
        $pr1 = PurchaseRequest::create([
            'pr_reference' => 'PR-WF-001',
            'department_id' => $prodDept?->id ?? 1,
            'requested_by_id' => $prodHead->id,
            'justification' => 'Raw materials for Q2 production - DRAFT. Production requirements.',
            'urgency' => 'normal',
            'status' => 'draft',
        ]);

        PurchaseRequestItem::create([
            'purchase_request_id' => $pr1->id,
            'item_description' => 'Steel Sheets 4x8',
            'quantity' => 100,
            'unit_of_measure' => 'pcs',
            'estimated_unit_cost' => 500,
            'line_order' => 1,
        ]);

        PurchaseRequestItem::create([
            'purchase_request_id' => $pr1->id,
            'item_description' => 'Welding Rods',
            'quantity' => 50,
            'unit_of_measure' => 'boxes',
            'estimated_unit_cost' => 500,
            'line_order' => 2,
        ]);

        // Stage 2: Submitted
        $pr2 = PurchaseRequest::create([
            'pr_reference' => 'PR-WF-002',
            'department_id' => $prodDept?->id ?? 1,
            'requested_by_id' => $prodHead->id,
            'justification' => 'Safety equipment - PENDING REVIEW. OSHA compliance.',
            'urgency' => 'urgent',
            'status' => 'pending_review',
            'submitted_by_id' => $prodHead->id,
            'submitted_at' => now()->subDays(1),
        ]);

        PurchaseRequestItem::create([
            'purchase_request_id' => $pr2->id,
            'item_description' => 'Safety Helmets',
            'quantity' => 20,
            'unit_of_measure' => 'pcs',
            'estimated_unit_cost' => 500,
            'line_order' => 1,
        ]);

        PurchaseRequestItem::create([
            'purchase_request_id' => $pr2->id,
            'item_description' => 'Safety Shoes',
            'quantity' => 20,
            'unit_of_measure' => 'pairs',
            'estimated_unit_cost' => 750,
            'line_order' => 2,
        ]);

        // Stage 3: Noted
        $pr3 = PurchaseRequest::create([
            'pr_reference' => 'PR-WF-003',
            'department_id' => $prodDept?->id ?? 1,
            'requested_by_id' => $prodHead->id,
            'justification' => 'Machine spare parts - PENDING REVIEW. Preventive maintenance.',
            'urgency' => 'normal',
            'status' => 'pending_review',
            'submitted_by_id' => $prodHead->id,
            'submitted_at' => now()->subDays(3),
            'noted_by_id' => $prodHead->id,
            'noted_at' => now()->subDays(2),
        ]);

        // Stage 4: Checked
        $pr4 = PurchaseRequest::create([
            'pr_reference' => 'PR-WF-004',
            'department_id' => $prodDept?->id ?? 1,
            'requested_by_id' => $prodHead->id,
            'justification' => 'Office supplies for production - REVIEWED. Administrative needs.',
            'urgency' => 'normal',
            'status' => 'reviewed',
            'submitted_by_id' => $prodHead->id,
            'submitted_at' => now()->subDays(5),
            'noted_by_id' => $prodHead->id,
            'noted_at' => now()->subDays(4),
            'checked_by_id' => $purchasingOfficer?->id,
            'checked_at' => now()->subDays(2),
        ]);

        // Stage 5: Reviewed
        $pr5 = PurchaseRequest::create([
            'pr_reference' => 'PR-WF-005',
            'department_id' => $prodDept?->id ?? 1,
            'requested_by_id' => $prodHead->id,
            'justification' => 'IT equipment - REVIEWED. System upgrade.',
            'urgency' => 'urgent',
            'status' => 'reviewed',
            'submitted_by_id' => $prodHead->id,
            'submitted_at' => now()->subDays(7),
            'noted_by_id' => $prodHead->id,
            'noted_at' => now()->subDays(6),
            'checked_by_id' => $purchasingOfficer?->id,
            'checked_at' => now()->subDays(4),
            'reviewed_by_id' => $plantMgr?->id,
            'reviewed_at' => now()->subDays(2),
        ]);

        // Stage 6: Budget Checked (>50K)
        $pr6 = PurchaseRequest::create([
            'pr_reference' => 'PR-WF-006',
            'department_id' => $prodDept?->id ?? 1,
            'requested_by_id' => $prodHead->id,
            'justification' => 'New CNC Machine - BUDGET VERIFIED. Capacity expansion.',
            'urgency' => 'normal',
            'status' => 'budget_verified',
            'submitted_by_id' => $prodHead->id,
            'submitted_at' => now()->subDays(10),
            'noted_by_id' => $prodHead->id,
            'noted_at' => now()->subDays(9),
            'checked_by_id' => $purchasingOfficer?->id,
            'checked_at' => now()->subDays(7),
            'reviewed_by_id' => $plantMgr?->id,
            'reviewed_at' => now()->subDays(5),
            'budget_checked_by_id' => $purchasingOfficer?->id,
            'budget_checked_at' => now()->subDays(3),
        ]);

        // Stage 7: Approved (>100K needs VP)
        $pr7 = PurchaseRequest::create([
            'pr_reference' => 'PR-WF-007',
            'department_id' => $prodDept?->id ?? 1,
            'requested_by_id' => $prodHead->id,
            'justification' => 'Production Line Expansion - APPROVED. New product line.',
            'urgency' => 'critical',
            'status' => 'approved',
            'submitted_by_id' => $prodHead->id,
            'submitted_at' => now()->subDays(15),
            'noted_by_id' => $prodHead->id,
            'noted_at' => now()->subDays(14),
            'checked_by_id' => $purchasingOfficer?->id,
            'checked_at' => now()->subDays(12),
            'reviewed_by_id' => $plantMgr?->id,
            'reviewed_at' => now()->subDays(10),
            'budget_checked_by_id' => $purchasingOfficer?->id,
            'budget_checked_at' => now()->subDays(8),
            'vp_approved_by_id' => $vp?->id,
            'vp_approved_at' => now()->subDays(5),
        ]);

        // Create PR by VP for SoD testing
        if ($vp) {
            $pr8 = PurchaseRequest::create([
                'pr_reference' => 'PR-WF-SOD',
                'department_id' => $prodDept?->id ?? 1,
                'requested_by_id' => $vp->id,
                'justification' => 'VP own request - SoD TEST (VP cannot approve own). Testing.',
                'urgency' => 'normal',
                'status' => 'pending_review',
                'submitted_by_id' => $vp->id,
                'submitted_at' => now(),
            ]);
        }

        $this->command->info('    ✓ 8 purchase requests created');
    }

    /**
     * Seed payroll runs at various stages
     */
    private function seedPayrollRuns($users): void
    {
        $this->command->info('  Creating payroll runs...');

        $hrManager = $users->get('hr.manager@ogamierp.local');
        $acctgOfficer = $users->get('acctg.officer@ogamierp.local');
        $vp = $users->get('vp@ogamierp.local');

        if (! $hrManager) {
            $this->command->warn('    HR Manager not found');

            return;
        }

        // Stage 1: Draft
        PayrollRun::create([
            'reference_no' => 'PRUN-WF-DRAFT-001',
            'pay_period_label' => 'March 1-15, 2026 - DRAFT',
            'cutoff_start' => '2026-03-01',
            'cutoff_end' => '2026-03-15',
            'pay_date' => '2026-03-20',
            'status' => 'draft',
            'run_type' => 'regular',
            'created_by' => $hrManager->id,
        ]);

        // Stage 2: Scope Set
        PayrollRun::create([
            'reference_no' => 'PRUN-WF-SCOPE-001',
            'pay_period_label' => 'March 16-31, 2026 - SCOPE SET',
            'cutoff_start' => '2026-03-16',
            'cutoff_end' => '2026-03-31',
            'pay_date' => '2026-04-05',
            'status' => 'SCOPE_SET',
            'run_type' => 'regular',
            'created_by' => $hrManager->id,
            'scope_confirmed_at' => now()->subDays(5),
        ]);

        // Stage 3: Processing
        PayrollRun::create([
            'reference_no' => 'PRUN-WF-PROC-001',
            'pay_period_label' => 'February 1-15, 2026 - PROCESSING',
            'cutoff_start' => '2026-02-01',
            'cutoff_end' => '2026-02-15',
            'pay_date' => '2026-02-20',
            'status' => 'processing',
            'run_type' => 'regular',
            'created_by' => $hrManager->id,
            'scope_confirmed_at' => now()->subDays(10),
            'pre_run_checked_at' => now()->subDays(9),
            'computation_started_at' => now()->subDays(8),
        ]);

        // Stage 4: Computed
        PayrollRun::create([
            'reference_no' => 'PRUN-WF-COMP-001',
            'pay_period_label' => 'February 16-28, 2026 - COMPUTED',
            'cutoff_start' => '2026-02-16',
            'cutoff_end' => '2026-02-28',
            'pay_date' => '2026-03-05',
            'status' => 'COMPUTED',
            'run_type' => 'regular',
            'created_by' => $hrManager->id,
            'scope_confirmed_at' => now()->subDays(15),
            'pre_run_checked_at' => now()->subDays(14),
            'computation_started_at' => now()->subDays(13),
            'computation_completed_at' => now()->subDays(12),
        ]);

        // Stage 5: Submitted for HR Review
        PayrollRun::create([
            'reference_no' => 'PRUN-WF-HR-001',
            'pay_period_label' => 'January 1-15, 2026 - HR REVIEW',
            'cutoff_start' => '2026-01-01',
            'cutoff_end' => '2026-01-15',
            'pay_date' => '2026-01-20',
            'status' => 'submitted',
            'run_type' => 'regular',
            'created_by' => $hrManager->id,
            'scope_confirmed_at' => now()->subDays(25),
            'pre_run_checked_at' => now()->subDays(24),
            'computation_started_at' => now()->subDays(23),
            'computation_completed_at' => now()->subDays(22),
            'submitted_at' => now()->subDays(20),
        ]);

        // Stage 6: HR Approved
        PayrollRun::create([
            'reference_no' => 'PRUN-WF-HRAPP-001',
            'pay_period_label' => 'January 16-31, 2026 - HR APPROVED',
            'cutoff_start' => '2026-01-16',
            'cutoff_end' => '2026-01-31',
            'pay_date' => '2026-02-05',
            'status' => 'HR_APPROVED',
            'run_type' => 'regular',
            'created_by' => $hrManager->id,
            'scope_confirmed_at' => now()->subDays(35),
            'pre_run_checked_at' => now()->subDays(34),
            'computation_started_at' => now()->subDays(33),
            'computation_completed_at' => now()->subDays(32),
            'submitted_at' => now()->subDays(30),
            'hr_approved_at' => now()->subDays(28),
            'hr_approved_by_id' => $hrManager->id,
        ]);

        // Stage 7: Accounting Approved (SoD test - different person)
        PayrollRun::create([
            'reference_no' => 'PRUN-WF-ACCTG-001',
            'pay_period_label' => 'December 1-15, 2025 - ACCTG APPROVED',
            'cutoff_start' => '2025-12-01',
            'cutoff_end' => '2025-12-15',
            'pay_date' => '2025-12-20',
            'status' => 'ACCTG_APPROVED',
            'run_type' => 'regular',
            'created_by' => $hrManager->id,
            'scope_confirmed_at' => now()->subDays(60),
            'pre_run_checked_at' => now()->subDays(59),
            'computation_started_at' => now()->subDays(58),
            'computation_completed_at' => now()->subDays(57),
            'submitted_at' => now()->subDays(55),
            'hr_approved_at' => now()->subDays(53),
            'hr_approved_by_id' => $hrManager->id,
            'acctg_approved_at' => now()->subDays(50),
            'acctg_approved_by_id' => $acctgOfficer?->id,
        ]);

        $this->command->info('    ✓ 7 payroll runs created');
    }

    /**
     * Seed journal entries at various stages
     */
    private function seedJournalEntries($users): void
    {
        $this->command->info('  Creating journal entries...');

        $acctgOfficer = $users->get('acctg.officer@ogamierp.local');
        $acctgManager = $users->get('vp@ogamierp.local'); // VP acts as accounting approver

        if (! $acctgOfficer) {
            $this->command->warn('    Accounting officer not found');

            return;
        }

        // Get or create COA accounts
        $cashAccount = ChartOfAccount::where('code', '1001')->first();
        $expenseAccount = ChartOfAccount::where('code', '5001')->first();

        if (! $cashAccount || ! $expenseAccount) {
            $this->command->warn('    Chart of accounts not properly seeded');

            return;
        }

        // Get fiscal period
        $fiscalPeriod = FiscalPeriod::where('status', 'open')->first() ?? FiscalPeriod::first();
        if (! $fiscalPeriod) {
            $this->command->warn('    No active fiscal period found');

            return;
        }

        // Stage 1: Draft
        $je1 = JournalEntry::create([
            'je_number' => 'JE-WF-001',
            'date' => now(),
            'description' => 'Test JE - DRAFT',
            'source_type' => 'manual',
            'source_id' => null,
            'status' => 'draft',
            'created_by' => $acctgOfficer->id,
            'fiscal_period_id' => $fiscalPeriod->id,
        ]);

        DB::table('journal_entry_lines')->insert([
            [
                'journal_entry_id' => $je1->id,
                'account_id' => $expenseAccount->id,
                'debit' => 100000,
                'credit' => null,
                'description' => 'Test expense',
            ],
            [
                'journal_entry_id' => $je1->id,
                'account_id' => $cashAccount->id,
                'debit' => null,
                'credit' => 100000,
                'description' => 'Cash payment',
            ],
        ]);

        // Stage 2: Submitted
        $je2 = JournalEntry::create([
            'je_number' => 'JE-WF-002',
            'date' => now()->subDays(1),
            'description' => 'Test JE - SUBMITTED',
            'source_type' => 'manual',
            'source_id' => null,
            'status' => 'submitted',
            'created_by' => $acctgOfficer->id,
            'fiscal_period_id' => $fiscalPeriod->id,
            'submitted_by' => $acctgOfficer->id,
        ]);

        DB::table('journal_entry_lines')->insert([
            [
                'journal_entry_id' => $je2->id,
                'account_id' => $expenseAccount->id,
                'debit' => 50000,
                'credit' => null,
                'description' => 'Office supplies',
            ],
            [
                'journal_entry_id' => $je2->id,
                'account_id' => $cashAccount->id,
                'debit' => null,
                'credit' => 50000,
                'description' => 'Cash',
            ],
        ]);

        // Stage 3: Posted (by different user - SoD compliant)
        $je3 = JournalEntry::create([
            'je_number' => 'JE-WF-003',
            'date' => now()->subDays(3),
            'description' => 'Test JE - POSTED (SoD compliant)',
            'source_type' => 'manual',
            'source_id' => null,
            'status' => 'posted',
            'created_by' => $acctgOfficer->id,
            'posted_by' => $acctgManager?->id ?? $acctgOfficer->id,
            'posted_at' => now()->subDays(1),
            'fiscal_period_id' => $fiscalPeriod->id,
            'submitted_by' => $acctgOfficer->id,
        ]);

        DB::table('journal_entry_lines')->insert([
            [
                'journal_entry_id' => $je3->id,
                'account_id' => $expenseAccount->id,
                'debit' => 25000,
                'credit' => null,
                'description' => 'Utilities',
            ],
            [
                'journal_entry_id' => $je3->id,
                'account_id' => $cashAccount->id,
                'debit' => null,
                'credit' => 25000,
                'description' => 'Cash',
            ],
        ]);

        $this->command->info('    ✓ 3 journal entries created');
    }

    /**
     * Seed vendor invoices at various stages
     */
    private function seedVendorInvoices($users): void
    {
        $this->command->info('  Creating vendor invoices...');

        $acctgOfficer = $users->get('acctg.officer@ogamierp.local');
        $vp = $users->get('vp@ogamierp.local');

        if (! $acctgOfficer) {
            $this->command->warn('    Accounting officer not found');

            return;
        }

        // Get a vendor
        $vendor = Vendor::first();
        if (! $vendor) {
            $this->command->warn('    No vendors found');

            return;
        }

        // Get fiscal period
        $fiscalPeriod = FiscalPeriod::where('status', 'open')->first() ?? FiscalPeriod::first();
        if (! $fiscalPeriod) {
            $this->command->warn('    No active fiscal period found');

            return;
        }

        // Stage 1: Draft
        VendorInvoice::create([
            'invoice_number' => 'INV-WF-001',
            'vendor_id' => $vendor->id,
            'fiscal_period_id' => $fiscalPeriod->id,
            'invoice_date' => now(),
            'due_date' => now()->addDays(30),
            'net_amount' => 50000,
            'vat_amount' => 3000,
            'description' => 'Office supplies - DRAFT',
            'status' => 'draft',
            'created_by' => $acctgOfficer->id,
        ]);

        // Stage 2: Pending Approval
        VendorInvoice::create([
            'invoice_number' => 'INV-WF-002',
            'vendor_id' => $vendor->id,
            'fiscal_period_id' => $fiscalPeriod->id,
            'invoice_date' => now()->subDays(2),
            'due_date' => now()->addDays(28),
            'net_amount' => 100000,
            'vat_amount' => 6000,
            'description' => 'Raw materials - PENDING',
            'status' => 'pending_approval',
            'created_by' => $acctgOfficer->id,
            'submitted_by' => $acctgOfficer->id,
            'submitted_at' => now()->subDays(1),
        ]);

        // Stage 3: Approved
        VendorInvoice::create([
            'invoice_number' => 'INV-WF-003',
            'vendor_id' => $vendor->id,
            'fiscal_period_id' => $fiscalPeriod->id,
            'invoice_date' => now()->subDays(5),
            'due_date' => now()->addDays(25),
            'net_amount' => 25000,
            'vat_amount' => 1500,
            'description' => 'Maintenance services - APPROVED',
            'status' => 'approved',
            'created_by' => $acctgOfficer->id,
            'submitted_by' => $acctgOfficer->id,
            'submitted_at' => now()->subDays(4),
            'approved_at' => now()->subDays(2),
            'approved_by' => $vp?->id,
        ]);

        // Stage 4: Paid
        VendorInvoice::create([
            'invoice_number' => 'INV-WF-004',
            'vendor_id' => $vendor->id,
            'fiscal_period_id' => $fiscalPeriod->id,
            'invoice_date' => now()->subDays(15),
            'due_date' => now()->subDays(5),
            'net_amount' => 75000,
            'vat_amount' => 4500,
            'description' => 'Equipment - PAID',
            'status' => 'paid',
            'created_by' => $acctgOfficer->id,
            'submitted_by' => $acctgOfficer->id,
            'submitted_at' => now()->subDays(14),
            'approved_at' => now()->subDays(12),
            'approved_by' => $vp?->id,
        ]);

        // Create invoice by VP for SoD testing
        if ($vp) {
            VendorInvoice::create([
                'invoice_number' => 'INV-WF-SOD',
                'vendor_id' => $vendor->id,
                'fiscal_period_id' => $fiscalPeriod->id,
                'invoice_date' => now(),
                'due_date' => now()->addDays(30),
                'net_amount' => 200000,
                'vat_amount' => 12000,
                'description' => 'VP created invoice - SoD TEST (VP cannot approve)',
                'status' => 'pending_approval',
                'created_by' => $vp->id,
                'submitted_by' => $vp->id,
                'submitted_at' => now(),
            ]);
        }

        $this->command->info('    ✓ 5 vendor invoices created');
    }

    /**
     * Seed material requisitions at various stages
     */
    private function seedMaterialRequisitions($users, $departments): void
    {
        $this->command->info('  Creating material requisitions...');

        $prodHead = $users->get('production.head@ogamierp.local');
        $whHead = $users->get('warehouse.head@ogamierp.local');
        $plantMgr = $users->get('plant.manager@ogamierp.local');
        $vp = $users->get('vp@ogamierp.local');

        if (! $prodHead) {
            $this->command->warn('    Production head not found');

            return;
        }

        $prodDept = $departments->get('PROD');
        $whDept = $departments->get('WH');

        // Stage 1: Draft
        MaterialRequisition::create([
            'mr_reference' => 'MR-WF-001',
            'department_id' => $prodDept?->id ?? 1,
            'requested_by_id' => $prodHead->id,
            'purpose' => 'Raw materials for production - DRAFT',
            'status' => 'draft',
        ]);

        // Stage 2: Submitted
        MaterialRequisition::create([
            'mr_reference' => 'MR-WF-002',
            'department_id' => $prodDept?->id ?? 1,
            'requested_by_id' => $prodHead->id,
            'purpose' => 'Urgent materials - SUBMITTED',
            'status' => 'submitted',
            'submitted_by_id' => $prodHead->id,
            'submitted_at' => now()->subDays(1),
        ]);

        // Stage 3: Noted (Warehouse)
        MaterialRequisition::create([
            'mr_reference' => 'MR-WF-003',
            'department_id' => $prodDept?->id ?? 1,
            'requested_by_id' => $prodHead->id,
            'purpose' => 'Standard materials - WAREHOUSE NOTED',
            'status' => 'noted',
            'submitted_by_id' => $prodHead->id,
            'submitted_at' => now()->subDays(2),
            'noted_by_id' => $whHead?->id,
            'noted_at' => now()->subDays(1),
        ]);

        // Stage 4: Checked
        MaterialRequisition::create([
            'mr_reference' => 'MR-WF-004',
            'department_id' => $prodDept?->id ?? 1,
            'requested_by_id' => $prodHead->id,
            'purpose' => 'Production supplies - CHECKED',
            'status' => 'checked',
            'submitted_by_id' => $prodHead->id,
            'submitted_at' => now()->subDays(4),
            'noted_by_id' => $whHead?->id,
            'noted_at' => now()->subDays(3),
            'checked_by_id' => $whHead?->id,
            'checked_at' => now()->subDays(2),
        ]);

        // Stage 5: Reviewed
        MaterialRequisition::create([
            'mr_reference' => 'MR-WF-005',
            'department_id' => $prodDept?->id ?? 1,
            'requested_by_id' => $prodHead->id,
            'purpose' => 'Critical parts - REVIEWED',
            'status' => 'reviewed',
            'submitted_by_id' => $prodHead->id,
            'submitted_at' => now()->subDays(6),
            'noted_by_id' => $whHead?->id,
            'noted_at' => now()->subDays(5),
            'checked_by_id' => $whHead?->id,
            'checked_at' => now()->subDays(4),
            'reviewed_by_id' => $plantMgr?->id,
            'reviewed_at' => now()->subDays(2),
        ]);

        // Create MR by VP for SoD testing
        if ($vp) {
            MaterialRequisition::create([
                'mr_reference' => 'MR-WF-SOD',
                'department_id' => $prodDept?->id ?? 1,
                'requested_by_id' => $vp->id,
                'purpose' => 'VP own MR - SoD TEST (VP cannot approve)',
                'status' => 'submitted',
                'submitted_by_id' => $vp->id,
                'submitted_at' => now(),
            ]);
        }

        $this->command->info('    ✓ 6 material requisitions created');
    }

    /**
     * Seed automation test data
     */
    private function seedAutomationTestData(): void
    {
        $this->command->info('  Creating automation test data...');

        // Create a journal entry that should auto-reverse
        $acctgOfficer = User::where('email', 'acctg.officer@ogamierp.local')->first();

        if ($acctgOfficer) {
            $expenseAccount = ChartOfAccount::where('code', '5100')->first();
            $liabilityAccount = ChartOfAccount::where('code', '2200')->first();

            if ($expenseAccount && $liabilityAccount) {
                $accrualJE = JournalEntry::create([
                    'je_number' => 'JE-WF-ACCRUAL-001',
                    'date' => now(),
                    'description' => 'Accrual for March utilities - AUTO REVERSAL TEST',
                    'source_type' => 'manual',
                    'source_id' => null,
                    'status' => 'posted',
                    'created_by' => $acctgOfficer->id,
                    'posted_by' => $acctgOfficer->id,
                    'posted_at' => now(),
                    'fiscal_period_id' => $fiscalPeriod->id,
                ]);

                DB::table('journal_entry_lines')->insert([
                    [
                        'journal_entry_id' => $accrualJE->id,
                        'account_id' => $expenseAccount->id,
                        'debit' => 150000,
                        'credit' => null,
                        'description' => 'Utilities expense accrual',
                    ],
                    [
                        'journal_entry_id' => $accrualJE->id,
                        'account_id' => $liabilityAccount->id,
                        'debit' => null,
                        'credit' => 150000,
                        'description' => 'Accrued utilities payable',
                    ],
                ]);

                $this->command->info('    ✓ Auto-reversal JE created');
            }
        }

        // Create recurring journal template
        $this->command->info('    ✓ Automation test data created');
    }

    /**
     * Clean up existing workflow test data to allow re-running
     */
    private function cleanupExistingData(): void
    {
        $this->command->info('  Cleaning up existing workflow test data...');

        // JE cleanup: posted entries are protected by a trigger, so wrap in try-catch
        try {
            DB::table('journal_entry_lines')
                ->whereIn('journal_entry_id', function ($query) {
                    $query->select('id')->from('journal_entries')
                        ->where('je_number', 'like', 'JE-WF-%');
                })->delete();
            DB::table('journal_entries')->where('je_number', 'like', 'JE-WF-%')->delete();
        } catch (\Throwable $e) {
            // Posted JEs are immutable — skip cleanup for those
        }
        DB::table('vendor_invoices')->where('invoice_number', 'like', 'INV-WF-%')->delete();
        DB::table('purchase_request_items')
            ->whereIn('purchase_request_id', function ($query) {
                $query->select('id')->from('purchase_requests')
                    ->where('pr_reference', 'like', 'PR-WF-%');
            })->delete();
        DB::table('purchase_requests')->where('pr_reference', 'like', 'PR-WF-%')->delete();
        DB::table('payroll_runs')->where('reference_no', 'like', 'PRUN-WF-%')->delete();
        DB::table('loans')->where('reference_no', 'like', 'LOAN-WF-%')->delete();
        DB::table('leave_requests')->where('reason', 'like', '%SoD TEST%')
            ->orWhere('reason', 'like', '%PENDING%')
            ->orWhere('reason', 'like', '%APPROVED%')
            ->orWhere('reason', 'like', '%REJECTED%')
            ->delete();
        DB::table('material_requisitions')->where('mr_reference', 'like', 'MR-WF-%')->delete();

        $this->command->info('  ✓ Cleanup complete');
    }

    /**
     * Seed BOM Components — link raw materials to each BOM.
     */
    private function seedBomComponents(): void
    {
        $this->command->info('  Creating BOM components...');

        $boms = DB::table('bill_of_materials')->get();
        $items = DB::table('item_masters')->pluck('id', 'item_code');

        if ($boms->isEmpty() || $items->isEmpty()) {
            $this->command->warn('    BOMs or items not seeded, skipping');
            return;
        }

        // Clean existing
        DB::table('bom_components')->truncate();

        $components = [];
        $now = now();

        foreach ($boms as $bom) {
            $productItem = DB::table('item_masters')->where('id', $bom->product_item_id)->first();
            if (! $productItem) continue;

            // BOM #1: Plastic Component A → PE Resin + Masterbatch Black + Carton Box
            if ($productItem->item_code === 'FG-PLASTIC-001') {
                $components[] = [
                    'bom_id' => $bom->id,
                    'component_item_id' => $items->get('RM-PE-001'),   // PE Resin
                    'qty_per_unit' => 2.50,
                    'unit_of_measure' => 'kg',
                    'scrap_factor_pct' => 3.00,
                ];
                $components[] = [
                    'bom_id' => $bom->id,
                    'component_item_id' => $items->get('RM-MB-001'),   // Masterbatch Black
                    'qty_per_unit' => 0.10,
                    'unit_of_measure' => 'kg',
                    'scrap_factor_pct' => 1.00,
                ];
                $components[] = [
                    'bom_id' => $bom->id,
                    'component_item_id' => $items->get('PKG-BOX-S'),   // Carton Box
                    'qty_per_unit' => 1.00,
                    'unit_of_measure' => 'pcs',
                    'scrap_factor_pct' => 0.50,
                ];
            }

            // BOM #2: Metal Bracket B → PP Resin + Masterbatch White + Carton Box
            if ($productItem->item_code === 'FG-METAL-002') {
                $components[] = [
                    'bom_id' => $bom->id,
                    'component_item_id' => $items->get('RM-PP-001'),   // PP Resin
                    'qty_per_unit' => 1.80,
                    'unit_of_measure' => 'kg',
                    'scrap_factor_pct' => 2.50,
                ];
                $components[] = [
                    'bom_id' => $bom->id,
                    'component_item_id' => $items->get('RM-MB-002'),   // Masterbatch White
                    'qty_per_unit' => 0.08,
                    'unit_of_measure' => 'kg',
                    'scrap_factor_pct' => 1.00,
                ];
                $components[] = [
                    'bom_id' => $bom->id,
                    'component_item_id' => $items->get('PKG-BOX-S'),   // Carton Box
                    'qty_per_unit' => 1.00,
                    'unit_of_measure' => 'pcs',
                    'scrap_factor_pct' => 0.50,
                ];
            }

            // BOM #3: Assembly Unit C → uses Plastic Component A + Metal Bracket B (sub-assembly) + PE Resin + Hydraulic Oil
            if ($productItem->item_code === 'FG-ASSY-003') {
                // Sub-assembly: finished goods used as components
                if ($items->has('FG-PLASTIC-001')) {
                    $components[] = [
                        'bom_id' => $bom->id,
                        'component_item_id' => $items->get('FG-PLASTIC-001'),
                        'qty_per_unit' => 2.00,
                        'unit_of_measure' => 'pcs',
                        'scrap_factor_pct' => 1.00,
                    ];
                }
                if ($items->has('FG-METAL-002')) {
                    $components[] = [
                        'bom_id' => $bom->id,
                        'component_item_id' => $items->get('FG-METAL-002'),
                        'qty_per_unit' => 1.00,
                        'unit_of_measure' => 'pcs',
                        'scrap_factor_pct' => 0.50,
                    ];
                }
                $components[] = [
                    'bom_id' => $bom->id,
                    'component_item_id' => $items->get('RM-PE-001'),   // PE Resin (adhesive)
                    'qty_per_unit' => 0.30,
                    'unit_of_measure' => 'kg',
                    'scrap_factor_pct' => 5.00,
                ];
                $components[] = [
                    'bom_id' => $bom->id,
                    'component_item_id' => $items->get('CON-OIL-01'),  // Hydraulic Oil
                    'qty_per_unit' => 0.05,
                    'unit_of_measure' => 'L',
                    'scrap_factor_pct' => 2.00,
                ];
            }
        }

        // Filter out any null component_item_ids
        $components = array_filter($components, fn($c) => $c['component_item_id'] !== null);

        if (! empty($components)) {
            DB::table('bom_components')->insert($components);
        }

        $this->command->info('    ✓ ' . count($components) . ' BOM components created');
    }
}
