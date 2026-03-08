import { useState, useEffect } from 'react'
import { useParams, useNavigate } from 'react-router-dom'
import { toast } from 'sonner'
import {
  ArrowLeft,
  Lock,
  XCircle,
  RefreshCw,
  AlertTriangle,
  Users,
  TrendingUp,
  TrendingDown,
  Banknote,
  Table2,

  Download,
} from 'lucide-react'
import {
  usePayrollRun,
  usePayrollDetails,
  useLockPayrollRun,
  useApprovePayrollRun,
  useCancelPayrollRun,
  useDownloadPayslip,
  useExportPayrollRegister,
  useExportPayrollBreakdown,
} from '@/hooks/usePayroll'
import SkeletonLoader from '@/components/ui/SkeletonLoader'
import StatusBadge from '@/components/ui/StatusBadge'
import CurrencyAmount from '@/components/ui/CurrencyAmount'
import ConfirmDestructiveDialog from '@/components/ui/ConfirmDestructiveDialog'
import SodActionButton from '@/components/ui/SodActionButton'
import type { PayrollDetail } from '@/types/payroll'

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function formatDate(iso: string) {
  return new Date(iso).toLocaleDateString('en-PH', {
    year: 'numeric', month: 'short', day: 'numeric',
  })
}

function formatMinutes(minutes: number): string {
  if (minutes <= 0) return '—'
  const h = Math.floor(minutes / 60)
  const m = minutes % 60
  return h > 0 ? `${h}h ${m > 0 ? m + 'm' : ''}`.trim() : `${m}m`
}

// ---------------------------------------------------------------------------
// Per-row payslip PDF download button
// ---------------------------------------------------------------------------

function DownloadPayslipButton({ runId, detailId }: { runId: string; detailId: number }) {
  const { download, isLoading } = useDownloadPayslip(runId, detailId)

  return (
    <button
      onClick={() => void download()}
      disabled={isLoading}
      title="Download payslip PDF"
      className="p-1 text-neutral-400 hover:text-neutral-900 disabled:opacity-40 disabled:cursor-not-allowed transition-colors"
    >
      {isLoading
        ? <RefreshCw className="h-3.5 w-3.5 animate-spin" />
        : <Download className="h-3.5 w-3.5" />
      }
    </button>
  )
}

// ---------------------------------------------------------------------------
// Summary card
// ---------------------------------------------------------------------------

interface SummaryCardProps {
  label: string
  value: React.ReactNode
  icon: React.ComponentType<{ className?: string }>
  iconClass?: string
}

function SummaryCard({ label, value, icon: Icon, iconClass = 'text-neutral-900' }: SummaryCardProps) {
  return (
    <div className="bg-white border border-neutral-200 rounded p-5 flex items-start gap-4">
      <div className={`p-2.5 rounded bg-neutral-50 ${iconClass.replace('text-', 'bg-').replace('-600', '-100')}`}>
        <Icon className={`h-5 w-5 ${iconClass}`} />
      </div>
      <div>
        <p className="text-xs font-medium text-neutral-500 font-medium">{label}</p>
        <div className="mt-1">{value}</div>
      </div>
    </div>
  )
}

// ---------------------------------------------------------------------------
// Payslips table
// ---------------------------------------------------------------------------

