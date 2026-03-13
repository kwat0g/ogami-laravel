import { createBrowserRouter, Navigate, Outlet } from 'react-router-dom'
import { lazy, Suspense } from 'react'
import AppLayout from '@/components/layout/AppLayout'
import AuthLayout from '@/components/layout/AuthLayout'
import SkeletonLoader from '@/components/ui/SkeletonLoader'
import { useAuthStore } from '@/stores/authStore'
import { useAuth } from '@/hooks/useAuth'
import { PayrollWizardProvider } from '@/contexts/PayrollWizardContext'

/**
 * Wraps the New Payroll Run wizard routes (Steps 1-3) in a shared
 * PayrollWizardProvider so all three pages share the same context instance.
 */
// eslint-disable-next-line react-refresh/only-export-components
function PayrollNewRunLayout() {
  return (
    <PayrollWizardProvider>
      <Outlet />
    </PayrollWizardProvider>
  )
}

// Route-level permission guard — redirects to /403 when user lacks permission.
// On page refresh the Zustand store is empty until /auth/me resolves, so we
// must wait for isLoading to be false before denying access to avoid a false
// 403 before the session is restored.
// eslint-disable-next-line react-refresh/only-export-components
function RequirePermission({ permission, children }: { permission: string; children: React.ReactNode }) {
  const { isLoading } = useAuth()
  const has = useAuthStore((s) => s.hasPermission(permission))
  if (isLoading) return <SkeletonLoader rows={6} />
  if (!has) return <Navigate to="/403" replace />
  return <>{children}</>
}

// Shorthand helpers
const guard = (permission: string, node: React.ReactNode) => (
  <RequirePermission permission={permission}>{node}</RequirePermission>
)

// Lazy-loaded pages
const LoginPage = lazy(() => import('@/pages/LoginPage'))
const Forbidden = lazy(() => import('@/pages/Forbidden'))
const NotFound = lazy(() => import('@/pages/NotFound'))
const Dashboard = lazy(() => import('@/pages/Dashboard'))

// HR domain
const EmployeeListPage = lazy(() => import('@/pages/hr/EmployeeListPage'))
const EmployeeDetailPage = lazy(() => import('@/pages/hr/EmployeeDetailPage'))
const EmployeeFormPage = lazy(() => import('@/pages/hr/EmployeeFormPage'))

// Payroll domain
const PayrollRunListPage = lazy(() => import('@/pages/payroll/PayrollRunListPage'))
const PayrollRunDetailPage = lazy(() => import('@/pages/payroll/PayrollRunDetailPage'))
const CreatePayrollRunPage = lazy(() => import('@/pages/payroll/CreatePayrollRunPage'))
// Wizard — Draft (new run, local until compute)
const PayrollRunDraftScopePage = lazy(() => import('@/pages/payroll/PayrollRunDraftScopePage'))
const PayrollRunDraftValidatePage = lazy(() => import('@/pages/payroll/PayrollRunDraftValidatePage'))
// Wizard step pages (v1.0)
const PayrollRunScopePage = lazy(() => import('@/pages/payroll/PayrollRunScopePage'))
const PayrollRunValidatePage = lazy(() => import('@/pages/payroll/PayrollRunValidatePage'))
const PayrollRunComputingPage = lazy(() => import('@/pages/payroll/PayrollRunComputingPage'))
const PayrollRunReviewPage = lazy(() => import('@/pages/payroll/PayrollRunReviewPage'))
const PayrollRunHrReviewPage = lazy(() => import('@/pages/payroll/PayrollRunHrReviewPage'))
const PayrollRunAcctgReviewPage = lazy(() => import('@/pages/payroll/PayrollRunAcctgReviewPage'))
const PayrollRunVpReviewPage = lazy(() => import('@/pages/payroll/PayrollRunVpReviewPage'))
const PayrollRunDisbursePage = lazy(() => import('@/pages/payroll/PayrollRunDisbursePage'))

// Accounting domain
const AccountsPage = lazy(() => import('@/pages/accounting/AccountsPage'))
const FiscalPeriodsPage = lazy(() => import('@/pages/accounting/FiscalPeriodsPage'))
const JournalEntriesPage = lazy(() => import('@/pages/accounting/JournalEntriesPage'))
const JournalEntryFormPage = lazy(() => import('@/pages/accounting/JournalEntryFormPage'))
const JournalEntryDetailPage = lazy(() => import('@/pages/accounting/JournalEntryDetailPage'))

// AP domain
const VendorsPage = lazy(() => import('@/pages/accounting/VendorsPage'))
const APInvoicesPage = lazy(() => import('@/pages/accounting/APInvoicesPage'))
const APInvoiceFormPage = lazy(() => import('@/pages/accounting/APInvoiceFormPage'))
const APDueDateMonitorPage = lazy(() => import('@/pages/accounting/APDueDateMonitorPage'))

// AR domain
const CustomersPage = lazy(() => import('@/pages/ar/CustomersPage'))
const CustomerInvoicesPage = lazy(() => import('@/pages/ar/CustomerInvoicesPage'))
const CustomerInvoiceFormPage = lazy(() => import('@/pages/ar/CustomerInvoiceFormPage'))
const CustomerInvoiceDetailPage = lazy(() => import('@/pages/ar/CustomerInvoiceDetailPage'))

// Tax domain
const VatLedgerPage = lazy(() => import('@/pages/accounting/VatLedgerPage'))
const TaxPeriodSummaryPage = lazy(() => import('@/pages/accounting/TaxPeriodSummaryPage'))

// Reports
const GovernmentReportsPage = lazy(() => import('@/pages/reports/GovernmentReportsPage'))

