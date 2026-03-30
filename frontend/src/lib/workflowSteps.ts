/**
 * Workflow Step Definitions
 *
 * Pre-configured StatusTimeline step arrays for each module's state machine.
 * Import these and pass to <StatusTimeline steps={...} currentStatus={entity.status} />.
 *
 * Each workflow maps the ordered states to human-readable labels with actor descriptions.
 */
import type { TimelineStep } from '@/components/ui/StatusTimeline'

// ── Purchase Request ──────────────────────────────────────────────────────────
export function getPurchaseRequestSteps(pr: {
  status: string
  submitted_by_name?: string | null
  submitted_at?: string | null
  reviewed_by_name?: string | null
  reviewed_at?: string | null
  reviewer_comments?: string | null
  budget_verified_by_name?: string | null
  budget_verified_at?: string | null
  budget_comments?: string | null
  approved_by_name?: string | null
  approved_at?: string | null
  approver_comments?: string | null
  converted_to_po_id?: number | null
}): TimelineStep[] {
  return [
    { label: 'Draft', status: 'draft' },
    { label: 'Submitted for Review', status: 'pending_review', actor: pr.submitted_by_name, timestamp: pr.submitted_at },
    { label: 'Technical Review', status: 'reviewed', actor: pr.reviewed_by_name, timestamp: pr.reviewed_at, comment: pr.reviewer_comments },
    { label: 'Budget Verified', status: 'budget_verified', actor: pr.budget_verified_by_name, timestamp: pr.budget_verified_at, comment: pr.budget_comments },
    { label: 'VP Approved', status: 'approved', actor: pr.approved_by_name, timestamp: pr.approved_at, comment: pr.approver_comments },
    ...(pr.converted_to_po_id ? [{ label: 'Converted to PO', status: 'converted_to_po' }] : []),
  ]
}

// ── Leave Request ─────────────────────────────────────────────────────────────
export function getLeaveRequestSteps(leave: {
  status: string
  head_approved_by_name?: string | null
  head_approved_at?: string | null
  manager_checked_by_name?: string | null
  manager_checked_at?: string | null
  ga_processed_by_name?: string | null
  ga_processed_at?: string | null
  vp_noted_by_name?: string | null
  vp_noted_at?: string | null
}): TimelineStep[] {
  return [
    { label: 'Filed', status: 'submitted' },
    { label: 'Head Approved', status: 'head_approved', actor: leave.head_approved_by_name, timestamp: leave.head_approved_at },
    { label: 'Manager Checked', status: 'manager_checked', actor: leave.manager_checked_by_name, timestamp: leave.manager_checked_at },
    { label: 'GA Processed', status: 'ga_processed', actor: leave.ga_processed_by_name, timestamp: leave.ga_processed_at },
    { label: 'Approved', status: 'approved', actor: leave.vp_noted_by_name, timestamp: leave.vp_noted_at },
  ]
}

// ── Loan Application ──────────────────────────────────────────────────────────
export function getLoanSteps(loan: {
  status: string
  head_noted_by_name?: string | null
  head_noted_at?: string | null
  head_note_remarks?: string | null
  manager_checked_by_name?: string | null
  manager_checked_at?: string | null
  manager_check_remarks?: string | null
  officer_reviewed_by_name?: string | null
  officer_reviewed_at?: string | null
  officer_review_remarks?: string | null
  supervisor_approved_by_name?: string | null
  supervisor_approved_at?: string | null
  approved_by_name?: string | null
  approved_at?: string | null
  disbursed_at?: string | null
}): TimelineStep[] {
  return [
    { label: 'Application Filed', status: 'pending' },
    { label: 'Head Noted', status: 'head_noted', actor: loan.head_noted_by_name, timestamp: loan.head_noted_at, comment: loan.head_note_remarks },
    { label: 'Manager Checked', status: 'manager_checked', actor: loan.manager_checked_by_name, timestamp: loan.manager_checked_at, comment: loan.manager_check_remarks },
    { label: 'Officer Reviewed', status: 'officer_reviewed', actor: loan.officer_reviewed_by_name, timestamp: loan.officer_reviewed_at, comment: loan.officer_review_remarks },
    { label: 'VP Approved', status: 'supervisor_approved', actor: loan.supervisor_approved_by_name, timestamp: loan.supervisor_approved_at },
    { label: 'Approved', status: 'approved', actor: loan.approved_by_name, timestamp: loan.approved_at },
    { label: 'Ready for Disbursement', status: 'ready_for_disbursement' },
    { label: 'Active (Disbursed)', status: 'active', timestamp: loan.disbursed_at },
  ]
}

