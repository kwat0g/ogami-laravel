/**
 * WizardStepHeader — shared header component for the 7-step Payroll Run Wizard.
 * Shows step number, title, description, and a step progress indicator.
 */

const STEPS = [
  'Define Run',
  'Set Scope',
  'Validate',
  'Compute',
  'Review',
  'Acctg Review',
  'Disburse',
]

interface Props {
  step: number           // 1-based current step
  title: string
  description?: string
  currentStep?: number  // deprecated alias for step — kept for compat
}

export function WizardStepHeader({ step, title, description }: Props) {
  return (
    <div className="space-y-4">
      {/* Step breadcrumb trail */}
      <div className="flex flex-wrap items-center gap-1.5 text-xs text-neutral-500">
        {STEPS.map((label, i) => {
          const stepNum = i + 1
          const isActive   = stepNum === step
          const isComplete = stepNum < step
          return (
            <span key={label} className="flex items-center gap-1.5">
              <span
                className={`w-5 h-5 rounded flex items-center justify-center text-[10px] font-medium shrink-0 ${
                  isActive
                    ? 'bg-neutral-900 text-white'
                    : isComplete
                    ? 'bg-neutral-600 text-white'
                    : 'bg-neutral-200 text-neutral-500'
                }`}
              >
                {isComplete ? '✓' : stepNum}
              </span>
              <span className={isActive ? 'text-neutral-900 font-medium' : isComplete ? 'text-neutral-700' : 'text-neutral-400'}>
                {label}
              </span>
              {i < STEPS.length - 1 && <span className="text-neutral-300 select-none">›</span>}
            </span>
          )
        })}
      </div>

      {/* Step heading */}
      <div>
        <p className="text-xs font-medium text-neutral-500 mb-0.5">Step {step} of {STEPS.length}</p>
        <h1 className="text-lg font-semibold text-neutral-900">{title}</h1>
        {description && <p className="text-sm text-neutral-500 mt-1">{description}</p>}
      </div>
    </div>
  )
}