// Financial reports (GL-001 – GL-005)
const GeneralLedgerPage = lazy(() => import('@/pages/accounting/GeneralLedgerPage'))
const TrialBalancePage = lazy(() => import('@/pages/accounting/TrialBalancePage'))
const BalanceSheetPage = lazy(() => import('@/pages/accounting/BalanceSheetPage'))
const IncomeStatementPage = lazy(() => import('@/pages/accounting/IncomeStatementPage'))
const CashFlowPage = lazy(() => import('@/pages/accounting/CashFlowPage'))

// Banking (GL-006)
const BankAccountsPage = lazy(() => import('@/pages/banking/BankAccountsPage'))
const BankReconciliationPage = lazy(() => import('@/pages/banking/BankReconciliationPage'))
const BankReconciliationDetailPage = lazy(() => import('@/pages/banking/BankReconciliationDetailPage'))

// HR — Attendance
const AttendanceListPage = lazy(() => import('@/pages/hr/attendance/AttendanceListPage'))
const AttendanceImportPage = lazy(() => import('@/pages/hr/attendance/AttendanceImportPage'))
const AttendanceDashboardPage = lazy(() => import('@/pages/hr/attendance/AttendanceDashboardPage'))
const AttendanceSummaryPage   = lazy(() => import('@/pages/hr/attendance/AttendanceSummaryPage'))
const OvertimeListPage = lazy(() => import('@/pages/hr/attendance/OvertimeListPage'))

// HR — Leave
const LeaveListPage = lazy(() => import('@/pages/hr/leave/LeaveListPage'))
const LeaveFormPage = lazy(() => import('@/pages/hr/leave/LeaveFormPage'))
const LeaveBalancesPage = lazy(() => import('@/pages/hr/leave/LeaveBalancesPage'))
const LeaveCalendarPage = lazy(() => import('@/pages/hr/leave/LeaveCalendarPage'))

// HR — Loans
const LoanListPage = lazy(() => import('@/pages/hr/loans/LoanListPage'))
const LoanFormPage = lazy(() => import('@/pages/hr/loans/LoanFormPage'))
const LoanDetailPage = lazy(() => import('@/pages/hr/loans/LoanDetailPage'))

// HR — Reference data
const DepartmentsPage = lazy(() => import('@/pages/hr/DepartmentsPage'))
const PositionsPage = lazy(() => import('@/pages/hr/PositionsPage'))
const ShiftsPage = lazy(() => import('@/pages/hr/ShiftsPage'))
const HRReportsPage = lazy(() => import('@/pages/hr/HRReportsPage'))

// Team Management (department-scoped)
const TeamEmployeeListPage = lazy(() => import('@/pages/team/TeamEmployeeListPage'))
const TeamEmployeeDetailPage = lazy(() => import('@/pages/team/TeamEmployeeDetailPage'))
const TeamAttendancePage = lazy(() => import('@/pages/team/TeamAttendancePage'))
const TeamLeavePage = lazy(() => import('@/pages/team/TeamLeavePage'))
const TeamOvertimePage = lazy(() => import('@/pages/team/TeamOvertimePage'))
const TeamLoanPage = lazy(() => import('@/pages/team/TeamLoanPage'))

// Employee self-service
const MyPayslipsPage = lazy(() => import('@/pages/employee/MyPayslipsPage'))
const MyLeavesPage = lazy(() => import('@/pages/employee/MyLeavesPage'))
const MyLoansPage = lazy(() => import('@/pages/employee/MyLoansPage'))
const MyOTPage = lazy(() => import('@/pages/employee/MyOTPage'))
const MyAttendancePage = lazy(() => import('@/pages/employee/MyAttendancePage'))

// Admin
const UsersPage = lazy(() => import('@/pages/admin/UsersPage'))
const SystemSettingsPage = lazy(() => import('@/pages/admin/SystemSettingsPage'))
const GlobalSearchPage = lazy(() => import('@/pages/GlobalSearchPage'))
const AuditLogsPage = lazy(() => import('@/pages/admin/AuditLogsPage'))
const ReferenceTablesPage = lazy(() => import('@/pages/admin/ReferenceTablesPage'))
const BackupPage = lazy(() => import('@/pages/admin/BackupPage'))

// Auth account actions
const ChangePasswordPage = lazy(() => import('@/pages/auth/ChangePasswordPage'))

// Procurement domain
const PurchaseRequestListPage  = lazy(() => import('@/pages/procurement/PurchaseRequestListPage'))
const CreatePurchaseRequestPage = lazy(() => import('@/pages/procurement/CreatePurchaseRequestPage'))
const PurchaseRequestDetailPage = lazy(() => import('@/pages/procurement/PurchaseRequestDetailPage'))
const PurchaseOrderListPage     = lazy(() => import('@/pages/procurement/PurchaseOrderListPage'))
const CreatePurchaseOrderPage   = lazy(() => import('@/pages/procurement/CreatePurchaseOrderPage'))
const PurchaseOrderDetailPage   = lazy(() => import('@/pages/procurement/PurchaseOrderDetailPage'))
const GoodsReceiptListPage      = lazy(() => import('@/pages/procurement/GoodsReceiptListPage'))
const CreateGoodsReceiptPage    = lazy(() => import('@/pages/procurement/CreateGoodsReceiptPage'))
const GoodsReceiptDetailPage    = lazy(() => import('@/pages/procurement/GoodsReceiptDetailPage'))
const ProcurementAnalyticsPage  = lazy(() => import('@/pages/procurement/ProcurementAnalyticsPage'))

