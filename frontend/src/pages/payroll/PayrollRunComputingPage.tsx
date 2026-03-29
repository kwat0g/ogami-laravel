/**
 * Step 4 — Computation in Progress
 * Polls /progress every 2 s while status === PROCESSING.
 * Shows per-department progress, final totals once COMPUTED.
 * Navigates to Step 5 (Review) when done.
 */
import { useNavigate, useParams } from 'react-router-dom'
import { toast } from 'sonner'
import { Loader2, CheckCircle, XCircle, ArrowRight, RefreshCw, PlayCircle, Ban } from 'lucide-react'
import {
  usePayrollRun,
  useComputationProgress,
  useBeginComputation,
  useCancelPayrollRun,
} from '@/hooks/usePayroll'
import { WizardStepHeader } from '@/components/payroll/WizardStepHeader'
import ConfirmDialog from '@/components/ui/ConfirmDialog'
import ConfirmDestructiveDialog from '@/components/ui/ConfirmDestructiveDialog'
import { firstErrorMessage } from '@/lib/errorHandler'

function formatCentavos(c: number | null | undefined): string {
  if (c == null) return '—'
  return new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' }).format(c / 100)
}

export default function PayrollRunComputingPage() {
  const { ulid: id } = useParams<{ ulid: string }>()
  const runId = id ?? null
  const navigate = useNavigate()

  const { data: run } = usePayrollRun(runId)
  const { data: progress } = useComputationProgress(runId)
  const beginComputation = useBeginComputation(runId)
  const cancelRun = useCancelPayrollRun(runId)

  const status = run?.status ?? progress?.status

  // Note: Manual navigation only - user must click "Proceed to Review"

  async function handleBeginComputation() {
    try {
      await beginComputation.mutateAsync()
      toast.success('Computation started successfully.')
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

  if (!run) {
    return (
      <div className="flex items-center gap-2 text-sm text-neutral-500">
        <Loader2 className="h-4 w-4 animate-spin" /> Loading…
      </div>
    )
  }

  const isPreRunChecked = status === 'PRE_RUN_CHECKED'
  const isProcessing = status === 'PROCESSING' || status === 'processing'
  const isFailed = status === 'FAILED' || status === 'failed'
  const isComputed = status === 'COMPUTED' || status === 'completed'

  const pct = progress?.percent_complete ?? 0

  return (
    <div className="max-w-5xl mx-auto space-y-6">
      <WizardStepHeader
        step={4}
        title="Payroll Computation"
        description={`Run #${run.reference_no} — ${isPreRunChecked ? 'All pre-run checks passed. Ready to begin computation.' : 'Employees are being processed. This may take a few minutes.'}`}
      />

      {/* Begin Computation — shown when checks passed but computation not yet started */}
      {isPreRunChecked && (
        <div className="bg-green-50 border border-green-200 rounded p-6 space-y-4">
          <div className="flex items-center gap-3">
            <CheckCircle className="h-7 w-7 text-green-500 shrink-0" />
            <div>
              <p className="text-base font-semibold text-neutral-900">All Pre-Run Checks Passed</p>
              <p className="text-sm text-neutral-500 mt-0.5">
                The run is ready to compute. Click below to start the payroll batch.
              </p>
            </div>
          </div>
          <ConfirmDialog
            title="Begin Payroll Computation?"
            description={`This will start the payroll computation for ${run.total_employees} employees. The process cannot be interrupted once started.`}
            confirmLabel="Begin Computation"
            onConfirm={handleBeginComputation}
          >
            <button
              type="button"
              disabled={beginComputation.isPending}
              className="flex items-center gap-2 px-6 py-2.5 bg-neutral-900 hover:bg-neutral-800 disabled:opacity-50 disabled:cursor-not-allowed text-white text-sm font-semibold rounded transition-colors"
            >
              {beginComputation.isPending ? (
                <>
                  <Loader2 className="h-4 w-4 animate-spin" /> Starting…
                </>
              ) : (
                <>
                  <PlayCircle className="h-4 w-4" /> Begin Computation
                </>
              )}
            </button>
          </ConfirmDialog>
        </div>
      )}

      {/* Status card */}
      <div
        className={`rounded border p-6 space-y-4 ${
          isFailed
            ? 'bg-red-50 border-red-200'
            : isComputed
              ? 'bg-green-50 border-green-200'
              : 'bg-neutral-50 border-neutral-200'
        }`}
      >
        {/* Status icon + message */}
        <div className="flex items-center gap-3">
          {isProcessing && <Loader2 className="h-7 w-7 text-neutral-500 animate-spin shrink-0" />}
          {isFailed && <XCircle className="h-7 w-7 text-red-500 shrink-0" />}
          {isComputed && <CheckCircle className="h-7 w-7 text-green-500 shrink-0" />}
          <div>
            <p className="text-base font-semibold text-neutral-900">
              {isProcessing && 'Computing payroll…'}
              {isFailed && 'Computation Failed'}
              {isComputed && 'Computation Complete!'}
              {!isProcessing && !isFailed && !isComputed && !isPreRunChecked && 'Waiting to start…'}
            </p>
            {progress?.current_department && isProcessing && (
              <p className="text-sm text-neutral-500 mt-0.5">
                Currently processing: <strong>{progress.current_department}</strong>
              </p>
            )}
          </div>
        </div>

        {/* Progress bar */}
        {isProcessing && (
          <div className="space-y-1">
            <div className="flex justify-between text-xs text-neutral-500">
              <span>
                {progress?.employees_processed ?? 0} of{' '}
                {progress?.total_employees ?? run.total_employees} employees
              </span>
              <span>{pct}%</span>
            </div>
            <div className="h-2 rounded bg-neutral-200 overflow-hidden">
              <div
                className="h-full bg-neutral-900 rounded transition-all duration-500"
                style={{ width: `${pct}%` }}
              />
            </div>
          </div>
        )}

        {/* Error message */}
        {isFailed && progress?.error && (
          <div className="bg-white border border-red-200 rounded px-4 py-3 text-sm text-red-700">
            {progress.error}
          </div>
        )}

        {/* Completion summary */}
        {isComputed && run && (
          <div className="grid grid-cols-3 gap-4 pt-2">
            <div className="text-center">
              <p className="text-lg font-semibold text-neutral-900">{run.total_employees}</p>
              <p className="text-xs text-neutral-500">Employees Processed</p>
            </div>
            <div className="text-center">
              <p className="text-lg font-semibold text-neutral-900">
                {formatCentavos(run.gross_pay_total_centavos)}
              </p>
              <p className="text-xs text-neutral-500">Total Gross</p>
            </div>
            <div className="text-center">
              <p className="text-lg font-semibold text-neutral-900">
                {formatCentavos(run.net_pay_total_centavos)}
              </p>
              <p className="text-xs text-neutral-500">Total Net Pay</p>
            </div>
          </div>
        )}
      </div>

      {/* Timing info */}
      {(progress?.started_at || run.computation_started_at) && (
        <p className="text-xs text-neutral-400">
          Started:{' '}
          {new Date(progress?.started_at ?? run.computation_started_at ?? '').toLocaleString(
            'en-PH',
          )}
          {(progress?.finished_at ?? run.computation_completed_at) &&
            ` · Finished: ${new Date(progress?.finished_at ?? run.computation_completed_at ?? '').toLocaleString('en-PH')}`}
        </p>
      )}

      {/* Navigation */}
      <div className="flex items-center justify-between pt-2 border-t border-neutral-100">
        <div className="flex items-center gap-2">
          {/* Cancel button available until submitted to accounting */}
          {![
            'SUBMITTED',
            'HR_APPROVED',
            'ACCTG_APPROVED',
            'APPROVED',
            'POSTED',
            'DISBURSED',
          ].includes(status || '') && (
            <ConfirmDestructiveDialog
              title="Cancel payroll run?"
              description="Cancelling will permanently stop this payroll run. All computation progress will be lost. This action cannot be undone."
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
        {isComputed && (
          <button
            type="button"
            onClick={() => navigate(`/payroll/runs/${runId}/review`)}
            className="flex items-center gap-2 px-6 py-2 bg-neutral-900 hover:bg-neutral-800 text-white text-sm font-medium rounded transition-colors"
          >
            Proceed to Review <ArrowRight className="h-4 w-4" />
          </button>
        )}
        {isFailed && (
          <button
            type="button"
            onClick={() => navigate(`/payroll/runs/${runId}/validate`)}
            className="flex items-center gap-2 px-6 py-2 bg-red-600 hover:bg-red-700 text-white text-sm font-medium rounded transition-colors"
          >
            <RefreshCw className="h-4 w-4" /> Back to Validation
          </button>
        )}
      </div>
    </div>
  )
}
