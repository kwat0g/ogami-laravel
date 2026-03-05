import { useEffect, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { z } from 'zod'
import { ArrowLeft, CalendarRange, AlertTriangle, Loader2, CheckCircle, XCircle, Ban } from 'lucide-react'
import { usePayPeriods, useRunDateConflictCheck } from '@/hooks/usePayroll'
import type { DateConflictCheck } from '@/hooks/usePayroll'
import { RUN_TYPE_LABELS, type PayrollRunType, type PayPeriod } from '@/types/payroll'
import { usePayrollWizard } from '@/contexts/PayrollWizardContext'

// ---------------------------------------------------------------------------
// Validation schema
// ---------------------------------------------------------------------------

const schema = z
  .object({
    run_type:      z.enum(['regular', 'thirteenth_month', 'adjustment', 'year_end_reconciliation', 'final_pay']).default('regular'),
    pay_period_id: z.number().optional(),
    cutoff_start:  z.string().min(1, 'Cutoff start is required'),
    cutoff_end:    z.string().min(1, 'Cutoff end is required'),
    pay_date:      z.string().min(1, 'Pay date is required'),
    notes:         z.string().max(1000).optional(),
  })
  .refine((d) => d.cutoff_end >= d.cutoff_start, {
    message: 'Cutoff end must be on or after cutoff start',
    path: ['cutoff_end'],
  })
  .refine((d) => d.pay_date >= d.cutoff_end, {
    message: 'Pay date must be on or after cutoff end',
    path: ['pay_date'],
  })

type FormValues = z.infer<typeof schema>

// ---------------------------------------------------------------------------
// Field helpers
// ---------------------------------------------------------------------------

function FieldError({ message }: { message?: string }) {
  if (!message) return null
  return <p className="mt-1 text-xs text-red-600">{message}</p>
}

function Label({ htmlFor, required, children }: { htmlFor: string; required?: boolean; children: React.ReactNode }) {
  return (
    <label htmlFor={htmlFor} className="block text-sm font-medium text-gray-700 mb-1">
      {children}
      {required && <span className="ml-1 text-red-500">*</span>}
    </label>
  )
}

// Run type hints
const RUN_TYPE_HINTS: Partial<Record<PayrollRunType, string>> = {
  thirteenth_month: '13th Month Run: Set cutoff to Jan 1–Dec 31 of the target year. No gov\'t contributions deducted. WHT applies only to amount exceeding ₱90,000 exemption.',
  adjustment: 'Adjustment Run: Used to correct prior payroll errors. Requires HR and Accounting approval.',
  year_end_reconciliation: 'Year-End Reconciliation: Reconciles annual taxable income and withholding tax per TRAIN law annualization.',
  final_pay: 'Final Pay: For separated employees. Includes last salary, pro-rated 13th month, unused SL/VL conversions.',
}

// ---------------------------------------------------------------------------
// Date conflict check panel
// ---------------------------------------------------------------------------

const CHECK_DESCRIPTIONS: Record<string, string> = {
  'PR-001': 'No overlapping payroll run exists for this period and run type',
  'PR-002': 'Cutoff start ≤ cutoff end ≤ pay date',
  'PR-003': 'At least one active employee exists in the system',
  'PR-004': 'An open pay period covers the requested cutoff range',
}

function ConflictCheckRow({ check }: { check: DateConflictCheck }) {
  const isPassed  = check.status === 'pass'
  const isBlock   = check.status === 'block'
  const isWarn    = check.status === 'warn'

  return (
    <div className={`flex items-start gap-3 rounded-lg px-3 py-2.5 border text-sm ${
      isBlock ? 'bg-red-50 border-red-200' :
      isWarn  ? 'bg-amber-50 border-amber-200' :
                'bg-green-50 border-green-100'
    }`}>
      {isPassed && <CheckCircle className="h-4 w-4 text-green-500 shrink-0 mt-0.5" />}
      {isBlock  && <XCircle     className="h-4 w-4 text-red-500 shrink-0 mt-0.5" />}
      {isWarn   && <AlertTriangle className="h-4 w-4 text-amber-500 shrink-0 mt-0.5" />}
      <div className="flex-1 min-w-0">
        <div className="flex items-baseline justify-between gap-2">
          <span className={`font-medium ${isBlock ? 'text-red-800' : isWarn ? 'text-amber-800' : 'text-green-800'}`}>
            {CHECK_DESCRIPTIONS[check.code] ?? check.label}
          </span>
          <span className="text-xs font-mono text-gray-400 shrink-0">{check.code}</span>
        </div>
        {check.message && !isPassed && (
          <p className={`text-xs mt-0.5 ${isBlock ? 'text-red-700' : 'text-amber-700'}`}>
            {check.message}
          </p>
        )}
      </div>
    </div>
  )
}

function ConflictCheckPanel({
  checking,
  checks,
  hasBlockers,
}: {
  checking: boolean
  checks: DateConflictCheck[]
  hasBlockers: boolean
}) {
  return (
    <div className="space-y-2">
      <div className="flex items-center justify-between">
        <p className="text-xs font-semibold text-gray-600 uppercase tracking-wide">Date Validation</p>
        {checking && (
          <span className="flex items-center gap-1.5 text-xs text-gray-400">
            <Loader2 className="h-3 w-3 animate-spin" /> Checking…
          </span>
        )}
      </div>

      {checking && checks.length === 0 ? (
        <div className="flex items-center gap-2 text-sm text-gray-400 py-2">
          <Loader2 className="h-4 w-4 animate-spin" />
          Checking for conflicts and validating dates…
        </div>
      ) : (
        <div className="space-y-1.5">
          {checks.map(c => <ConflictCheckRow key={c.code} check={c} />)}
        </div>
      )}

      {!checking && hasBlockers && (
        <div className="flex items-start gap-2 bg-red-100 border border-red-300 rounded-lg px-3 py-2.5 text-sm text-red-800">
          <Ban className="h-4 w-4 shrink-0 mt-0.5 text-red-600" />
          <span>
            <strong>Cannot proceed.</strong> Fix the issues above before creating this payroll run.
          </span>
        </div>
      )}

      {!checking && !hasBlockers && checks.length > 0 && (
        <div className="flex items-center gap-2 text-xs text-green-700">
          <CheckCircle className="h-3.5 w-3.5 text-green-500" />
          All validation checks passed — you may proceed.
        </div>
      )}
    </div>
  )
}

// ---------------------------------------------------------------------------
// Page — Step 1 of the 7-step Payroll Run Wizard
// NOTE: No API call is made here. Data is kept in sessionStorage via
// PayrollWizardContext and only written to the DB when the user clicks
// "Begin Computation" on the final wizard step (/payroll/runs/new/validate).
// ---------------------------------------------------------------------------

export default function CreatePayrollRunPage() {
  const navigate = useNavigate()
  const { state, setStep1 } = usePayrollWizard()

  // Fetch open pay periods for the dropdown
  const { data: payPeriods = [], isLoading: periodsLoading } = usePayPeriods('open')

  const {
    register,
    handleSubmit,
    setValue,
    watch,
    formState: { errors, isSubmitting },
  } = useForm<FormValues>({
    resolver:      zodResolver(schema),
    defaultValues: {
      run_type:      (state.step1?.run_type     ?? 'regular') as PayrollRunType,
      pay_period_id: state.step1?.pay_period_id,
      cutoff_start:  state.step1?.cutoff_start  ?? '',
      cutoff_end:    state.step1?.cutoff_end    ?? '',
      pay_date:      state.step1?.pay_date      ?? '',
      notes:         state.step1?.notes         ?? '',
    },
  })

  const cutoffStart = watch('cutoff_start')
  const cutoffEnd   = watch('cutoff_end')
  const payDate     = watch('pay_date')
  const runType     = watch('run_type') as PayrollRunType
  const hint        = RUN_TYPE_HINTS[runType]

  // Debounce the API check so it doesn't fire on every keypress.
  const [validationParams, setValidationParams] = useState<{
    cutoff_start: string; cutoff_end: string; pay_date: string; run_type: string
  } | null>(null)

  useEffect(() => {
    if (!cutoffStart || !cutoffEnd || !payDate) {
      setValidationParams(null)
      return
    }
    const timer = setTimeout(() => {
      setValidationParams({ cutoff_start: cutoffStart, cutoff_end: cutoffEnd, pay_date: payDate, run_type: runType })
    }, 600)
    return () => clearTimeout(timer)
  }, [cutoffStart, cutoffEnd, payDate, runType])

  const { data: conflictData, isFetching: conflictChecking } = useRunDateConflictCheck(validationParams)

  const showValidationPanel = validationParams !== null || conflictChecking
  const hasApiBlockers      = conflictData ? !conflictData.can_proceed : false
  const hasZodDateErrors    = !!(errors.cutoff_start || errors.cutoff_end || errors.pay_date)

  // Pre-select the pay period in the dropdown if returning to this step
  useEffect(() => {
    if (state.step1?.pay_period_id && payPeriods.length) {
      const el = document.getElementById('pay_period') as HTMLSelectElement | null
      if (el) el.value = String(state.step1.pay_period_id)
    }
  }, [payPeriods, state.step1?.pay_period_id])

  // When a period is selected from the dropdown, auto-fill dates
  function handlePeriodSelect(e: React.ChangeEvent<HTMLSelectElement>) {
    const periodId = parseInt(e.target.value, 10)
    if (isNaN(periodId)) {
      setValue('pay_period_id', undefined)
      return
    }
    const period = payPeriods.find((p: PayPeriod) => p.id === periodId)
    if (period) {
      setValue('pay_period_id', period.id)
      setValue('cutoff_start',  period.cutoff_start)
      setValue('cutoff_end',    period.cutoff_end)
      setValue('pay_date',      period.pay_date)
    }
  }

  // No API call — just persist to wizard context and move to Step 2
  const onSubmit = (values: FormValues) => {
    // Safety net: block if the live conflict check found blockers
    if (hasApiBlockers) return
    setStep1({
      run_type:      values.run_type,
      pay_period_id: values.pay_period_id,
      cutoff_start:  values.cutoff_start,
      cutoff_end:    values.cutoff_end,
      pay_date:      values.pay_date,
      notes:         values.notes || undefined,
    })
    navigate('/payroll/runs/new/scope')
  }

  const canProceed = !hasApiBlockers && !hasZodDateErrors && !conflictChecking

  return (
    <div className="max-w-2xl">
      {/* Back */}
      <button
        onClick={() => navigate('/payroll/runs')}
        className="flex items-center gap-1.5 text-sm text-gray-500 hover:text-gray-800 mb-6 transition-colors"
      >
        <ArrowLeft className="h-4 w-4" />
        Back to Payroll Runs
      </button>

      {/* Wizard step indicator */}
      <div className="flex flex-wrap items-center gap-1.5 mb-6 text-xs text-gray-500">
        {['Define Run', 'Set Scope', 'Validate', 'Compute', 'Review', 'Acctg Review', 'Disburse'].map((step, i) => (
          <span key={step} className="flex items-center gap-1.5">
            <span className={`w-5 h-5 rounded-full flex items-center justify-center text-[10px] font-bold shrink-0 ${i === 0 ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-500'}`}>{i + 1}</span>
            <span className={i === 0 ? 'text-blue-600 font-semibold' : 'text-gray-400'}>{step}</span>
            {i < 6 && <span className="text-gray-300">›</span>}
          </span>
        ))}
      </div>

      {/* Header */}
      <div className="flex items-center gap-3 mb-8">
        <div className="p-2 bg-blue-100 rounded-lg">
          <CalendarRange className="h-5 w-5 text-blue-600" />
        </div>
        <div>
          <h1 className="text-2xl font-bold text-gray-900">New Payroll Run</h1>
          <p className="text-sm text-gray-500 mt-0.5">Step 1 of 7 — Define the run type and pay period.</p>
        </div>
      </div>

      {/* Form card */}
      <div className="bg-white border border-gray-200 rounded-xl p-6">
        <form onSubmit={handleSubmit(onSubmit)} noValidate className="space-y-5">

          {/* Run Type */}
          <div>
            <Label htmlFor="run_type" required>Run Type</Label>
            <select
              id="run_type"
              {...register('run_type')}
              className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 outline-none"
            >
              {(Object.entries(RUN_TYPE_LABELS) as [PayrollRunType, string][]).map(([value, label]) => (
                <option key={value} value={value}>{label}</option>
              ))}
            </select>
            {hint && (
              <div className="mt-2 flex items-start gap-2 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2 text-xs text-amber-800">
                <AlertTriangle className="h-3.5 w-3.5 shrink-0 mt-0.5 text-amber-600" />
                {hint}
              </div>
            )}
            <FieldError message={errors.run_type?.message} />
          </div>

          {/* Pay Period dropdown */}
          <div>
            <Label htmlFor="pay_period">Pay Period</Label>
            <div className="relative">
              {periodsLoading && (
                <Loader2 className="absolute right-3 top-1/2 -translate-y-1/2 h-4 w-4 animate-spin text-gray-400 pointer-events-none" />
              )}
              <select
                id="pay_period"
                onChange={handlePeriodSelect}
                disabled={periodsLoading}
                className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 outline-none disabled:bg-gray-50"
                defaultValue=""
              >
                <option value="">— Select a pay period (auto-fills dates) —</option>
                {payPeriods.map((p: PayPeriod) => (
                  <option key={p.id} value={p.id}>
                    {p.label} · {p.cutoff_start} → {p.cutoff_end} · Pay: {p.pay_date}
                  </option>
                ))}
              </select>
            </div>
            <p className="mt-1 text-xs text-gray-400">
              Only open pay periods are listed. Selecting one auto-fills the dates below; you can still adjust them manually.
            </p>
          </div>

          {/* Dates row */}
          <div className="grid grid-cols-3 gap-4">
            <div>
              <Label htmlFor="cutoff_start" required>Cutoff Start</Label>
              <input
                id="cutoff_start"
                type="date"
                {...register('cutoff_start')}
                className={`w-full border rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 outline-none ${
                  errors.cutoff_start ? 'border-red-400 bg-red-50' : 'border-gray-300'
                }`}
              />
              <FieldError message={errors.cutoff_start?.message} />
            </div>
            <div>
              <Label htmlFor="cutoff_end" required>Cutoff End</Label>
              <input
                id="cutoff_end"
                type="date"
                {...register('cutoff_end')}
                className={`w-full border rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 outline-none ${
                  errors.cutoff_end ? 'border-red-400 bg-red-50' : 'border-gray-300'
                }`}
              />
              <FieldError message={errors.cutoff_end?.message} />
            </div>
            <div>
              <Label htmlFor="pay_date" required>Pay Date</Label>
              <input
                id="pay_date"
                type="date"
                {...register('pay_date')}
                className={`w-full border rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 outline-none ${
                  errors.pay_date ? 'border-red-400 bg-red-50' : 'border-gray-300'
                }`}
              />
              <FieldError message={errors.pay_date?.message} />
            </div>
          </div>

          {/* Live conflict / date validation panel */}
          {showValidationPanel && (
            <ConflictCheckPanel
              checking={conflictChecking}
              checks={conflictData?.checks ?? []}
              hasBlockers={hasApiBlockers}
            />
          )}

          {/* Notes */}
          <div>
            <Label htmlFor="notes">Reference / Notes <span className="text-gray-400 font-normal">(optional)</span></Label>
            <textarea
              id="notes"
              rows={2}
              {...register('notes')}
              placeholder="e.g. January 2026 Second Half Regular Payroll"
              className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 outline-none resize-none"
            />
            <FieldError message={errors.notes?.message} />
          </div>

          {/* Workflow info */}
          <div className="bg-blue-50 border border-blue-100 rounded-lg p-3 text-xs text-blue-800 space-y-1">
            <p className="font-semibold">7-Step Workflow</p>
            <p>You will configure <strong>Scope → Validate</strong> locally. The payroll run is only saved to the database when you click <strong>"Begin Computation"</strong> on the final setup step.</p>
            <p className="text-blue-600">SoD enforced: the initiator cannot approve at HR or Accounting stages.</p>
          </div>

          {/* Actions */}
          <div className="flex items-center justify-between pt-2 border-t border-gray-100">
            <button
              type="button"
              onClick={() => navigate('/payroll/runs')}
              className="px-4 py-2 text-sm text-gray-600 hover:text-gray-900 transition-colors"
            >
              Cancel
            </button>
            <button
              type="submit"
              disabled={isSubmitting || !canProceed || (validationParams !== null && conflictChecking)}
              title={
                hasApiBlockers     ? 'Fix the date validation errors above before proceeding.' :
                conflictChecking   ? 'Validating dates…' :
                hasZodDateErrors   ? 'Correct the date errors before proceeding.' :
                undefined
              }
              className="flex items-center gap-2 px-6 py-2 bg-blue-600 hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed text-white text-sm font-medium rounded-lg transition-colors"
            >
              {isSubmitting
                ? <><Loader2 className="h-4 w-4 animate-spin" /> Saving…</>
                : conflictChecking && validationParams
                ? <><Loader2 className="h-4 w-4 animate-spin" /> Validating…</>
                : 'Next: Set Scope →'
              }
            </button>
          </div>

        </form>
      </div>
    </div>
  )
}