// Inventory
const ItemCategoriesPage               = lazy(() => import('@/pages/inventory/ItemCategoriesPage'))
const ItemMasterListPage               = lazy(() => import('@/pages/inventory/ItemMasterListPage'))
const ItemMasterFormPage               = lazy(() => import('@/pages/inventory/ItemMasterFormPage'))
const WarehouseLocationsPage           = lazy(() => import('@/pages/inventory/WarehouseLocationsPage'))
const StockBalancePage                 = lazy(() => import('@/pages/inventory/StockBalancePage'))
const StockLedgerPage                  = lazy(() => import('@/pages/inventory/StockLedgerPage'))
const StockAdjustmentsPage             = lazy(() => import('@/pages/inventory/StockAdjustmentsPage'))
const InventoryValuationPage           = lazy(() => import('@/pages/inventory/InventoryValuationPage'))
const PhysicalCountPage                = lazy(() => import('@/pages/inventory/PhysicalCountPage'))
const MaterialRequisitionListPage      = lazy(() => import('@/pages/inventory/MaterialRequisitionListPage'))
const CreateMaterialRequisitionPage    = lazy(() => import('@/pages/inventory/CreateMaterialRequisitionPage'))
const MaterialRequisitionDetailPage    = lazy(() => import('@/pages/inventory/MaterialRequisitionDetailPage'))

// Production / PPC
const BomListPage                    = lazy(() => import('@/pages/production/BomListPage'))
const CreateBomPage                  = lazy(() => import('@/pages/production/CreateBomPage'))
const EditBomPage                    = lazy(() => import('@/pages/production/EditBomPage'))
const DeliveryScheduleListPage       = lazy(() => import('@/pages/production/DeliveryScheduleListPage'))
const CreateDeliverySchedulePage     = lazy(() => import('@/pages/production/CreateDeliverySchedulePage'))
const ProductionOrderListPage        = lazy(() => import('@/pages/production/ProductionOrderListPage'))
const CreateProductionOrderPage      = lazy(() => import('@/pages/production/CreateProductionOrderPage'))
const ProductionOrderDetailPage      = lazy(() => import('@/pages/production/ProductionOrderDetailPage'))
const ProductionCostPage             = lazy(() => import('@/pages/production/ProductionCostPage'))

// Delivery
const DeliveryReceiptListPage         = lazy(() => import('@/pages/delivery/DeliveryReceiptListPage'))
const CreateDeliveryReceiptPage       = lazy(() => import('@/pages/delivery/CreateDeliveryReceiptPage'))
const DeliveryReceiptDetailPage       = lazy(() => import('@/pages/delivery/DeliveryReceiptDetailPage'))
const ShipmentsPage                   = lazy(() => import('@/pages/delivery/ShipmentsPage'))

// ISO / IATF
const DocumentRegisterPage           = lazy(() => import('@/pages/iso/DocumentRegisterPage'))
const CreateIsoDocumentPage          = lazy(() => import('@/pages/iso/CreateIsoDocumentPage'))
const IsoDocumentDetailPage          = lazy(() => import('@/pages/iso/IsoDocumentDetailPage'))
const AuditListPage                  = lazy(() => import('@/pages/iso/AuditListPage'))
const CreateIsoAuditPage             = lazy(() => import('@/pages/iso/CreateIsoAuditPage'))
const IsoAuditDetailPage             = lazy(() => import('@/pages/iso/IsoAuditDetailPage'))

// Maintenance
const EquipmentListPage              = lazy(() => import('@/pages/maintenance/EquipmentListPage'))
const EquipmentDetailPage            = lazy(() => import('@/pages/maintenance/EquipmentDetailPage'))
const CreateEquipmentPage            = lazy(() => import('@/pages/maintenance/CreateEquipmentPage'))
const WorkOrderListPage              = lazy(() => import('@/pages/maintenance/WorkOrderListPage'))
const WorkOrderDetailPage            = lazy(() => import('@/pages/maintenance/WorkOrderDetailPage'))
const CreateWorkOrderPage            = lazy(() => import('@/pages/maintenance/CreateWorkOrderPage'))

// QC additions
const CreateInspectionPage           = lazy(() => import('@/pages/qc/CreateInspectionPage'))
const CreateNcrPage                  = lazy(() => import('@/pages/qc/CreateNcrPage'))

// Mold
const MoldListPage                   = lazy(() => import('@/pages/mold/MoldListPage'))
const MoldDetailPage                 = lazy(() => import('@/pages/mold/MoldDetailPage'))
const CreateMoldPage                 = lazy(() => import('@/pages/mold/CreateMoldPage'))

// QC / QA
const InspectionListPage             = lazy(() => import('@/pages/qc/InspectionListPage'))
const InspectionDetailPage           = lazy(() => import('@/pages/qc/InspectionDetailPage'))
const NcrListPage                    = lazy(() => import('@/pages/qc/NcrListPage'))
const NcrDetailPage                  = lazy(() => import('@/pages/qc/NcrDetailPage'))
const QcTemplateListPage             = lazy(() => import('@/pages/qc/QcTemplateListPage'))
const CapaListPage                   = lazy(() => import('@/pages/qc/CapaListPage'))
const QcDefectRatePage               = lazy(() => import('@/pages/qc/QcDefectRatePage'))

// VP Approvals
const VpApprovalsDashboardPage = lazy(() => import('@/pages/approvals/VpApprovalsDashboardPage'))

// Additional AP
const APInvoiceDetailPage = lazy(() => import('@/pages/accounting/APInvoiceDetailPage'))

// Pay periods
const PayPeriodListPage = lazy(() => import('@/pages/payroll/PayPeriodListPage'))

// Executive
const ExecutiveLeaveApprovalPage    = lazy(() => import('@/pages/executive/ExecutiveLeaveApprovalPage'))
const ExecutiveOvertimeApprovalPage = lazy(() => import('@/pages/executive/ExecutiveOvertimeApprovalPage'))

// Employee self-service — profile
const MyProfilePage = lazy(() => import('@/pages/employee/MyProfilePage'))

