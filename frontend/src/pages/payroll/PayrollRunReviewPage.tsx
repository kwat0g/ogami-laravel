/**
 * Step 5 — Payroll Review
 * Three-tab interface: Breakdown, Exceptions, and Gov Reports.
 * Payroll initiator reviews all employee payslip data, flags anomalies, then submits for Accounting approval.
 * The "Submit for Accounting Approval" button is only enabled when no employees are in FLAGGED state.
 */
import { useState, Fragment } from 'react'
import { useNavigate, useParams } from 'react-router-dom'
import { toast } from 'sonner'
import {
  ArrowLeft, ArrowRight, Flag, CheckSquare, AlertTriangle, Loader2,
  Search, ChevronDown, ChevronUp, Ban,
} from 'lucide-react'
import {
  usePayrollRun,
  usePayrollBreakdown,
  usePayrollExceptions,
  useFlagEmployee,
  useSubmitForHrApproval,
  useCancelPayrollRun,
} from '@/hooks/usePayroll'
import type { PayrollDetail } from '@/types/payroll'
import { WizardStepHeader } from '@/components/payroll/WizardStepHeader'

function formatCentavos(c: number | null | undefined): string {
  if (c == null) return '—'
  return new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' }).format(c / 100)
}

// ── Breakdown tab ─────────────────────────────────────────────────────────────
function BreakdownTab({ runId }: { runId: string | null }) {
  const [page, setPage]                 = useState(1)
  const [search, setSearch]             = useState('')
  const [expandedId, setExpandedId]     = useState<number | null>(null)
  const [flagFilter, setFlagFilter]     = useState<string>('')

  const { data, isLoading } = usePayrollBreakdown(runId, {
    page,
    per_page: 50,
    search: search || undefined,
    flag: (flagFilter as 'none' | 'flagged' | 'resolved') || undefined,
  })
  const flagEmployee = useFlagEmployee(runId)

  async function toggleFlag(detail: PayrollDetail) {
    const newFlag: 'flagged' | 'none' = detail.employee_flag === 'flagged' ? 'none' : 'flagged'
    try {
      await flagEmployee.mutateAsync({ detailId: detail.id, flag: newFlag })
    } catch {
      toast.error('Failed to update flag.')
    }
  }

  return (
    <div className="space-y-4">
      {/* Summary bar */}
      {data?.summary && (
        <div className="grid grid-cols-2 sm:grid-cols-4 gap-3">
          {[
            { label: 'Employees', val: data.summary.total_employees },
            { label: 'Total Gross', val: formatCentavos(data.summary.total_gross) },
            { label: 'Total Deductions', val: formatCentavos(data.summary.total_deductions) },
            { label: 'Net Pay', val: formatCentavos(data.summary.total_net) },
          ].map(({ label, val }) => (
            <div key={label} className="bg-gray-50 rounded-lg p-3">
              <p className="text-xs text-gray-500">{label}</p>
              <p className="text-base font-semibold text-gray-900">{val}</p>
            </div>
          ))}
        </div>
      )}

      {/* Filters */}
      <div className="flex gap-2">
        <div className="relative flex-1">
          <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400" />
          <input
            value={search}
            onChange={e => setSearch(e.target.value)}
            placeholder="Search employees…"
            className="w-full pl-9 pr-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none"
          />
        </div>
        <select
          value={flagFilter}
          onChange={e => setFlagFilter(e.target.value)}
          className="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 outline-none"
        >
          <option value="">All Employees</option>
          <option value="flagged">Flagged Only</option>
          <option value="resolved">Resolved</option>
          <option value="none">No Flag</option>
        </select>
      </div>

      {/* Table */}
      {isLoading ? (
        <div className="flex items-center gap-2 text-sm text-gray-400 py-8">
          <Loader2 className="h-4 w-4 animate-spin" /> Loading breakdown…
        </div>
      ) : (
        <div className="overflow-x-auto">
          <table className="w-full text-sm text-left border-collapse">
            <thead>
              <tr className="border-b border-gray-200 text-xs text-gray-500 uppercase tracking-wide">
                <th className="px-3 py-2.5">Employee</th>
                <th className="px-3 py-2.5 text-right">Gross</th>
                <th className="px-3 py-2.5 text-right">Deductions</th>
                <th className="px-3 py-2.5 text-right">Net Pay</th>
                <th className="px-3 py-2.5 text-center">Flag</th>
                <th className="px-3 py-2.5 text-center">Detail</th>
              </tr>
            </thead>
            <tbody>
              {(data?.data ?? []).map(detail => {
                const isExpanded = expandedId === detail.id
                const isFlagged  = detail.employee_flag === 'flagged'
                return (
                  <Fragment key={detail.id}>
                    <tr
                      className={`border-b border-gray-100 even:bg-slate-50 hover:bg-blue-50/60 transition-colors ${isFlagged ? 'bg-amber-50' : ''}`}
                    >
                      <td className="px-3 py-2">
                        <p className="font-medium text-gray-800">
                          {detail.employee ? `${detail.employee.first_name} ${detail.employee.last_name}` : `#${detail.employee_id}`}
                        </p>
                        {detail.is_below_min_wage && (
                          <span className="text-xs text-red-600 font-medium">↓ Below min wage</span>
                        )}
                        {detail.ln007_applied && (
                          <span className="ml-1 text-xs text-amber-600 font-medium">LN-007</span>
                        )}
                      </td>
                      <td className="py-2 pr-4 text-right text-gray-700">{formatCentavos(detail.gross_pay_centavos)}</td>
                      <td className="py-2 pr-4 text-right text-gray-700">{formatCentavos(detail.total_deductions_centavos)}</td>
                      <td className="py-2 pr-4 text-right font-medium text-gray-900">{formatCentavos(detail.net_pay_centavos)}</td>
                      <td className="py-2 pr-4 text-center">
                        <button
                          type="button"
                          onClick={() => toggleFlag(detail)}
                          disabled={flagEmployee.isPending}
                          title={isFlagged ? 'Remove flag' : 'Flag for review'}
                          className={`p-1 rounded transition-colors ${isFlagged ? 'text-amber-600 hover:text-amber-800' : 'text-gray-300 hover:text-amber-500'}`}
                        >
                          <Flag className="h-4 w-4" />
                        </button>
                      </td>
                      <td className="px-3 py-2 text-center">
                        <button
                          type="button"
                          onClick={() => setExpandedId(isExpanded ? null : detail.id)}
                          className="p-1 rounded text-gray-400 hover:text-gray-700"
                        >
                          {isExpanded ? <ChevronUp className="h-4 w-4" /> : <ChevronDown className="h-4 w-4" />}
                        </button>
                      </td>
                    </tr>
                    {isExpanded && (
                      <tr key={`${detail.id}-expanded`} className="bg-gray-50">
                        <td colSpan={6} className="px-4 py-3">
                          <div className="grid grid-cols-2 sm:grid-cols-4 gap-3 text-xs">
                            <div><p className="text-gray-400">Basic Pay</p><p className="font-medium">{formatCentavos(detail.basic_pay_centavos)}</p></div>
                            <div><p className="text-gray-400">OT Pay</p><p className="font-medium">{formatCentavos(detail.overtime_pay_centavos)}</p></div>
                            <div><p className="text-gray-400">SSS (EE)</p><p className="font-medium">{formatCentavos(detail.sss_ee_centavos)}</p></div>
                            <div><p className="text-gray-400">PhilHealth (EE)</p><p className="font-medium">{formatCentavos(detail.philhealth_ee_centavos)}</p></div>
                            <div><p className="text-gray-400">Pag-IBIG (EE)</p><p className="font-medium">{formatCentavos(detail.pagibig_ee_centavos)}</p></div>
                            <div><p className="text-gray-400">Withholding Tax</p><p className="font-medium">{formatCentavos(detail.withholding_tax_centavos)}</p></div>
                            <div><p className="text-gray-400">Loan Deductions</p><p className="font-medium">{formatCentavos(detail.loan_deductions_centavos)}</p></div>
                            <div><p className="text-gray-400">Other Deductions</p><p className="font-medium">{formatCentavos(detail.other_deductions_centavos)}</p></div>
                            {detail.ln007_applied && (
                              <div className="col-span-2">
                                <p className="text-amber-600">LN-007 Applied — Truncated: {formatCentavos(detail.ln007_truncated_amt)} · Carried Fwd: {formatCentavos(detail.ln007_carried_fwd)}</p>
                              </div>
                            )}
                            {detail.review_note && (
                              <div className="col-span-4 bg-amber-50 p-2 rounded">
                                <p className="text-amber-800"><strong>Review Note:</strong> {detail.review_note}</p>
                              </div>
                            )}
                          </div>
                        </td>
                      </tr>
                    )}
                  </Fragment>
                )
              })}
            </tbody>
          </table>
        </div>
      )}

      {/* Pagination */}
      {data?.meta?.last_page > 1 && (
        <div className="flex items-center justify-between text-sm text-gray-500">
          <span>Page {data.meta?.current_page} of {data.meta?.last_page} ({data.meta?.total} employees)</span>
          <div className="flex gap-2">
            <button
              type="button"
              disabled={page <= 1}
              onClick={() => setPage(p => p - 1)}
              className="px-3 py-1 border border-gray-300 rounded disabled:opacity-40"
            >← Prev</button>
            <button
              type="button"
              disabled={page >= (data.meta?.last_page ?? 1)}
              onClick={() => setPage(p => p + 1)}
              className="px-3 py-1 border border-gray-300 rounded disabled:opacity-40"
            >Next →</button>
          </div>
        </div>
      )}
    </div>
  )
}

