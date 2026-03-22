/**
 * Action Categories - Defines which actions require confirmation modals
 * and what type of confirmation to use.
 * 
 * This centralizes the decision logic for destructive/important actions.
 */

export interface ActionCategory {
  /** Type of confirmation required */
  type: 'destructive' | 'confirm' | 'none'
  /** The confirmation word for destructive actions (e.g., 'DELETE', 'VOID') */
  confirmWord?: string
  /** Default title for the confirmation dialog */
  title: string
  /** Default description for the confirmation dialog */
  description: string
  /** Custom confirm button label */
  confirmLabel?: string
}

/** Actions that permanently delete data */
export const DESTRUCTIVE_ACTIONS: Record<string, ActionCategory> = {
  // HR
  'employee.delete': {
    type: 'destructive',
    confirmWord: 'DELETE',
    title: 'Delete Employee?',
    description: 'This will permanently delete the employee record and all associated data. This action cannot be undone.',
    confirmLabel: 'Delete Employee',
  },
  'employee.terminate': {
    type: 'destructive',
    confirmWord: 'TERMINATE',
    title: 'Terminate Employee?',
    description: 'This will terminate the employee and mark all active records as ended.',
    confirmLabel: 'Terminate',
  },
  
  // Payroll
  'payroll.void': {
    type: 'destructive',
    confirmWord: 'VOID',
    title: 'Void Payroll Run?',
    description: 'This action cannot be undone. All released payments will be reversed.',
    confirmLabel: 'Void Payroll',
  },
  'payroll.delete': {
    type: 'destructive',
    confirmWord: 'DELETE',
    title: 'Delete Payroll Run?',
    description: 'This will permanently delete this payroll run and all associated calculations.',
    confirmLabel: 'Delete',
  },
  
  // Accounting
  'journal_entry.delete': {
    type: 'destructive',
    confirmWord: 'DELETE',
    title: 'Delete Journal Entry?',
    description: 'This will permanently delete this journal entry. Posted entries should be reversed instead.',
    confirmLabel: 'Delete',
  },
  'journal_entry.reverse': {
    type: 'destructive',
    confirmWord: 'REVERSE',
    title: 'Reverse Journal Entry?',
    description: 'This will create a reversing entry. This action cannot be undone.',
    confirmLabel: 'Reverse',
  },
  'vendor.delete': {
    type: 'destructive',
    confirmWord: 'DELETE',
    title: 'Delete Vendor?',
    description: 'This will permanently delete this vendor and all associated records.',
    confirmLabel: 'Delete Vendor',
  },
  'customer.delete': {
    type: 'destructive',
    confirmWord: 'DELETE',
    title: 'Delete Customer?',
    description: 'This will permanently delete this customer and all associated records.',
    confirmLabel: 'Delete Customer',
  },
  'invoice.delete': {
    type: 'destructive',
    confirmWord: 'DELETE',
    title: 'Delete Invoice?',
    description: 'This will permanently delete this invoice. Approved invoices should be cancelled instead.',
    confirmLabel: 'Delete',
  },
  'invoice.write_off': {
    type: 'destructive',
    confirmWord: 'WRITE OFF',
    title: 'Write Off Invoice?',
    description: 'This will mark the invoice as bad debt. This action cannot be undone.',
    confirmLabel: 'Write Off',
  },
  
  // Inventory
  'item.delete': {
    type: 'destructive',
    confirmWord: 'DELETE',
    title: 'Delete Item?',
    description: 'This will permanently delete this item from the item master.',
    confirmLabel: 'Delete Item',
  },
  'adjustment.delete': {
    type: 'destructive',
    confirmWord: 'DELETE',
    title: 'Delete Stock Adjustment?',
    description: 'This will delete this stock adjustment and reverse its effects.',
    confirmLabel: 'Delete',
  },
  
  // Production
  'bom.delete': {
    type: 'destructive',
    confirmWord: 'DELETE',
    title: 'Delete BOM?',
    description: 'This will permanently delete this Bill of Materials.',
    confirmLabel: 'Delete BOM',
  },
  'work_order.delete': {
    type: 'destructive',
    confirmWord: 'DELETE',
    title: 'Delete Work Order?',
    description: 'This will permanently delete this work order.',
    confirmLabel: 'Delete',
  },
  
  // QC
  'ncr.delete': {
    type: 'destructive',
    confirmWord: 'DELETE',
    title: 'Delete NCR?',
    description: 'This will permanently delete this Non-Conformance Report.',
    confirmLabel: 'Delete NCR',
  },
  
  // Admin
  'user.delete': {
    type: 'destructive',
    confirmWord: 'DELETE',
    title: 'Delete User Account?',
    description: 'This will permanently delete this user account and all associated access.',
    confirmLabel: 'Delete User',
  },
  'backup.delete': {
    type: 'destructive',
    confirmWord: 'DELETE',
    title: 'Delete Backup?',
    description: 'This will permanently delete this backup file.',
    confirmLabel: 'Delete Backup',
  },
}

