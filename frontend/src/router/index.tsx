import { createBrowserRouter, Navigate, Outlet, RouterProvider } from 'react-router-dom'
import { lazy, Suspense } from 'react'
import AppLayout from '@/components/layout/AppLayout'
import AuthLayout from '@/components/layout/AuthLayout'
import ErrorBoundary from '@/components/ui/ErrorBoundary'
import SkeletonLoader from '@/components/ui/SkeletonLoader'
import { getLandingPath } from '@/lib/roleLanding'
import { useAuthStore } from '@/stores/authStore'
import { useAuth } from '@/hooks/useAuth'
import { PayrollWizardProvider } from '@/contexts/PayrollWizardContext'

/**
 * Wraps the New Payroll Run wizard routes (Steps 1-3) in a shared
 * PayrollWizardProvider so all three pages share the same context instance.
 */
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
// Supports pipe-separated permissions (OR logic): 'perm1|perm2' checks if user has EITHER permission.
function RequirePermission({ permission, children }: { permission: string; children: React.ReactNode }) {
  const { isLoading } = useAuth()
  const permissions = permission.split('|')
  const has = useAuthStore((s) => permissions.some((p) => s.hasPermission(p)))
  if (isLoading) return <SkeletonLoader rows={6} />
  if (!has) return <Navigate to="/403" replace />
  return <>{children}</>
}

// Role-aware landing route for authenticated users.
function RoleLandingRedirect() {
  const { user, isLoading } = useAuth()
  if (isLoading) return <SkeletonLoader rows={6} />
  return <Navigate to={getLandingPath(user)} replace />
}

// Shorthand helpers
const guard = (permission: string, node: React.ReactNode) => (
  <RequirePermission permission={permission}>{node}</RequirePermission>
)


// Helper: Wrap lazy imports to catch "Failed to fetch dynamically imported module" errors.
// When a chunk fails to load, it reloads the page to get the latest chunk mapping.
// eslint-disable-next-line @typescript-eslint/no-explicit-any
function lazyWithRetry<T extends React.ComponentType<any>>(importFunc: () => Promise<{ default: T }>) {
  return lazy(() =>
    importFunc().catch((error) => {
      console.warn("Chunk failed to load, reloading...", error)
      window.location.reload()
      // Return a dummy component to appease React until the page reloads
      return { default: (() => <SkeletonLoader rows={6} />) as unknown as T }
    })
  )
}

// Lazy-loaded pages
const LoginPage = lazyWithRetry(() => import('@/pages/LoginPage'))
const Forbidden = lazyWithRetry(() => import('@/pages/Forbidden'))
const NotFound = lazyWithRetry(() => import('@/pages/NotFound'))
const Dashboard = lazyWithRetry(() => import('@/pages/Dashboard'))

// HR domain
const EmployeeListPage = lazyWithRetry(() => import('@/pages/hr/EmployeeListPage'))
const EmployeeDetailPage = lazyWithRetry(() => import('@/pages/hr/EmployeeDetailPage'))
const EmployeeFormPage = lazyWithRetry(() => import('@/pages/hr/EmployeeFormPage'))

// Payroll domain
const PayrollRunListPage = lazyWithRetry(() => import('@/pages/payroll/PayrollRunListPage'))
const PayrollRunDetailPage = lazyWithRetry(() => import('@/pages/payroll/PayrollRunDetailPage'))
const CreatePayrollRunPage = lazyWithRetry(() => import('@/pages/payroll/CreatePayrollRunPage'))
// Wizard — Draft (new run, local until compute)
const PayrollRunDraftScopePage = lazyWithRetry(() => import('@/pages/payroll/PayrollRunDraftScopePage'))
const PayrollRunDraftValidatePage = lazyWithRetry(() => import('@/pages/payroll/PayrollRunDraftValidatePage'))
// Wizard step pages (v1.0)
const PayrollRunScopePage = lazyWithRetry(() => import('@/pages/payroll/PayrollRunScopePage'))
const PayrollRunValidatePage = lazyWithRetry(() => import('@/pages/payroll/PayrollRunValidatePage'))
const PayrollRunComputingPage = lazyWithRetry(() => import('@/pages/payroll/PayrollRunComputingPage'))
const PayrollRunReviewPage = lazyWithRetry(() => import('@/pages/payroll/PayrollRunReviewPage'))
const PayrollRunHrReviewPage = lazyWithRetry(() => import('@/pages/payroll/PayrollRunHrReviewPage'))
const PayrollRunAcctgReviewPage = lazyWithRetry(() => import('@/pages/payroll/PayrollRunAcctgReviewPage'))
const PayrollRunVpReviewPage = lazyWithRetry(() => import('@/pages/payroll/PayrollRunVpReviewPage'))
const PayrollRunDisbursePage = lazyWithRetry(() => import('@/pages/payroll/PayrollRunDisbursePage'))

// Accounting domain
const AccountsPage = lazyWithRetry(() => import('@/pages/accounting/AccountsPage'))
const FiscalPeriodsPage = lazyWithRetry(() => import('@/pages/accounting/FiscalPeriodsPage'))
const JournalEntriesPage = lazyWithRetry(() => import('@/pages/accounting/JournalEntriesPage'))
const JournalEntryFormPage = lazyWithRetry(() => import('@/pages/accounting/JournalEntryFormPage'))
const JournalEntryDetailPage = lazyWithRetry(() => import('@/pages/accounting/JournalEntryDetailPage'))

// AP domain
const VendorsPage = lazyWithRetry(() => import('@/pages/accounting/VendorsPage'))
const APInvoicesPage = lazyWithRetry(() => import('@/pages/accounting/APInvoicesPage'))
const APInvoiceFormPage = lazyWithRetry(() => import('@/pages/accounting/APInvoiceFormPage'))
const APDueDateMonitorPage = lazyWithRetry(() => import('@/pages/accounting/APDueDateMonitorPage'))

// AR domain
const CustomersPage = lazyWithRetry(() => import('@/pages/ar/CustomersPage'))
const CustomerInvoicesPage = lazyWithRetry(() => import('@/pages/ar/CustomerInvoicesPage'))
const CustomerInvoiceFormPage = lazyWithRetry(() => import('@/pages/ar/CustomerInvoiceFormPage'))
const CustomerInvoiceDetailPage = lazyWithRetry(() => import('@/pages/ar/CustomerInvoiceDetailPage'))

// Tax domain
const VatLedgerPage = lazyWithRetry(() => import('@/pages/accounting/VatLedgerPage'))
const TaxPeriodSummaryPage = lazyWithRetry(() => import('@/pages/accounting/TaxPeriodSummaryPage'))

// Reports
const GovernmentReportsPage = lazyWithRetry(() => import('@/pages/reports/GovernmentReportsPage'))