// ── Exceptions tab ────────────────────────────────────────────────────────────
function ExceptionsTab({ runId }: { runId: number }) {
  const { data, isLoading } = usePayrollExceptions(runId)
  const rows = (data?.data ?? []) as Record<string, unknown>[]

  if (isLoading) return <div className="flex items-center gap-2 text-sm text-gray-400 py-8"><Loader2 className="h-4 w-4 animate-spin" /> Loading exceptions…</div>
  if (!rows.length) return <div className="py-8 text-center text-sm text-green-600"><CheckSquare className="h-6 w-6 mx-auto mb-2" /> No exceptions found for this run.</div>

  return (
    <div className="space-y-2">
      {rows.map((row, i) => (
        <div key={i} className="bg-amber-50 border border-amber-200 rounded-lg px-4 py-3 text-sm">
          <div className="flex items-center gap-2">
            <AlertTriangle className="h-4 w-4 text-amber-600 shrink-0" />
            <span className="font-medium text-amber-800">{(row.employee_name as string) ?? `Employee #${row.employee_id}`}</span>
            {Boolean(row.department) && <span className="text-xs text-gray-500">— {row.department as string}</span>}
          </div>
          <div className="mt-1 flex flex-wrap gap-3 text-xs text-gray-600">
            {Boolean(row.is_below_min_wage) && <span className="bg-red-100 text-red-700 px-2 py-0.5 rounded-full">Below Min Wage</span>}
            {Boolean(row.ln007_applied)     && <span className="bg-purple-100 text-purple-700 px-2 py-0.5 rounded-full">LN-007 Applied</span>}
            {Boolean(row.has_deferred_deductions) && <span className="bg-orange-100 text-orange-700 px-2 py-0.5 rounded-full">Deferred Deductions</span>}
          </div>
        </div>
      ))}
    </div>
  )
}