/** Actions that change status or state (reversible, but important) */
export const CONFIRM_ACTIONS: Record<string, ActionCategory> = {
  // HR
  'employee.activate': {
    type: 'confirm',
    title: 'Activate Employee?',
    description: 'This will activate the employee account and enable system access.',
    confirmLabel: 'Activate',
  },
  'employee.suspend': {
    type: 'confirm',
    title: 'Suspend Employee?',
    description: 'This will suspend the employee and disable system access.',
    confirmLabel: 'Suspend',
  },
  'leave.approve': {
    type: 'confirm',
    title: 'Approve Leave Request?',
    description: 'This will approve the leave request and deduct from available balance.',
    confirmLabel: 'Approve',
  },
  'leave.reject': {
    type: 'confirm',
    title: 'Reject Leave Request?',
    description: 'This will reject the leave request.',
    confirmLabel: 'Reject',
  },
  'loan.approve': {
    type: 'confirm',
    title: 'Approve Loan?',
    description: 'This will approve the loan application and create the amortization schedule.',
    confirmLabel: 'Approve Loan',
  },
  
  // Payroll
  'payroll.submit': {
    type: 'confirm',
    title: 'Submit for Approval?',
    description: 'This will submit the payroll run for HR approval. No further edits can be made.',
    confirmLabel: 'Submit',
  },
  'payroll.approve': {
    type: 'confirm',
    title: 'Approve Payroll?',
    description: 'This will approve the payroll run for disbursement.',
    confirmLabel: 'Approve',
  },
  'payroll.publish': {
    type: 'confirm',
    title: 'Publish Payslips?',
    description: 'This will publish payslips to employees. This cannot be undone.',
    confirmLabel: 'Publish',
  },
  
  // Accounting
  'journal_entry.post': {
    type: 'confirm',
    title: 'Post Journal Entry?',
    description: 'This will post the entry to the General Ledger. Posted entries cannot be edited.',
    confirmLabel: 'Post Entry',
  },
  'journal_entry.submit': {
    type: 'confirm',
    title: 'Submit Journal Entry?',
    description: 'This will submit the journal entry for approval.',
    confirmLabel: 'Submit',
  },
  'invoice.approve': {
    type: 'confirm',
    title: 'Approve Invoice?',
    description: 'This will generate the invoice number and post to GL.',
    confirmLabel: 'Approve',
  },
  'invoice.cancel': {
    type: 'confirm',
    title: 'Cancel Invoice?',
    description: 'This will cancel the invoice and create a reversing entry.',
    confirmLabel: 'Cancel',
  },
  'vendor.archive': {
    type: 'confirm',
    title: 'Archive Vendor?',
    description: 'This will archive the vendor and prevent new transactions.',
    confirmLabel: 'Archive',
  },
  
  // Procurement
  'po.approve': {
    type: 'confirm',
    title: 'Approve Purchase Order?',
    description: 'This will approve the PO and send it to the vendor.',
    confirmLabel: 'Approve PO',
  },
  'po.cancel': {
    type: 'confirm',
    title: 'Cancel Purchase Order?',
    description: 'This will cancel the PO and notify the vendor.',
    confirmLabel: 'Cancel PO',
  },
  'pr.approve': {
    type: 'confirm',
    title: 'Approve Purchase Request?',
    description: 'This will approve the PR for conversion to PO.',
    confirmLabel: 'Approve',
  },
  
  // Inventory
  'mrq.approve': {
    type: 'confirm',
    title: 'Approve Material Requisition?',
    description: 'This will approve the MR for fulfillment.',
    confirmLabel: 'Approve',
  },
  'adjustment.post': {
    type: 'confirm',
    title: 'Post Stock Adjustment?',
    description: 'This will apply the adjustment to stock levels.',
    confirmLabel: 'Post Adjustment',
  },
  
  // Production
  'work_order.release': {
    type: 'confirm',
    title: 'Release Work Order?',
    description: 'This will release the WO to production.',
    confirmLabel: 'Release',
  },
  'work_order.complete': {
    type: 'confirm',
    title: 'Complete Work Order?',
    description: 'This will mark the WO as complete and update inventory.',
    confirmLabel: 'Complete',
  },
  
  // System
  'settings.save': {
    type: 'confirm',
    title: 'Save System Settings?',
    description: 'This will update system-wide configuration.',
    confirmLabel: 'Save Settings',
  },
}

/** Get the action category for a given action key */
export function getActionCategory(actionKey: string): ActionCategory | null {
  return DESTRUCTIVE_ACTIONS[actionKey] ?? CONFIRM_ACTIONS[actionKey] ?? null
}

/** Check if an action requires confirmation */
export function requiresConfirmation(actionKey: string): boolean {
  const category = getActionCategory(actionKey)
  return category?.type === 'destructive' || category?.type === 'confirm'
}

/** Check if an action is destructive (requires typing confirmation word) */
export function isDestructive(actionKey: string): boolean {
  return getActionCategory(actionKey)?.type === 'destructive'
}