// Financial reports (GL-001 – GL-005)
const GeneralLedgerPage = lazyWithRetry(() => import('@/pages/accounting/GeneralLedgerPage'))
const TrialBalancePage = lazyWithRetry(() => import('@/pages/accounting/TrialBalancePage'))
const BalanceSheetPage = lazyWithRetry(() => import('@/pages/accounting/BalanceSheetPage'))
const IncomeStatementPage = lazyWithRetry(() => import('@/pages/accounting/IncomeStatementPage'))
const CashFlowPage = lazyWithRetry(() => import('@/pages/accounting/CashFlowPage'))

// Banking (GL-006)
const BankAccountsPage = lazyWithRetry(() => import('@/pages/banking/BankAccountsPage'))
const BankReconciliationPage = lazyWithRetry(() => import('@/pages/banking/BankReconciliationPage'))
const BankReconciliationDetailPage = lazyWithRetry(() => import('@/pages/banking/BankReconciliationDetailPage'))

// HR — Attendance
const AttendanceListPage = lazyWithRetry(() => import('@/pages/hr/attendance/AttendanceListPage'))
const AttendanceImportPage = lazyWithRetry(() => import('@/pages/hr/attendance/AttendanceImportPage'))
const AttendanceDashboardPage = lazyWithRetry(() => import('@/pages/hr/attendance/AttendanceDashboardPage'))
const OvertimeListPage = lazyWithRetry(() => import('@/pages/hr/attendance/OvertimeListPage'))
const TimeInOutPage = lazyWithRetry(() => import('@/pages/employee/TimeInOutPage'))
const WorkLocationsPage = lazyWithRetry(() => import('@/pages/hr/attendance/WorkLocationsPage'))

// HR — Leave
const LeaveListPage = lazyWithRetry(() => import('@/pages/hr/leave/LeaveListPage'))
const LeaveFormPage = lazyWithRetry(() => import('@/pages/hr/leave/LeaveFormPage'))
const LeaveDetailPage = lazyWithRetry(() => import('@/pages/hr/leave/LeaveDetailPage'))
const LeaveBalancesPage = lazyWithRetry(() => import('@/pages/hr/leave/LeaveBalancesPage'))
const LeaveCalendarPage = lazyWithRetry(() => import('@/pages/hr/leave/LeaveCalendarPage'))

// HR — Loans
const LoanListPage = lazyWithRetry(() => import('@/pages/hr/loans/LoanListPage'))
const _LoanFormPage = lazyWithRetry(() => import('@/pages/hr/loans/LoanFormPage'))
const LoanDetailPage = lazyWithRetry(() => import('@/pages/hr/loans/LoanDetailPage'))

// HR — Recruitment
// HR — Recruitment (consolidated tabbed page + detail/form pages)
const RecruitmentPage = lazyWithRetry(() => import('@/pages/hr/recruitment/RecruitmentPage'))
const RequisitionFormPage = lazyWithRetry(() => import('@/pages/hr/recruitment/RequisitionFormPage'))
const RequisitionDetailPage = lazyWithRetry(() => import('@/pages/hr/recruitment/RequisitionDetailPage'))
const ApplicationFormPage = lazyWithRetry(() => import('@/pages/hr/recruitment/ApplicationFormPage'))
const ApplicationDetailPage = lazyWithRetry(() => import('@/pages/hr/recruitment/ApplicationDetailPage'))
const JobPostingFormPage = lazyWithRetry(() => import('@/pages/hr/recruitment/JobPostingFormPage'))
const JobPostingDetailPage = lazyWithRetry(() => import('@/pages/hr/recruitment/JobPostingDetailPage'))
const InterviewDetailPage = lazyWithRetry(() => import('@/pages/hr/recruitment/InterviewDetailPage'))
const OfferDetailPage = lazyWithRetry(() => import('@/pages/hr/recruitment/OfferDetailPage'))
const CandidateProfilePage = lazyWithRetry(() => import('@/pages/hr/recruitment/CandidateProfilePage'))

// HR — Org Chart + Performance Appraisals + Clearance (Gap Fix)
const OrgChartPage = lazyWithRetry(() => import('@/pages/hr/OrgChartPage'))
const PerformanceAppraisalListPage = lazyWithRetry(() => import('@/pages/hr/PerformanceAppraisalListPage'))
const EmployeeClearancePage = lazyWithRetry(() => import('@/pages/hr/EmployeeClearancePage'))
const OnboardingChecklistPage = lazyWithRetry(() => import('@/pages/hr/OnboardingChecklistPage'))

// HR — Reference data
const DepartmentsPage = lazyWithRetry(() => import('@/pages/hr/DepartmentsPage'))
const PositionsPage = lazyWithRetry(() => import('@/pages/hr/PositionsPage'))
const ShiftsPage = lazyWithRetry(() => import('@/pages/hr/ShiftsPage'))

// Team Management (department-scoped)
const TeamEmployeeListPage = lazyWithRetry(() => import('@/pages/team/TeamEmployeeListPage'))
const TeamEmployeeDetailPage = lazyWithRetry(() => import('@/pages/team/TeamEmployeeDetailPage'))
const TeamAttendancePage = lazyWithRetry(() => import('@/pages/team/TeamAttendancePage'))
const TeamLeavePage = lazyWithRetry(() => import('@/pages/team/TeamLeavePage'))
const TeamOvertimePage = lazyWithRetry(() => import('@/pages/team/TeamOvertimePage'))
const TeamLoanPage = lazyWithRetry(() => import('@/pages/team/TeamLoanPage'))

// Employee self-service
const MyPayslipsPage = lazyWithRetry(() => import('@/pages/employee/MyPayslipsPage'))
const MyLeavesPage = lazyWithRetry(() => import('@/pages/employee/MyLeavesPage'))
const MyLoansPage = lazyWithRetry(() => import('@/pages/employee/MyLoansPage'))
const MyOTPage = lazyWithRetry(() => import('@/pages/employee/MyOTPage'))
const _MyAttendancePage = lazyWithRetry(() => import('@/pages/employee/MyAttendancePage'))

// Admin
const UsersPage = lazyWithRetry(() => import('@/pages/admin/UsersPage'))
const SystemSettingsPage = lazyWithRetry(() => import('@/pages/admin/SystemSettingsPage'))
const GlobalSearchPage = lazyWithRetry(() => import('@/pages/GlobalSearchPage'))
const AuditLogsPage = lazyWithRetry(() => import('@/pages/admin/AuditLogsPage'))
const ReferenceTablesPage = lazyWithRetry(() => import('@/pages/admin/ReferenceTablesPage'))
const BackupPage = lazyWithRetry(() => import('@/pages/admin/BackupPage'))

// Auth account actions
const ChangePasswordPage = lazyWithRetry(() => import('@/pages/auth/ChangePasswordPage'))

