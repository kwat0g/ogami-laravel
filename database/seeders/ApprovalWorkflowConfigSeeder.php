<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * F-007: Seeds approval workflow configurations for all document types.
 *
 * These match the currently hardcoded approval chains in each domain service.
 * Admin can reconfigure these via UI without code deployments.
 */
class ApprovalWorkflowConfigSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        $configs = [
            // ── Leave Request (4-step chain from AD-084-00) ────────────────
            ['document_type' => 'leave_request', 'step_order' => 1, 'step_name' => 'Department Head Approval', 'required_permission' => 'leaves.head_approve', 'target_status' => 'head_approved', 'sod_with_creator' => true, 'sod_with_previous_step' => false],
            ['document_type' => 'leave_request', 'step_order' => 2, 'step_name' => 'Plant Manager Check', 'required_permission' => 'leaves.manager_check', 'target_status' => 'manager_checked', 'sod_with_creator' => true, 'sod_with_previous_step' => false],
            ['document_type' => 'leave_request', 'step_order' => 3, 'step_name' => 'GA Officer Processing', 'required_permission' => 'leaves.ga_process', 'target_status' => 'ga_processed', 'sod_with_creator' => true, 'sod_with_previous_step' => false],
            ['document_type' => 'leave_request', 'step_order' => 4, 'step_name' => 'VP Notation', 'required_permission' => 'leaves.vp_note', 'target_status' => 'approved', 'sod_with_creator' => true, 'sod_with_previous_step' => false],

            // ── Loan (5-step chain) ────────────────────────────────────────
            ['document_type' => 'loan', 'step_order' => 1, 'step_name' => 'Department Head Notation', 'required_permission' => 'loans.head_note', 'target_status' => 'head_noted', 'sod_with_creator' => true, 'sod_with_previous_step' => false],
            ['document_type' => 'loan', 'step_order' => 2, 'step_name' => 'Manager Check', 'required_permission' => 'loans.manager_check', 'target_status' => 'manager_checked', 'sod_with_creator' => true, 'sod_with_previous_step' => false],
            ['document_type' => 'loan', 'step_order' => 3, 'step_name' => 'Officer Review', 'required_permission' => 'loans.officer_review', 'target_status' => 'officer_reviewed', 'sod_with_creator' => true, 'sod_with_previous_step' => false],
            ['document_type' => 'loan', 'step_order' => 4, 'step_name' => 'VP Approval', 'required_permission' => 'loans.vp_approve', 'target_status' => 'ready_for_disbursement', 'sod_with_creator' => true, 'sod_with_previous_step' => true],

            // ── Purchase Request (3-step chain) ────────────────────────────
            ['document_type' => 'purchase_request', 'step_order' => 1, 'step_name' => 'Department Head Review', 'required_permission' => 'purchase_requests.head_approve', 'target_status' => 'head_approved', 'sod_with_creator' => true, 'sod_with_previous_step' => false],
            ['document_type' => 'purchase_request', 'step_order' => 2, 'step_name' => 'Purchasing Check', 'required_permission' => 'purchase_requests.purchasing_review', 'target_status' => 'purchasing_reviewed', 'sod_with_creator' => true, 'sod_with_previous_step' => false],
            ['document_type' => 'purchase_request', 'step_order' => 3, 'step_name' => 'VP Approval', 'required_permission' => 'purchase_requests.vp_approve', 'target_status' => 'approved', 'sod_with_creator' => true, 'sod_with_previous_step' => true],

            // ── Payroll Run (3-step approval chain) ────────────────────────
            ['document_type' => 'payroll_run', 'step_order' => 1, 'step_name' => 'HR Manager Approval', 'required_permission' => 'payroll.hr_approve', 'target_status' => 'HR_APPROVED', 'sod_with_creator' => true, 'sod_with_previous_step' => false],
            ['document_type' => 'payroll_run', 'step_order' => 2, 'step_name' => 'Accounting Manager Approval', 'required_permission' => 'payroll.acctg_approve', 'target_status' => 'ACCTG_APPROVED', 'sod_with_creator' => true, 'sod_with_previous_step' => true],
            ['document_type' => 'payroll_run', 'step_order' => 3, 'step_name' => 'VP Approval', 'required_permission' => 'payroll.vp_approve', 'target_status' => 'VP_APPROVED', 'sod_with_creator' => true, 'sod_with_previous_step' => true],

            // ── Budget (2-step chain) ──────────────────────────────────────
            ['document_type' => 'budget', 'step_order' => 1, 'step_name' => 'Department Head Review', 'required_permission' => 'budget.review', 'target_status' => 'reviewed', 'sod_with_creator' => true, 'sod_with_previous_step' => false],
            ['document_type' => 'budget', 'step_order' => 2, 'step_name' => 'Finance Approval', 'required_permission' => 'budget.approve', 'target_status' => 'approved', 'sod_with_creator' => true, 'sod_with_previous_step' => true],

            // ── Overtime Request (4-step chain) ────────────────────────────
            ['document_type' => 'overtime_request', 'step_order' => 1, 'step_name' => 'Supervisor Approval', 'required_permission' => 'attendance.ot_supervisor_approve', 'target_status' => 'supervisor_approved', 'sod_with_creator' => true, 'sod_with_previous_step' => false],
            ['document_type' => 'overtime_request', 'step_order' => 2, 'step_name' => 'Manager Check', 'required_permission' => 'attendance.ot_manager_check', 'target_status' => 'manager_checked', 'sod_with_creator' => true, 'sod_with_previous_step' => false],
            ['document_type' => 'overtime_request', 'step_order' => 3, 'step_name' => 'Officer Review', 'required_permission' => 'attendance.ot_officer_review', 'target_status' => 'officer_reviewed', 'sod_with_creator' => true, 'sod_with_previous_step' => false],
            ['document_type' => 'overtime_request', 'step_order' => 4, 'step_name' => 'VP/Executive Approval', 'required_permission' => 'attendance.ot_vp_approve', 'target_status' => 'approved', 'sod_with_creator' => true, 'sod_with_previous_step' => true],
        ];

        foreach ($configs as $config) {
            DB::table('approval_workflow_configs')->updateOrInsert(
                [
                    'document_type' => $config['document_type'],
                    'step_order' => $config['step_order'],
                ],
                array_merge($config, [
                    'amount_threshold_centavos' => null,
                    'department_id' => null,
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]),
            );
        }
    }
}