function PayslipsTable({
  details,
  page,
  lastPage,
  onPageChange,
  runId,
  isCompleted,
  onTrace,
}: {
  details: PayrollDetail[]
  page: number
  lastPage: number
  onPageChange: (p: number) => void
  runId: string
  isCompleted: boolean
  onTrace: (d: PayrollDetail) => void
}) {
  if (details.length === 0) {
    return (
      <div className="py-10 text-center text-neutral-400 text-sm">
        No payslip data yet. Lock the run to start computation.
      </div>
    )
  }

  return (
    <>
      <div className="overflow-x-auto">
        <table className="min-w-full text-sm">
          <thead className="bg-neutral-50 border-b border-neutral-200">
            <tr>
              <th className="px-3 py-2.5 text-left text-xs font-semibold text-neutral-500 font-medium">Employee</th>
              <th className="px-3 py-2.5 text-right text-xs font-semibold text-neutral-500 font-medium">Days</th>
              <th className="px-3 py-2.5 text-right text-xs font-semibold text-neutral-500 font-medium">OT</th>
              <th className="px-3 py-2.5 text-right text-xs font-semibold text-neutral-500 font-medium">Basic Pay</th>
              <th className="px-3 py-2.5 text-right text-xs font-semibold text-neutral-500 font-medium">Gross Pay</th>
              <th className="px-3 py-2.5 text-right text-xs font-semibold text-neutral-500 font-medium">Deductions</th>
              <th className="px-3 py-2.5 text-right text-xs font-semibold text-neutral-500 font-medium">Net Pay</th>
              <th className="px-3 py-2.5 text-center text-xs font-semibold text-neutral-500 font-medium">Flags</th>
              <th className="px-3 py-2.5 text-center text-xs font-semibold text-neutral-500 font-medium">Trace</th>
              {isCompleted && (
                <th className="px-3 py-2.5 text-center text-xs font-semibold text-neutral-500 font-medium">PDF</th>
              )}
            </tr>
          </thead>
          <tbody className="divide-y divide-neutral-100">
            {details.map((d) => (
              <tr key={d.id} className=" hover:bg-neutral-50/60 transition-colors">
                <td className="px-3 py-2">
                  <div className="font-medium text-neutral-900">
                    {d.employee
                      ? `${d.employee.first_name} ${d.employee.last_name}`
                      : `Employee #${d.employee_id}`}
                  </div>
                  <div className="text-xs text-neutral-400 font-mono mt-0.5">
                    {d.employee?.employee_code ?? ''}
                  </div>
                </td>
                <td className="px-3 py-2 text-right text-neutral-700 tabular-nums">
                  {d.days_worked}
                  {d.leave_days_paid > 0 && (
                    <span className="text-xs text-neutral-400 ml-1">(+{d.leave_days_paid}L)</span>
                  )}
                </td>
                <td className="px-4 py-3 text-right text-neutral-700">
                  {formatMinutes(d.overtime_regular_minutes + d.overtime_rest_day_minutes)}
                </td>
                <td className="px-4 py-3 text-right">
                  <CurrencyAmount centavos={d.basic_pay_centavos} />
                </td>
                <td className="px-4 py-3 text-right">
                  <CurrencyAmount centavos={d.gross_pay_centavos} />
                </td>
                <td className="px-4 py-3 text-right">
                  <CurrencyAmount centavos={d.total_deductions_centavos} />
                </td>
                <td className="px-4 py-3 text-right">
                  <CurrencyAmount centavos={d.net_pay_centavos} />
                </td>
                <td className="px-3 py-2 text-center">
                  <div className="flex items-center justify-center gap-1">
                    {d.is_below_min_wage && (
                      <span title="Below minimum wage" className="inline-flex items-center px-1.5 py-0.5 rounded text-xs bg-red-100 text-red-700 font-medium">
                        MWE
                      </span>
                    )}
                    {d.has_deferred_deductions && (
                      <span title="Has deferred deductions" className="inline-flex items-center px-1.5 py-0.5 rounded text-xs bg-yellow-100 text-yellow-700 font-medium">
                        DEF
                      </span>
                    )}
                  </div>
                </td>
                <td className="px-3 py-2 text-center">
                  {d.loan_deduction_detail && d.loan_deduction_detail.length > 0 && (
                    <button
                      onClick={() => onTrace(d)}
                      className="text-xs px-2 py-1 border border-neutral-200 rounded hover:bg-neutral-50 text-neutral-600"
                      title="View deduction trace"
                    >
                      Trace
                    </button>
                  )}
                </td>
                {isCompleted && (
                  <td className="px-3 py-2 text-center">
                    <DownloadPayslipButton runId={runId} detailId={d.id} />
                  </td>
                )}
              </tr>
            ))}
          </tbody>
        </table>
      </div>

      {/* Pagination */}
      {lastPage > 1 && (
        <div className="px-4 py-3 border-t border-neutral-100 flex items-center justify-between text-sm text-neutral-600">
          <span>Page {page} of {lastPage}</span>
          <div className="flex gap-2">
            <button
              disabled={page <= 1}
              onClick={() => onPageChange(page - 1)}
              className="px-3 py-1 rounded border border-neutral-200 disabled:opacity-40 disabled:cursor-not-allowed hover:bg-neutral-50 transition-colors"
            >
              Previous
            </button>
            <button
              disabled={page >= lastPage}
              onClick={() => onPageChange(page + 1)}
              className="px-3 py-1 rounded border border-neutral-200 disabled:opacity-40 disabled:cursor-not-allowed hover:bg-neutral-50 transition-colors"
            >
              Next
            </button>
          </div>
        </div>
      )}
    </>
  )
}

