/**
 * Step 6 — Accounting Manager Review
 * Accounting Manager reviews the GL journal entry preview and cash requirement summary.
 * Must check 3 items before approving. Can permanently reject the run.
 * SoD-007: Accounting approver ≠ initiator.
 *
 * HR Managers can view this page in read-only mode to track the approval status.
 */
import { useState } from 'react'
import { useNavigate, useParams } from 'react-router-dom'
import { toast } from 'sonner'
import { ArrowLeft, ArrowRight, ShieldAlert, ThumbsUp, XCircle, Loader2, Eye } from 'lucide-react'
import {
  usePayrollRun,
  useGlPreview,
  useAcctgApprove,
  usePayrollApprovals,
  usePayrollBreakdown,
} from '@/hooks/usePayroll'
import { WizardStepHeader } from '@/components/payroll/WizardStepHeader'
import { useAuth } from '@/hooks/useAuth'
import { useAuthStore } from '@/stores/authStore'

function formatPHP(n: number): string {
  return new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' }).format(n)
}

// Employee Breakdown Component
function EmployeeBreakdown({ runId }: { runId: string | null }) {
  const [page, setPage] = useState(1)
  const { data, isLoading } = usePayrollBreakdown(runId, { page, per_page: 10 })

  if (isLoading)
    return <div className="text-sm text-neutral-400 py-4">Loading employee breakdown…</div>
  if (!data?.data?.length)
    return <div className="text-sm text-neutral-400 py-4">No employee data available.</div>

  return (
    <div className="space-y-4">
      <div className="overflow-x-auto">
        <table className="w-full text-sm">
          <thead>
            <tr className="border-b border-neutral-200 text-xs text-neutral-500 uppercase">
              <th className="py-2 text-left">Employee</th>
              <th className="py-2 text-right">Gross Pay</th>
              <th className="py-2 text-right">SSS (EE)</th>
              <th className="py-2 text-right">PhilHealth (EE)</th>
              <th className="py-2 text-right">Pag-IBIG (EE)</th>
              <th className="py-2 text-right">Withholding Tax</th>
              <th className="py-2 text-right">Net Pay</th>
            </tr>
          </thead>
          <tbody>
            {data.data.map(
              (detail: {
                id: number
                employee?: { first_name: string; last_name: string; employee_code?: string }
                employee_id: number
                gross_pay_centavos?: number
                sss_ee_centavos?: number
                philhealth_ee_centavos?: number
                pagibig_ee_centavos?: number
                tax_withheld_centavos?: number
                net_pay_centavos?: number
              }) => (
                <tr key={detail.id} className="border-b border-neutral-100">
                  <td className="py-2">
                    <p className="font-medium text-neutral-800">
                      {detail.employee
                        ? `${detail.employee.first_name} ${detail.employee.last_name}`
                        : `#${detail.employee_id}`}
                    </p>
                    <p className="text-xs text-neutral-400">{detail.employee?.employee_code}</p>
                  </td>
                  <td className="py-2 text-right">
                    {formatPHP((detail.gross_pay_centavos ?? 0) / 100)}
                  </td>
                  <td className="py-2 text-right">
                    {formatPHP((detail.sss_ee_centavos ?? 0) / 100)}
                  </td>
                  <td className="py-2 text-right">
                    {formatPHP((detail.philhealth_ee_centavos ?? 0) / 100)}
                  </td>
                  <td className="py-2 text-right">
                    {formatPHP((detail.pagibig_ee_centavos ?? 0) / 100)}
                  </td>
                  <td className="py-2 text-right">
                    {formatPHP((detail.withholding_tax_centavos ?? 0) / 100)}
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

      {data?.meta?.last_page > 1 && (
        <div className="flex items-center justify-between text-sm text-neutral-500">
          <span>
            Page {data.meta?.current_page} of {data.meta?.last_page}
          </span>
          <div className="flex gap-2">
            <button
              type="button"
              disabled={page <= 1}
              onClick={() => setPage((p) => p - 1)}
              className="px-3 py-1 border border-neutral-300 rounded disabled:opacity-40 disabled:cursor-not-allowed"
            >
              ← Prev
            </button>
            <button
              type="button"
              disabled={page >= (data.meta?.last_page ?? 1)}
              onClick={() => setPage((p) => p + 1)}
              className="px-3 py-1 border border-neutral-300 rounded disabled:opacity-40 disabled:cursor-not-allowed"
            >
              Next →
            </button>
          </div>
        </div>
      )}
    </div>
  )
}

const CHECKLIST_ITEMS = [
  'I have reviewed the GL journal entry and confirmed account codes are correct.',
  'I have verified the cash requirement against available fund balance.',
  'I approve the disbursement to proceed to payroll bank processing.',
]

export default function PayrollRunAcctgReviewPage() {
  const { ulid: id } = useParams<{ ulid: string }>()
  const runId = id ?? null
  const navigate = useNavigate()

  const { data: run } = usePayrollRun(runId)
  const { data: gl } = useGlPreview(runId)
  const acctgApprove = useAcctgApprove(runId)
  const { data: approvals } = usePayrollApprovals(runId)
  const { user } = useAuth()
  const hasAcctgApprove = useAuthStore((s) => s.hasPermission('payroll.acctg_approve'))

  const [checked, setChecked] = useState<Record<number, boolean>>({})
  const [rejectionReason, setRejectionReason] = useState('')
  const [showRejectForm, setShowRejectForm] = useState(false)

  const allChecked = CHECKLIST_ITEMS.every((_, i) => !!checked[i])
  const sodViolation = user?.id === run?.initiated_by_id
  const isReadOnly = !hasAcctgApprove

  async function handleApprove() {
    if (!allChecked || sodViolation) return
    try {
      await acctgApprove.mutateAsync({
        action: 'APPROVED',
        checkboxes_checked: CHECKLIST_ITEMS.filter((_, i) => checked[i]),
      })
      toast.success('Accounting approval recorded. Forwarded to VP for final approval.')
      navigate(`/payroll/runs/${runId}/vp-review`) // After Accounting approval, go to VP review
    } catch (err: unknown) {
      const msg = (err as { response?: { data?: { message?: string } } })?.response?.data?.message
      toast.error(msg ?? 'Approval failed.')
    }
  }

  async function handleReject() {
    const reason = rejectionReason.trim()
    if (!reason) {
      toast.error('Rejection reason is required.')
      return
    }
    if (reason.length < 10) {
      toast.error('Rejection reason must be at least 10 characters.')
      return
    }
    try {
      await acctgApprove.mutateAsync({
        action: 'REJECTED',
        rejection_reason: reason,
      })
      toast.error('Payroll run rejected. The run must be restarted from Step 1.')
      navigate('/payroll/runs')
    } catch (err: unknown) {
      type ApiError = {
        response?: { data?: { message?: string; errors?: Record<string, string[]> } }
      }
      const data = (err as ApiError)?.response?.data
      const validationMsg = data?.errors ? Object.values(data.errors).flat()[0] : undefined
      toast.error(validationMsg ?? data?.message ?? 'Rejection failed.')
    }
  }

  if (!run) return null

  // Only show back button when in DRAFT status
  const canGoBack = run.status === 'DRAFT' || run.status === 'draft'

  return (
    <div className="max-w-5xl mx-auto space-y-6">
      {canGoBack && (
        <button
          onClick={() => navigate(`/payroll/runs/${runId}/review`)}
          className="flex items-center gap-1.5 text-sm text-neutral-500 hover:text-neutral-800 transition-colors"
        >
          <ArrowLeft className="h-4 w-4" /> Back to Review
        </button>
      )}

      <WizardStepHeader
        step={6}
        title={isReadOnly ? 'Accounting Review (View Only)' : 'Accounting Manager Review'}
        description={`Run #${run.reference_no} — ${isReadOnly ? 'View GL entries and approval status.' : 'Review GL entries, cash requirement, and provide final approval.'}`}
      />

      {/* Read-only badge for HR Manager view */}
      {isReadOnly && (
        <div className="flex items-start gap-3 bg-neutral-50 border border-neutral-200 rounded p-4">
          <Eye className="h-5 w-5 text-neutral-500 shrink-0 mt-0.5" />
          <div>
            <p className="text-sm font-semibold text-neutral-800">View Only Mode</p>
            <p className="text-xs text-neutral-800 mt-0.5">
              You can view the GL preview and approval status, but only an Accounting Manager can
              approve this payroll run.
            </p>
          </div>
        </div>
      )}

      {/* SoD badge */}
      {sodViolation && (
        <div className="flex items-start gap-3 bg-red-50 border border-red-200 rounded p-4">
          <ShieldAlert className="h-5 w-5 text-red-500 shrink-0 mt-0.5" />
          <div>
            <p className="text-sm font-semibold text-red-800">SoD Violation — SOD-007</p>
            <p className="text-xs text-red-700 mt-0.5">
              You initiated this payroll run. A different user must perform Accounting approval
              (Segregation of Duties policy).
            </p>
          </div>
        </div>
      )}

      {/* GL Preview */}
      {gl && (
        <div className="bg-white border border-neutral-200 rounded overflow-hidden">
          <div className="px-5 py-4 border-b border-neutral-100">
            <h3 className="text-sm font-semibold text-neutral-800">Journal Entry Preview</h3>
            <p className="text-xs text-neutral-500 mt-0.5">
              This entry will be posted to the General Ledger upon disbursement.
            </p>
          </div>

          <div className="grid grid-cols-1 sm:grid-cols-2 divide-y sm:divide-y-0 sm:divide-x divide-neutral-100">
            {/* Debits */}
            <div className="p-5">
              <p className="text-xs font-semibold text-neutral-500 font-medium mb-3">Debit</p>
              <table className="w-full text-sm">
                <tbody>
                  {(gl.debits ?? []).map((entry, i) => (
                    <tr key={i} className="border-b border-neutral-50">
                      <td className="py-1.5 text-neutral-500 font-mono text-xs">{entry.account}</td>
                      <td className="py-1.5 text-neutral-700 pl-3">{entry.description}</td>
                      <td className="py-1.5 text-right font-medium text-neutral-900">
                        {formatPHP(entry.amount)}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>

            {/* Credits */}
            <div className="p-5">
              <p className="text-xs font-semibold text-neutral-500 font-medium mb-3">Credit</p>
              <table className="w-full text-sm">
                <tbody>
                  {(gl.credits ?? []).map((entry, i) => (
                    <tr key={i} className="border-b border-neutral-50">
                      <td className="py-1.5 text-neutral-500 font-mono text-xs">{entry.account}</td>
                      <td className="py-1.5 text-neutral-700 pl-3">{entry.description}</td>
                      <td className="py-1.5 text-right font-medium text-neutral-900">
                        {formatPHP(entry.amount)}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </div>

          {/* Cash requirement summary */}
          <div className="bg-neutral-50 px-5 py-3 border-t border-neutral-100">
            <div className="flex items-center justify-between text-sm">
              <span className="text-blue-900 font-medium">Total Cash Outflow Required</span>
              <span className="text-blue-900 font-bold text-base">
                {formatPHP(gl.total_cash_outflow)}
              </span>
            </div>
            <div className="grid grid-cols-2 sm:grid-cols-4 gap-3 mt-2 text-xs text-neutral-800">
              <span>Net Pay: {formatPHP(gl.total_net_pay)}</span>
              <span>SSS: {formatPHP(gl.total_sss_ee + gl.total_sss_er)}</span>
              <span>PhilHealth: {formatPHP(gl.total_philhealth_ee + gl.total_philhealth_er)}</span>
              <span>Pag-IBIG: {formatPHP(gl.total_pagibig_ee + gl.total_pagibig_er)}</span>
              {gl.total_withholding_tax > 0 && (
                <span>WHT: {formatPHP(gl.total_withholding_tax)}</span>
              )}
            </div>
          </div>
        </div>
      )}

      {/* Employee Breakdown */}
      <div className="bg-white border border-neutral-200 rounded overflow-hidden">
        <div className="px-5 py-4 border-b border-neutral-100">
          <h3 className="text-sm font-semibold text-neutral-800">Employee Payroll Breakdown</h3>
          <p className="text-xs text-neutral-500 mt-0.5">
            Individual employee salary details and deductions.
          </p>
        </div>
        <div className="p-5">
          <EmployeeBreakdown runId={runId} />
        </div>
      </div>

      {/* Prior approval history */}
      {approvals && approvals.filter((a) => a.stage === 'ACCOUNTING').length > 0 && (
        <div className="bg-amber-50 border border-amber-200 rounded p-4 space-y-2">
          <p className="text-xs font-semibold text-amber-800 font-medium">
            Prior Accounting Review Actions
          </p>
          {approvals
            .filter((a) => a.stage === 'ACCOUNTING')
            .map((apr) => (
              <div key={apr.id} className="text-xs text-amber-700">
                <strong>{apr.actor?.name ?? `User #${apr.actor_id}`}</strong>{' '}
                {apr.action.toLowerCase()} on {new Date(apr.acted_at).toLocaleString('en-PH')}
                {apr.comments && <p className="mt-0.5 italic">"{apr.comments}"</p>}
              </div>
            ))}
        </div>
      )}

      {/* Checklist - only visible to Accounting Managers with approval permission */}
      {!sodViolation && !isReadOnly && (
        <div className="bg-white border border-neutral-200 rounded p-5 space-y-3">
          <h3 className="text-sm font-semibold text-neutral-800">Approval Checklist</h3>
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

      {/* Reject form */}
      {showRejectForm && (
        <div className="bg-red-50 border border-red-200 rounded p-5 space-y-3">
          <p className="text-sm font-semibold text-red-800">Permanent Rejection</p>
          <p className="text-xs text-red-700">
            This action permanently rejects the run. It must be restarted from Step 1. This cannot
            be undone.
          </p>
          <textarea
            rows={3}
            value={rejectionReason}
            onChange={(e) => setRejectionReason(e.target.value)}
            placeholder="Rejection reason (min. 10 characters, required)…"
            className="w-full border border-red-300 rounded px-3 py-2 text-sm resize-none focus:ring-2 focus:ring-red-500 outline-none"
          />
          <div className="flex gap-2">
            <button
              type="button"
              onClick={() => setShowRejectForm(false)}
              className="px-4 py-2 text-sm text-neutral-600 hover:text-neutral-900"
            >
              Cancel
            </button>
            <button
              type="button"
              onClick={handleReject}
              disabled={acctgApprove.isPending}
              className="px-4 py-2 bg-red-600 hover:bg-red-700 text-white text-sm font-medium rounded"
            >
              {acctgApprove.isPending ? 'Rejecting…' : 'Confirm Permanent Rejection'}
            </button>
          </div>
        </div>
      )}

      {/* Actions - hide if SoD violation */}
      {!sodViolation && (
        <div
          className={`flex items-center pt-4 border-t border-neutral-100 ${isReadOnly ? 'justify-end' : 'justify-between'}`}
        >
          {!isReadOnly && (
            <button
              type="button"
              onClick={() => setShowRejectForm(true)}
              disabled={acctgApprove.isPending || showRejectForm}
              className="flex items-center gap-2 px-5 py-2 border border-red-300 text-red-600 hover:bg-red-50 text-sm font-medium rounded transition-colors"
            >
              <XCircle className="h-4 w-4" /> Reject Run
            </button>
          )}

          {isReadOnly ? (
            <button
              type="button"
              onClick={() => navigate('/payroll/runs')}
              className="flex items-center gap-2 px-6 py-2 bg-neutral-600 hover:bg-neutral-700 text-white text-sm font-medium rounded transition-colors"
            >
              <ArrowLeft className="h-4 w-4" /> Back to Payroll Runs
            </button>
          ) : (
            <button
              type="button"
              onClick={handleApprove}
              disabled={
                acctgApprove.isPending ||
                !allChecked ||
                (run.status !== 'HR_APPROVED' && run.status !== 'SUBMITTED')
              }
              className="flex items-center gap-2 px-6 py-2 bg-green-600 hover:bg-green-700 disabled:opacity-50 disabled:cursor-not-allowed text-white text-sm font-medium rounded transition-colors"
            >
              {acctgApprove.isPending ? (
                <>
                  <Loader2 className="h-4 w-4 animate-spin" /> Processing…
                </>
              ) : (
                <>
                  <ThumbsUp className="h-4 w-4" /> Approve & Forward to VP{' '}
                  <ArrowRight className="h-4 w-4" />
                </>
              )}
            </button>
          )}
        </div>
      )}
    </div>
  )
}