// Procurement domain
const PurchaseRequestListPage  = lazyWithRetry(() => import('@/pages/procurement/PurchaseRequestListPage'))
const CreatePurchaseRequestPage = lazyWithRetry(() => import('@/pages/procurement/CreatePurchaseRequestPage'))
const PurchaseRequestDetailPage = lazyWithRetry(() => import('@/pages/procurement/PurchaseRequestDetailPage'))
const PurchaseOrderListPage     = lazyWithRetry(() => import('@/pages/procurement/PurchaseOrderListPage'))
const CreatePurchaseOrderPage   = lazyWithRetry(() => import('@/pages/procurement/CreatePurchaseOrderPage'))
const PurchaseOrderDetailPage   = lazyWithRetry(() => import('@/pages/procurement/PurchaseOrderDetailPage'))
const GoodsReceiptListPage      = lazyWithRetry(() => import('@/pages/procurement/GoodsReceiptListPage'))
const CreateGoodsReceiptPage    = lazyWithRetry(() => import('@/pages/procurement/CreateGoodsReceiptPage'))
const GoodsReceiptDetailPage    = lazyWithRetry(() => import('@/pages/procurement/GoodsReceiptDetailPage'))
const ProcurementAnalyticsPage  = lazyWithRetry(() => import('@/pages/procurement/ProcurementAnalyticsPage'))

// Inventory
const ItemCategoriesPage               = lazyWithRetry(() => import('@/pages/inventory/ItemCategoriesPage'))
const ItemMasterListPage               = lazyWithRetry(() => import('@/pages/inventory/ItemMasterListPage'))
const ItemMasterFormPage               = lazyWithRetry(() => import('@/pages/inventory/ItemMasterFormPage'))
const WarehouseLocationsPage           = lazyWithRetry(() => import('@/pages/inventory/WarehouseLocationsPage'))
const StockBalancePage                 = lazyWithRetry(() => import('@/pages/inventory/StockBalancePage'))
const StockLedgerPage                  = lazyWithRetry(() => import('@/pages/inventory/StockLedgerPage'))
const StockAdjustmentsPage             = lazyWithRetry(() => import('@/pages/inventory/StockAdjustmentsPage'))
const InventoryValuationPage           = lazyWithRetry(() => import('@/pages/inventory/InventoryValuationPage'))
const InventoryAnalyticsPage           = lazyWithRetry(() => import('@/pages/inventory/InventoryAnalyticsPage'))
const PhysicalCountPage                = lazyWithRetry(() => import('@/pages/inventory/PhysicalCountPage'))
const MaterialRequisitionListPage      = lazyWithRetry(() => import('@/pages/inventory/MaterialRequisitionListPage'))
const CreateMaterialRequisitionPage    = lazyWithRetry(() => import('@/pages/inventory/CreateMaterialRequisitionPage'))
const MaterialRequisitionDetailPage    = lazyWithRetry(() => import('@/pages/inventory/MaterialRequisitionDetailPage'))

// Production / PPC
const BomListPage                    = lazyWithRetry(() => import('@/pages/production/BomListPage'))
const CreateBomPage                  = lazyWithRetry(() => import('@/pages/production/CreateBomPage'))
const EditBomPage                    = lazyWithRetry(() => import('@/pages/production/EditBomPage'))
const DeliveryScheduleListPage = lazyWithRetry(() => import('@/pages/production/DeliveryScheduleListPage'))
const CreateDeliverySchedulePage = lazyWithRetry(() => import('@/pages/production/CreateDeliverySchedulePage'))
const DeliveryScheduleDetailPage = lazyWithRetry(() => import('@/pages/production/DeliveryScheduleDetailPage'))
const CombinedDeliveryScheduleListPage = lazyWithRetry(() => import('@/pages/production/CombinedDeliveryScheduleListPage'))
const CombinedDeliveryScheduleDetailPage = lazyWithRetry(() => import('@/pages/production/CombinedDeliveryScheduleDetailPage'))
const ProductionOrderListPage = lazyWithRetry(() => import('@/pages/production/ProductionOrderListPage'))
const CreateProductionOrderPage       = lazyWithRetry(() => import('@/pages/production/CreateProductionOrderPage'))
const ProductionOrderDetailPage      = lazyWithRetry(() => import('@/pages/production/ProductionOrderDetailPage'))
const ProductionCostPage             = lazyWithRetry(() => import('@/pages/production/ProductionCostPage'))
const BomCostBreakdownPage           = lazyWithRetry(() => import('@/pages/production/BomCostBreakdownPage'))
const BomDetailPage                  = lazyWithRetry(() => import('@/pages/production/BomDetailPage'))

// Production — Work Centers, Routing, MRP (Gap Fix)
const WorkCenterListPage             = lazyWithRetry(() => import('@/pages/production/WorkCenterListPage'))
const RoutingListPage                = lazyWithRetry(() => import('@/pages/production/RoutingListPage'))
const MrpResultsPage                 = lazyWithRetry(() => import('@/pages/production/MrpResultsPage'))

// Delivery
const DeliveryReceiptListPage         = lazyWithRetry(() => import('@/pages/delivery/DeliveryReceiptListPage'))
const CreateDeliveryReceiptPage       = lazyWithRetry(() => import('@/pages/delivery/CreateDeliveryReceiptPage'))
const FleetPage                       = lazyWithRetry(() => import('@/pages/delivery/FleetPage'))
const DeliveryReceiptDetailPage       = lazyWithRetry(() => import('@/pages/delivery/DeliveryReceiptDetailPage'))
const ShipmentsPage                   = lazyWithRetry(() => import('@/pages/delivery/ShipmentsPage'))

// Maintenance
const EquipmentListPage              = lazyWithRetry(() => import('@/pages/maintenance/EquipmentListPage'))
const EquipmentDetailPage            = lazyWithRetry(() => import('@/pages/maintenance/EquipmentDetailPage'))
const CreateEquipmentPage            = lazyWithRetry(() => import('@/pages/maintenance/CreateEquipmentPage'))
const WorkOrderListPage              = lazyWithRetry(() => import('@/pages/maintenance/WorkOrderListPage'))
const WorkOrderDetailPage            = lazyWithRetry(() => import('@/pages/maintenance/WorkOrderDetailPage'))
const CreateWorkOrderPage            = lazyWithRetry(() => import('@/pages/maintenance/CreateWorkOrderPage'))

// QC additions
const CreateInspectionPage           = lazyWithRetry(() => import('@/pages/qc/CreateInspectionPage'))
const CreateNcrPage                  = lazyWithRetry(() => import('@/pages/qc/CreateNcrPage'))

// Mold
const MoldListPage                   = lazyWithRetry(() => import('@/pages/mold/MoldListPage'))
const MoldDetailPage                 = lazyWithRetry(() => import('@/pages/mold/MoldDetailPage'))
const CreateMoldPage                 = lazyWithRetry(() => import('@/pages/mold/CreateMoldPage'))

