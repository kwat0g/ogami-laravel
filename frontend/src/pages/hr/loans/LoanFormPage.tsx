import { toast } from 'sonner'
import { useNavigate } from 'react-router-dom'
import { useForm, Controller } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useLoanTypes, useCreateLoan } from '@/hooks/useLoans'
import { useEmployees } from '@/hooks/useEmployees'
import SkeletonLoader from '@/components/ui/SkeletonLoader'
import { loanApplicationSchema, type LoanApplicationFormValues } from '@/schemas/loan'

export default function LoanFormPage() {
  const navigate = useNavigate()

  const { data: loanTypes, isLoading: ltLoading } = useLoanTypes()
  const { data: employeesData, isLoading: empLoading } = useEmployees({ per_page: 200, employment_status: 'active' })
  const create = useCreateLoan()

  const {
    register,
    handleSubmit,
    control,
    formState: { errors, isSubmitting },
  } = useForm<LoanApplicationFormValues>({
    resolver: zodResolver(loanApplicationSchema),
    mode: 'onBlur',
  })

  const employees = employeesData?.data ?? []

  if (ltLoading || empLoading) return <SkeletonLoader rows={6} />

  const onSubmit = (values: LoanApplicationFormValues) => {
    create.mutate(values, {
      onSuccess: (data) => {
        toast.success('Loan application submitted.')
        navigate(`/hr/loans/${data.ulid}`)
      },
      onError: () => toast.error('Failed to submit loan application.'),
    })
  }

  return (
    <div className="max-w-4xl mx-auto">
      <div className="flex items-center justify-between mb-6">
        <h1 className="text-lg font-semibold text-neutral-900">New Loan Application</h1>
        <button onClick={() => navigate('/hr/loans')} className="text-sm text-neutral-500 hover:text-neutral-700">← Back</button>
      </div>

      <div className="bg-white border border-neutral-200 rounded-lg p-6">
        {create.isError && (
          <div className="text-red-600 text-sm mb-4 bg-red-50 rounded px-3 py-2">
            {(create.error as { response?: { data?: { message?: string } } })?.response?.data?.message ?? 'Failed to create loan.'}
          </div>
        )}

        <form onSubmit={handleSubmit(onSubmit)} className="space-y-4" noValidate>

          {/* Employee */}
          <div>
            <label className="block text-sm font-medium text-neutral-700 mb-1">Employee</label>
            <Controller
              name="employee_id"
              control={control}
              render={({ field }) => (
                <select
                  {...field}
                  onChange={(e) => field.onChange(Number(e.target.value))}
                  className="w-full border border-neutral-300 rounded px-3 py-2 text-sm outline-none focus:ring-1 focus:ring-neutral-400"
                >
                  <option value="">Select employee…</option>
                  {employees.map((emp) => (
                    <option key={emp.id} value={emp.id}>{emp.full_name}</option>
                  ))}
                </select>
              )}
            />
            {errors.employee_id && <p className="mt-1 text-xs text-red-600">{errors.employee_id.message}</p>}
          </div>

          {/* Loan Type */}
          <div>
            <label className="block text-sm font-medium text-neutral-700 mb-1">Loan Type</label>
            <Controller
              name="loan_type_id"
              control={control}
              render={({ field }) => (
                <select
                  {...field}
                  onChange={(e) => field.onChange(Number(e.target.value))}
                  className="w-full border border-neutral-300 rounded px-3 py-2 text-sm outline-none focus:ring-1 focus:ring-neutral-400"
                >
                  <option value="">Select type…</option>
                  {(loanTypes ?? []).map((lt) => (
                    <option key={lt.id} value={lt.id}>{lt.name}</option>
                  ))}
                </select>
              )}
            />
            {errors.loan_type_id && <p className="mt-1 text-xs text-red-600">{errors.loan_type_id.message}</p>}
          </div>

          {/* Principal + Term */}
          <div className="grid grid-cols-2 gap-4">
            <div>
              <label className="block text-sm font-medium text-neutral-700 mb-1">Principal (PHP)</label>
              <input
                type="number"
                min="0"
                step="0.01"
                {...register('principal')}
                className="w-full border border-neutral-300 rounded px-3 py-2 text-sm outline-none focus:ring-1 focus:ring-neutral-400"
                placeholder="0.00"
              />
              {errors.principal && <p className="mt-1 text-xs text-red-600">{errors.principal.message}</p>}
            </div>
            <div>
              <label className="block text-sm font-medium text-neutral-700 mb-1">Term (months)</label>
              <input
                type="number"
                min="1"
                {...register('term_months')}
                className="w-full border border-neutral-300 rounded px-3 py-2 text-sm outline-none focus:ring-1 focus:ring-neutral-400"
                placeholder="12"
              />
              {errors.term_months && <p className="mt-1 text-xs text-red-600">{errors.term_months.message}</p>}
            </div>
          </div>

          {/* Loan Date */}
          <div>
            <label className="block text-sm font-medium text-neutral-700 mb-1">Loan Date</label>
            <input
              type="date"
              {...register('loan_date')}
              className="w-full border border-neutral-300 rounded px-3 py-2 text-sm outline-none focus:ring-1 focus:ring-neutral-400"
            />
            {errors.loan_date && <p className="mt-1 text-xs text-red-600">{errors.loan_date.message}</p>}
          </div>

          {/* Purpose */}
          <div>
            <label className="block text-sm font-medium text-neutral-700 mb-1">Purpose</label>
            <textarea
              {...register('purpose')}
              rows={2}
              placeholder="Brief description of purpose…"
              className="w-full border border-neutral-300 rounded px-3 py-2 text-sm outline-none focus:ring-1 focus:ring-neutral-400"
            />
            {errors.purpose && <p className="mt-1 text-xs text-red-600">{errors.purpose.message}</p>}
          </div>

          <div className="flex justify-end gap-3 pt-2">
            <button type="button" onClick={() => navigate('/hr/loans')} className="px-4 py-2 text-sm text-neutral-600 hover:bg-neutral-100 rounded">Cancel</button>
            <button
              type="submit"
              disabled={create.isPending || isSubmitting}
              className="px-4 py-2 text-sm bg-neutral-900 hover:bg-neutral-800 text-white rounded disabled:opacity-50 disabled:cursor-not-allowed"
            >
              {create.isPending ? 'Submitting…' : 'Submit Application'}
            </button>
          </div>
        </form>
      </div>
    </div>
  )
}