// ── Production Order ──────────────────────────────────────────────────────────
export function getProductionOrderSteps(_order: {
  status: string
}): TimelineStep[] {
  return [
    { label: 'Draft', status: 'draft' },
    { label: 'Released', status: 'released' },
    { label: 'In Progress', status: 'in_progress' },
    { label: 'Completed', status: 'completed' },
  ]
}

// ── Purchase Order ────────────────────────────────────────────────────────────
export function getPurchaseOrderSteps(_po: {
  status: string
}): TimelineStep[] {
  return [
    { label: 'Draft', status: 'draft' },
    { label: 'Submitted', status: 'submitted' },
    { label: 'Acknowledged', status: 'acknowledged' },
    { label: 'Partially Received', status: 'partially_received' },
    { label: 'Received in Full', status: 'received_in_full' },
  ]
}

// ── Vendor Invoice (AP) ──────────────────────────────────────────────────────
export function getVendorInvoiceSteps(_inv: {
  status: string
}): TimelineStep[] {
  return [
    { label: 'Draft', status: 'draft' },
    { label: 'Submitted', status: 'pending_approval' },
    { label: 'Head Noted', status: 'head_noted' },
    { label: 'Manager Checked', status: 'manager_checked' },
    { label: 'Officer Reviewed', status: 'officer_reviewed' },
    { label: 'Approved', status: 'approved' },
    { label: 'Partially Paid', status: 'partially_paid' },
    { label: 'Paid', status: 'paid' },
  ]
}

// ── Customer Invoice (AR) ────────────────────────────────────────────────────
export function getCustomerInvoiceSteps(_inv: {
  status: string
}): TimelineStep[] {
  return [
    { label: 'Draft', status: 'draft' },
    { label: 'Approved', status: 'approved' },
    { label: 'Partially Paid', status: 'partially_paid' },
    { label: 'Paid', status: 'paid' },
  ]
}

// ── Payroll Run ──────────────────────────────────────────────────────────────
export function getPayrollRunSteps(_run: {
  status: string
}): TimelineStep[] {
  return [
    { label: 'Draft', status: 'DRAFT' },
    { label: 'Scope Set', status: 'SCOPE_SET' },
    { label: 'Pre-Run Checked', status: 'PRE_RUN_CHECKED' },
    { label: 'Computing', status: 'PROCESSING' },
    { label: 'Computed', status: 'COMPUTED' },
    { label: 'Under Review', status: 'REVIEW' },
    { label: 'Submitted', status: 'SUBMITTED' },
    { label: 'HR Approved', status: 'HR_APPROVED' },
    { label: 'Acctg Approved', status: 'ACCTG_APPROVED' },
    { label: 'VP Approved', status: 'VP_APPROVED' },
    { label: 'Disbursed', status: 'DISBURSED' },
    { label: 'Published', status: 'PUBLISHED' },
  ]
}

// ── Delivery Receipt ─────────────────────────────────────────────────────────
export function getDeliveryReceiptSteps(_dr: {
  status: string
}): TimelineStep[] {
  return [
    { label: 'Draft', status: 'draft' },
    { label: 'Ready for Pickup', status: 'ready_for_pickup' },
    { label: 'In Transit', status: 'in_transit' },
    { label: 'Delivered', status: 'delivered' },
  ]
}

// ── Material Requisition ─────────────────────────────────────────────────────
export function getMaterialRequisitionSteps(_mrq: {
  status: string
}): TimelineStep[] {
  return [
    { label: 'Draft', status: 'draft' },
    { label: 'Submitted', status: 'submitted' },
    { label: 'Picked', status: 'picked' },
    { label: 'Issued', status: 'issued' },
  ]
}

// ── Client Order ─────────────────────────────────────────────────────────────
export function getClientOrderSteps(_order: {
  status: string
}): TimelineStep[] {
  return [
    { label: 'Pending Review', status: 'pending' },
    { label: 'Negotiating', status: 'negotiating' },
    { label: 'Client Responded', status: 'client_responded' },
    { label: 'VP Approval', status: 'vp_pending' },
    { label: 'Approved', status: 'approved' },
    { label: 'Completed', status: 'completed' },
  ]
}

// ── Helper: Check if a status is a rejection/cancellation terminal state ─────
export function isRejectedStatus(status: string): boolean {
  const rejectedStatuses = [
    'rejected', 'cancelled', 'returned', 'voided',
    'REJECTED', 'CANCELLED', 'RETURNED',
    'written_off', 'scrap',
  ]
  return rejectedStatuses.includes(status)
}