// QC / QA
const InspectionListPage             = lazyWithRetry(() => import('@/pages/qc/InspectionListPage'))
const InspectionDetailPage           = lazyWithRetry(() => import('@/pages/qc/InspectionDetailPage'))
const NcrListPage                    = lazyWithRetry(() => import('@/pages/qc/NcrListPage'))
const NcrDetailPage                  = lazyWithRetry(() => import('@/pages/qc/NcrDetailPage'))
const QcTemplateListPage             = lazyWithRetry(() => import('@/pages/qc/QcTemplateListPage'))
const CapaListPage                   = lazyWithRetry(() => import('@/pages/qc/CapaListPage'))
const QcDefectRatePage               = lazyWithRetry(() => import('@/pages/qc/QcDefectRatePage'))
const QuarantineManagementPage       = lazyWithRetry(() => import('@/pages/qc/QuarantineManagementPage'))

// VP Approvals
const VpApprovalsDashboardPage = lazyWithRetry(() => import('@/pages/approvals/VpApprovalsDashboardPage'))

// Additional AP
const APInvoiceDetailPage = lazyWithRetry(() => import('@/pages/accounting/APInvoiceDetailPage'))

// Pay periods + Final Pay
const PayPeriodListPage = lazyWithRetry(() => import('@/pages/payroll/PayPeriodListPage'))
const FinalPayPage = lazyWithRetry(() => import('@/pages/payroll/FinalPayPage'))

// (ExecutiveLeaveApprovalPage and ExecutiveOvertimeApprovalPage consolidated into VpApprovalsDashboardPage)

// Employee self-service — profile
const MyProfilePage = lazyWithRetry(() => import('@/pages/employee/MyProfilePage'))

// Vendor Portal
const VendorPortalLayout         = lazyWithRetry(() => import('@/pages/vendor-portal/VendorPortalLayout'))
const VendorPortalDashboardPage  = lazyWithRetry(() => import('@/pages/vendor-portal/VendorPortalDashboardPage'))
const VendorOrdersPage           = lazyWithRetry(() => import('@/pages/vendor-portal/VendorOrdersPage'))
const VendorOrderDetailPage      = lazyWithRetry(() => import('@/pages/vendor-portal/VendorOrderDetailPage'))
const VendorItemsPage            = lazyWithRetry(() => import('@/pages/vendor-portal/VendorItemsPage'))
const VendorGoodsReceiptsPage    = lazyWithRetry(() => import('@/pages/vendor-portal/VendorGoodsReceiptsPage'))
const VendorGoodsReceiptDetailPage = lazyWithRetry(() => import('@/pages/vendor-portal/VendorGoodsReceiptDetailPage'))
const VendorInvoicesPage         = lazyWithRetry(() => import('@/pages/vendor-portal/VendorInvoicesPage'))
const VendorInvoiceDetailPage    = lazyWithRetry(() => import('@/pages/vendor-portal/VendorInvoiceDetailPage'))

// CRM — Staff pages
const TicketListPage      = lazyWithRetry(() => import('@/pages/crm/TicketListPage'))
const TicketDetailPage    = lazyWithRetry(() => import('@/pages/crm/TicketDetailPage'))
const CrmDashboardPage    = lazyWithRetry(() => import('@/pages/crm/CrmDashboardPage'))


// Sales Module (Phase 1)
const QuotationListPage      = lazyWithRetry(() => import('@/pages/sales/QuotationListPage'))
const QuotationMarginPage    = lazyWithRetry(() => import('@/pages/sales/QuotationMarginPage'))
const SalesOrderListPage     = lazyWithRetry(() => import('@/pages/sales/SalesOrderListPage'))

// Inventory Enhancements (Phase 1)
const StockTransferPage   = lazyWithRetry(() => import('@/pages/inventory/StockTransferPage'))

// Phase 2-4 pages
const QuotationDetailPage = lazyWithRetry(() => import('@/pages/sales/QuotationDetailPage'))
const SalesOrderDetailPage = lazyWithRetry(() => import('@/pages/sales/SalesOrderDetailPage'))
const PaymentBatchListPage = lazyWithRetry(() => import('@/pages/procurement/PaymentBatchListPage'))
const DunningNoticesPage = lazyWithRetry(() => import('@/pages/ar/DunningNoticesPage'))
const MaintenanceAnalyticsPage = lazyWithRetry(() => import('@/pages/maintenance/MaintenanceAnalyticsPage'))
const MoldLifecyclePage = lazyWithRetry(() => import('@/pages/mold/MoldLifecyclePage'))
const SpcDashboardPage = lazyWithRetry(() => import('@/pages/qc/SpcDashboardPage'))
const SupplierQualityPage = lazyWithRetry(() => import('@/pages/qc/SupplierQualityPage'))
const BirFormGeneratorPage = lazyWithRetry(() => import('@/pages/tax/BirFormGeneratorPage'))
const BirFilingListPage = lazyWithRetry(() => import('@/pages/tax/BirFilingListPage'))
const DeliveryRoutesPage = lazyWithRetry(() => import('@/pages/delivery/DeliveryRoutesPage'))

// Budget domain
const CostCentersPage = lazyWithRetry(() => import('@/pages/budget/CostCentersPage'))
const DepartmentBudgetsPage = lazyWithRetry(() => import('@/pages/budget/DepartmentBudgetsPage'))
const BudgetLinesPage = lazyWithRetry(() => import('@/pages/budget/BudgetLinesPage'))
const BudgetVariancePage = lazyWithRetry(() => import('@/pages/budget/BudgetVariancePage'))

// Enhancement pages
const FinancialRatiosPage            = lazyWithRetry(() => import('@/pages/accounting/FinancialRatiosPage'))
const BlanketPurchaseOrdersPage      = lazyWithRetry(() => import('@/pages/procurement/BlanketPurchaseOrdersPage'))

// Fixed Assets domain (reduced)
const FixedAssetsPage          = lazyWithRetry(() => import('@/pages/fixed-assets/FixedAssetsPage'))
const FixedAssetDetailPage     = lazyWithRetry(() => import('@/pages/fixed-assets/FixedAssetDetailPage'))

// Recurring Templates
const RecurringTemplatesPage = lazyWithRetry(() => import('@/pages/accounting/RecurringTemplatesPage'))

// Credit Notes
const VendorCreditNotesPage   = lazyWithRetry(() => import('@/pages/accounting/VendorCreditNotesPage'))
const ApAgingReportPage       = lazyWithRetry(() => import('@/pages/accounting/ApAgingReportPage'))
const CustomerCreditNotesPage = lazyWithRetry(() => import('@/pages/ar/CustomerCreditNotesPage'))
const ArAgingReportPage       = lazyWithRetry(() => import('@/pages/ar/ArAgingReportPage'))

// Analytics pages (new)
const ExecutiveAnalyticsDashboard = lazyWithRetry(() => import('@/pages/dashboard/ExecutiveAnalyticsDashboard'))
const VendorScorecardPage         = lazyWithRetry(() => import('@/pages/procurement/VendorScorecardPage'))