// Vendor Portal
const VendorPortalLayout         = lazy(() => import('@/pages/vendor-portal/VendorPortalLayout'))
const VendorPortalDashboardPage  = lazy(() => import('@/pages/vendor-portal/VendorPortalDashboardPage'))
const VendorOrdersPage           = lazy(() => import('@/pages/vendor-portal/VendorOrdersPage'))
const VendorOrderDetailPage      = lazy(() => import('@/pages/vendor-portal/VendorOrderDetailPage'))
const VendorItemsPage            = lazy(() => import('@/pages/vendor-portal/VendorItemsPage'))
const VendorGoodsReceiptsPage    = lazy(() => import('@/pages/vendor-portal/VendorGoodsReceiptsPage'))
const VendorInvoicesPage         = lazy(() => import('@/pages/vendor-portal/VendorInvoicesPage'))

// CRM — Staff pages
const TicketListPage      = lazy(() => import('@/pages/crm/TicketListPage'))
const TicketDetailPage    = lazy(() => import('@/pages/crm/TicketDetailPage'))
const CrmDashboardPage    = lazy(() => import('@/pages/crm/CrmDashboardPage'))

// Budget domain
const CostCentersPage = lazy(() => import('@/pages/budget/CostCentersPage'))
const BudgetLinesPage = lazy(() => import('@/pages/budget/BudgetLinesPage'))
const BudgetVsActualPage = lazy(() => import('@/pages/budget/BudgetVsActualPage'))

// Fixed Assets domain
const FixedAssetsPage          = lazy(() => import('@/pages/fixed-assets/FixedAssetsPage'))
const FixedAssetDetailPage     = lazy(() => import('@/pages/fixed-assets/FixedAssetDetailPage'))
const FixedAssetCategoriesPage = lazy(() => import('@/pages/fixed-assets/FixedAssetCategoriesPage'))
const AssetDisposalPage        = lazy(() => import('@/pages/fixed-assets/AssetDisposalPage'))

// Recurring Templates
const RecurringTemplatesPage = lazy(() => import('@/pages/accounting/RecurringTemplatesPage'))

// Credit Notes
const VendorCreditNotesPage   = lazy(() => import('@/pages/accounting/VendorCreditNotesPage'))
const ApAgingReportPage       = lazy(() => import('@/pages/accounting/ApAgingReportPage'))
const CustomerCreditNotesPage = lazy(() => import('@/pages/ar/CustomerCreditNotesPage'))
const ArAgingReportPage       = lazy(() => import('@/pages/ar/ArAgingReportPage'))

// Vendor RFQs
const VendorRfqListPage   = lazy(() => import('@/pages/procurement/VendorRfqListPage'))
const VendorRfqDetailPage = lazy(() => import('@/pages/procurement/VendorRfqDetailPage'))

// Client Portal
const ClientPortalLayout        = lazy(() => import('@/pages/client-portal/ClientPortalLayout'))
const ClientTicketsPage         = lazy(() => import('@/pages/client-portal/ClientTicketsPage'))
const ClientTicketDetailPage    = lazy(() => import('@/pages/client-portal/ClientTicketDetailPage'))
const ClientNewTicketPage       = lazy(() => import('@/pages/client-portal/ClientNewTicketPage'))

const withSuspense = (node: React.ReactNode) => (
  <Suspense fallback={<SkeletonLoader rows={6} />}>{node}</Suspense>
)

