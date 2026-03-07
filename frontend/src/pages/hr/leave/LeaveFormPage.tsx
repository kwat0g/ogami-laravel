import { useNavigate } from 'react-router-dom'
import { useForm, Controller } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useLeaveTypes, useCreateLeaveRequest } from '@/hooks/useLeave'
import { useEmployees } from '@/hooks/useEmployees'
import SkeletonLoader from '@/components/ui/SkeletonLoader'
import { leaveRequestSchema, type LeaveRequestFormValues } from '@/schemas/leave'

export default function LeaveFormPage() {
  const navigate = useNavigate()

  const { data: leaveTypesData, isLoading: ltLoading } = useLeaveTypes()
  const { data: employeesData, isLoading: empLoading } = useEmployees({ per_page: 200, employment_status: 'active' })
  const create = useCreateLeaveRequest()

  const {
    register,
    handleSubmit,
    control,
    watch,
    formState: { errors, isSubmitting },
  } = useForm<LeaveRequestFormValues>({
    resolver: zodResolver(leaveRequestSchema),
    mode: 'onBlur',
    defaultValues: { is_half_day: false },
  })

  const isHalfDay = watch('is_half_day')

  const leaveTypes = leaveTypesData ?? []
  const employees  = employeesData?.data ?? []

  if (ltLoading || empLoading) return <SkeletonLoader rows={6} />

  const onSubmit = (values: LeaveRequestFormValues) => {
    create.mutate(values, {
      onSuccess: () => navigate('/hr/leave'),
    })
  }

  return (
    <div className="max-w-xl">
      <div className="flex items-center justify-between mb-6">
        <h1 className="text-2xl font-bold text-gray-900">File Leave Request</h1>
        <button onClick={() => navigate('/hr/leave')} className="text-sm text-gray-500 hover:text-gray-700">← Back</button>
      </div>

      <div className="bg-white border border-gray-200 rounded-xl p-6">
        {create.isError && (
          <div className="text-red-600 text-sm mb-4 bg-red-50 rounded-lg px-3 py-2">
            {(create.error as { response?: { data?: { message?: string } } })?.response?.data?.message ?? 'Failed to file leave request.'}
          </div>
        )}

        <form onSubmit={handleSubmit(onSubmit)} className="space-y-4" noValidate>

          {/* Employee */}
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">Employee</label>
            <Controller
              name="employee_id"
              control={control}
              render={({ field }) => (
                <select
                  {...field}
                  onChange={(e) => field.onChange(Number(e.target.value))}
                  className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-blue-500"
                >
                  <option value="">Select employee…</option>
                  {employees.map((emp) => (
                    <option key={emp.id} value={emp.id}>{emp.full_name} ({emp.employee_code})</option>
                  ))}
                </select>
              )}
            />
            {errors.employee_id && <p className="mt-1 text-xs text-red-600">{errors.employee_id.message}</p>}
          </div>

          {/* Leave Type */}
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">Leave Type</label>
            <Controller
              name="leave_type_id"
              control={control}
              render={({ field }) => (
                <select
                  {...field}
                  onChange={(e) => field.onChange(Number(e.target.value))}
                  className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-blue-500"
                >
                  <option value="">Select type…</option>
                  {leaveTypes.map((lt) => (
                    <option key={lt.id} value={lt.id}>{lt.name}</option>
                  ))}
                </select>
              )}
            />
            {errors.leave_type_id && <p className="mt-1 text-xs text-red-600">{errors.leave_type_id.message}</p>}
          </div>

          {/* Dates */}
          <div className="grid grid-cols-2 gap-4">
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">Date From</label>
              <input
                type="date"
                {...register('date_from')}
                className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-blue-500"
              />
              {errors.date_from && <p className="mt-1 text-xs text-red-600">{errors.date_from.message}</p>}
            </div>
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">Date To</label>
              <input
                type="date"
                {...register('date_to')}
                className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-blue-500"
              />
              {errors.date_to && <p className="mt-1 text-xs text-red-600">{errors.date_to.message}</p>}
            </div>
          </div>

          {/* Half day */}
          <label className="flex items-center gap-2 text-sm text-gray-700 cursor-pointer">
            <input
              type="checkbox"
              {...register('is_half_day')}
              className="rounded"
            />
            Half day leave
          </label>

          {isHalfDay && (
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">Half Day Period</label>
              <Controller
                name="half_day_period"
                control={control}
                render={({ field }) => (
                  <select
                    {...field}
                    className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-blue-500"
                  >
                    <option value="">Select…</option>
                    <option value="AM">AM (Morning)</option>
                    <option value="PM">PM (Afternoon)</option>
                  </select>
                )}
              />
              {errors.half_day_period && <p className="mt-1 text-xs text-red-600">{errors.half_day_period.message}</p>}
            </div>
          )}

          {/* Reason */}
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">Reason</label>
            <textarea
              {...register('reason')}
              rows={3}
              placeholder="Briefly describe the reason…"
              className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-blue-500"
            />
            {errors.reason && <p className="mt-1 text-xs text-red-600">{errors.reason.message}</p>}
          </div>

          <div className="flex justify-end gap-3 pt-2">
            <button type="button" onClick={() => navigate('/hr/leave')} className="px-4 py-2 text-sm text-gray-600 hover:bg-gray-100 rounded-lg">Cancel</button>
            <button
              type="submit"
              disabled={create.isPending || isSubmitting}
              className="px-4 py-2 text-sm bg-blue-600 hover:bg-blue-700 text-white rounded-lg disabled:opacity-50"
            >
              {create.isPending ? 'Filing…' : 'File Leave'}
            </button>
          </div>
        </form>
      </div>
    </div>
  )
}
