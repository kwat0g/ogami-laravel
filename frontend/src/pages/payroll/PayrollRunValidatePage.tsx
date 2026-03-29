/**
 * Step 3 — Pre-Run Validation
 * Runs all pre-run checks (PR-001 to PR-010) and displays results.
 * Auto-refreshes every 10 s so the user can fix issues and see them clear.
 * Blocking checks (BLOCK severity) prevent advancing to computation.
 */
import { useState } from 'react'
import { useNavigate, useParams } from 'react-router-dom'
import { toast } from 'sonner'
import {
  ArrowLeft,
  ArrowRight,
  CheckCircle,
  XCircle,
  AlertTriangle,
  Loader2,
  RefreshCw,
  Ban,
} from 'lucide-react'
import {
  usePayrollRun,
  usePreRunChecks,
  useAcknowledgePreRun,
  useCancelPayrollRun,
} from '@/hooks/usePayroll'
import type { PreRunCheckResult } from '@/types/payroll'
import { WizardStepHeader } from '@/components/payroll/WizardStepHeader'
import ConfirmDialog from '@/components/ui/ConfirmDialog'
import ConfirmDestructiveDialog from '@/components/ui/ConfirmDestructiveDialog'
import { firstErrorMessage } from '@/lib/errorHandler'

function SeverityIcon({ check }: { check: PreRunCheckResult }) {
  if (check.status === 'pass') return <CheckCircle className="h-5 w-5 text-green-500 shrink-0" />
  if (check.status === 'block') return <XCircle className="h-5 w-5 text-red-500 shrink-0" />
  return <AlertTriangle className="h-5 w-5 text-amber-500 shrink-0" />
}