// ── Main page ─────────────────────────────────────────────────────────────────
type Tab = 'breakdown' | 'exceptions' | 'gov'

export default function PayrollRunReviewPage() {
  const { ulid: id } = useParams<{ ulid: string }>()
  const runId = id ?? null
  const navigate = useNavigate()

  const { data: run }       = usePayrollRun(runId)
  const submitForHr          = useSubmitForHrApproval(runId)
  const cancelRun            = useCancelPayrollRun(runId)
  const [tab, setTab]        = useState<Tab>('breakdown')
  const [confirmCancel, setConfirmCancel] = useState(false)

  async function handleSubmitForHr() {
    try {
      await submitForHr.mutateAsync()
      toast.success('Run submitted for Accounting Manager approval.')
      navigate(`/payroll/runs/${runId}/acctg-review`)
    } catch {
      toast.error('Failed to submit for Accounting approval.')
    }
  }

  async function handleCancel() {
    try {
      await cancelRun.mutateAsync()
      toast.success('Payroll run cancelled.')
      navigate('/payroll/runs')
    } catch {
      toast.error('Failed to cancel payroll run.')
    }
  }

  if (!run) return null

  // Prevent going back if already submitted or beyond
  const isLocked = ['SUBMITTED', 'HR_APPROVED', 'ACCTG_APPROVED', 'APPROVED', 'POSTED', 'DISBURSED'].includes(run.status)
  const canSubmit = ['REVIEW', 'COMPUTED'].includes(run.status)

  const TABS: { key: Tab; label: string }[] = [
    { key: 'breakdown',  label: 'Breakdown' },
    { key: 'exceptions', label: 'Exceptions' },
    { key: 'gov',        label: 'Gov Reports' },
  ]

  return (
    <div className="max-w-6xl space-y-6">
      {!isLocked && (
        <button
          onClick={() => navigate(`/payroll/runs/${runId}/compute`)}
          className="flex items-center gap-1.5 text-sm text-gray-500 hover:text-gray-800 transition-colors"
        >
          <ArrowLeft className="h-4 w-4" /> Back to Computation
        </button>
      )}

      <WizardStepHeader
        step={5}
        title="Payroll Review"
        description={`Run #${run.reference_no} — Review computed payslips, flag anomalies, then submit for Accounting approval.`}
      />

      {/* Tab strip */}
      <div className="border-b border-gray-200">
        <div className="flex gap-6">
          {TABS.map(({ key, label }) => (
            <button
              key={key}
              type="button"
              onClick={() => setTab(key)}
              className={`pb-2 text-sm font-medium border-b-2 transition-colors ${
                tab === key
                  ? 'border-blue-600 text-blue-600'
                  : 'border-transparent text-gray-500 hover:text-gray-800'
              }`}
            >
              {label}
            </button>
          ))}
        </div>
      </div>

      {/* Tab content */}
      <div>
        {tab === 'breakdown'  && <BreakdownTab  runId={runId} />}
        {tab === 'exceptions' && <ExceptionsTab runId={runId} />}
        {tab === 'gov' && (
          <div className="py-8 text-center text-sm text-gray-400">
            Gov report exports (SSS R3, PhilHealth RF-1, Pag-IBIG MCRF) available after disbursement.
          </div>
        )}
      </div>

      {/* Actions */}
      <div className="flex items-center justify-between pt-4 border-t border-gray-100">
        <div className="flex items-center gap-2">
          {!isLocked ? (
            <button
              type="button"
              onClick={() => navigate(`/payroll/runs/${runId}/compute`)}
              className="flex items-center gap-1.5 px-4 py-2 text-sm text-gray-600 hover:text-gray-900 transition-colors"
            >
              <ArrowLeft className="h-4 w-4" /> Back
            </button>
          ) : (
            <div className="text-sm text-amber-600">
              ⚠️ Payroll submitted — review only
            </div>
          )}
          
          {/* Cancel button - available until submitted to accounting */}
          {!isLocked && (
            confirmCancel ? (
              <div className="flex items-center gap-2">
                <span className="text-xs text-red-600">Cancel this run?</span>
                <button
                  type="button"
                  onClick={() => void handleCancel()}
                  disabled={cancelRun.isPending}
                  className="flex items-center gap-1 px-3 py-1.5 text-xs bg-red-600 hover:bg-red-700 disabled:opacity-50 text-white rounded-md transition-colors"
                >
                  {cancelRun.isPending ? <Loader2 className="h-3 w-3 animate-spin" /> : null}
                  Confirm
                </button>
                <button
                  type="button"
                  onClick={() => setConfirmCancel(false)}
                  className="px-3 py-1.5 text-xs text-gray-600 hover:text-gray-900 border border-gray-200 rounded-md transition-colors"
                >
                  Keep
                </button>
              </div>
            ) : (
              <button
                type="button"
                onClick={() => setConfirmCancel(true)}
                className="flex items-center gap-1.5 px-3 py-2 text-sm text-red-600 hover:text-red-800 border border-red-200 hover:border-red-400 rounded-lg transition-colors"
              >
                <Ban className="h-4 w-4" /> Cancel Run
              </button>
            )
          )}
        </div>
        <button
          type="button"
          onClick={handleSubmitForHr}
          disabled={submitForHr.isPending || !canSubmit}
          className="flex items-center gap-2 px-6 py-2 bg-blue-600 hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed text-white text-sm font-medium rounded-lg transition-colors"
        >
          {submitForHr.isPending
            ? <><Loader2 className="h-4 w-4 animate-spin" /> Submitting…</>
            : isLocked 
              ? <>Already Submitted</>
              : <>Submit for Accounting Approval <ArrowRight className="h-4 w-4" /></>
          }
        </button>
      </div>
    </div>
  )
}
