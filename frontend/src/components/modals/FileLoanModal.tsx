import { useMemo, useState } from 'react'
import { useCreateLoan, useLoanTypes } from '@/hooks/useLoans'
import { useAuthStore } from '@/stores/authStore'
import { toast } from 'sonner'
import { X, Calculator, Calendar, AlertTriangle } from 'lucide-react'

interface FileLoanModalProps {
  isOpen: boolean
  onClose: () => void
  onSuccess?: () => void
}

/** Monthly amortization using simple reducing-balance approach. */
function computeMonthlyAmortization(
  principal: number,
  annualRate: number,
  termMonths: number,
): number {
  if (termMonths <= 0 || principal <= 0) return 0
  if (annualRate === 0) return principal / termMonths
  const r = annualRate / 12
  return (principal * r * Math.pow(1 + r, termMonths)) / (Math.pow(1 + r, termMonths) - 1)
}

function formatPeso(amount: number): string {
  return `₱${amount.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`
}

const LOAN_PURPOSE_PRESETS = [
  'Medical / Hospitalization',
  'Education / Tuition',
  'Home Repair / Renovation',
  'Calamity / Disaster Relief',
  'Business Capital / Investment',
  'Debt Consolidation',
  'Vehicle Purchase / Repair',
  'Personal / Family Needs',
  'Wedding / Special Occasion',
  'Travel',
  'Other',
] as const

type PurposePreset = typeof LOAN_PURPOSE_PRESETS[number]