// ---------------------------------------------------------------------------
// Exceptions table
// ---------------------------------------------------------------------------

function ExceptionsTable({ details }: { details: PayrollDetail[] }) {
  const exceptions = details.filter(
    (d) => d.is_below_min_wage || d.has_deferred_deductions,
  )

  if (exceptions.length === 0) {
    return (
      <div className="py-10 text-center text-neutral-400 text-sm">
        No exceptions found — all employees are within normal parameters.
      </div>
    )
  }

  return (
    <div className="overflow-x-auto">
      <table className="min-w-full text-sm">
        <thead className="bg-neutral-50 border-b border-neutral-200">
          <tr>
            <th className="px-3 py-2.5 text-left text-xs font-semibold text-neutral-500 font-medium">Employee</th>
            <th className="px-3 py-2.5 text-right text-xs font-semibold text-neutral-500 font-medium">Net Pay</th>
            <th className="px-3 py-2.5 text-right text-xs font-semibold text-neutral-500 font-medium">Deferred Loans</th>
            <th className="px-3 py-2.5 text-left text-xs font-semibold text-neutral-500 font-medium">Issue</th>
          </tr>
        </thead>
        <tbody className="divide-y divide-neutral-100">
          {exceptions.map((d) => (
            <tr key={d.id} className="bg-orange-50/50  hover:bg-neutral-50/60 transition-colors">
              <td className="px-3 py-2">
                <div className="font-medium text-neutral-900">
                  {d.employee
                    ? `${d.employee.first_name} ${d.employee.last_name}`
                    : `Employee #${d.employee_id}`}
                </div>
                <div className="text-xs text-neutral-400 font-mono mt-0.5">
                  {d.employee?.employee_code ?? ''}
                </div>
              </td>
              <td className="px-4 py-3 text-right">
                <CurrencyAmount centavos={d.net_pay_centavos} />
              </td>
              <td className="px-4 py-3 text-right">
                {d.has_deferred_deductions
                  ? <CurrencyAmount centavos={d.loan_deductions_centavos} />
                  : <span className="text-neutral-400">—</span>
                }
              </td>
              <td className="px-3 py-2">
                <div className="flex flex-col gap-1">
                  {d.is_below_min_wage && (
                    <span className="text-xs text-red-700 font-medium flex items-center gap-1">
                      <AlertTriangle className="h-3 w-3" />
                      Net pay falls below semi-monthly minimum wage floor
                    </span>
                  )}
                  {d.has_deferred_deductions && (
                    <span className="text-xs text-yellow-700 font-medium flex items-center gap-1">
                      <AlertTriangle className="h-3 w-3" />
                      Loan deductions deferred — insufficient take-home pay
                    </span>
                  )}
                </div>
              </td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  )
}

// ---------------------------------------------------------------------------
// Main page
// ---------------------------------------------------------------------------

type Tab = 'payslips' | 'exceptions'

export default function PayrollRunDetailPage() {
  const { ulid: id } = useParams<{ ulid: string }>()
  const runId   = id ?? null
  const navigate = useNavigate()

  const [activeTab, setActiveTab]   = useState<Tab>('payslips')
  const [detailPage, setDetailPage] = useState(1)
  const [traceDetail, setTraceDetail] = useState<import('@/types/payroll').PayrollDetail | null>(null)

  // Auto-poll while processing
  const isPolling = (status?: string) =>
    status === 'locked' || status === 'processing'

  const { data: run, isLoading: runLoading, isError: runError, refetch: refetchRun } = usePayrollRun(runId)
  const { data: detailsData, isLoading: detailsLoading } = usePayrollDetails(
    runId,
    detailPage,
  )

  const lockMutation       = useLockPayrollRun(runId!)
  const approveMutation    = useApprovePayrollRun(runId!)
  const cancelMutation     = useCancelPayrollRun(runId!)
  const exportRegister     = useExportPayrollRegister(runId!)
  const exportBreakdown    = useExportPayrollBreakdown(runId!)

  // Periodically refetch when computing
  const shouldPoll = isPolling(run?.status)
  useEffect(() => {
    if (!shouldPoll) return
    const timer = setTimeout(() => { void refetchRun() }, 5000)
    return () => clearTimeout(timer)
  }, [shouldPoll, refetchRun])

  // ── v1.0 Workflow: Redirect to the correct wizard step page ──────────────
  const V1_STATUS_REDIRECT: Partial<Record<string, string>> = {
    DRAFT:           `/payroll/runs/${runId}/scope`,
    SCOPE_SET:       `/payroll/runs/${runId}/validate`,
    PRE_RUN_CHECKED: `/payroll/runs/${runId}/compute`,
    PROCESSING:      `/payroll/runs/${runId}/compute`,
    FAILED:          `/payroll/runs/${runId}/compute`,
    COMPUTED:        `/payroll/runs/${runId}/review`,
    REVIEW:          `/payroll/runs/${runId}/review`,
    SUBMITTED:       `/payroll/runs/${runId}/acctg-review`,
    RETURNED:        `/payroll/runs/${runId}/review`,
    HR_APPROVED:     `/payroll/runs/${runId}/acctg-review`,
    REJECTED:        `/payroll/runs/${runId}/review`,
    ACCTG_APPROVED:  `/payroll/runs/${runId}/disburse`,
    DISBURSED:       `/payroll/runs/${runId}/disburse`,
    PUBLISHED:       `/payroll/runs/${runId}/disburse`,
  }
  const redirectTo = run ? V1_STATUS_REDIRECT[run.status] : undefined
  useEffect(() => {
    if (redirectTo) navigate(redirectTo, { replace: true })
  }, [redirectTo, navigate])

  // Redirect to list if run is not found (404 / deleted / stale URL)
  useEffect(() => {
    if (!runLoading && (runError || (!run && runId !== null))) {
      navigate('/payroll/runs', { replace: true })
    }
  }, [runLoading, runError, run, runId, navigate])

  if (runLoading) return <SkeletonLoader rows={8} />
  if (!run) return null

  if (redirectTo) return null

  const isCompleted  = run.status === 'completed'
  const isDraft      = run.status === 'draft'
  const isCancelled  = run.status === 'cancelled'
  const isRejected   = run.status === 'REJECTED'
  const nonCancellableStatuses = ['cancelled', 'DISBURSED', 'PUBLISHED', 'posted']
  const canCancel    = !nonCancellableStatuses.includes(run.status) && run.approved_at === null

  const exceptionCount = (detailsData?.data ?? []).filter(
    (d) => d.is_below_min_wage || d.has_deferred_deductions,
  ).length

  // ── Actions ──────────────────────────────────────────────────────────────

  const handleLock = async () => {
    try {
      const result = await lockMutation.mutateAsync()
      toast.success(`Run locked. ${result.total_jobs} employees queued for computation.`)
    } catch {
      toast.error('Failed to lock run. Check that no date overlap exists.')
    }
  }

  const handleApprove = async () => {
    try {
      await approveMutation.mutateAsync()
      toast.success('Payroll run approved.')
    } catch {
      toast.error('Failed to approve run. Remember: approver must be different from creator (SoD).')
    }
  }

  const handleCancel = async () => {
    try {
      await cancelMutation.mutateAsync()
      toast.success('Payroll run cancelled.')
      navigate('/payroll/runs')
    } catch {
      toast.error('Failed to cancel run.')
    }
  }

  return (
    <>
    <div className="max-w-7xl mx-auto">
      {/* Back nav */}
      <button
        onClick={() => navigate('/payroll/runs')}
        className="flex items-center gap-1.5 text-sm text-neutral-500 hover:text-neutral-800 mb-6 transition-colors"
      >
        <ArrowLeft className="h-4 w-4" />
        Back to Payroll Runs
      </button>

      {/* Run header */}
      <div className="flex flex-wrap items-start justify-between gap-4 mb-6">
        <div>
          <div className="flex items-center gap-3">
            <h1 className="text-lg font-semibold text-neutral-900">{run.reference_no}</h1>
            <StatusBadge status={run.status}>{run.status?.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase())}</StatusBadge>
            {run.run_type === 'thirteenth_month' && (
              <span className="inline-flex items-center px-2 py-0.5 rounded-full text-xs bg-amber-100 text-amber-700 font-medium">
                13th Month
              </span>
            )}
            {shouldPoll && (
              <span className="flex items-center gap-1.5 text-xs text-neutral-900">
                <RefreshCw className="h-3 w-3 animate-spin" />
                Computing…
              </span>
            )}
          </div>
          <p className="text-sm text-neutral-500 mt-1">
            {run.pay_period_label}
            {' · '}
            Cutoff: {formatDate(run.cutoff_start)} – {formatDate(run.cutoff_end)}
            {' · '}
            Pay Date: {formatDate(run.pay_date)}
          </p>
        </div>

        {/* Action buttons */}
        <div className="flex items-center gap-2">
          {isDraft && (
            <ConfirmDestructiveDialog
              title="Lock payroll run?"
              description={`Locking will queue computation for all active employees in the system. The cutoff period will be reserved. This cannot be undone easily.`}
              confirmWord="LOCK"
              confirmLabel="Lock & Compute"
              onConfirm={handleLock}
            >
              <button className="flex items-center gap-2 bg-neutral-900 hover:bg-neutral-800 text-white text-sm font-medium px-4 py-2 rounded transition-colors">
                <Lock className="h-4 w-4" />
                Lock Run
              </button>
            </ConfirmDestructiveDialog>
          )}

          {isCompleted && (
            <SodActionButton
              initiatedById={run.initiated_by_id}
              label="Approve Run"
              onClick={handleApprove}
              isLoading={approveMutation.isPending}
              variant="primary"
            />
          )}

          {isCompleted && (
            <>
              <button
                onClick={() => void exportBreakdown.download()}
                disabled={exportBreakdown.isLoading}
                title="Full payroll breakdown with attendance, OT, tax, deductions"
                className="flex items-center gap-2 bg-neutral-900 text-white hover:bg-neutral-800 text-sm font-medium px-4 py-2 rounded transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
              >
                {exportBreakdown.isLoading ? <RefreshCw className="h-4 w-4 animate-spin" /> : <Table2 className="h-4 w-4" />}
                Full Breakdown (Excel)
              </button>
              <button
                onClick={() => void exportRegister.download()}
                disabled={exportRegister.isLoading}
                className="flex items-center gap-2 border border-neutral-200 text-neutral-600 hover:bg-neutral-50 text-sm font-medium px-4 py-2 rounded transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
              >
                {exportRegister.isLoading ? <RefreshCw className="h-4 w-4 animate-spin" /> : <Table2 className="h-4 w-4" />}
                Export Register
              </button>
            </>
          )}

          {canCancel && (
            <ConfirmDestructiveDialog
              title="Cancel payroll run?"
              description="Cancelling will mark this run as cancelled. Employees will not be paid from this run. You can create a new run with the same period."
              confirmWord="CANCEL"
              confirmLabel="Cancel Run"
              onConfirm={handleCancel}
            >
              <button className="flex items-center gap-2 border border-red-200 text-red-600 hover:bg-red-50 text-sm font-medium px-4 py-2 rounded transition-colors">
                <XCircle className="h-4 w-4" />
                Cancel Run
              </button>
            </ConfirmDestructiveDialog>
          )}
        </div>
      </div>

      {/* Cancelled / Rejected banner */}
      {(isCancelled || isRejected) && (
        <div className={`flex items-start gap-3 rounded border px-5 py-4 mb-6 ${
          isCancelled
            ? 'bg-neutral-50 border-neutral-300 text-neutral-700'
            : 'bg-red-50 border-red-300 text-red-800'
        }`}>
          <XCircle className={`h-5 w-5 mt-0.5 shrink-0 ${isCancelled ? 'text-neutral-400' : 'text-red-500'}`} />
          <div>
            <p className="font-semibold text-sm">
              {isCancelled ? 'Payroll run cancelled' : 'Payroll run permanently rejected'}
            </p>
            <p className="text-xs mt-0.5 opacity-75">
              {isCancelled
                ? 'This run has been cancelled. No payments will be disbursed. The data below is for reference only.'
                : 'This run was rejected by Accounting. No payments will be disbursed. A new run must be started from Step 1.'}
            </p>
          </div>
        </div>
      )}

      {/* Summary cards */}
      <div className="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <SummaryCard
          label="Employees"
          value={<span className="text-lg font-semibold text-neutral-800">{run.total_employees}</span>}
          icon={Users}
          iconClass="text-neutral-900"
        />
        <SummaryCard
          label="Gross Pay"
          value={<CurrencyAmount centavos={run.gross_pay_total} size="lg" />}
          icon={TrendingUp}
          iconClass="text-green-600"
        />
        <SummaryCard
          label="Total Deductions"
          value={<CurrencyAmount centavos={run.total_deductions} size="lg" />}
          icon={TrendingDown}
          iconClass="text-orange-600"
        />
        <SummaryCard
          label="Net Pay"
          value={<CurrencyAmount centavos={run.net_pay_total} size="xl" />}
          icon={Banknote}
          iconClass="text-neutral-900"
        />
      </div>

      {/* Notes */}
      {run.notes && (
        <div className="bg-neutral-50 border border-neutral-200 rounded px-4 py-3 text-sm text-neutral-600 mb-6">
          {run.notes}
        </div>
      )}

      {/* Tabs */}
      <div className="bg-white border border-neutral-200 rounded overflow-hidden">
        <div className="border-b border-neutral-200 px-4 flex gap-1">
          {(['payslips', 'exceptions'] as Tab[]).map((tab) => (
            <button
              key={tab}
              onClick={() => setActiveTab(tab)}
              className={[
                'px-4 py-3 text-sm font-medium transition-colors border-b-2 -mb-px',
                activeTab === tab
                  ? 'border-neutral-900 text-neutral-900'
                  : 'border-transparent text-neutral-500 hover:text-neutral-700',
              ].join(' ')}
            >
              {tab.charAt(0).toUpperCase() + tab.slice(1)}
              {tab === 'exceptions' && exceptionCount > 0 && (
                <span className="ml-2 inline-flex items-center px-1.5 py-0.5 rounded-full text-xs bg-orange-100 text-orange-700 font-semibold">
                  {exceptionCount}
                </span>
              )}
            </button>
          ))}
        </div>

        {detailsLoading ? (
          <div className="p-6">
            <SkeletonLoader rows={6} />
          </div>
        ) : (
          <>
            {activeTab === 'payslips' && (
              <PayslipsTable
                details={detailsData?.data ?? []}
                page={detailPage}
                lastPage={detailsData?.meta?.last_page ?? 1}
                onPageChange={setDetailPage}
                runId={runId!}
                isCompleted={isCompleted}
                onTrace={setTraceDetail}
              />
            )}
            {activeTab === 'exceptions' && (
              <ExceptionsTable details={detailsData?.data ?? []} />
            )}
          </>
        )}
      </div>
    </div>

    {/* Deduction Trace Drawer */}
    {traceDetail && (
      <div className="fixed inset-0 bg-black/40 flex items-start justify-end z-50" onClick={() => setTraceDetail(null)}>
        <div
          className="h-full w-full max-w-md bg-white shadow-xl overflow-y-auto p-6 flex flex-col gap-4"
          onClick={e => e.stopPropagation()}
        >
          <div className="flex items-center justify-between">
            <h2 className="text-lg font-semibold text-slate-900">Deduction Trace</h2>
            <button onClick={() => setTraceDetail(null)} className="text-slate-400 hover:text-neutral-600 text-xl leading-none">&times;</button>
          </div>
          <p className="text-sm text-neutral-500">
            {traceDetail.employee
              ? `${traceDetail.employee.first_name} ${traceDetail.employee.last_name}`
              : `Employee #${traceDetail.employee_id}`}
          </p>

          {/* Loan deductions */}
          <section>
            <h3 className="text-xs font-semibold text-slate-700 font-medium mb-2">Loan Deductions</h3>
            {traceDetail.loan_deduction_detail && traceDetail.loan_deduction_detail.length > 0 ? (
              <table className="w-full text-xs border rounded overflow-hidden">
                <thead className="bg-neutral-50">
                  <tr>
                    {['Loan', 'Scheduled', 'Deducted', 'Deferred'].map(h => (
                      <th key={h} className="px-3 py-1.5 text-left text-neutral-500 font-medium">{h}</th>
                    ))}
                  </tr>
                </thead>
                <tbody className="divide-y divide-neutral-100">
                  {traceDetail.loan_deduction_detail.map((ld, idx) => (
                    <tr key={idx}>
                      <td className="px-3 py-1.5 font-mono">{ld.loan_id}</td>
                      <td className="px-3 py-1.5 text-right">{((ld.amortisation_centavos ?? 0) / 100).toFixed(2)}</td>
                      <td className="px-3 py-1.5 text-right">{((ld.deducted_centavos ?? 0) / 100).toFixed(2)}</td>
                      <td className="px-3 py-1.5 text-right text-amber-600">{ld.deferred ? 'Yes' : '—'}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            ) : (
              <p className="text-xs text-slate-400">No loan deductions.</p>
            )}
          </section>

          {/* Summary */}
          <section className="space-y-1 text-sm">
            <h3 className="text-xs font-semibold text-slate-700 font-medium mb-2">Net Pay Summary</h3>
            {[
              ['Gross Pay',        (traceDetail.gross_pay_centavos / 100).toFixed(2)],
              ['Statutory (EE)',   ((traceDetail.sss_ee_centavos + traceDetail.philhealth_ee_centavos + traceDetail.pagibig_ee_centavos) / 100).toFixed(2)],
              ['Withholding Tax',  (traceDetail.withholding_tax_centavos / 100).toFixed(2)],
              ['Loan Deductions',  (traceDetail.loan_deductions_centavos / 100).toFixed(2)],
              ['Total Deductions', (traceDetail.total_deductions_centavos / 100).toFixed(2)],
              ['Net Pay',          (traceDetail.net_pay_centavos / 100).toFixed(2)],
            ].map(([label, value]) => (
              <div key={label} className="flex justify-between border-b border-neutral-100 pb-1">
                <span className="text-neutral-500">{label}</span>
                <span className="font-medium tabular-nums">{value}</span>
              </div>
            ))}
          </section>

          {traceDetail.notes && (
            <div className="rounded border-l-4 border-amber-400 bg-amber-50 px-3 py-2 text-xs text-amber-800">
              {traceDetail.notes}
            </div>
          )}
        </div>
      </div>
    )}
  </>
  )
}