// Vendor RFQs
const VendorRfqListPage   = lazyWithRetry(() => import('@/pages/procurement/VendorRfqListPage'))
const VendorRfqDetailPage = lazyWithRetry(() => import('@/pages/procurement/VendorRfqDetailPage'))

// Client Portal
const ClientPortalLayout        = lazyWithRetry(() => import('@/pages/client-portal/ClientPortalLayout'))
const ClientDashboardPage       = lazyWithRetry(() => import('@/pages/client-portal/ClientDashboardPage'))
const ClientTicketsPage         = lazyWithRetry(() => import('@/pages/client-portal/ClientTicketsPage'))
const ClientTicketDetailPage    = lazyWithRetry(() => import('@/pages/client-portal/ClientTicketDetailPage'))
const ClientNewTicketPage       = lazyWithRetry(() => import('@/pages/client-portal/ClientNewTicketPage'))
const ClientShopPage            = lazyWithRetry(() => import('@/pages/client-portal/ClientShopPage'))
const ClientOrdersPage = lazyWithRetry(() => import('@/pages/client-portal/ClientOrdersPage'))
const ClientOrderDetailPage = lazyWithRetry(() => import('@/pages/client-portal/ClientOrderDetailPage'))
const OrderReceiptPage = lazyWithRetry(() => import('@/pages/client-portal/OrderReceiptPage'))
const ClientOrdersReviewPage    = lazyWithRetry(() => import('@/pages/sales/ClientOrdersReviewPage'))
const ClientOrderReviewDetailPage = lazyWithRetry(() => import('@/pages/sales/ClientOrderDetailPage'))

const withSuspense = (node: React.ReactNode) => (
  <ErrorBoundary>
    <Suspense fallback={<SkeletonLoader rows={6} />}>{node}</Suspense>
  </ErrorBoundary>
)