function CheckRow({
  check,
  warnAcked,
  onAck,
}: {
  check: PreRunCheckResult
  warnAcked: boolean
  onAck: () => void
}) {
  return (
    <div
      className={`flex items-start gap-3 p-3 rounded border ${
        check.status === 'block'
          ? 'bg-red-50 border-red-200'
          : check.status === 'warn'
            ? 'bg-amber-50 border-amber-200'
            : 'bg-green-50 border-green-100'
      }`}
    >
      <SeverityIcon check={check} />
      <div className="flex-1 min-w-0">
        <div className="flex items-baseline justify-between gap-2">
          <p className="text-sm font-medium text-neutral-800">{check.label}</p>
          <span className="text-xs font-mono text-neutral-400 shrink-0">{check.code}</span>
        </div>
        <p className="text-xs text-neutral-500 mt-0.5">{check.message}</p>
        {check.details?.employees && check.details.employees.length > 0 && (
          <div className="mt-2 border border-amber-100 rounded divide-y divide-amber-50 max-h-32 overflow-y-auto">
            {check.details.employees.map((emp) => (
              <div key={emp.employee_code} className="flex items-center gap-2 px-3 py-1.5">
                <span className="text-xs font-mono text-neutral-400 shrink-0">
                  {emp.employee_code}
                </span>
                <span className="text-xs text-neutral-700">{emp.full_name}</span>
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
              className="accent-neutral-700"
            />
            <span className="text-xs text-amber-800">
              I acknowledge this warning and wish to proceed
            </span>
          </label>
        )}
      </div>
    </div>
  )
}

export default function PayrollRunValidatePage() {
  const { ulid: id } = useParams<{ ulid: string }>()
  const runId = id ?? null
  const navigate = useNavigate()

  const { data: run } = usePayrollRun(runId)
  const { data: result, isFetching, refetch } = usePreRunChecks(runId, true)
  const acknowledge = useAcknowledgePreRun(runId)
  const cancelRun = useCancelPayrollRun(runId)

  const [warnAcked, setWarnAcked] = useState<Record<string, boolean>>({})

  const cancellableStatuses = [
    // v1.0 workflow statuses
    'DRAFT',
    'SCOPE_SET',
    'PRE_RUN_CHECKED',
    'PROCESSING',
    'COMPUTED',
    'REVIEW',
    'SUBMITTED',
    'HR_APPROVED',
    'ACCTG_APPROVED',
    'FAILED',
    'RETURNED',
    'REJECTED',
    // Legacy lowercase statuses
    'draft',
    'locked',
    'processing',
    'completed',
  ]
  const canCancel = run ? cancellableStatuses.includes(run.status) : false

  const checks = result?.checks ?? []
  const hasBlocker = result?.has_blockers ?? false
  const warnChecks = checks.filter((c) => c.status === 'warn')
  const allWarnsAck = warnChecks.every((c) => !!warnAcked[c.code])
  const canProceed = !hasBlocker && allWarnsAck

  function toggleWarn(code: string) {
    setWarnAcked((prev) => ({ ...prev, [code]: !prev[code] }))
  }

  // ── Validation for acknowledgment ─────────────────────────────────────────
  function validateAcknowledge(): boolean {
    if (hasBlocker) {
      toast.error('Please fix all blocking issues before proceeding.')
      return false
    }
    if (!allWarnsAck && warnChecks.length > 0) {
      toast.error('Please acknowledge all warnings to continue.')
      return false
    }
    return true
  }

  async function handleAcknowledge() {
    if (!validateAcknowledge()) return
    const ackedWarnings = warnChecks.filter((c) => warnAcked[c.code]).map((c) => c.code)
    try {
      await acknowledge.mutateAsync(ackedWarnings)
      toast.success('Pre-run checks acknowledged. Proceeding to computation.')
      navigate(`/payroll/runs/${runId}/compute`)
    } catch (err) {
      toast.error(firstErrorMessage(err))
    }
  }

  async function handleCancel() {
    try {
      await cancelRun.mutateAsync()
      toast.success('Payroll run cancelled.')
      navigate('/payroll/runs')
    } catch (err) {
      toast.error(firstErrorMessage(err))
    }
  }

  if (!run) return null

  // Only show back button when in DRAFT status
  const canGoBack = run.status === 'DRAFT' || run.status === 'draft'

  return (
    <div className="max-w-5xl mx-auto space-y-6">
      {canGoBack && (
        <button
          onClick={() => navigate(`/payroll/runs/${runId}/scope`)}
          className="flex items-center gap-1.5 text-sm text-neutral-500 hover:text-neutral-800 transition-colors"
        >
          <ArrowLeft className="h-4 w-4" /> Back to Scope
        </button>
      )}

      <WizardStepHeader
        step={3}
        title="Pre-Run Validation"
        description={`Run #${run.reference_no} — All checks must pass (or warnings acknowledged) before computation begins.`}
      />

      {/* Refresh button + pass/fail summary */}
      <div className="flex items-center justify-between">
        <div className="flex items-center gap-3 text-sm">
          {result && (
            <>
              <span className="text-green-600 font-medium">{result.total_passed} passed</span>
              <span className="text-neutral-300">|</span>
              {hasBlocker ? (
                <span className="text-red-600 font-medium">Blockers found — fix required</span>
              ) : (
                <span className="text-amber-600 font-medium">
                  {warnChecks.length} warning{warnChecks.length !== 1 ? 's' : ''}
                </span>
              )}
            </>
          )}
        </div>
        <button
          type="button"
          onClick={() => void refetch()}
          disabled={isFetching}
          className="flex items-center gap-1.5 text-xs text-neutral-500 hover:text-neutral-800 transition-colors"
        >
          <RefreshCw className={`h-3.5 w-3.5 ${isFetching ? 'animate-spin' : ''}`} />
          Refresh checks
        </button>
      </div>

      {/* Checks list */}
      {isFetching && !result ? (
        <div className="flex items-center gap-2 text-sm text-neutral-500 py-8">
          <Loader2 className="h-5 w-5 animate-spin" />
          Running pre-run checks…
        </div>
      ) : (
        <div className="space-y-2">
          {checks.map((check) => (
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
      <p className="text-xs text-neutral-400">
        Checks auto-refresh every 10 seconds. Fix any blockers in their respective modules then
        refresh.
      </p>

      {/* Actions */}
      <div className="flex items-center justify-between pt-2 border-t border-neutral-100">
        <div className="flex items-center gap-2">
          <button
            type="button"
            onClick={() => navigate(`/payroll/runs/${runId}/scope`)}
            className="flex items-center gap-1.5 px-4 py-2 text-sm text-neutral-600 hover:text-neutral-900 transition-colors"
          >
            <ArrowLeft className="h-4 w-4" /> Back to Scope
          </button>
          {canCancel && (
            <ConfirmDestructiveDialog
              title="Cancel payroll run?"
              description="Cancelling will permanently stop this payroll run. This action cannot be undone."
              confirmWord="CANCEL"
              confirmLabel="Cancel Run"
              onConfirm={handleCancel}
            >
              <button
                type="button"
                disabled={cancelRun.isPending}
                className="flex items-center gap-1.5 px-3 py-2 text-sm text-red-600 hover:text-red-800 border border-red-200 hover:border-red-400 rounded transition-colors disabled:opacity-50"
              >
                {cancelRun.isPending ? <Loader2 className="h-4 w-4 animate-spin" /> : <Ban className="h-4 w-4" />}
                Cancel Run
              </button>
            </ConfirmDestructiveDialog>
          )}
        </div>

        <ConfirmDialog
          title="Proceed to Computation?"
          description={
            hasBlocker
              ? 'Cannot proceed - blocking issues must be fixed first.'
              : !allWarnsAck && warnChecks.length > 0
                ? 'Cannot proceed - all warnings must be acknowledged first.'
                : 'This will begin the payroll computation process for all in-scope employees. Proceed?'
          }
          confirmLabel="Proceed to Computation"
          onConfirm={handleAcknowledge}
        >
          <button
            type="button"
            disabled={!canProceed || acknowledge.isPending}
            title={
              hasBlocker
                ? 'Fix all blocking issues first.'
                : !allWarnsAck
                  ? 'Acknowledge all warnings to continue.'
                  : undefined
            }
            className="flex items-center gap-2 px-6 py-2 bg-neutral-900 hover:bg-neutral-800 disabled:opacity-50 disabled:cursor-not-allowed text-white text-sm font-medium rounded transition-colors"
          >
            {acknowledge.isPending ? (
              <>
                <Loader2 className="h-4 w-4 animate-spin" /> Acknowledging…
              </>
            ) : (
              <>
                Proceed to Computation <ArrowRight className="h-4 w-4" />
              </>
            )}
          </button>
        </ConfirmDialog>
      </div>
    </div>
  )
}
