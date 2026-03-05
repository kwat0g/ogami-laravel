/**
 * Step 3 — Pre-Run Validation
 * Runs all 8 pre-run checks (PR-001 to PR-008) and displays results.
 * Auto-refreshes every 10 s so the user can fix issues and see them clear.
 * Blocking checks (BLOCK severity) prevent advancing to computation.
 */
import { useState } from 'react'
import { useNavigate, useParams } from 'react-router-dom'
import { toast } from 'sonner'
import {
  ArrowLeft, ArrowRight, CheckCircle, XCircle, AlertTriangle, Loader2, RefreshCw, Ban,
} from 'lucide-react'
import { usePayrollRun, usePreRunChecks, useAcknowledgePreRun, useCancelPayrollRun } from '@/hooks/usePayroll'
import type { PreRunCheckResult } from '@/types/payroll'
import { WizardStepHeader } from '@/components/payroll/WizardStepHeader'

function SeverityIcon({ check }: { check: PreRunCheckResult }) {
  if (check.status === 'pass')  return <CheckCircle  className="h-5 w-5 text-green-500 shrink-0" />
  if (check.status === 'block') return <XCircle      className="h-5 w-5 text-red-500 shrink-0" />
  return                               <AlertTriangle className="h-5 w-5 text-amber-500 shrink-0" />
}

function CheckRow({ check, warnAcked, onAck }: {
  check: PreRunCheckResult
  warnAcked: boolean
  onAck: () => void
}) {
  return (
    <div className={`flex items-start gap-3 p-3 rounded-lg border ${
      check.status === 'block' ? 'bg-red-50 border-red-200' :
      check.status === 'warn'  ? 'bg-amber-50 border-amber-200' :
      'bg-green-50 border-green-100'
    }`}>
      <SeverityIcon check={check} />
      <div className="flex-1 min-w-0">
        <div className="flex items-baseline justify-between gap-2">
          <p className="text-sm font-medium text-gray-800">{check.label}</p>
          <span className="text-xs font-mono text-gray-400 shrink-0">{check.code}</span>
        </div>
        <p className="text-xs text-gray-500 mt-0.5">{check.message}</p>
        {check.details?.employees && check.details.employees.length > 0 && (
          <div className="mt-2 border border-amber-100 rounded-lg divide-y divide-amber-50 max-h-32 overflow-y-auto">
            {check.details.employees.map(emp => (
              <div key={emp.employee_code} className="flex items-center gap-2 px-3 py-1.5">
                <span className="text-xs font-mono text-gray-400 shrink-0">{emp.employee_code}</span>
                <span className="text-xs text-gray-700">{emp.full_name}</span>
              </div>
            ))}
          </div>
        )}
        {check.status === 'warn' && (
          <label className="flex items-center gap-2 mt-2 cursor-pointer select-none">
            <input
              type="checkbox"
              checked={warnAcked}
              onChange={onAck}
              className="accent-amber-600"
            />
            <span className="text-xs text-amber-800">I acknowledge this warning and wish to proceed</span>
          </label>
        )}
      </div>
    </div>
  )
}