const router = createBrowserRouter([
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
      { index: true, element: <RoleLandingRedirect /> },
      { path: '/dashboard', element: withSuspense(<Dashboard />) },
      { path: '/dashboard/executive-analytics', element: withSuspense(guard('reports.financial_statements', <ExecutiveAnalyticsDashboard />)) },

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
      { path: '/admin/work-locations', element: withSuspense(guard('system.edit_settings', <WorkLocationsPage />)) },
      { path: '/hr/overtime', element: withSuspense(guard('hr.full_access', <OvertimeListPage />)) },

      // HR — Leave
      { path: '/hr/leave', element: withSuspense(guard('hr.full_access', <LeaveListPage />)) },
      { path: '/hr/leave/new', element: withSuspense(guard('hr.full_access', <LeaveFormPage />)) },
      // M14 FIX: Add leave detail page route for supervisors to view before approving
      { path: '/hr/leave/:id', element: withSuspense(guard('hr.full_access', <LeaveDetailPage />)) },
      { path: '/hr/leave/balances', element: withSuspense(guard('hr.full_access', <LeaveBalancesPage />)) },
      { path: '/hr/leave/calendar', element: withSuspense(guard('hr.full_access', <LeaveCalendarPage />)) },

      // HR — Loans (view/approve only — loan applications filed by employees via self-service)
      { path: '/hr/loans', element: withSuspense(guard('hr.full_access', <LoanListPage />)) },
      { path: '/hr/loans/:ulid', element: withSuspense(guard('hr.full_access', <LoanDetailPage />)) },

      // HR — Recruitment (consolidated tabbed page + detail/form pages)
      { path: '/hr/recruitment', element: withSuspense(guard('hr.full_access|recruitment.requisitions.view|recruitment.requisitions.create|recruitment.interviews.evaluate', <RecruitmentPage />)) },
      { path: '/hr/recruitment/requisitions/new', element: withSuspense(guard('hr.full_access|recruitment.requisitions.create', <RequisitionFormPage />)) },
      { path: '/hr/recruitment/requisitions/:ulid', element: withSuspense(guard('hr.full_access|recruitment.requisitions.view', <RequisitionDetailPage />)) },
      { path: '/hr/recruitment/requisitions/:ulid/edit', element: withSuspense(guard('hr.full_access|recruitment.requisitions.edit', <RequisitionFormPage />)) },
      { path: '/hr/recruitment/applications/new', element: withSuspense(guard('hr.full_access|recruitment.applications.review', <ApplicationFormPage />)) },
      { path: '/hr/recruitment/applications/:ulid', element: withSuspense(guard('hr.full_access|recruitment.applications.view', <ApplicationDetailPage />)) },
      { path: '/hr/recruitment/postings/new', element: withSuspense(guard('hr.full_access|recruitment.postings.create', <JobPostingFormPage />)) },
      { path: '/hr/recruitment/postings/:ulid', element: withSuspense(guard('hr.full_access|recruitment.postings.view', <JobPostingDetailPage />)) },
      { path: '/hr/recruitment/postings/:ulid/edit', element: withSuspense(guard('hr.full_access|recruitment.postings.create', <JobPostingFormPage />)) },
      { path: '/hr/recruitment/interviews/:id', element: withSuspense(guard('hr.full_access|recruitment.interviews.view|recruitment.interviews.evaluate', <InterviewDetailPage />)) },
      { path: '/hr/recruitment/offers/:ulid', element: withSuspense(guard('hr.full_access|recruitment.offers.view', <OfferDetailPage />)) },
      { path: '/hr/recruitment/candidates/:id', element: withSuspense(guard('hr.full_access|recruitment.candidates.view', <CandidateProfilePage />)) },

      // HR — Reference
      { path: '/hr/org-chart', element: withSuspense(guard('hr.full_access', <OrgChartPage />)) },
      { path: '/hr/appraisals', element: withSuspense(guard('hr.full_access', <PerformanceAppraisalListPage />)) },
      { path: '/hr/departments', element: withSuspense(guard('hr.full_access', <DepartmentsPage />)) },
      { path: '/hr/positions', element: withSuspense(guard('hr.full_access', <PositionsPage />)) },
      { path: '/hr/shifts', element: withSuspense(guard('hr.full_access', <ShiftsPage />)) },
      { path: '/hr/employees/:ulid/clearance', element: withSuspense(guard('hr.full_access', <EmployeeClearancePage />)) },
      { path: '/hr/employees/:ulid/onboarding', element: withSuspense(guard('hr.full_access', <OnboardingChecklistPage />)) },

      // ── Team Management (department-scoped) ────────────────────────────────
      { path: '/team/employees', element: withSuspense(guard('employees.view_team', <TeamEmployeeListPage />)) },
      { path: '/team/employees/:ulid', element: withSuspense(guard('employees.view_team', <TeamEmployeeDetailPage />)) },
      { path: '/team/attendance', element: withSuspense(guard('attendance.view_team', <TeamAttendancePage />)) },
      { path: '/team/leave', element: withSuspense(guard('leaves.view_team', <TeamLeavePage />)) },
      { path: '/team/overtime', element: withSuspense(guard('overtime.view', <TeamOvertimePage />)) },
      { path: '/team/loans', element: withSuspense(guard('loans.view_department', <TeamLoanPage />)) },
      { path: '/team/shifts', element: withSuspense(guard('attendance.manage_shifts', <ShiftsPage />)) },

      // ── Executive domain (redirects to unified approvals page) ───────────
      { path: '/executive/leave-approvals', element: <Navigate to="/approvals/pending" replace /> },
      { path: '/executive/overtime-approvals', element: <Navigate to="/approvals/pending" replace /> },

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
      { path: '/payroll/final-pay', element: withSuspense(guard('payroll.view_runs', <FinalPayPage />)) },

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
      { path: '/ar/dunning',         element: withSuspense(guard('ar.dunning.view', <DunningNoticesPage />)) },

      // ── Accounting — Loan Approvals ──────────────────────────────────────
      { path: '/accounting/loans', element: withSuspense(guard('loans.accounting_approve', <LoanListPage />)) },
      { path: '/accounting/loans/:ulid', element: withSuspense(guard('loans.accounting_approve', <LoanDetailPage />)) },

      // ── Tax domain ────────────────────────────────────────────────────────
      { path: '/accounting/vat-ledger', element: withSuspense(guard('reports.vat', <VatLedgerPage />)) },
      { path: '/accounting/tax-summary', element: withSuspense(guard('reports.vat', <TaxPeriodSummaryPage />)) },
      { path: '/tax/bir-forms', element: withSuspense(guard('reports.vat', <BirFormGeneratorPage />)) },
      { path: '/tax/bir-filings', element: withSuspense(guard('reports.vat', <BirFilingListPage />)) },

      // ── Reports ───────────────────────────────────────────────────────────
      { path: '/reports/government', element: withSuspense(guard('payroll.gov_reports', <GovernmentReportsPage />)) },

      // ── Financial reports (GL-001 – GL-005) ──────────────────────────────
      { path: '/accounting/gl', element: withSuspense(guard('journal_entries.view', <GeneralLedgerPage />)) },
      { path: '/accounting/trial-balance', element: withSuspense(guard('reports.financial_statements', <TrialBalancePage />)) },
      { path: '/accounting/balance-sheet', element: withSuspense(guard('reports.financial_statements', <BalanceSheetPage />)) },
      { path: '/accounting/income-statement', element: withSuspense(guard('reports.financial_statements', <IncomeStatementPage />)) },
      { path: '/accounting/financial-ratios', element: withSuspense(guard('reports.financial_statements', <FinancialRatiosPage />)) },
      { path: '/accounting/cash-flow', element: withSuspense(guard('reports.financial_statements', <CashFlowPage />)) },

      // ── Banking (GL-006) ──────────────────────────────────────────────────
      { path: '/banking/accounts', element: withSuspense(guard('bank_accounts.view', <BankAccountsPage />)) },
      { path: '/banking/reconciliations', element: withSuspense(guard('bank_reconciliations.view', <BankReconciliationPage />)) },
      { path: '/banking/reconciliations/:ulid', element: withSuspense(guard('bank_reconciliations.view', <BankReconciliationDetailPage />)) },

      // ── Employee self-service ──────────────────────────────────────────────
      { path: '/self-service/payslips', element: withSuspense(guard('payslips.view', <MyPayslipsPage />)) },
      { path: '/me/leaves', element: withSuspense(guard('leaves.view_own', <MyLeavesPage />)) },
      { path: '/me/loans', element: withSuspense(guard('loans.view_own', <MyLoansPage />)) },
      { path: '/me/overtime', element: withSuspense(guard('overtime.view', <MyOTPage />)) },
      { path: '/me/attendance', element: withSuspense(guard('attendance.view_own', <TimeInOutPage />)) },
      { path: '/me/profile', element: withSuspense(guard('self.view_profile', <MyProfilePage />)) },

      // ── Account actions ────────────────────────────────────────────────────
      { path: '/account/change-password', element: withSuspense(<ChangePasswordPage />) },

      // ── Procurement domain ────────────────────────────────────────────────
      { path: '/procurement/purchase-requests', element: withSuspense(guard('procurement.purchase-request.view', <PurchaseRequestListPage />)) },
      { path: '/procurement/purchase-requests/new', element: withSuspense(guard('procurement.purchase-request.create|procurement.purchase-request.create-dept', <CreatePurchaseRequestPage />)) },
      { path: '/procurement/purchase-requests/:ulid/edit', element: withSuspense(guard('procurement.purchase-request.create|procurement.purchase-request.create-dept', <CreatePurchaseRequestPage />)) },
      { path: '/procurement/purchase-requests/:ulid', element: withSuspense(guard('procurement.purchase-request.view', <PurchaseRequestDetailPage />)) },
      { path: '/procurement/blanket-pos', element: withSuspense(guard('procurement.purchase-order.view', <BlanketPurchaseOrdersPage />)) },
      { path: '/procurement/purchase-orders', element: withSuspense(guard('procurement.purchase-order.view', <PurchaseOrderListPage />)) },
      { path: '/procurement/purchase-orders/new', element: withSuspense(guard('procurement.purchase-order.create', <CreatePurchaseOrderPage />)) },
      { path: '/procurement/purchase-orders/:ulid', element: withSuspense(guard('procurement.purchase-order.view', <PurchaseOrderDetailPage />)) },
      { path: '/procurement/goods-receipts', element: withSuspense(guard('procurement.goods-receipt.view', <GoodsReceiptListPage />)) },
      { path: '/procurement/payment-batches', element: withSuspense(guard('ap.payment_batches.view', <PaymentBatchListPage />)) },
      { path: '/procurement/goods-receipts/new', element: withSuspense(guard('procurement.goods-receipt.create', <CreateGoodsReceiptPage />)) },
      { path: '/procurement/goods-receipts/:ulid', element: withSuspense(guard('procurement.goods-receipt.view', <GoodsReceiptDetailPage />)) },
      { path: '/procurement/analytics', element: withSuspense(guard('procurement.purchase-order.view', <ProcurementAnalyticsPage />)) },
      { path: '/procurement/vendor-scorecard', element: withSuspense(guard('procurement.purchase-order.view', <VendorScorecardPage />)) },

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
      { path: '/inventory/transfers', element: withSuspense(guard('inventory.transfers.manage', <StockTransferPage />)) },
      { path: '/inventory/analytics', element: withSuspense(guard('inventory.stock.view', <InventoryAnalyticsPage />)) },

      // ── Production / PPC ──────────────────────────────────────────────────
      { path: '/production/boms', element: withSuspense(guard('production.bom.view', <BomListPage />)) },
      { path: '/production/boms/new', element: withSuspense(guard('production.bom.manage', <CreateBomPage />)) },
      { path: '/production/boms/:ulid', element: withSuspense(guard('production.bom.view', <BomDetailPage />)) },
      { path: '/production/boms/:ulid/edit', element: withSuspense(guard('production.bom.manage', <EditBomPage />)) },
    { path: '/production/delivery-schedules', element: withSuspense(guard('production.delivery-schedule.view', <DeliveryScheduleListPage />)) },
    { path: '/production/delivery-schedules/new', element: withSuspense(guard('production.delivery-schedule.manage', <CreateDeliverySchedulePage />)) },
    { path: '/production/delivery-schedules/:ulid', element: withSuspense(guard('production.delivery-schedule.view', <DeliveryScheduleDetailPage />)) },
    // Combined delivery schedules deprecated — now part of multi-item delivery schedules
    { path: '/production/combined-delivery-schedules', element: withSuspense(guard('production.delivery-schedule.view', <DeliveryScheduleListPage />)) },
    { path: '/production/combined-delivery-schedules/:ulid', element: withSuspense(guard('production.delivery-schedule.view', <DeliveryScheduleListPage />)) },
    { path: '/production/orders', element: withSuspense(guard('production.orders.view', <ProductionOrderListPage />)) },
      { path: '/production/orders/new', element: withSuspense(guard('production.orders.create', <CreateProductionOrderPage />)) },
      { path: '/production/orders/:ulid', element: withSuspense(guard('production.orders.view', <ProductionOrderDetailPage />)) },
      { path: '/production/cost-analysis', element: withSuspense(guard('production.orders.view', <ProductionCostPage />)) },
      { path: '/production/boms/:ulid/cost-breakdown', element: withSuspense(guard('production.bom.view', <BomCostBreakdownPage />)) },

      // Production — Work Centers, Routing, MRP (Gap Fix)
      { path: '/production/work-centers', element: withSuspense(guard('production.orders.view', <WorkCenterListPage />)) },
      { path: '/production/routings', element: withSuspense(guard('production.orders.view', <RoutingListPage />)) },
      { path: '/production/mrp', element: withSuspense(guard('production.orders.view', <MrpResultsPage />)) },

      // ── QC / QA ────────────────────────────────────────────────────
      { path: '/qc/inspections', element: withSuspense(guard('qc.inspections.view', <InspectionListPage />)) },
      { path: '/qc/inspections/new', element: withSuspense(guard('qc.inspections.create', <CreateInspectionPage />)) },
      { path: '/qc/inspections/:ulid', element: withSuspense(guard('qc.inspections.view', <InspectionDetailPage />)) },
      { path: '/qc/ncrs', element: withSuspense(guard('qc.ncr.view', <NcrListPage />)) },
      { path: '/qc/spc', element: withSuspense(guard('qc.inspections.view', <SpcDashboardPage />)) },
      { path: '/qc/supplier-quality', element: withSuspense(guard('qc.inspections.view', <SupplierQualityPage />)) },
      { path: '/qc/ncrs/new', element: withSuspense(guard('qc.ncr.create', <CreateNcrPage />)) },
      { path: '/qc/ncrs/:ulid', element: withSuspense(guard('qc.ncr.view', <NcrDetailPage />)) },
      { path: '/qc/templates', element: withSuspense(guard('qc.templates.view', <QcTemplateListPage />)) },
      { path: '/qc/capa',      element: withSuspense(guard('qc.ncr.view',       <CapaListPage />)) },
      { path: '/qc/defect-rate', element: withSuspense(guard('qc.inspections.view', <QcDefectRatePage />)) },
      { path: '/qc/quarantine', element: withSuspense(guard('qc.inspections.view', <QuarantineManagementPage />)) },

      // ── Maintenance ───────────────────────────────────────────────────────
      { path: '/maintenance/equipment', element: withSuspense(guard('maintenance.view', <EquipmentListPage />)) },
      { path: '/maintenance/equipment/new', element: withSuspense(guard('maintenance.manage', <CreateEquipmentPage />)) },
      { path: '/maintenance/equipment/:ulid', element: withSuspense(guard('maintenance.view', <EquipmentDetailPage />)) },
      { path: '/maintenance/work-orders', element: withSuspense(guard('maintenance.view', <WorkOrderListPage />)) },
      { path: '/maintenance/work-orders/new', element: withSuspense(guard('maintenance.manage', <CreateWorkOrderPage />)) },
      { path: '/maintenance/work-orders/:ulid', element: withSuspense(guard('maintenance.view', <WorkOrderDetailPage />)) },
      { path: '/maintenance/analytics', element: withSuspense(guard('maintenance.view', <MaintenanceAnalyticsPage />)) },

      // ── Mold ─────────────────────────────────────────────────────────────
      { path: '/mold/masters', element: withSuspense(guard('mold.view', <MoldListPage />)) },
      { path: '/mold/masters/new', element: withSuspense(guard('mold.manage', <CreateMoldPage />)) },
      { path: '/mold/masters/:ulid', element: withSuspense(guard('mold.view', <MoldDetailPage />)) },
      { path: '/mold/lifecycle', element: withSuspense(guard('mold.view', <MoldLifecyclePage />)) },

      // ── Delivery / Logistics ─────────────────────────────────────────
      { path: '/delivery/receipts', element: withSuspense(guard('delivery.view', <DeliveryReceiptListPage />)) },
      { path: '/delivery/receipts/new', element: withSuspense(guard('delivery.manage', <CreateDeliveryReceiptPage />)) },
      { path: '/delivery/receipts/:ulid', element: withSuspense(guard('delivery.view', <DeliveryReceiptDetailPage />)) },
      { path: '/delivery/fleet', element: withSuspense(guard('delivery.view', <FleetPage />)) },
      { path: '/delivery/shipments', element: withSuspense(guard('delivery.view', <ShipmentsPage />)) },
      { path: '/delivery/routes', element: withSuspense(guard('delivery.routes.view', <DeliveryRoutesPage />)) },

      // ── VP Approvals Dashboard ────────────────────────────────────────────
      { path: '/approvals/pending', element: withSuspense(guard('loans.vp_approve|procurement.purchase-request.view|inventory.mrq.vp_approve|payroll.vp_approve|leaves.vp_note', <VpApprovalsDashboardPage />)) },
      { path: '/approvals/loans',   element: <Navigate to="/approvals/pending" replace /> },
      { path: '/approvals/loans/:ulid', element: withSuspense(guard('loans.vp_approve', <LoanDetailPage />)) },
      { path: '/approvals/budget-verification', element: withSuspense(guard('procurement.purchase-request.budget-check', <PurchaseRequestListPage lockedStatus="reviewed" pageTitle="Budget Verification" />)) },

      // ── Admin ──────────────────────────────────────────────────────────────
      { path: '/admin/users', element: withSuspense(guard('system.manage_users', <UsersPage />)) },
      { path: '/admin/settings', element: withSuspense(guard('system.edit_settings', <SystemSettingsPage />)) },
      { path: '/search', element: withSuspense(<GlobalSearchPage />) },
      { path: '/admin/audit-logs', element: withSuspense(guard('system.view_audit_log', <AuditLogsPage />)) },
      { path: '/admin/reference-tables', element: withSuspense(guard('system.edit_settings', <ReferenceTablesPage />)) },      { path: '/admin/backup', element: withSuspense(guard('system.manage_backups', <BackupPage />)) },

      // ── CRM — Staff portal ────────────────────────────────────────────────
      { path: '/crm/dashboard',           element: withSuspense(guard('crm.tickets.view', <CrmDashboardPage />)) },
      { path: '/crm/tickets',             element: withSuspense(guard('crm.tickets.view', <TicketListPage />)) },
      { path: '/crm/tickets/:ulid',       element: withSuspense(guard('crm.tickets.view', <TicketDetailPage />)) },

      // ── Budget ────────────────────────────────────────────────────────────
      { path: '/budget/cost-centers',       element: withSuspense(guard('budget.view', <CostCentersPage />)) },
      { path: '/budget/department-budgets', element: withSuspense(guard('budget.view', <DepartmentBudgetsPage />)) },
      { path: '/budget/lines',             element: withSuspense(guard('budget.view', <BudgetLinesPage />)) },
      { path: '/budget/variance',          element: withSuspense(guard('budget.view', <BudgetVariancePage />)) },

      // ── Fixed Assets ──────────────────────────────────────────────────────
      { path: '/fixed-assets',             element: withSuspense(guard('fixed_assets.view', <FixedAssetsPage />)) },
      { path: '/fixed-assets/:ulid',       element: withSuspense(guard('fixed_assets.view', <FixedAssetDetailPage />)) },

      // ── Recurring Journal Templates ────────────────────────────────────────
      { path: '/accounting/recurring-templates', element: withSuspense(guard('journal_entries.view', <RecurringTemplatesPage />)) },

      // ── Credit Notes ──────────────────────────────────────────────────────
      { path: '/accounting/ap/credit-notes', element: withSuspense(guard('vendor_invoices.view', <VendorCreditNotesPage />)) },
      { path: '/ar/credit-notes',            element: withSuspense(guard('customer_invoices.view', <CustomerCreditNotesPage />)) },

      // ── Vendor RFQs ───────────────────────────────────────────────────────
      { path: '/procurement/rfqs',        element: withSuspense(guard('procurement.purchase-order.view', <VendorRfqListPage />)) },
      { path: '/procurement/rfqs/:ulid',  element: withSuspense(guard('procurement.purchase-order.view', <VendorRfqDetailPage />)) },

    // ── Sales / Client Orders Review ───────────────────────────────────────
    { path: '/sales/client-orders', element: withSuspense(guard('sales.order_review', <ClientOrdersReviewPage />)) },
    { path: '/sales/client-orders/:ulid', element: withSuspense(guard('sales.order_review', <ClientOrderReviewDetailPage />)) },
    { path: '/sales/quotations', element: withSuspense(guard('sales.quotations.view', <QuotationListPage />)) },
    { path: '/sales/quotations/:ulid', element: withSuspense(guard('sales.quotations.view', <QuotationDetailPage />)) },
    { path: '/sales/quotations/:ulid/margin', element: withSuspense(guard('sales.quotations.view', <QuotationMarginPage />)) },
    { path: '/sales/orders', element: withSuspense(guard('sales.orders.view', <SalesOrderListPage />)) },
    // Note: No /sales/orders/new route — sales orders are created by converting accepted quotations
    { path: '/sales/orders/:ulid', element: withSuspense(guard('sales.orders.view', <SalesOrderDetailPage />)) },
    ],
  },

  // ── Vendor Portal (standalone layout, vendor role only) ──────────────────
  {
    path: '/vendor-portal',
    element: <Suspense fallback={null}><VendorPortalLayout /></Suspense>,
    children: [
      { index: true, element: <Navigate to="/vendor-portal/dashboard" replace /> },
      { path: 'dashboard', element: withSuspense(guard('vendor_portal.view_orders', <VendorPortalDashboardPage />)) },
      { path: 'change-password', element: withSuspense(<ChangePasswordPage />) },
      { path: 'orders',    element: withSuspense(guard('vendor_portal.view_orders', <VendorOrdersPage />)) },
      { path: 'orders/:ulid', element: withSuspense(guard('vendor_portal.view_orders', <VendorOrderDetailPage />)) },
      { path: 'items',     element: withSuspense(guard('vendor_portal.manage_items', <VendorItemsPage />)) },
      { path: 'goods-receipts', element: withSuspense(guard('vendor_portal.view_receipts', <VendorGoodsReceiptsPage />)) },
      { path: 'goods-receipts/:id', element: withSuspense(guard('vendor_portal.view_receipts', <VendorGoodsReceiptDetailPage />)) },
      { path: 'invoices', element: withSuspense(guard('vendor_portal.view_receipts', <VendorInvoicesPage />)) },
      { path: 'invoices/:id', element: withSuspense(guard('vendor_portal.view_receipts', <VendorInvoiceDetailPage />)) },
    ],
  },

  // ── Client Portal (standalone layout, client role only) ──────────────────
  {
    path: '/client-portal',
    element: <Suspense fallback={null}><ClientPortalLayout /></Suspense>,
    children: [
      { index: true, element: withSuspense(guard('client_portal.view_dashboard', <ClientDashboardPage />)) },
      { path: 'shop',            element: withSuspense(guard('client_portal.view_shop', <ClientShopPage />)) },
      { path: 'orders', element: withSuspense(guard('client_portal.view_orders', <ClientOrdersPage />)) },
      { path: 'orders/:ulid', element: withSuspense(guard('client_portal.view_orders', <ClientOrderDetailPage />)) },
      { path: 'deliveries/:ulid', element: withSuspense(guard('client_portal.view_orders', <OrderReceiptPage />)) },
      { path: 'tickets',         element: withSuspense(guard('crm.tickets.view', <ClientTicketsPage />)) },
      { path: 'change-password', element: withSuspense(<ChangePasswordPage />) },
      { path: 'tickets/new',     element: withSuspense(guard('crm.tickets.create', <ClientNewTicketPage />)) },
      { path: 'tickets/:ulid',   element: withSuspense(guard('crm.tickets.view', <ClientTicketDetailPage />)) },
    ],
  },

  // ── Utility pages ────────────────────────────────────────────────────────
  { path: '/403', element: withSuspense(<Forbidden />) },
  { path: '*', element: withSuspense(<NotFound />) },
])

export default function AppRouter(): React.ReactElement {
  return <RouterProvider router={router} />
}