export const router = createBrowserRouter([
  // ── Auth shell ───────────────────────────────────────────────────────────
  {
    element: <AuthLayout />,
    children: [
      { path: '/login', element: withSuspense(<LoginPage />) },
    ],
  },

  // ── App shell (requires authentication) ─────────────────────────────────
  {
    element: <AppLayout />,
    children: [
      { index: true, element: <Navigate to="/dashboard" replace /> },
      { path: '/dashboard', element: withSuspense(<Dashboard />) },

      // ── HR domain (all routes require hr.full_access - HR department only) ───
      // Note: Non-HR managers should use Team Management (/team/*) routes instead
      { path: '/hr/employees', element: withSuspense(guard('hr.full_access', <EmployeeListPage view="team" />)) },
      { path: '/hr/employees/all', element: withSuspense(guard('hr.full_access', <EmployeeListPage view="all" />)) },
      { path: '/hr/employees/new', element: withSuspense(guard('hr.full_access', <EmployeeFormPage />)) },
      { path: '/hr/employees/:ulid', element: withSuspense(guard('hr.full_access', <EmployeeDetailPage />)) },
      { path: '/hr/employees/:ulid/edit', element: withSuspense(guard('hr.full_access', <EmployeeFormPage />)) },

      // HR — Attendance
      { path: '/hr/attendance', element: withSuspense(guard('hr.full_access', <AttendanceListPage />)) },
      { path: '/hr/attendance/import', element: withSuspense(guard('hr.full_access', <AttendanceImportPage />)) },
      { path: '/hr/attendance/dashboard', element: withSuspense(guard('hr.full_access', <AttendanceDashboardPage />)) },
      { path: '/hr/attendance/summary',   element: withSuspense(guard('attendance.view_team', <AttendanceSummaryPage />)) },
      { path: '/hr/overtime', element: withSuspense(guard('hr.full_access', <OvertimeListPage />)) },

      // HR — Leave
      { path: '/hr/leave', element: withSuspense(guard('hr.full_access', <LeaveListPage />)) },
      { path: '/hr/leave/new', element: withSuspense(guard('hr.full_access', <LeaveFormPage />)) },
      { path: '/hr/leave/balances', element: withSuspense(guard('hr.full_access', <LeaveBalancesPage />)) },
      { path: '/hr/leave/calendar', element: withSuspense(guard('hr.full_access', <LeaveCalendarPage />)) },

      // HR — Loans
      { path: '/hr/loans', element: withSuspense(guard('hr.full_access', <LoanListPage />)) },
      { path: '/hr/loans/new', element: withSuspense(guard('hr.full_access', <LoanFormPage />)) },
      { path: '/hr/loans/:ulid', element: withSuspense(guard('hr.full_access', <LoanDetailPage />)) },

      // HR — Reference
      { path: '/hr/departments', element: withSuspense(guard('hr.full_access', <DepartmentsPage />)) },
      { path: '/hr/positions', element: withSuspense(guard('hr.full_access', <PositionsPage />)) },
      { path: '/hr/shifts', element: withSuspense(guard('hr.full_access', <ShiftsPage />)) },
      { path: '/hr/reports', element: withSuspense(guard('hr.full_access', <HRReportsPage />)) },

      // ── Team Management (department-scoped) ────────────────────────────────
      { path: '/team/employees', element: withSuspense(guard('employees.view_team', <TeamEmployeeListPage />)) },
      { path: '/team/employees/:ulid', element: withSuspense(guard('employees.view_team', <TeamEmployeeDetailPage />)) },
      { path: '/team/attendance', element: withSuspense(guard('attendance.view_team', <TeamAttendancePage />)) },
      { path: '/team/leave', element: withSuspense(guard('leaves.view_team', <TeamLeavePage />)) },
      { path: '/team/overtime', element: withSuspense(guard('overtime.view', <TeamOvertimePage />)) },
      { path: '/team/loans', element: withSuspense(guard('loans.view_department', <TeamLoanPage />)) },
      { path: '/team/shifts', element: withSuspense(guard('attendance.manage_shifts', <ShiftsPage />)) },

      // ── Executive domain ──────────────────────────────────────────────────
      { path: '/executive/leave-approvals', element: withSuspense(guard('leaves.ga_process', <ExecutiveLeaveApprovalPage />)) },
      { path: '/executive/overtime-approvals', element: withSuspense(guard('overtime.executive_approve', <ExecutiveOvertimeApprovalPage />)) },

      // ── Payroll domain ────────────────────────────────────────────────────
      { path: '/payroll/runs', element: withSuspense(guard('payroll.view_runs', <PayrollRunListPage />)) },
      // New run wizard (Steps 1-3 are local — no DB write until Step 3 commit)
      {
        path: '/payroll/runs/new',
        element: <RequirePermission permission="payroll.initiate"><PayrollNewRunLayout /></RequirePermission>,
        children: [
          { index: true, element: withSuspense(<CreatePayrollRunPage />) },
          { path: 'scope', element: withSuspense(<PayrollRunDraftScopePage />) },
          { path: 'validate', element: withSuspense(<PayrollRunDraftValidatePage />) },
        ],
      },
      { path: '/payroll/runs/:ulid', element: withSuspense(guard('payroll.view_runs', <PayrollRunDetailPage />)) },
      // Wizard steps (existing runs — resume after DB record exists)
      { path: '/payroll/runs/:ulid/scope', element: withSuspense(guard('payroll.initiate', <PayrollRunScopePage />)) },
      { path: '/payroll/runs/:ulid/validate', element: withSuspense(guard('payroll.initiate', <PayrollRunValidatePage />)) },
      { path: '/payroll/runs/:ulid/compute', element: withSuspense(guard('payroll.initiate', <PayrollRunComputingPage />)) },
      { path: '/payroll/runs/:ulid/review', element: withSuspense(guard('payroll.view_runs', <PayrollRunReviewPage />)) },
      { path: '/payroll/runs/:ulid/hr-review', element: withSuspense(guard('payroll.hr_approve', <PayrollRunHrReviewPage />)) },
      { path: '/payroll/runs/:ulid/acctg-review', element: withSuspense(guard('payroll.acctg_approve', <PayrollRunAcctgReviewPage />)) },
      { path: '/payroll/runs/:ulid/vp-review', element: withSuspense(guard('payroll.vp_approve', <PayrollRunVpReviewPage />)) },
      { path: '/payroll/runs/:ulid/disburse', element: withSuspense(guard('payroll.view_runs', <PayrollRunDisbursePage />)) },
      { path: '/payroll/periods', element: withSuspense(guard('payroll.manage_pay_periods', <PayPeriodListPage />)) },

      // ── Accounting domain ─────────────────────────────────────────────────
      { path: '/accounting/accounts', element: withSuspense(guard('chart_of_accounts.view', <AccountsPage />)) },
      { path: '/accounting/fiscal-periods', element: withSuspense(guard('fiscal_periods.view', <FiscalPeriodsPage />)) },
      { path: '/accounting/journal-entries', element: withSuspense(guard('journal_entries.view', <JournalEntriesPage />)) },
      { path: '/accounting/journal-entries/new', element: withSuspense(guard('journal_entries.create', <JournalEntryFormPage />)) },
      { path: '/accounting/journal-entries/:ulid', element: withSuspense(guard('journal_entries.view', <JournalEntryDetailPage />)) },

      // ── AP domain ─────────────────────────────────────────────────────────
      { path: '/accounting/vendors', element: withSuspense(guard('vendors.view', <VendorsPage />)) },
      { path: '/accounting/ap/invoices', element: withSuspense(guard('vendor_invoices.view', <APInvoicesPage />)) },
      { path: '/accounting/ap/invoices/new', element: withSuspense(guard('vendor_invoices.create', <APInvoiceFormPage />)) },
      { path: '/accounting/ap/invoices/:ulid', element: withSuspense(guard('vendor_invoices.view', <APInvoiceDetailPage />)) },
      { path: '/accounting/ap/monitor', element: withSuspense(guard('vendor_invoices.view', <APDueDateMonitorPage />)) },
      { path: '/accounting/ap/aging-report', element: withSuspense(guard('vendor_invoices.view', <ApAgingReportPage />)) },

      // ── AR domain ─────────────────────────────────────────────────────────
      { path: '/ar/customers', element: withSuspense(guard('customers.view', <CustomersPage />)) },
      { path: '/ar/invoices', element: withSuspense(guard('customer_invoices.view', <CustomerInvoicesPage />)) },
      { path: '/ar/invoices/new', element: withSuspense(guard('customer_invoices.create', <CustomerInvoiceFormPage />)) },
      { path: '/ar/invoices/:ulid', element: withSuspense(guard('customer_invoices.view', <CustomerInvoiceDetailPage />)) },
      { path: '/ar/aging-report',    element: withSuspense(guard('customer_invoices.view', <ArAgingReportPage />)) },

      // ── Accounting — Loan Approvals ──────────────────────────────────────
      { path: '/accounting/loans', element: withSuspense(guard('loans.accounting_approve', <LoanListPage />)) },
      { path: '/accounting/loans/:ulid', element: withSuspense(guard('loans.accounting_approve', <LoanDetailPage />)) },

      // ── Tax domain ────────────────────────────────────────────────────────
      { path: '/accounting/vat-ledger', element: withSuspense(guard('reports.vat', <VatLedgerPage />)) },
      { path: '/accounting/tax-summary', element: withSuspense(guard('reports.vat', <TaxPeriodSummaryPage />)) },

      // ── Reports ───────────────────────────────────────────────────────────
      { path: '/reports/government', element: withSuspense(guard('payroll.gov_reports', <GovernmentReportsPage />)) },

      // ── Financial reports (GL-001 – GL-005) ──────────────────────────────
      { path: '/accounting/gl', element: withSuspense(guard('journal_entries.view', <GeneralLedgerPage />)) },
      { path: '/accounting/trial-balance', element: withSuspense(guard('reports.financial_statements', <TrialBalancePage />)) },
      { path: '/accounting/balance-sheet', element: withSuspense(guard('reports.financial_statements', <BalanceSheetPage />)) },
      { path: '/accounting/income-statement', element: withSuspense(guard('reports.financial_statements', <IncomeStatementPage />)) },
      { path: '/accounting/cash-flow', element: withSuspense(guard('reports.financial_statements', <CashFlowPage />)) },

      // ── Banking (GL-006) ──────────────────────────────────────────────────
      { path: '/banking/accounts', element: withSuspense(guard('bank_accounts.view', <BankAccountsPage />)) },
      { path: '/banking/reconciliations', element: withSuspense(guard('bank_reconciliations.view', <BankReconciliationPage />)) },
      { path: '/banking/reconciliations/:ulid', element: withSuspense(guard('bank_reconciliations.view', <BankReconciliationDetailPage />)) },

      // ── Employee self-service ──────────────────────────────────────────────
      { path: '/self-service/payslips', element: withSuspense(<MyPayslipsPage />) },
      { path: '/me/leaves', element: withSuspense(<MyLeavesPage />) },
      { path: '/me/loans', element: withSuspense(<MyLoansPage />) },
      { path: '/me/overtime', element: withSuspense(<MyOTPage />) },
      { path: '/me/attendance', element: withSuspense(<MyAttendancePage />) },
      { path: '/me/profile', element: withSuspense(<MyProfilePage />) },

      // ── Account actions ────────────────────────────────────────────────────
      { path: '/account/change-password', element: withSuspense(<ChangePasswordPage />) },

      // ── Procurement domain ────────────────────────────────────────────────
      { path: '/procurement/purchase-requests', element: withSuspense(guard('procurement.purchase-request.view', <PurchaseRequestListPage />)) },
      { path: '/procurement/purchase-requests/new', element: withSuspense(guard('procurement.purchase-request.create', <CreatePurchaseRequestPage />)) },
      { path: '/procurement/purchase-requests/:ulid', element: withSuspense(guard('procurement.purchase-request.view', <PurchaseRequestDetailPage />)) },
      { path: '/procurement/purchase-orders', element: withSuspense(guard('procurement.purchase-order.view', <PurchaseOrderListPage />)) },
      { path: '/procurement/purchase-orders/new', element: withSuspense(guard('procurement.purchase-order.create', <CreatePurchaseOrderPage />)) },
      { path: '/procurement/purchase-orders/:ulid', element: withSuspense(guard('procurement.purchase-order.view', <PurchaseOrderDetailPage />)) },
      { path: '/procurement/goods-receipts', element: withSuspense(guard('procurement.goods-receipt.view', <GoodsReceiptListPage />)) },
      { path: '/procurement/goods-receipts/new', element: withSuspense(guard('procurement.goods-receipt.create', <CreateGoodsReceiptPage />)) },
      { path: '/procurement/goods-receipts/:ulid', element: withSuspense(guard('procurement.goods-receipt.view', <GoodsReceiptDetailPage />)) },
      { path: '/procurement/analytics', element: withSuspense(guard('procurement.purchase-order.view', <ProcurementAnalyticsPage />)) },

      // ── Inventory domain ──────────────────────────────────────────────────
      { path: '/inventory/categories', element: withSuspense(guard('inventory.items.view', <ItemCategoriesPage />)) },
      { path: '/inventory/items', element: withSuspense(guard('inventory.items.view', <ItemMasterListPage />)) },
      { path: '/inventory/items/new', element: withSuspense(guard('inventory.items.create', <ItemMasterFormPage />)) },
      { path: '/inventory/items/:ulid', element: withSuspense(guard('inventory.items.edit', <ItemMasterFormPage />)) },
      { path: '/inventory/locations', element: withSuspense(guard('inventory.locations.view', <WarehouseLocationsPage />)) },
      { path: '/inventory/stock', element: withSuspense(guard('inventory.stock.view', <StockBalancePage />)) },
      { path: '/inventory/ledger', element: withSuspense(guard('inventory.stock.view', <StockLedgerPage />)) },
      { path: '/inventory/adjustments', element: withSuspense(guard('inventory.adjustments.create', <StockAdjustmentsPage />)) },
      { path: '/inventory/requisitions', element: withSuspense(guard('inventory.mrq.view', <MaterialRequisitionListPage />)) },
      { path: '/inventory/requisitions/new', element: withSuspense(guard('inventory.mrq.create', <CreateMaterialRequisitionPage />)) },
      { path: '/inventory/requisitions/:ulid', element: withSuspense(guard('inventory.mrq.view', <MaterialRequisitionDetailPage />)) },
      { path: '/inventory/valuation', element: withSuspense(guard('inventory.stock.view', <InventoryValuationPage />)) },
      { path: '/inventory/physical-count', element: withSuspense(guard('inventory.adjustments.create', <PhysicalCountPage />)) },

      // ── Production / PPC ──────────────────────────────────────────────────
      { path: '/production/boms', element: withSuspense(guard('production.bom.view', <BomListPage />)) },
      { path: '/production/boms/new', element: withSuspense(guard('production.bom.manage', <CreateBomPage />)) },
      { path: '/production/boms/:ulid/edit', element: withSuspense(guard('production.bom.manage', <EditBomPage />)) },
      { path: '/production/delivery-schedules', element: withSuspense(guard('production.delivery-schedule.view', <DeliveryScheduleListPage />)) },
      { path: '/production/delivery-schedules/new', element: withSuspense(guard('production.delivery-schedule.manage', <CreateDeliverySchedulePage />)) },
      { path: '/production/orders', element: withSuspense(guard('production.orders.view', <ProductionOrderListPage />)) },
      { path: '/production/orders/new', element: withSuspense(guard('production.orders.create', <CreateProductionOrderPage />)) },
      { path: '/production/orders/:ulid', element: withSuspense(guard('production.orders.view', <ProductionOrderDetailPage />)) },
      { path: '/production/cost-analysis', element: withSuspense(guard('production.orders.view', <ProductionCostPage />)) },

      // ── QC / QA ────────────────────────────────────────────────────
      { path: '/qc/inspections', element: withSuspense(guard('qc.inspections.view', <InspectionListPage />)) },
      { path: '/qc/inspections/new', element: withSuspense(guard('qc.inspections.create', <CreateInspectionPage />)) },
      { path: '/qc/inspections/:ulid', element: withSuspense(guard('qc.inspections.view', <InspectionDetailPage />)) },
      { path: '/qc/ncrs', element: withSuspense(guard('qc.ncr.view', <NcrListPage />)) },
      { path: '/qc/ncrs/new', element: withSuspense(guard('qc.ncr.create', <CreateNcrPage />)) },
      { path: '/qc/ncrs/:ulid', element: withSuspense(guard('qc.ncr.view', <NcrDetailPage />)) },
      { path: '/qc/templates', element: withSuspense(guard('qc.templates.view', <QcTemplateListPage />)) },
      { path: '/qc/capa',      element: withSuspense(guard('qc.ncr.view',       <CapaListPage />)) },
      { path: '/qc/defect-rate', element: withSuspense(guard('qc.inspections.view', <QcDefectRatePage />)) },

      // ── Maintenance ───────────────────────────────────────────────────────
      { path: '/maintenance/equipment', element: withSuspense(guard('maintenance.view', <EquipmentListPage />)) },
      { path: '/maintenance/equipment/new', element: withSuspense(guard('maintenance.manage', <CreateEquipmentPage />)) },
      { path: '/maintenance/equipment/:ulid', element: withSuspense(guard('maintenance.view', <EquipmentDetailPage />)) },
      { path: '/maintenance/work-orders', element: withSuspense(guard('maintenance.view', <WorkOrderListPage />)) },
      { path: '/maintenance/work-orders/new', element: withSuspense(guard('maintenance.manage', <CreateWorkOrderPage />)) },
      { path: '/maintenance/work-orders/:ulid', element: withSuspense(guard('maintenance.view', <WorkOrderDetailPage />)) },

      // ── Mold ─────────────────────────────────────────────────────────────
      { path: '/mold/masters', element: withSuspense(guard('mold.view', <MoldListPage />)) },
      { path: '/mold/masters/new', element: withSuspense(guard('mold.manage', <CreateMoldPage />)) },
      { path: '/mold/masters/:ulid', element: withSuspense(guard('mold.view', <MoldDetailPage />)) },

      // ── Delivery / Logistics ─────────────────────────────────────────
      { path: '/delivery/receipts', element: withSuspense(guard('delivery.view', <DeliveryReceiptListPage />)) },
      { path: '/delivery/receipts/new', element: withSuspense(guard('delivery.manage', <CreateDeliveryReceiptPage />)) },
      { path: '/delivery/receipts/:ulid', element: withSuspense(guard('delivery.view', <DeliveryReceiptDetailPage />)) },
      { path: '/delivery/shipments', element: withSuspense(guard('delivery.view', <ShipmentsPage />)) },

      // ── ISO / IATF ──────────────────────────────────────────────────
      { path: '/iso/documents', element: withSuspense(guard('iso.view', <DocumentRegisterPage />)) },
      { path: '/iso/documents/new', element: withSuspense(guard('iso.manage', <CreateIsoDocumentPage />)) },
      { path: '/iso/documents/:ulid', element: withSuspense(guard('iso.view', <IsoDocumentDetailPage />)) },
      { path: '/iso/audits', element: withSuspense(guard('iso.view', <AuditListPage />)) },
      { path: '/iso/audits/new', element: withSuspense(guard('iso.manage', <CreateIsoAuditPage />)) },
      { path: '/iso/audits/:ulid', element: withSuspense(guard('iso.view', <IsoAuditDetailPage />)) },

      // ── VP Approvals Dashboard ────────────────────────────────────────────
      { path: '/approvals/pending', element: withSuspense(guard('loans.vp_approve', <VpApprovalsDashboardPage />)) },
      { path: '/approvals/loans',   element: withSuspense(guard('loans.vp_approve', <LoanListPage />)) },
      { path: '/approvals/loans/:ulid', element: withSuspense(guard('loans.vp_approve', <LoanDetailPage />)) },

      // ── Admin ──────────────────────────────────────────────────────────────
      { path: '/admin/users', element: withSuspense(guard('system.manage_users', <UsersPage />)) },
      { path: '/admin/settings', element: withSuspense(guard('system.edit_settings', <SystemSettingsPage />)) },
      { path: '/search', element: withSuspense(<GlobalSearchPage />) },
      { path: '/admin/audit-logs', element: withSuspense(guard('system.view_audit_log', <AuditLogsPage />)) },
      { path: '/admin/reference-tables', element: withSuspense(guard('system.edit_settings', <ReferenceTablesPage />)) },      { path: '/admin/backup', element: withSuspense(guard('system.manage_backups', <BackupPage />)) },

      // ── CRM — Staff portal ────────────────────────────────────────────────
      { path: '/crm/dashboard',      element: withSuspense(guard('crm.tickets.view', <CrmDashboardPage />)) },
      { path: '/crm/tickets',        element: withSuspense(guard('crm.tickets.view', <TicketListPage />)) },
      { path: '/crm/tickets/:ulid',  element: withSuspense(guard('crm.tickets.view', <TicketDetailPage />)) },

      // ── Budget ────────────────────────────────────────────────────────────
      { path: '/budget/cost-centers', element: withSuspense(guard('budget.view', <CostCentersPage />)) },
      { path: '/budget/lines',        element: withSuspense(guard('budget.view', <BudgetLinesPage />)) },
      { path: '/budget/vs-actual',    element: withSuspense(guard('budget.view', <BudgetVsActualPage />)) },

      // ── Fixed Assets ──────────────────────────────────────────────────────
      { path: '/fixed-assets',             element: withSuspense(guard('fixed_assets.view', <FixedAssetsPage />)) },
      { path: '/fixed-assets/categories',  element: withSuspense(guard('fixed_assets.view', <FixedAssetCategoriesPage />)) },
      { path: '/fixed-assets/disposals',    element: withSuspense(guard('fixed_assets.view', <AssetDisposalPage />)) },
      { path: '/fixed-assets/:ulid',       element: withSuspense(guard('fixed_assets.view', <FixedAssetDetailPage />)) },

      // ── Recurring Journal Templates ────────────────────────────────────────
      { path: '/accounting/recurring-templates', element: withSuspense(guard('journal_entries.view', <RecurringTemplatesPage />)) },

      // ── Credit Notes ──────────────────────────────────────────────────────
      { path: '/accounting/ap/credit-notes', element: withSuspense(guard('vendor_invoices.view', <VendorCreditNotesPage />)) },
      { path: '/ar/credit-notes',            element: withSuspense(guard('customer_invoices.view', <CustomerCreditNotesPage />)) },

      // ── Vendor RFQs ───────────────────────────────────────────────────────
      { path: '/procurement/rfqs',        element: withSuspense(guard('procurement.purchase-request.view', <VendorRfqListPage />)) },
      { path: '/procurement/rfqs/:ulid',  element: withSuspense(guard('procurement.purchase-request.view', <VendorRfqDetailPage />)) },
    ],
  },

  // ── Vendor Portal (standalone layout, vendor role only) ──────────────────
  {
    path: '/vendor-portal',
    element: <Suspense fallback={null}><VendorPortalLayout /></Suspense>,
    children: [
      { index: true, element: <Navigate to="/vendor-portal/dashboard" replace /> },
      { path: 'dashboard', element: withSuspense(guard('vendor_portal.view_orders', <VendorPortalDashboardPage />)) },
      { path: 'orders',    element: withSuspense(guard('vendor_portal.view_orders', <VendorOrdersPage />)) },
      { path: 'orders/:ulid', element: withSuspense(guard('vendor_portal.view_orders', <VendorOrderDetailPage />)) },
      { path: 'items',     element: withSuspense(guard('vendor_portal.manage_items', <VendorItemsPage />)) },
      { path: 'goods-receipts', element: withSuspense(guard('vendor_portal.view_receipts', <VendorGoodsReceiptsPage />)) },
      { path: 'invoices', element: withSuspense(guard('vendor_portal.view_receipts', <VendorInvoicesPage />)) },
    ],
  },

  // ── Client Portal (standalone layout, client role only) ──────────────────
  {
    path: '/client-portal',
    element: <Suspense fallback={null}><ClientPortalLayout /></Suspense>,
    children: [
      { index: true, element: <Navigate to="/client-portal/tickets" replace /> },
      { path: 'tickets',         element: withSuspense(guard('crm.tickets.view', <ClientTicketsPage />)) },
      { path: 'tickets/new',     element: withSuspense(guard('crm.tickets.create', <ClientNewTicketPage />)) },
      { path: 'tickets/:ulid',   element: withSuspense(guard('crm.tickets.view', <ClientTicketDetailPage />)) },
    ],
  },

  // ── Utility pages ────────────────────────────────────────────────────────
  { path: '/403', element: withSuspense(<Forbidden />) },
  { path: '*', element: withSuspense(<NotFound />) },
])
