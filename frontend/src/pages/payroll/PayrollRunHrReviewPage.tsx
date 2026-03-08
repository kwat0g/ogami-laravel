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
import { ArrowLeft, ArrowRight, ShieldAlert, ThumbsUp, RotateCcw, Loader2 } from 'lucide-react'
import { usePayrollRun, useHrApprove, usePayrollApprovals } from '@/hooks/usePayroll'
import { WizardStepHeader } from '@/components/payroll/WizardStepHeader'
import { useAuth } from '@/hooks/useAuth'

function formatCentavos(c: number | null | undefined): string {
  if (c == null) return '—'
  return new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' }).format(c / 100)
}

const CHECKLIST_ITEMS = [
  'I have reviewed the payslip breakdown and exception report.',
  'I confirm that employee inclusion/exclusion aligns with active headcount.',
  'I certify that the computed amounts are correct to the best of my knowledge.',
]

export default function PayrollRunHrReviewPage() {
  const { ulid: id } = useParams<{ ulid: string }>()
  const runId    = id ?? null
  const navigate = useNavigate()

  const { data: run }       = usePayrollRun(runId)
  const hrApprove            = useHrApprove(runId)
  const { data: approvals }  = usePayrollApprovals(runId)
  const { user }             = useAuth()

  const [checked, setChecked]       = useState<Record<number, boolean>>({})
  const [comments, setComments]     = useState('')
  const [action, setAction]         = useState<'APPROVED' | 'RETURNED' | null>(null)
  const [returnReason, setReturnReason] = useState('')

  const allChecked = CHECKLIST_ITEMS.every((_, i) => !!checked[i])

  // SoD check: user cannot approve a run they initiated
  const isInitiator = user?.id === run?.initiated_by_id
  const sodViolation = isInitiator

  async function handleAction(act: 'APPROVED' | 'RETURNED') {
    if (act === 'APPROVED' && (!allChecked || sodViolation)) return

    try {
      await hrApprove.mutateAsync({
        action: act,
        comments:       comments || undefined,
        return_comments: act === 'RETURNED' ? returnReason : undefined,
        checkboxes_checked: act === 'APPROVED'
          ? CHECKLIST_ITEMS.filter((_, i) => checked[i])
          : undefined,
      })

      if (act === 'APPROVED') {
        toast.success('HR approval recorded. Forwarded to Accounting Manager.')
        navigate(`/payroll/runs/${runId}/acctg-review`)
      } else {
        toast.info('Run returned to initiator with your comments.')
        navigate(`/payroll/runs`)
      }
    } catch (err: unknown) {
      const msg = (err as { response?: { data?: { message?: string } } })?.response?.data?.message
      toast.error(msg ?? 'Action failed.')
    }
  }

  if (!run) return null

  return (
    <div className="max-w-3xl space-y-6">
      <button
        onClick={() => navigate(`/payroll/runs/${runId}/review`)}
        className="flex items-center gap-1.5 text-sm text-neutral-500 hover:text-neutral-800 transition-colors"
      >
        <ArrowLeft className="h-4 w-4" /> Back to Review
      </button>

      <WizardStepHeader
        step={6}
        title="HR Manager Review"
        description={`Run #${run.reference_no} — HR Manager must review and approve or return this payroll run.`}
      />

      {/* SoD badge */}
      {sodViolation && (
        <div className="flex items-start gap-3 bg-red-50 border border-red-200 rounded p-4">
          <ShieldAlert className="h-5 w-5 text-red-500 shrink-0 mt-0.5" />
          <div>
            <p className="text-sm font-semibold text-red-800">SoD Violation — SOD-005/006</p>
            <p className="text-xs text-red-700 mt-0.5">
              You initiated this payroll run. A different user must perform HR approval (Segregation of Duties policy).
            </p>
          </div>
        </div>
      )}

      {/* Summary card */}
      <div className="bg-white border border-neutral-200 rounded p-5">
        <h3 className="text-sm font-semibold text-neutral-800 mb-4">Run Summary</h3>
        <div className="grid grid-cols-2 sm:grid-cols-4 gap-4 text-sm">
          <div><p className="text-neutral-400 text-xs">Reference</p><p className="font-medium">{run.reference_no}</p></div>
          <div><p className="text-neutral-400 text-xs">Pay Date</p><p className="font-medium">{new Date(run.pay_date).toLocaleDateString('en-PH')}</p></div>
          <div><p className="text-neutral-400 text-xs">Employees</p><p className="font-medium">{run.total_employees}</p></div>
          <div><p className="text-neutral-400 text-xs">Net Pay Total</p><p className="font-medium">{formatCentavos(run.net_pay_total_centavos)}</p></div>
        </div>
      </div>

      {/* Prior approvals (if run was returned before) */}
      {approvals && approvals.filter(a => a.stage === 'HR_REVIEW').length > 0 && (
        <div className="bg-amber-50 border border-amber-200 rounded p-4 space-y-2">
          <p className="text-xs font-semibold text-amber-800 font-medium">Prior HR Review Actions</p>
          {approvals.filter(a => a.stage === 'HR_REVIEW').map(apr => (
            <div key={apr.id} className="text-xs text-amber-700">
              <strong>{apr.actor?.name ?? `User #${apr.actor_id}`}</strong> {apr.action === 'RETURNED' ? 'returned' : 'approved'} on {new Date(apr.acted_at).toLocaleString('en-PH')}
              {apr.comments && <p className="mt-0.5 text-amber-600 italic">"{apr.comments}"</p>}
            </div>
          ))}
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
                onChange={() => setChecked(prev => ({ ...prev, [i]: !prev[i] }))}
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
          onChange={e => setComments(e.target.value)}
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
            onChange={e => setReturnReason(e.target.value)}
            placeholder="Explain why the run is being returned for rework…"
            className="w-full border border-red-300 rounded px-3 py-2 text-sm resize-none focus:ring-2 focus:ring-red-500 outline-none"
          />
        </div>
      )}

      {/* Actions */}
      <div className="flex items-center justify-between pt-4 border-t border-neutral-100">
        <button
          type="button"
          onClick={() => {
            if (action !== 'RETURNED') {
              setAction('RETURNED')
              return
            }
            if (!returnReason.trim()) {
              toast.error('Please provide a return reason before confirming.')
              return
            }
            void handleAction('RETURNED')
          }}
          disabled={hrApprove.isPending}
          className="flex items-center gap-2 px-5 py-2 border border-neutral-300 text-neutral-700 hover:bg-neutral-50 text-sm font-medium rounded transition-colors"
        >
          <RotateCcw className="h-4 w-4" />
          {action === 'RETURNED' ? 'Confirm Return' : 'Return for Rework'}
        </button>

        <button
          type="button"
          onClick={() => handleAction('APPROVED')}
          disabled={hrApprove.isPending || !allChecked || sodViolation || run.status !== 'SUBMITTED'}
          className="flex items-center gap-2 px-6 py-2 bg-green-600 hover:bg-green-700 disabled:opacity-50 disabled:cursor-not-allowed text-white text-sm font-medium rounded transition-colors"
        >
          {hrApprove.isPending
            ? <><Loader2 className="h-4 w-4 animate-spin" /> Processing…</>
            : <><ThumbsUp className="h-4 w-4" /> Approve <ArrowRight className="h-4 w-4" /></>
          }
        </button>
      </div>
    </div>
  )
}