export default function FileLoanModal({ isOpen, onClose, onSuccess }: FileLoanModalProps) {
  const { user } = useAuthStore()
  const employeeId = user?.employee_id as number | undefined

  const { data: loanTypes = [], isLoading: loadingTypes } = useLoanTypes()

  const [formData, setFormData] = useState({
    loan_type_id: '',
    principal_amount: '',
    term_months: '',
    deduction_cutoff: '2nd' as '1st' | '2nd',
    purpose: '',
  })
  const [purposePreset, setPurposePreset] = useState<PurposePreset | ''>('')

  const createMutation = useCreateLoan()

  const selectedType = useMemo(
    () => loanTypes.find(t => t.id === parseInt(formData.loan_type_id)),
    [loanTypes, formData.loan_type_id],
  )

  const principal = parseFloat(formData.principal_amount) || 0
  const termMonths = parseInt(formData.term_months) || 0

  const amountError = useMemo((): string | null => {
    if (!selectedType || !principal) return null
    const minPeso = selectedType.min_amount_centavos / 100
    const maxPeso = selectedType.max_amount_centavos / 100
    if (principal < minPeso)
      return `Amount is below the minimum of ${formatPeso(minPeso)} for ${selectedType.name}.`
    if (principal > maxPeso)
      return `Amount exceeds the maximum of ${formatPeso(maxPeso)} for ${selectedType.name}.`
    return null
  }, [selectedType, principal])

  const monthlyAmort = useMemo(() => {
    if (!selectedType || !principal || !termMonths) return 0
    return computeMonthlyAmortization(principal, selectedType.interest_rate_annual, termMonths)
  }, [selectedType, principal, termMonths])

  const totalPayable = monthlyAmort * termMonths

  if (!isOpen) return null

  const handleTypeChange = (id: string) => {
    setFormData(f => ({ ...f, loan_type_id: id, term_months: '' }))
  }

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault()

    if (!employeeId) {
      toast.error('No employee profile linked to your account')
      return
    }

    if (!principal || principal <= 0) {
      toast.error('Please enter a valid loan amount')
      return
    }

    if (amountError) {
      toast.error(amountError)
      return
    }

    if (!termMonths || termMonths <= 0) {
      toast.error('Please select a repayment term')
      return
    }

    if (selectedType && termMonths > selectedType.max_term_months) {
      toast.error(`Maximum term for ${selectedType.name} is ${selectedType.max_term_months} months`)
      return
    }

    const resolvedPurpose = purposePreset === 'Other' ? formData.purpose.trim() : purposePreset
    if (!resolvedPurpose) {
      toast.error('Please select or enter a purpose')
      return
    }

    try {
      await createMutation.mutateAsync({
        employee_id: employeeId,
        loan_type_id: parseInt(formData.loan_type_id),
        principal_centavos: Math.round(principal * 100),
        term_months: termMonths,
        deduction_cutoff: formData.deduction_cutoff,
        purpose: resolvedPurpose,
      })

      toast.success('Loan application filed successfully')
      setFormData({
        loan_type_id: '',
        principal_amount: '',
        term_months: '',
        deduction_cutoff: '2nd',
        purpose: '',
      })
      setPurposePreset('')
      onSuccess?.()
      onClose()
    } catch (error: unknown) {
      const err = error as { response?: { data?: { message?: string } } }
      toast.error(err.response?.data?.message || 'Failed to file loan application')
    }
  }

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50">
      <div className="bg-white rounded-lg shadow-lg max-w-md w-full max-h-[90vh] overflow-y-auto border border-neutral-200">
        <div className="px-4 py-3 border-b border-neutral-200 flex items-center justify-between sticky top-0 bg-white z-10">
          <h2 className="text-base font-semibold text-neutral-900">Apply for Loan</h2>
          <button onClick={onClose} className="text-neutral-400 hover:text-neutral-600">
            <X className="h-4 w-4" />
          </button>
        </div>

        <form onSubmit={handleSubmit} className="p-4 space-y-3">
          {/* Loan Type */}
          <div>
            <label className="block text-sm font-medium text-neutral-700 mb-1">
              Loan Type <span className="text-red-500">*</span>
            </label>
            <select
              value={formData.loan_type_id}
              onChange={(e) => handleTypeChange(e.target.value)}
              className="w-full border border-neutral-300 rounded px-3 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-neutral-400"
              required
              disabled={loadingTypes}
            >
              <option value="">{loadingTypes ? 'Loading...' : 'Select loan type...'}</option>
              {loanTypes.map((type) => (
                <option key={type.id} value={type.id}>
                  {type.name} — up to {type.max_term_months} months
                </option>
              ))}
            </select>
          </div>

          {/* Principal Amount */}
          <div>
            <label className="block text-sm font-medium text-neutral-700 mb-1">
              Loan Amount (₱) <span className="text-red-500">*</span>
            </label>
            <input
              type="number"
              min={selectedType ? selectedType.min_amount_centavos / 100 : undefined}
              max={selectedType ? selectedType.max_amount_centavos / 100 : undefined}
              step="100"
              placeholder="e.g. 10,000"
              value={formData.principal_amount}
              onChange={(e) => setFormData({ ...formData, principal_amount: e.target.value })}
              className={`w-full border rounded px-3 py-1.5 text-sm focus:outline-none focus:ring-1 transition-colors ${
                amountError
                  ? 'border-red-400 focus:ring-red-400 bg-red-50'
                  : 'border-neutral-300 focus:ring-neutral-400'
              }`}
              required
            />
            {amountError ? (
              <div className="flex items-start gap-1.5 mt-1.5">
                <AlertTriangle className="h-3.5 w-3.5 text-red-500 mt-0.5 flex-shrink-0" />
                <p className="text-xs text-red-600">{amountError}</p>
              </div>
            ) : selectedType && (
              <p className="text-xs text-neutral-400 mt-1">
                Min: {formatPeso(selectedType.min_amount_centavos / 100)} ·
                Max: {formatPeso(selectedType.max_amount_centavos / 100)}
              </p>
            )}
          </div>

          {/* Repayment Term — dropdown */}
          <div>
            <label className="block text-sm font-medium text-neutral-700 mb-1">
              Repayment Term <span className="text-red-500">*</span>
            </label>
            <select
              value={formData.term_months}
              onChange={(e) => setFormData({ ...formData, term_months: e.target.value })}
              className="w-full border border-neutral-300 rounded px-3 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-neutral-400 max-h-32"
              required
              disabled={!selectedType}
            >
              <option value="">{!selectedType ? 'Select a loan type first' : 'Select number of months...'}</option>
              {selectedType && Array.from({ length: selectedType.max_term_months }, (_, i) => i + 1).map((m) => (
                <option key={m} value={m}>{m} {m === 1 ? 'month' : 'months'}</option>
              ))}
            </select>
          </div>

          {/* Amortization summary */}
          {monthlyAmort > 0 && (
            <div className="bg-neutral-50 border border-neutral-200 rounded p-4 space-y-2">
              <div className="flex items-center gap-2 text-neutral-700 font-medium text-sm mb-1">
                <Calculator className="h-4 w-4" />
                Repayment Summary
              </div>
              <div className="grid grid-cols-2 gap-x-4 gap-y-1 text-sm">
                <span className="text-neutral-500">Monthly deduction</span>
                <span className="font-semibold text-neutral-900 text-right">{formatPeso(monthlyAmort)}</span>
                <span className="text-neutral-500">Total payable</span>
                <span className="font-semibold text-neutral-900 text-right">{formatPeso(totalPayable)}</span>
                {selectedType && selectedType.interest_rate_annual > 0 && (
                  <>
                    <span className="text-neutral-500">Interest ({(selectedType.interest_rate_annual * 100).toFixed(1)}% p.a.)</span>
                    <span className="font-semibold text-amber-600 text-right">{formatPeso(totalPayable - principal)}</span>
                  </>
                )}
              </div>
            </div>
          )}

          {/* Deduction Cut-off */}
          <div>
            <label className="block text-sm font-medium text-neutral-700 mb-1 flex items-center gap-1.5">
              <Calendar className="h-4 w-4 text-neutral-400" />
              Deduct on which cut-off? <span className="text-red-500">*</span>
            </label>
            <p className="text-xs text-neutral-400 mb-2">
              Choose when your monthly amortization will be deducted from your salary.
            </p>
            <div className="grid grid-cols-2 gap-3">
              {(['1st', '2nd'] as const).map((cutoff) => (
                <button
                  key={cutoff}
                  type="button"
                  onClick={() => setFormData(f => ({ ...f, deduction_cutoff: cutoff }))}
                  className={`rounded border p-3 text-left transition-colors ${
                    formData.deduction_cutoff === cutoff
                      ? 'border-neutral-900 bg-neutral-100'
                      : 'border-neutral-200 hover:border-neutral-300'
                  }`}
                >
                  <div className={`text-sm font-semibold ${formData.deduction_cutoff === cutoff ? 'text-neutral-900' : 'text-neutral-700'}`}>
                    {cutoff} Cut-off
                  </div>
                  <div className="text-xs text-neutral-400 mt-0.5">
                    {cutoff === '1st' ? 'Deducted every 1st–15th' : 'Deducted every 16th–end'}
                  </div>
                </button>
              ))}
            </div>
          </div>

          {/* Purpose */}
          <div>
            <label className="block text-sm font-medium text-neutral-700 mb-1">
              Purpose <span className="text-red-500">*</span>
            </label>
            <select
              value={purposePreset}
              onChange={(e) => setPurposePreset(e.target.value as PurposePreset | '')}
              className="w-full border border-neutral-300 rounded px-3 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-neutral-400"
              required
            >
              <option value="">Select a reason...</option>
              {LOAN_PURPOSE_PRESETS.map((p) => (
                <option key={p} value={p}>{p}</option>
              ))}
            </select>
            {purposePreset === 'Other' && (
              <textarea
                value={formData.purpose}
                onChange={(e) => setFormData({ ...formData, purpose: e.target.value })}
                placeholder="Please describe the purpose of this loan..."
                className="w-full border border-neutral-300 rounded px-3 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-neutral-400 resize-none mt-2"
                rows={2}
                required
              />
            )}
          </div>

          {/* Actions */}
          <div className="flex gap-3 pt-1">
            <button
              type="button"
              onClick={onClose}
              className="flex-1 px-4 py-1.5 border border-neutral-300 text-neutral-700 rounded hover:bg-neutral-50 text-sm font-medium"
            >
              Cancel
            </button>
            <button
              type="submit"
              disabled={createMutation.isPending}
              className="flex-1 px-4 py-1.5 bg-neutral-900 text-white rounded hover:bg-neutral-800 disabled:opacity-50 text-sm font-medium"
            >
              {createMutation.isPending ? 'Submitting...' : 'Submit Application'}
            </button>
          </div>
        </form>
      </div>
    </div>
  )
}

