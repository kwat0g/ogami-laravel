/**
 * Typed constants for every Spatie permission name in the ERP.
 *
 * Usage:
 *   import { PERMISSIONS } from '@/lib/permissions'
 *   const canApprove = hasPermission(PERMISSIONS.leaves.approve)
 *
 * Mirrors RolePermissionSeeder.php (v1.0 matrix) — keep in sync when new
 * permissions are added.  The legacy alias block at the bottom preserves
 * backward compatibility with old permission names still seeded for tests.
 */

// ── Build helper ─────────────────────────────────────────────────────────────

function perms<R extends string, A extends string>(
  resource: R,
  actions: A[],
): Record<A, `${R}.${A}`> {
  return Object.fromEntries(
    actions.map((a) => [a, `${resource}.${a}`]),
  ) as Record<A, `${R}.${A}`>
}

// ── Permission Map — canonical names from RolePermissionSeeder ───────────────

export const PERMISSIONS = {
  // ── HR Employee ──────────────────────────────────────────────────────────
  employees: perms('employees', [
    'view', 'view_team', 'view_full_record', 'view_salary',
    'view_unmasked_gov_ids', 'view_masked_gov_ids',
    'create', 'update', 'update_salary',
    'activate', 'suspend', 'terminate',
    'upload_documents', 'download_documents',
    'export', 'manage_structure',
  ]),

  // ── Attendance ───────────────────────────────────────────────────────────
  attendance: perms('attendance', [
    'view_own', 'view_team', 'import_csv',
    'view_anomalies', 'resolve_anomalies', 'manage_shifts',
    // legacy aliases
    'view', 'create', 'update', 'delete', 'export', 'lock', 'unlock',
  ]),

  // ── Overtime ─────────────────────────────────────────────────────────────
  overtime: perms('overtime', [
    'view', 'submit', 'approve', 'reject', 'supervise', 'executive_approve',
    // legacy aliases
    'create', 'update',
  ]),

  // ── Leave ────────────────────────────────────────────────────────────────
  leaves: perms('leaves', [
    'view_own', 'view_team', 'file_own', 'file_on_behalf',
    'cancel', 'head_approve', 'manager_check', 'ga_process', 'vp_note',
    'approve', 'reject',
    'adjust_balance', 'configure_types', 'trigger_sil_monetization',
    // legacy aliases
    'view', 'create', 'supervise', 'executive_approve',
  ]),

  leave_balances: perms('leave_balances', ['view', 'adjust']),

  // ── Loans ────────────────────────────────────────────────────────────────
  loans: perms('loans', [
    'view_own', 'view_department', 'apply',
    'supervisor_review', 'hr_approve', 'accounting_approve',
    'view_ln007_log', 'configure_types',
    // v2 5-stage approval chain
    'head_note', 'manager_check', 'officer_review', 'vp_approve',
    // legacy aliases
    'view', 'create', 'update', 'approve', 'reject',
  ]),

  // ── Payroll ──────────────────────────────────────────────────────────────
  payroll: perms('payroll', [
    'view_own_payslip', 'download_own_payslip',
    'view_runs', 'manage_pay_periods',
    'initiate', 'pre_run_validate', 'compute',
    'review_breakdown', 'flag_employee',
    'submit_for_hr', 'hr_approve', 'hr_return',
    'acctg_approve', 'acctg_reject',
    'vp_approve',
    'disburse', 'download_bank_file',
    'publish', 'view_deduction_trace',
    'download_register', 'gov_reports',
    // legacy aliases
    'view', 'submit', 'approve', 'post', 'recall',
  ]),

  payslips: perms('payslips', ['view', 'download']),

  // ── BIR / Gov Reports ────────────────────────────────────────────────────
  reports: perms('reports', [
    'financial_statements', 'gl', 'trial_balance',
    'ap_aging', 'ar_aging', 'vat', 'bank_reconciliation',
    'bir_2316', 'bir_alphalist', 'bir_1601c',
    'sss_sbr2', 'philhealth_rf1', 'pagibig_mc',
  ]),

  // ── GL & Journal Entries ─────────────────────────────────────────────────
  journal_entries:   perms('journal_entries',  ['view', 'create', 'update', 'submit', 'post', 'reverse', 'export']),
  chart_of_accounts: perms('chart_of_accounts', ['view', 'manage']),
  fiscal_periods:    perms('fiscal_periods',    ['view', 'manage']),

  // ── AP ───────────────────────────────────────────────────────────────────
  vendors:         perms('vendors',         ['view', 'manage', 'archive', 'accredit', 'suspend']),
  vendor_invoices: perms('vendor_invoices', [
    'view', 'create', 'update', 'submit',
    'approve', 'reject', 'record_payment', 'cancel', 'export',
  ]),
  vendor_payments: perms('vendor_payments', ['view', 'create']),
  bir_2307:        perms('bir_2307',        ['generate']),

  // ── AR ───────────────────────────────────────────────────────────────────
  customers:        perms('customers',        ['view', 'manage', 'archive']),
  customer_invoices: perms('customer_invoices', [
    'view', 'create', 'update', 'approve', 'cancel',
    'override_credit', 'receive_payment', 'write_off', 'apply_payment', 'export',
  ]),

  // ── Banking ──────────────────────────────────────────────────────────────
  bank_accounts:        perms('bank_accounts',        ['view', 'create', 'update', 'delete']),
  bank_reconciliations: perms('bank_reconciliations', ['view', 'create', 'certify']),

  // ── Self-service ─────────────────────────────────────────────────────────
  self: perms('self', ['view_profile', 'submit_profile_update', 'view_attendance']),

  // ── System Administration (admin only) ───────────────────────────────────
  system: perms('system', [
    'manage_users', 'assign_roles', 'assign_departments',
    'unlock_accounts', 'edit_settings',
    'manage_rate_tables', 'manage_holidays', 'manage_ewt_atc',
    'reopen_fiscal_period', 'view_audit_log',
    'view_horizon', 'view_pulse', 'manage_backups',
  ]),

  // ── HR ───────────────────────────────────────────────────────────────────
  hr: perms('hr', ['full_access']),

  // ── Procurement ──────────────────────────────────────────────────────────
  procurement: {
    purchase_request: perms('procurement.purchase-request', [
      'view', 'create', 'note', 'check', 'review', 'budget-check',
    ]),
    purchase_order: perms('procurement.purchase-order', [
      'view', 'create', 'manage',
    ]),
    goods_receipt: perms('procurement.goods-receipt', [
      'view', 'create', 'confirm',
    ]),
  },

  // ── Inventory ────────────────────────────────────────────────────────────
  inventory: {
    items: perms('inventory.items', ['view', 'create', 'edit']),
    locations: perms('inventory.locations', ['view', 'manage']),
    stock: perms('inventory.stock', ['view']),
    adjustments: perms('inventory.adjustments', ['create']),
    mrq: perms('inventory.mrq', ['view', 'create', 'note', 'check', 'review', 'vp_approve', 'fulfill']),
  },

  // ── Production / PPC ─────────────────────────────────────────────────────
  production: {
    bom: perms('production.bom', ['view', 'manage']),
    delivery_schedule: perms('production.delivery-schedule', ['view', 'manage']),
    orders: perms('production.orders', ['view', 'create', 'release', 'complete', 'log_output']),
    qc_override: 'production.qc-override' as const,
  },

  // ── QC / QA ──────────────────────────────────────────────────────────────
  qc: {
    templates: perms('qc.templates', ['view', 'manage']),
    inspections: perms('qc.inspections', ['view', 'create']),
    ncr: perms('qc.ncr', ['view', 'create', 'close']),
  },

  // ── Maintenance ──────────────────────────────────────────────────────────
  maintenance: perms('maintenance', ['view', 'manage']),

  // ── Mold ─────────────────────────────────────────────────────────────────
  mold: perms('mold', ['view', 'manage', 'log_shots']),

  // ── Delivery / Logistics ─────────────────────────────────────────────────
  delivery: {
    ...perms('delivery', ['view', 'manage']),
    routes: perms('delivery.routes', ['view', 'manage']),
  },

  // ── ISO / IATF ───────────────────────────────────────────────────────────
  iso: perms('iso', ['view', 'manage', 'audit']),

  // ── CRM ──────────────────────────────────────────────────────────────────
  crm: {
    tickets: perms('crm.tickets', ['view', 'create', 'reply', 'manage', 'assign', 'close']),
  },

  // ── Sales / Client Orders ─────────────────────────────────────────────────
  sales: {
    orders: perms('sales', ['order_review', 'order_approve', 'order_reject', 'order_negotiate', 'order_vp_approve']),
    quotations: perms('sales.quotations', ['view', 'create', 'update', 'send', 'accept']),
    sales_orders: perms('sales.orders', ['view', 'confirm', 'cancel']),
  },

  // ── VP Approvals ─────────────────────────────────────────────────────────
  approvals: {
    vp: perms('approvals.vp', ['view', 'approve']),
  },

  // ── Vendor Portal ────────────────────────────────────────────────────────
  vendor_portal: perms('vendor_portal', [
    'view_orders', 'update_fulfillment', 'manage_items', 'view_receipts',
  ]),

  // ── Client Portal ──────────────────────────────────────────────────────
  client_portal: perms('client_portal', [
    'view_dashboard', 'view_shop', 'view_orders',
  ]),
  // ── AP Extras ──────────────────────────────────────────────────────────
  ap: {
    payment_batches: perms('ap.payment_batches', ['view', 'create', 'approve']),
  },

  // ── AR Extras ─────────────────────────────────────────────────────────
  ar: {
    dunning: perms('ar.dunning', ['view', 'create', 'send']),
  },

  // ── Budget ───────────────────────────────────────────────────────────
  budget: perms('budget', ['view', 'manage', 'approve']),

  // ── Fixed Assets ─────────────────────────────────────────────────────
  fixed_assets: perms('fixed_assets', ['view', 'manage', 'dispose']),} as const

export type PermissionString =
  typeof PERMISSIONS[keyof typeof PERMISSIONS][keyof typeof PERMISSIONS[keyof typeof PERMISSIONS]]
