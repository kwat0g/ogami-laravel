/**
 * Step 6 — HR Manager Review
 * HR Manager reviews the submitted payroll run:
 * - Views prior-period variance summary
 * - Checks SOD-005 / SOD-006 auto-badge (cannot approve own run)
 * - Must check 3 items before approving
 * - Can return the run for rework with a comment
 */
import { useState } from 'react'
import { useNavigate, useParams } from 'react-router-dom'
import { toast } from 'sonner'
import { ArrowLeft, ArrowRight, ThumbsUp, RotateCcw, Loader2 } from 'lucide-react'
import { usePayrollRun, useHrApprove, usePayrollApprovals, usePayrollDetails } from '@/hooks/usePayroll'
import { WizardStepHeader } from '@/components/payroll/WizardStepHeader'
import { useAuth } from '@/hooks/useAuth'
import { useAuthStore } from '@/stores/authStore'
import ConfirmDialog from '@/components/ui/ConfirmDialog'
import { firstErrorMessage } from '@/lib/errorHandler'

function formatCentavos(c: number | null | undefined): string {
  if (c == null) return '—'
  return new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' }).format(c / 100)
}

const CHECKLIST_ITEMS = [
  'I have reviewed the payroll breakdown and certify that the computed amounts are correct.',
]

export default function PayrollRunHrReviewPage() {
  const { ulid: id } = useParams<{ ulid: string }>()
  const runId = id ?? null
  const navigate = useNavigate()

  const { data: run } = usePayrollRun(runId)
  const hrApprove = useHrApprove(runId)
  const { data: approvals } = usePayrollApprovals(runId)
  const { user } = useAuth()
  const { hasRole } = useAuthStore()
  const [detailPage, setDetailPage] = useState(1)
  const { data: detailsData } = usePayrollDetails(runId, detailPage)

  const [checked, setChecked] = useState<Record<number, boolean>>({})
  const [comments, setComments] = useState('')
  const [action, setAction] = useState<'APPROVED' | 'RETURNED' | null>(null)
  const [returnReason, setReturnReason] = useState('')

  const allChecked = CHECKLIST_ITEMS.every((_, i) => !!checked[i])

  // SoD check: user cannot approve a run they initiated (super_admin bypasses SoD)
  const isSuperAdmin = hasRole('super_admin')
  const isInitiator = user?.id === run?.initiated_by_id
  const sodViolation = isInitiator && !isSuperAdmin

  // ── Validation for approval ───────────────────────────────────────────────
  function validateApproval(): boolean {
    if (sodViolation) {
      return false
    }
    if (!allChecked) {
      return false
    }
    return true
  }

  // ── Validation for return ─────────────────────────────────────────────────
  function validateReturn(): boolean {
    if (!returnReason.trim()) {
      return false
    }
    if (returnReason.trim().length < 10) {
      return false
    }
    return true
  }

  async function handleAction(act: 'APPROVED' | 'RETURNED') {
    if (act === 'APPROVED' && !validateApproval()) return
    if (act === 'RETURNED' && !validateReturn()) return

    try {
      await hrApprove.mutateAsync({
        action: act,
        comments: comments || undefined,
        return_comments: act === 'RETURNED' ? returnReason : undefined,
        checkboxes_checked:
          act === 'APPROVED' ? CHECKLIST_ITEMS.filter((_, i) => checked[i]) : undefined,
      })

      if (act === 'APPROVED') {
        toast.success('HR approval recorded. Forwarded to Accounting Manager.')
        navigate(`/payroll/runs/${runId}/acctg-review`) // After HR approval, go to Accounting review
      } else {
        toast.info('Run returned to initiator with your comments.')
        navigate(`/payroll/runs`)
      }
    } catch (err) {
      toast.error(firstErrorMessage(err))
    }
  }

  if (!run) return null

  // Only show back button to previous step when in DRAFT status
  const canGoBackToPrevStep = run.status === 'DRAFT' || run.status === 'draft'

  return (
    <div className="max-w-5xl mx-auto space-y-6">
      {/* Back to Payroll Runs list - always visible */}
      <button
        onClick={() => navigate('/payroll/runs')}
        className="flex items-center gap-1.5 text-sm text-neutral-500 hover:text-neutral-800 transition-colors"
      >
        <ArrowLeft className="h-4 w-4" /> Back to Payroll Runs
      </button>

      {canGoBackToPrevStep && (
        <button
          onClick={() => navigate(`/payroll/runs/${runId}/review`)}
          className="flex items-center gap-1.5 text-sm text-neutral-500 hover:text-neutral-800 transition-colors"
        >
          <ArrowLeft className="h-4 w-4" /> Back to Review
        </button>
      )}

      <WizardStepHeader
        step={6}
        title="HR Manager Review"
        description={`Run #${run.reference_no} — HR Manager must review and approve or return this payroll run.`}
      />

      {/* Summary card */}
      <div className="bg-white border border-neutral-200 rounded p-5">
        <h3 className="text-sm font-semibold text-neutral-800 mb-4">Run Summary</h3>
        <div className="grid grid-cols-2 sm:grid-cols-4 gap-x-4 gap-y-3 text-sm">
          <div>
            <p className="text-neutral-400 text-xs">Reference</p>
            <p className="font-medium">{run.reference_no}</p>
          </div>
          <div>
            <p className="text-neutral-400 text-xs">Pay Period</p>
            <p className="font-medium">{run.pay_period_label}</p>
          </div>
          <div>
            <p className="text-neutral-400 text-xs">Period Start</p>
            <p className="font-medium">{new Date(run.cutoff_start).toLocaleDateString('en-PH')}</p>
          </div>
          <div>
            <p className="text-neutral-400 text-xs">Period End</p>
            <p className="font-medium">{new Date(run.cutoff_end).toLocaleDateString('en-PH')}</p>
          </div>
          <div>
            <p className="text-neutral-400 text-xs">Pay Date</p>
            <p className="font-medium">{new Date(run.pay_date).toLocaleDateString('en-PH')}</p>
          </div>
          <div>
            <p className="text-neutral-400 text-xs">Employees</p>
            <p className="font-medium">{run.total_employees}</p>
          </div>
          <div>
            <p className="text-neutral-400 text-xs">Gross Pay</p>
            <p className="font-medium">{formatCentavos(run.gross_pay_total_centavos)}</p>
          </div>
          <div>
            <p className="text-neutral-400 text-xs">Net Pay Total</p>
            <p className="font-medium text-neutral-900">{formatCentavos(run.net_pay_total_centavos)}</p>
          </div>
        </div>
      </div>

      {/* Prior approvals (if run was returned before) */}
      {approvals && approvals.filter((a) => a.stage === 'HR_REVIEW').length > 0 && (
        <div className="bg-amber-50 border border-amber-200 rounded p-4 space-y-2">
          <p className="text-xs font-semibold text-amber-800 font-medium">
            Prior HR Review Actions
          </p>
          {approvals
            .filter((a) => a.stage === 'HR_REVIEW')
            .map((apr) => (
              <div key={apr.id} className="text-xs text-amber-700">
                <strong>{apr.actor?.name ?? `User #${apr.actor_id}`}</strong>{' '}
                {apr.action === 'RETURNED' ? 'returned' : 'approved'} on{' '}
                {new Date(apr.acted_at).toLocaleString('en-PH')}
                {apr.comments && <p className="mt-0.5 text-amber-600 italic">"{apr.comments}"</p>}
              </div>
            ))}
        </div>
      )}

      {/* Payroll Breakdown */}
      {detailsData && detailsData.data.length > 0 && (
        <div className="bg-white border border-neutral-200 rounded-xl overflow-hidden">
          <div className="px-5 py-3 border-b border-neutral-100 flex items-center justify-between">
            <h3 className="text-sm font-semibold text-neutral-800">
              Payroll Breakdown
              <span className="ml-2 text-xs font-normal text-neutral-400">
                {detailsData.meta.total} employees
              </span>
            </h3>
            {detailsData.meta.last_page > 1 && (
              <div className="flex items-center gap-3 text-xs text-neutral-500">
                <span>Page {detailsData.meta.current_page} of {detailsData.meta.last_page}</span>
                <div className="flex gap-1">
                  <button
                    onClick={() => setDetailPage((p) => Math.max(1, p - 1))}
                    disabled={detailsData.meta.current_page === 1}
                    className="px-2.5 py-1 border border-neutral-200 rounded hover:bg-neutral-50 disabled:opacity-40"
                  >←</button>
                  <button
                    onClick={() => setDetailPage((p) => Math.min(detailsData.meta.last_page, p + 1))}
                    disabled={detailsData.meta.current_page === detailsData.meta.last_page}
                    className="px-2.5 py-1 border border-neutral-200 rounded hover:bg-neutral-50 disabled:opacity-40"
                  >→</button>
                </div>
              </div>
            )}
          </div>
          <div className="overflow-auto max-h-72">
            <table className="min-w-full text-sm">
              <thead className="bg-neutral-50 border-b border-neutral-200 sticky top-0">
                <tr>
                  <th className="px-3 py-2.5 text-left text-xs font-semibold text-neutral-500 whitespace-nowrap">Employee</th>
                  <th className="px-3 py-2.5 text-right text-xs font-semibold text-neutral-500 whitespace-nowrap">Days</th>
                  <th className="px-3 py-2.5 text-right text-xs font-semibold text-neutral-500 whitespace-nowrap">Basic Pay</th>
                  <th className="px-3 py-2.5 text-right text-xs font-semibold text-neutral-500 whitespace-nowrap">OT Pay</th>
                  <th className="px-3 py-2.5 text-right text-xs font-semibold text-neutral-500 whitespace-nowrap">Gross Pay</th>
                  <th className="px-3 py-2.5 text-right text-xs font-semibold text-neutral-500 whitespace-nowrap">SSS</th>
                  <th className="px-3 py-2.5 text-right text-xs font-semibold text-neutral-500 whitespace-nowrap">PhilHealth</th>
                  <th className="px-3 py-2.5 text-right text-xs font-semibold text-neutral-500 whitespace-nowrap">Pag-IBIG</th>
                  <th className="px-3 py-2.5 text-right text-xs font-semibold text-neutral-500 whitespace-nowrap">Tax</th>
                  <th className="px-3 py-2.5 text-right text-xs font-semibold text-neutral-500 whitespace-nowrap">Net Pay</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-neutral-100">
                {detailsData.data.map((d) => (
                  <tr key={d.id} className="hover:bg-neutral-50">
                    <td className="px-3 py-2.5 whitespace-nowrap">
                      <p className="font-medium text-neutral-800">
                        {d.employee ? `${d.employee.first_name} ${d.employee.last_name}` : `Employee #${d.employee_id}`}
                      </p>
                      {d.employee?.employee_code && (
                        <p className="text-xs text-neutral-400">{d.employee.employee_code}</p>
                      )}
                    </td>
                    <td className="px-3 py-2.5 text-right text-neutral-600 text-xs">
                      <span>{d.days_worked}d</span>
                      {d.days_absent > 0 && <span className="text-red-400 ml-1">-{d.days_absent}ab</span>}
                    </td>
                    <td className="px-3 py-2.5 text-right text-neutral-700">{formatCentavos(d.basic_pay_centavos)}</td>
                    <td className="px-3 py-2.5 text-right text-neutral-600">
                      {d.overtime_pay_centavos > 0 ? formatCentavos(d.overtime_pay_centavos) : <span className="text-neutral-300">—</span>}
                    </td>
                    <td className="px-3 py-2.5 text-right text-neutral-700">{formatCentavos(d.gross_pay_centavos)}</td>
                    <td className="px-3 py-2.5 text-right text-neutral-500 text-xs">{formatCentavos(d.sss_ee_centavos)}</td>
                    <td className="px-3 py-2.5 text-right text-neutral-500 text-xs">{formatCentavos(d.philhealth_ee_centavos)}</td>
                    <td className="px-3 py-2.5 text-right text-neutral-500 text-xs">{formatCentavos(d.pagibig_ee_centavos)}</td>
                    <td className="px-3 py-2.5 text-right text-neutral-500 text-xs">{formatCentavos(d.withholding_tax_centavos)}</td>
                    <td className="px-3 py-2.5 text-right font-semibold text-neutral-900 whitespace-nowrap">{formatCentavos(d.net_pay_centavos)}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>
      )}

      {/* Checklist */}
      {!sodViolation && (
        <div className="bg-white border border-neutral-200 rounded p-5 space-y-3">
          <h3 className="text-sm font-semibold text-neutral-800">Approval Checklist</h3>
          <p className="text-xs text-neutral-500">You must check all items before approving.</p>
          {CHECKLIST_ITEMS.map((item, i) => (
            <label key={i} className="flex items-start gap-3 cursor-pointer">
              <input
                type="checkbox"
                checked={!!checked[i]}
                onChange={() => setChecked((prev) => ({ ...prev, [i]: !prev[i] }))}
                className="accent-neutral-900 mt-0.5"
              />
              <span className="text-sm text-neutral-700">{item}</span>
            </label>
          ))}
        </div>
      )}

      {/* Comments */}
      <div>
        <label className="block text-sm font-medium text-neutral-700 mb-1">
          Comments <span className="text-neutral-400 font-normal">(optional)</span>
        </label>
        <textarea
          rows={3}
          value={comments}
          onChange={(e) => setComments(e.target.value)}
          placeholder="Add approval comments or notes for the record…"
          className="w-full border border-neutral-300 rounded px-3 py-2 text-sm resize-none focus:ring-2 focus:ring-neutral-500 outline-none"
        />
      </div>

      {/* Return reason */}
      {action === 'RETURNED' && (
        <div>
          <label className="block text-sm font-medium text-neutral-700 mb-1">
            Return Reason <span className="text-red-500">*</span>
          </label>
          <textarea
            rows={3}
            value={returnReason}
            onChange={(e) => setReturnReason(e.target.value)}
            placeholder="Explain why the run is being returned for rework (minimum 10 characters)..."
            className={`w-full border rounded px-3 py-2 text-sm resize-none focus:ring-2 outline-none ${
              returnReason.trim() && returnReason.trim().length < 10
                ? 'border-red-300 focus:ring-red-500'
                : 'border-red-300 focus:ring-red-500'
            }`}
          />
          {returnReason.trim() && returnReason.trim().length < 10 && (
            <p className="text-xs text-red-500 mt-1">
              Return reason must be at least 10 characters.
            </p>
          )}
        </div>
      )}

      {/* Actions - only show if not SoD violation */}
      {!sodViolation && (
        <div className="flex items-center justify-between pt-4 border-t border-neutral-100">
          {action !== 'RETURNED' ? (
            <button
              type="button"
              onClick={() => setAction('RETURNED')}
              disabled={hrApprove.isPending}
              className="flex items-center gap-2 px-5 py-2 border border-neutral-300 text-neutral-700 hover:bg-neutral-50 text-sm font-medium rounded transition-colors"
            >
              <RotateCcw className="h-4 w-4" />
              Return for Rework
            </button>
          ) : (
            <div className="flex items-center gap-2">
              <button
                type="button"
                onClick={() => setAction(null)}
                disabled={hrApprove.isPending}
                className="px-4 py-2 text-sm text-neutral-600 hover:text-neutral-900 border border-neutral-200 rounded transition-colors"
              >
                Cancel
              </button>
              <ConfirmDialog
                title="Return Payroll Run for Rework?"
                description={`This will return the payroll run to the initiator with your comments. The initiator will need to fix any issues and resubmit.`}
                confirmLabel="Confirm Return"
                onConfirm={() => handleAction('RETURNED')}
              >
                <button
                  type="button"
                  disabled={hrApprove.isPending || !returnReason.trim() || returnReason.trim().length < 10}
                  className="flex items-center gap-2 px-5 py-2 border border-amber-300 bg-amber-50 text-amber-700 hover:bg-amber-100 text-sm font-medium rounded transition-colors disabled:opacity-50"
                >
                  <RotateCcw className="h-4 w-4" />
                  Confirm Return
                </button>
              </ConfirmDialog>
            </div>
          )}

          <ConfirmDialog
            title="Approve Payroll Run?"
            description={`This will approve the payroll run and forward it to the Accounting Manager for review. Please ensure you have reviewed all flagged items.`}
            confirmLabel="Approve & Forward"
            onConfirm={() => handleAction('APPROVED')}
          >
            <button
              type="button"
              disabled={hrApprove.isPending || !allChecked || run.status !== 'SUBMITTED'}
              className="flex items-center gap-2 px-6 py-2 bg-neutral-900 hover:bg-neutral-800 disabled:opacity-50 disabled:cursor-not-allowed text-white text-sm font-medium rounded transition-colors"
            >
              {hrApprove.isPending ? (
                <>
                  <Loader2 className="h-4 w-4 animate-spin" /> Processing…
                </>
              ) : (
                <>
                  <ThumbsUp className="h-4 w-4" /> Approve <ArrowRight className="h-4 w-4" />
                </>
              )}
            </button>
          </ConfirmDialog>
        </div>
      )}
    </div>
  )
}
