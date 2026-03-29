/**
 * Step 7b — VP Final Approval
 * VP reviews the payroll summary and provides final authorization before disbursement.
 * SoD-008: VP approver ≠ initiator.
 */
import { useState } from 'react'
import { useNavigate, useParams } from 'react-router-dom'
import { toast } from 'sonner'
import { ArrowLeft, ArrowRight, ShieldAlert, ShieldCheck, Loader2 } from 'lucide-react'
import {
  usePayrollRun,
  usePayrollApprovals,
  usePayrollBreakdown,
  useVpApprovePayroll,
} from '@/hooks/usePayroll'
import { WizardStepHeader } from '@/components/payroll/WizardStepHeader'
import { useAuth } from '@/hooks/useAuth'
import { useAuthStore } from '@/stores/authStore'
import ConfirmDialog from '@/components/ui/ConfirmDialog'
import { firstErrorMessage } from '@/lib/errorHandler'

function formatPHP(n: number): string {
  return new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' }).format(n)
}

const VP_CHECKLIST_ITEMS = [
  'I have reviewed the payroll summary and confirmed the total amounts are within budget.',
  'I have verified that HR and Accounting approvals are recorded and valid.',
  'I authorize the disbursement of payroll to employee bank accounts.',
]

export default function PayrollRunVpReviewPage(): JSX.Element {
  const { ulid: id } = useParams<{ ulid: string }>()
  const runId = id ?? null
  const navigate = useNavigate()

  const { data: run } = usePayrollRun(runId)
  const vpApprove = useVpApprovePayroll(runId ?? '')
  const { data: approvals } = usePayrollApprovals(runId)
  const { data: breakdown } = usePayrollBreakdown(runId, { per_page: 5 })
  const { user } = useAuth()
  const hasVpApprove = useAuthStore((s) => s.hasPermission('payroll.vp_approve'))

  const [checked, setChecked] = useState<Record<number, boolean>>({})
  const [comments, setComments] = useState('')

  const allChecked = VP_CHECKLIST_ITEMS.every((_, i) => !!checked[i])
  const sodViolation = user?.id === run?.initiated_by_id
  const isReadOnly = !hasVpApprove

  // ── Validation for approval ───────────────────────────────────────────────
  function validateApproval(): boolean {
    if (sodViolation) {
      toast.error('SoD Violation: You cannot approve a payroll run you initiated.')
      return false
    }
    if (!allChecked) {
      toast.error('Please check all items in the VP approval checklist.')
      return false
    }
    return true
  }

  async function handleApprove() {
    if (!validateApproval()) return
    try {
      await vpApprove.mutateAsync({
        checkboxes_checked: VP_CHECKLIST_ITEMS.filter((_, i) => checked[i]),
        comments: comments.trim() || null,
      })
      toast.success('VP approval recorded. Payroll is now ready for disbursement.')
      navigate(`/payroll/runs/${runId}/disburse`)
    } catch (err) {
      toast.error(firstErrorMessage(err))
    }
  }

  if (!run) return <></>

  // Only show back button when in DRAFT status
  const canGoBack = run.status === 'DRAFT' || run.status === 'draft'

  return (
    <div className="max-w-5xl mx-auto space-y-6">
      {canGoBack && (
        <button
          onClick={() => navigate(`/payroll/runs/${runId}/acctg-review`)}
          className="flex items-center gap-1.5 text-sm text-neutral-500 hover:text-neutral-800 transition-colors"
        >
          <ArrowLeft className="h-4 w-4" /> Back to Accounting Review
        </button>
      )}

      <WizardStepHeader
        step={7}
        title={isReadOnly ? 'VP Review (View Only)' : 'VP Final Approval'}
        description={`Run #${run.reference_no} — ${isReadOnly ? 'View approval status.' : 'Review payroll totals and authorize disbursement.'}`}
      />

      {/* SoD badge */}
      {sodViolation && (
        <div className="flex items-start gap-3 bg-red-50 border border-red-200 rounded p-4">
          <ShieldAlert className="h-5 w-5 text-red-500 shrink-0 mt-0.5" />
          <div>
            <p className="text-sm font-semibold text-red-800">SoD Violation — SOD-008</p>
            <p className="text-xs text-red-700 mt-0.5">
              You initiated this payroll run. A different VP must provide final approval
              (Segregation of Duties policy).
            </p>
          </div>
        </div>
      )}

      {/* Summary Card */}
      <div className="bg-white border border-neutral-200 rounded-xl overflow-hidden">
        <div className="px-5 py-4 border-b border-neutral-100">
          <h3 className="text-sm font-semibold text-neutral-800">Payroll Summary</h3>
        </div>
        <div className="grid grid-cols-2 sm:grid-cols-4 gap-4 p-5">
          <div>
            <p className="text-xs text-neutral-500">Total Employees</p>
            <p className="text-lg font-bold text-neutral-900">{run.total_employees ?? 0}</p>
          </div>
          <div>
            <p className="text-xs text-neutral-500">Total Gross Pay</p>
            <p className="text-lg font-bold text-neutral-900">
              {formatPHP((run.gross_pay_total_centavos ?? 0) / 100)}
            </p>
          </div>
          <div>
            <p className="text-xs text-neutral-500">Total Deductions</p>
            <p className="text-lg font-bold text-red-600">
              {formatPHP((run.total_deductions_centavos ?? 0) / 100)}
            </p>
          </div>
          <div>
            <p className="text-xs text-neutral-500">Total Net Pay</p>
            <p className="text-lg font-bold text-green-700">
              {formatPHP((run.net_pay_total_centavos ?? 0) / 100)}
            </p>
          </div>
        </div>
      </div>

      {/* Approval Trail */}
      {approvals && approvals.length > 0 && (
        <div className="bg-white border border-neutral-200 rounded-xl overflow-hidden">
          <div className="px-5 py-4 border-b border-neutral-100">
            <h3 className="text-sm font-semibold text-neutral-800">Approval History</h3>
          </div>
          <div className="p-5 space-y-2">
            {approvals.map((apr) => (
              <div key={apr.id} className="flex items-center gap-3 text-sm">
                <ShieldCheck className="h-4 w-4 text-green-500 shrink-0" />
                <div>
                  <span className="font-medium text-neutral-800">
                    {apr.actor?.name ?? `User #${apr.actor_id}`}
                  </span>
                  <span className="text-neutral-500 ml-1">
                    ({apr.stage}) — {apr.action.toLowerCase()} on{' '}
                    {new Date(apr.acted_at).toLocaleString('en-PH')}
                  </span>
                  {apr.comments && (
                    <p className="text-xs text-neutral-400 mt-0.5 italic">"{apr.comments}"</p>
                  )}
                </div>
              </div>
            ))}
          </div>
        </div>
      )}

      {/* Top 5 Employees Preview */}
      {breakdown?.data && breakdown.data.length > 0 && (
        <div className="bg-white border border-neutral-200 rounded-xl overflow-hidden">
          <div className="px-5 py-4 border-b border-neutral-100">
            <h3 className="text-sm font-semibold text-neutral-800">
              Employee Preview (Top {Math.min(5, breakdown.meta?.total ?? 0)} of{' '}
              {breakdown.meta?.total ?? 0})
            </h3>
          </div>
          <div className="p-5 overflow-x-auto">
            <table className="w-full text-sm">
              <thead>
                <tr className="border-b border-neutral-200 text-xs text-neutral-500 uppercase">
                  <th className="py-2 text-left">Employee</th>
                  <th className="py-2 text-right">Gross</th>
                  <th className="py-2 text-right">Net Pay</th>
                </tr>
              </thead>
              <tbody>
                {breakdown.data.map(
                  (detail: {
                    id: number
                    employee?: { first_name: string; last_name: string }
                    employee_id: number
                    gross_pay_centavos?: number
                    net_pay_centavos?: number
                  }) => (
                    <tr key={detail.id} className="border-b border-neutral-100">
                      <td className="py-2 font-medium text-neutral-800">
                        {detail.employee
                          ? `${detail.employee.first_name} ${detail.employee.last_name}`
                          : `#${detail.employee_id}`}
                      </td>
                      <td className="py-2 text-right">
                        {formatPHP((detail.gross_pay_centavos ?? 0) / 100)}
                      </td>
                      <td className="py-2 text-right font-medium">
                        {formatPHP((detail.net_pay_centavos ?? 0) / 100)}
                      </td>
                    </tr>
                  ),
                )}
              </tbody>
            </table>
          </div>
        </div>
      )}

      {/* VP Checklist */}
      {!sodViolation && !isReadOnly && (
        <div className="bg-white border border-neutral-200 rounded p-5 space-y-3">
          <h3 className="text-sm font-semibold text-neutral-800">VP Approval Checklist</h3>
          {VP_CHECKLIST_ITEMS.map((item, i) => (
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

          <div className="pt-3">
            <label className="text-xs text-neutral-500">Comments (optional)</label>
            <textarea
              rows={2}
              value={comments}
              onChange={(e) => setComments(e.target.value)}
              placeholder="Optional comments..."
              className="w-full mt-1 border border-neutral-300 rounded px-3 py-2 text-sm resize-none focus:ring-2 focus:ring-neutral-500 outline-none"
            />
          </div>
        </div>
      )}

      {/* Actions - hide if SoD violation */}
      {!sodViolation && (
        <div className="flex items-center justify-end pt-4 border-t border-neutral-100">
          {isReadOnly ? (
            <button
              type="button"
              onClick={() => navigate('/payroll/runs')}
              className="flex items-center gap-2 px-6 py-2 bg-neutral-600 hover:bg-neutral-700 text-white text-sm font-medium rounded transition-colors"
            >
              <ArrowLeft className="h-4 w-4" /> Back to Payroll Runs
            </button>
          ) : (
            <ConfirmDialog
              title="Authorize Disbursement?"
              description={`This is the final authorization for payroll disbursement. Once approved, the GL will be posted and employee payments will be processed. Please ensure all previous approvals are valid.`}
              confirmLabel="Authorize Disbursement"
              onConfirm={handleApprove}
            >
              <button
                type="button"
                disabled={vpApprove.isPending || !allChecked || run.status !== 'ACCTG_APPROVED'}
                className="flex items-center gap-2 px-6 py-2 bg-neutral-900 hover:bg-neutral-800 disabled:opacity-50 disabled:cursor-not-allowed text-white text-sm font-medium rounded transition-colors"
              >
                {vpApprove.isPending ? (
                  <>
                    <Loader2 className="h-4 w-4 animate-spin" /> Processing…
                  </>
                ) : (
                  <>
                    <ShieldCheck className="h-4 w-4" /> Approve &amp; Authorize Disbursement{' '}
                    <ArrowRight className="h-4 w-4" />
                  </>
                )}
              </button>
            </ConfirmDialog>
          )}
        </div>
      )}
    </div>
  )
}