export default function PayrollRunValidatePage() {
  const { ulid: id } = useParams<{ ulid: string }>()
  const runId    = id ?? null
  const navigate = useNavigate()

  const { data: run }                           = usePayrollRun(runId)
  const { data: result, isFetching, refetch }   = usePreRunChecks(runId, true)
  const acknowledge                             = useAcknowledgePreRun(runId)
  const cancelRun                               = useCancelPayrollRun(runId)

  const [warnAcked, setWarnAcked]     = useState<Record<string, boolean>>({})
  const [confirmCancel, setConfirmCancel] = useState(false)

  const cancellableStatuses = [
    // v1.0 workflow statuses
    'DRAFT', 'SCOPE_SET', 'PRE_RUN_CHECKED', 'PROCESSING', 'COMPUTED',
    'REVIEW', 'SUBMITTED', 'HR_APPROVED', 'ACCTG_APPROVED', 'FAILED', 'RETURNED', 'REJECTED',
    // Legacy lowercase statuses
    'draft', 'locked', 'processing', 'completed',
  ]
  const canCancel = run ? cancellableStatuses.includes(run.status) : false

  const checks      = result?.checks ?? []
  const hasBlocker  = result?.has_blockers ?? false
  const warnChecks  = checks.filter(c => c.status === 'warn')
  const allWarnsAck = warnChecks.every(c => !!warnAcked[c.code])
  const canProceed  = !hasBlocker && allWarnsAck

  function toggleWarn(code: string) {
    setWarnAcked(prev => ({ ...prev, [code]: !prev[code] }))
  }

  async function handleAcknowledge() {
    const ackedWarnings = warnChecks.filter(c => warnAcked[c.code]).map(c => c.code)
    try {
      await acknowledge.mutateAsync(ackedWarnings)
      toast.success('Pre-run checks acknowledged. Proceed to computation.')
      navigate(`/payroll/runs/${runId}/compute`)
    } catch {
      toast.error('Failed to acknowledge pre-run checks.')
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

  return (
    <div className="max-w-3xl space-y-6">
      <button
        onClick={() => navigate(`/payroll/runs/${runId}/scope`)}
        className="flex items-center gap-1.5 text-sm text-gray-500 hover:text-gray-800 transition-colors"
      >
        <ArrowLeft className="h-4 w-4" /> Back to Scope
      </button>

      <WizardStepHeader
        step={3}
        title="Pre-Run Validation"
        description={`Run #${run.reference_no} — All 8 checks must pass (or warnings acknowledged) before computation begins.`}
      />

      {/* Refresh button + pass/fail summary */}
      <div className="flex items-center justify-between">
        <div className="flex items-center gap-3 text-sm">
          {result && (
            <>
              <span className="text-green-600 font-medium">{result.total_passed} passed</span>
              <span className="text-gray-300">|</span>
              {hasBlocker
                ? <span className="text-red-600 font-medium">Blockers found — fix required</span>
                : <span className="text-amber-600 font-medium">{warnChecks.length} warning{warnChecks.length !== 1 ? 's' : ''}</span>
              }
            </>
          )}
        </div>
        <button
          type="button"
          onClick={() => void refetch()}
          disabled={isFetching}
          className="flex items-center gap-1.5 text-xs text-gray-500 hover:text-gray-800 transition-colors"
        >
          <RefreshCw className={`h-3.5 w-3.5 ${isFetching ? 'animate-spin' : ''}`} />
          Refresh checks
        </button>
      </div>

      {/* Checks list */}
      {isFetching && !result ? (
        <div className="flex items-center gap-2 text-sm text-gray-500 py-8">
          <Loader2 className="h-5 w-5 animate-spin" />
          Running pre-run checks…
        </div>
      ) : (
        <div className="space-y-2">
          {checks.map(check => (
            <CheckRow
              key={check.code}
              check={check}
              warnAcked={!!warnAcked[check.code]}
              onAck={() => toggleWarn(check.code)}
            />
          ))}
        </div>
      )}

      {/* Auto-refresh notice */}
      <p className="text-xs text-gray-400">
        Checks auto-refresh every 10 seconds. Fix any blockers in their respective modules then refresh.
      </p>

      {/* Actions */}
      <div className="flex items-center justify-between pt-2 border-t border-gray-100">
        <div className="flex items-center gap-2">
          <button
            type="button"
            onClick={() => navigate(`/payroll/runs/${runId}/scope`)}
            className="flex items-center gap-1.5 px-4 py-2 text-sm text-gray-600 hover:text-gray-900 transition-colors"
          >
            <ArrowLeft className="h-4 w-4" /> Back to Scope
          </button>
          {canCancel && (
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
                  Confirm Cancel
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
          onClick={handleAcknowledge}
          disabled={!canProceed || acknowledge.isPending}
          title={hasBlocker ? 'Fix all blocking issues first.' : !allWarnsAck ? 'Acknowledge all warnings to continue.' : undefined}
          className="flex items-center gap-2 px-6 py-2 bg-blue-600 hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed text-white text-sm font-medium rounded-lg transition-colors"
        >
          {acknowledge.isPending
            ? <><Loader2 className="h-4 w-4 animate-spin" /> Acknowledging…</>
            : <>Proceed to Computation <ArrowRight className="h-4 w-4" /></>
          }
        </button>
      </div>
    </div>
  )
}
