/**
 * Step 3 (Draft) — Review & Begin Computation for a new payroll run.
 *
 * This is the point where the payroll run is first written to the database.
 * Clicking "Begin Computation" performs an atomic sequence:
 *
 *   1. POST /payroll/runs                   — create the run (DRAFT)
 *   2. PATCH /payroll/runs/{id}/scope       — save scope + exclusions (SCOPE_SET)
 *   3. POST /payroll/runs/{id}/pre-run-checks — run PR-001…PR-008 checks
 *   4a. Blockers found → cancel then archive the run, show errors, back to idle
 *   4b. Warnings only  → show ack checkboxes, wait for user confirmation
 *   4c. All pass       → proceed directly
 *   5. POST /payroll/runs/{id}/acknowledge  — transition to PRE_RUN_CHECKED
 *   6. POST /payroll/runs/{id}/compute      — dispatch batch jobs (PROCESSING)
 *   7. Navigate to /payroll/runs/{id}/compute (live progress view)
 *
 * On completion, the wizard context is cleared from sessionStorage.
 */
import { useEffect, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { toast } from 'sonner'
import {
  ArrowLeft, CheckCircle, XCircle, AlertTriangle, Loader2, PlayCircle, RefreshCw,
} from 'lucide-react'
import api from '@/lib/api'
import { usePayrollWizard } from '@/contexts/PayrollWizardContext'
import type { PreRunCheckResult, PreRunValidationResult, PayrollRun } from '@/types/payroll'
import { WizardStepHeader } from '@/components/payroll/WizardStepHeader'
import { RUN_TYPE_LABELS } from '@/types/payroll'

// ── Phase machine ─────────────────────────────────────────────────────────────

type Phase =
  | { kind: 'idle' }
  | { kind: 'running'; label: string }
  | { kind: 'blocked'; checks: PreRunCheckResult[] }
  | { kind: 'ack'; runId: string; checks: PreRunCheckResult[]; acked: Record<string, boolean> }

// ── Helper components ─────────────────────────────────────────────────────────

function SeverityIcon({ check }: { check: PreRunCheckResult }) {
  if (check.status === 'pass')  return <CheckCircle  className="h-5 w-5 text-green-500 shrink-0" />
  if (check.status === 'block') return <XCircle      className="h-5 w-5 text-red-500 shrink-0" />
  return                               <AlertTriangle className="h-5 w-5 text-amber-500 shrink-0" />
}

function CheckRow({ check, acked, onAck }: {
  check: PreRunCheckResult
  acked: boolean
  onAck: () => void
}) {
  return (
    <div className={`flex items-start gap-3 p-3 rounded border ${
      check.status === 'block' ? 'bg-red-50 border-red-200' :
      check.status === 'warn'  ? 'bg-amber-50 border-amber-200' :
      'bg-green-50 border-green-100'
    }`}>
      <SeverityIcon check={check} />
      <div className="flex-1 min-w-0">
        <div className="flex items-baseline justify-between gap-2">
          <p className="text-sm font-medium text-neutral-800">{check.label}</p>
          <span className="text-xs font-mono text-neutral-400 shrink-0">{check.code}</span>
        </div>
        <p className="text-xs text-neutral-500 mt-0.5">{check.message}</p>
        {check.status === 'warn' && (
          <label className="flex items-center gap-2 mt-2 cursor-pointer select-none">
            <input
              type="checkbox"
              checked={acked}
              onChange={onAck}
              className="accent-neutral-700"
            />
            <span className="text-xs text-amber-800">I acknowledge this warning and wish to proceed</span>
          </label>
        )}
      </div>
    </div>
  )
}

// ── Main page ─────────────────────────────────────────────────────────────────

export default function PayrollRunDraftValidatePage() {
  const navigate = useNavigate()
  const { state, clear } = usePayrollWizard()
  const { step1, step2 } = state

  const [phase, setPhase] = useState<Phase>({ kind: 'idle' })
  const [isNavigating, setIsNavigating] = useState(false)

  // Guard: redirect back if wizard data is missing
  useEffect(() => {
    if (isNavigating) return  // Don't redirect if we're already navigating away
    if (!step1) { navigate('/payroll/runs/new', { replace: true }); return }
    if (!step2) { navigate('/payroll/runs/new/scope', { replace: true }); return }
  }, [step1, step2, navigate, isNavigating])

  if (!step1 || !step2) return null   // waiting for redirect
  // Non-null aliases — TypeScript cannot narrow past the early return above
  const s1 = step1
  const s2 = step2

  // ── Helpers ───────────────────────────────────────────────────────────────

  async function doAcknowledgeAndCompute(runId: string, warnCodes: string[]) {
    setPhase({ kind: 'running', label: 'Acknowledging checks…' })
    await api.post(`/payroll/runs/${runId}/acknowledge`, {
      acknowledged_warnings: warnCodes,
    })
    setPhase({ kind: 'running', label: 'Dispatching computation jobs…' })
    await api.post(`/payroll/runs/${runId}/compute`)
  }

  async function cancelAndArchiveRun(runId: string) {
    // Archive is allowed only for cancelled/rejected runs.
    await api.patch(`/payroll/runs/${runId}/cancel`)
    await api.delete(`/payroll/runs/${runId}`)
  }

  // ── Main commit sequence ──────────────────────────────────────────────────

  async function handleBeginComputation() {
    setPhase({ kind: 'running', label: 'Creating payroll run…' })
    let runId: string | null = null

    try {
      // 1 — Create run record
      const createRes = await api.post<{ data: PayrollRun }>('/payroll/runs', {
        run_type:      s1.run_type,
        pay_period_id: s1.pay_period_id,
        cutoff_start:  s1.cutoff_start,
        cutoff_end:    s1.cutoff_end,
        pay_date:      s1.pay_date,
        notes:         s1.notes,
      })
      runId = createRes.data.data.ulid

      // 2 — Confirm scope
      setPhase({ kind: 'running', label: 'Saving employee scope…' })
      await api.patch(`/payroll/runs/${runId}/scope`, {
        departments:           s2.departments.length ? s2.departments : undefined,
        employment_types:      s2.employment_types,
        include_unpaid_leave:  s2.include_unpaid_leave,
        include_probation_end: s2.include_probation_end,
        exclusions:            s2.exclusions.map(e => ({
          employee_id: e.employee_id,
          reason:      e.reason,
        })),
      })

      // 3 — Pre-run checks
      setPhase({ kind: 'running', label: 'Running pre-run checks…' })
      const checksRes = await api.post<{ data: PreRunValidationResult }>(
        `/payroll/runs/${runId}/pre-run-checks`,
      )
      const checksData = checksRes.data.data

      // 4a — Blockers: rollback and show errors
      if (checksData.has_blockers) {
        try { await cancelAndArchiveRun(runId) } catch { /* ignore cleanup error */ }
        setPhase({ kind: 'blocked', checks: checksData.checks })
        return
      }

      const warnings = checksData.checks.filter(c => c.status === 'warn')

      // 4c — All pass, no warnings → proceed immediately
      if (warnings.length === 0) {
        await doAcknowledgeAndCompute(runId, [])
        setIsNavigating(true)
        clear()
        navigate(`/payroll/runs/${runId}/compute`, { replace: true })
        return
      }

      // 4b — Warnings need acknowledgment
      setPhase({ kind: 'ack', runId, checks: checksData.checks, acked: {} })

    } catch (err) {
      // Clean up partially-created run
      if (runId) {
        try { await cancelAndArchiveRun(runId) } catch { /* ignore cleanup error */ }
      }
      setPhase({ kind: 'idle' })
      const msg = (err as { response?: { data?: { message?: string } } })?.response?.data?.message
      toast.error(msg ?? 'Failed to begin computation. Please try again.')
    }
  }

  // ── Acknowledgment submit ─────────────────────────────────────────────────

  async function handleProceedFromAck() {
    if (phase.kind !== 'ack') return

    const warnings = phase.checks.filter(c => c.status === 'warn')
    const allAcked = warnings.every(c => !!phase.acked[c.code])
    if (!allAcked) {
      toast.error('Acknowledge all warnings before proceeding.')
      return
    }

    try {
      await doAcknowledgeAndCompute(phase.runId, warnings.map(c => c.code))
      setIsNavigating(true)
      clear()
      navigate(`/payroll/runs/${phase.runId}/compute`, { replace: true })
    } catch {
      toast.error('Failed to start computation.')
      setPhase({ kind: 'idle' })
    }
  }

  function toggleAck(code: string) {
    if (phase.kind !== 'ack') return
    setPhase({ ...phase, acked: { ...phase.acked, [code]: !phase.acked[code] } })
  }

  // ─────────────────────────────────────────────────────────────────────────

  const isRunning = phase.kind === 'running'
  const runLabel  = RUN_TYPE_LABELS[s1.run_type] ?? s1.run_type

  // Helper to format snake_case to proper label (e.g., 'project_based' → 'Project Based')
  const formatLabel = (value: string): string => {
    return value
      .split('_')
      .map(word => word.charAt(0).toUpperCase() + word.slice(1).toLowerCase())
      .join(' ')
  }

  return (
    <div className="max-w-3xl space-y-6">
      <button
        onClick={() => navigate('/payroll/runs/new/scope')}
        className="flex items-center gap-1.5 text-sm text-neutral-500 hover:text-neutral-800 transition-colors"
        disabled={isRunning}
      >
        <ArrowLeft className="h-4 w-4" /> Back to Scope
      </button>

      <WizardStepHeader
        step={3}
        title="Review & Begin Computation"
        description="The payroll run will be saved to the database when you click Begin Computation."
      />

      {/* Draft notice */}
      <div className="flex items-center gap-2 bg-amber-50 border border-amber-200 rounded px-4 py-2.5 text-xs text-amber-800">
        <span className="font-semibold">Not saved yet.</span>
        <span>Everything above is still in draft. Clicking <strong>Begin Computation</strong> creates the run and starts processing.</span>
      </div>

      {/* Run summary card */}
      <div className="bg-white border border-neutral-200 rounded p-5 space-y-4">
        <h3 className="text-sm font-semibold text-neutral-800">Run Summary</h3>
        <dl className="grid grid-cols-2 sm:grid-cols-3 gap-x-6 gap-y-3 text-sm">
          <div>
            <dt className="text-xs text-neutral-500 font-medium">Type</dt>
            <dd className="font-medium text-neutral-900 mt-0.5">{runLabel}</dd>
          </div>
          <div>
            <dt className="text-xs text-neutral-500 font-medium">Cutoff Start</dt>
            <dd className="font-medium text-neutral-900 mt-0.5">{s1.cutoff_start}</dd>
          </div>
          <div>
            <dt className="text-xs text-neutral-500 font-medium">Cutoff End</dt>
            <dd className="font-medium text-neutral-900 mt-0.5">{s1.cutoff_end}</dd>
          </div>
          <div>
            <dt className="text-xs text-neutral-500 font-medium">Pay Date</dt>
            <dd className="font-medium text-neutral-900 mt-0.5">{s1.pay_date}</dd>
          </div>
          <div>
            <dt className="text-xs text-neutral-500 font-medium">Employment Types</dt>
            <dd className="font-medium text-neutral-900 mt-0.5">
              {s2.employment_types.map(formatLabel).join(', ') || '—'}
            </dd>
          </div>
          <div>
            <dt className="text-xs text-neutral-500 font-medium">Exclusions</dt>
            <dd className="font-medium text-neutral-900 mt-0.5">
              {s2.exclusions.length > 0 ? `${s2.exclusions.length} employee(s)` : 'None'}
            </dd>
          </div>
          {s1.notes && (
            <div className="col-span-2 sm:col-span-3">
              <dt className="text-xs text-neutral-500 font-medium">Notes</dt>
              <dd className="text-neutral-700 mt-0.5">{s1.notes}</dd>
            </div>
          )}
        </dl>
      </div>

      {/* ── Blocked — show errors, run was rolled back ─────────────────────── */}
      {phase.kind === 'blocked' && (
        <div className="space-y-3">
          <div className="flex items-center gap-2 bg-red-50 border border-red-200 rounded px-4 py-3">
            <XCircle className="h-5 w-5 text-red-500 shrink-0" />
            <div>
              <p className="text-sm font-semibold text-red-800">Pre-Run Checks Failed</p>
              <p className="text-xs text-red-600 mt-0.5">
                The draft run was not saved. Fix the blocking issues below, then try again.
              </p>
            </div>
          </div>
          <div className="space-y-2">
            {phase.checks.filter(c => c.status !== 'pass').map(check => (
              <CheckRow key={check.code} check={check} acked={false} onAck={() => {}} />
            ))}
          </div>
          <button
            type="button"
            onClick={() => setPhase({ kind: 'idle' })}
            className="flex items-center gap-2 px-5 py-2 bg-neutral-100 hover:bg-neutral-200 text-neutral-700 text-sm font-medium rounded transition-colors"
          >
            <RefreshCw className="h-4 w-4" /> Try Again
          </button>
        </div>
      )}

      {/* ── Warning acknowledgment ─────────────────────────────────────────── */}
      {phase.kind === 'ack' && (
        <div className="space-y-3">
          <div className="flex items-center gap-2 bg-amber-50 border border-amber-200 rounded px-4 py-3">
            <AlertTriangle className="h-5 w-5 text-amber-500 shrink-0" />
            <div>
              <p className="text-sm font-semibold text-amber-800">Warnings Detected</p>
              <p className="text-xs text-amber-600 mt-0.5">
                Acknowledge all warnings below to proceed with computation.
              </p>
            </div>
          </div>
          <div className="space-y-2">
            {phase.checks.map(check => (
              <CheckRow
                key={check.code}
                check={check}
                acked={!!phase.acked[check.code]}
                onAck={() => toggleAck(check.code)}
              />
            ))}
          </div>
          <div className="flex items-center justify-between pt-2 border-t border-neutral-100">
            <button
              type="button"
              onClick={() => {
                // Cancel then archive the pending run and return to idle.
                void cancelAndArchiveRun(phase.runId)
                  .catch(() => {})
                  .finally(() => setPhase({ kind: 'idle' }))
              }}
              className="text-sm text-neutral-500 hover:text-neutral-800"
            >
              Cancel &amp; go back
            </button>
            <button
              type="button"
              onClick={() => void handleProceedFromAck()}
              disabled={phase.checks.filter(c => c.status === 'warn').some(c => !phase.acked[c.code])}
              className="flex items-center gap-2 px-6 py-2.5 bg-neutral-900 hover:bg-neutral-800 disabled:opacity-50 disabled:cursor-not-allowed text-white text-sm font-semibold rounded transition-colors"
            >
              <PlayCircle className="h-4 w-4" /> Acknowledge &amp; Begin Computation
            </button>
          </div>
        </div>
      )}

      {/* ── Idle / Running — main action button ────────────────────────────── */}
      {(phase.kind === 'idle' || phase.kind === 'running') && (
        <div className="flex items-center justify-between pt-2 border-t border-neutral-100">
          <button
            type="button"
            onClick={() => navigate('/payroll/runs/new/scope')}
            disabled={isRunning}
            className="flex items-center gap-1.5 px-4 py-2 text-sm text-neutral-600 hover:text-neutral-900 disabled:opacity-40 transition-colors"
          >
            <ArrowLeft className="h-4 w-4" /> Back to Scope
          </button>

          <button
            type="button"
            onClick={() => void handleBeginComputation()}
            disabled={isRunning}
            className="flex items-center gap-2 px-7 py-2.5 bg-neutral-900 hover:bg-neutral-800 disabled:opacity-50 disabled:cursor-not-allowed text-white text-sm font-semibold rounded transition-colors"
          >
            {isRunning ? (
              <><Loader2 className="h-4 w-4 animate-spin" /> {phase.label}</>
            ) : (
              <><PlayCircle className="h-4 w-4" /> Begin Computation</>
            )}
          </button>
        </div>
      )}
    </div>
  )
}
