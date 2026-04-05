import { useState } from 'react'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { z } from 'zod'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { Plus, Lock, Calendar, Loader2 } from 'lucide-react'
import api from '@/lib/api'
import { firstErrorMessage } from '@/lib/errorHandler'
import { PERMISSIONS } from '@/lib/permissions'
import PageHeader from '@/components/ui/PageHeader'
import StatusBadge from '@/components/ui/StatusBadge'
import SkeletonLoader from '@/components/ui/SkeletonLoader'
import EmptyState from '@/components/ui/EmptyState'
import FormField from '@/components/ui/FormField'
import PermissionGuard from '@/components/ui/PermissionGuard'
import ConfirmDestructiveDialog from '@/components/ui/ConfirmDestructiveDialog'
import type { ApiSuccess } from '@/types/api'

// ── Types ─────────────────────────────────────────────────────────────────────

interface PayPeriod {
  id:           number
  label:        string
  cutoff_start: string
  cutoff_end:   string
  pay_date:     string
  frequency:    'semi_monthly' | 'monthly' | 'weekly'
  status:       'open' | 'closed'
  created_at:   string
}

interface PaginatedPayPeriods {
  data: PayPeriod[]
  meta: { current_page: number; last_page: number; per_page: number; total: number }
}

// ── Create form schema ─────────────────────────────────────────────────────────

const createSchema = z.object({
  label:        z.string().min(1, 'Label is required').max(60),
  cutoff_start: z.string().min(1, 'Cutoff start date is required'),
  cutoff_end:   z.string().min(1, 'Cutoff end date is required'),
  pay_date:     z.string().min(1, 'Pay date is required'),
  frequency:    z.enum(['semi_monthly', 'monthly', 'weekly']),
}).refine((data) => {
  // Validate cutoff_end is after cutoff_start
  return new Date(data.cutoff_end) >= new Date(data.cutoff_start)
}, {
  message: 'Cutoff end date must be after cutoff start date',
  path: ['cutoff_end'],
}).refine((data) => {
  // Validate pay_date is after cutoff_end
  return new Date(data.pay_date) >= new Date(data.cutoff_end)
}, {
  message: 'Pay date must be on or after cutoff end date',
  path: ['pay_date'],
})

type CreateFormValues = z.infer<typeof createSchema>

// ── Hooks ─────────────────────────────────────────────────────────────────────

function usePayPeriods(page: number, status?: string) {
  return useQuery<PaginatedPayPeriods>({
    queryKey: ['pay-periods', page, status],
    queryFn: async () => {
      const res = await api.get<PaginatedPayPeriods>('/payroll/periods', {
        params: { page, per_page: 20, ...(status ? { status } : {}) },
      })
      return res.data
    },
    staleTime: 30_000,
  })
}

function useCreatePayPeriod() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async (payload: CreateFormValues) => {
      const res = await api.post<ApiSuccess<PayPeriod>>('/payroll/periods', payload)
      return res.data.data
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['pay-periods'] })
    },
  })
}

function useClosePayPeriod() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async (id: number) => {
      const res = await api.patch<ApiSuccess<PayPeriod>>(`/payroll/periods/${id}/close`)
      return res.data.data
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['pay-periods'] })
    },
  })
}

// ── Component ─────────────────────────────────────────────────────────────────

export default function PayPeriodListPage() {
  const [page, setPage]           = useState(1)
  const [statusFilter, setStatusFilter] = useState<string>('')
  const [showCreate, setShowCreate]     = useState(false)

  const { data, isLoading } = usePayPeriods(page, statusFilter || undefined)
  const create = useCreatePayPeriod()
  const close  = useClosePayPeriod()

  const {
    register,
    handleSubmit,
    reset,
    formState: { errors, isSubmitting },
    watch,
  } = useForm<CreateFormValues>({
    resolver: zodResolver(createSchema),
    mode: 'onBlur',
    defaultValues: { frequency: 'semi_monthly' },
  })

  // Watch dates for validation feedback
  const watchCutoffStart = watch('cutoff_start')
  const watchCutoffEnd = watch('cutoff_end')
  const watchPayDate = watch('pay_date')

  const onCreateSubmit = async (values: CreateFormValues) => {
    try {
      await create.mutateAsync(values)
      toast.success('Pay period created successfully.')
      setShowCreate(false)
      reset()
    } catch (err) {
      toast.error(firstErrorMessage(err))
    }
  }

  const handleClose = async (period: PayPeriod) => {
    try {
      await close.mutateAsync(period.id)
      toast.success(`Pay period "${period.label}" closed successfully.`)
    } catch (err) {
      toast.error(firstErrorMessage(err))
    }
  }

  // ── Validation helper for close ───────────────────────────────────────────
  function _validateClose(period: PayPeriod): boolean {
    if (period.status === 'closed') {
      return false
    }
    return true
  }

  const inputCls = 'w-full border border-neutral-300 rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-neutral-500'
  const inputErrorCls = 'w-full border border-red-300 rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-red-500'

  return (
    <div>
      <PageHeader
        title="Pay Periods"
        subtitle="Manage payroll cutoff windows and release dates."
        actions={
          <PermissionGuard permission={`${PERMISSIONS.payroll.initiate}|${PERMISSIONS.payroll.hr_approve}|${PERMISSIONS.hr.full_access}`}>
            <button
              type="button"
              onClick={() => setShowCreate(!showCreate)}
              className="inline-flex items-center gap-1.5 bg-neutral-900 hover:bg-neutral-800 text-white text-sm font-medium px-4 py-2 rounded"
            >
              <Plus className="h-4 w-4" />
              New Period
            </button>
          </PermissionGuard>
        }
      />

      {/* Create form */}
      {showCreate && (
        <form
          onSubmit={handleSubmit(onCreateSubmit)}
          className="bg-white rounded border border-neutral-200 p-5 mb-5 space-y-4"
        >
          <h3 className="text-sm font-semibold text-neutral-700">Create Pay Period</h3>
          <div className="grid grid-cols-2 gap-4">
            <FormField label="Label" required error={errors.label?.message} htmlFor="pp_label">
              <input 
                id="pp_label" 
                className={errors.label ? inputErrorCls : inputCls} 
                placeholder="e.g. Feb 2026 1st" 
                {...register('label')} 
              />
            </FormField>
            <FormField label="Frequency" required error={errors.frequency?.message} htmlFor="pp_freq">
              <select id="pp_freq" className={inputCls} {...register('frequency')}>
                <option value="semi_monthly">Semi-Monthly</option>
                <option value="monthly">Monthly</option>
                <option value="weekly">Weekly</option>
              </select>
            </FormField>
            <FormField label="Cutoff Start" required error={errors.cutoff_start?.message} htmlFor="pp_cs">
              <input 
                id="pp_cs" 
                type="date" 
                className={errors.cutoff_start ? inputErrorCls : inputCls} 
                {...register('cutoff_start')} 
              />
            </FormField>
            <FormField label="Cutoff End" required error={errors.cutoff_end?.message} htmlFor="pp_ce">
              <input 
                id="pp_ce" 
                type="date" 
                className={errors.cutoff_end ? inputErrorCls : inputCls} 
                {...register('cutoff_end')} 
              />
              {watchCutoffStart && watchCutoffEnd && new Date(watchCutoffEnd) < new Date(watchCutoffStart) && (
                <p className="text-xs text-red-500 mt-1">Cutoff end must be after start date.</p>
              )}
            </FormField>
            <FormField label="Pay Date" required error={errors.pay_date?.message} htmlFor="pp_pd">
              <input 
                id="pp_pd" 
                type="date" 
                className={errors.pay_date ? inputErrorCls : inputCls} 
                {...register('pay_date')} 
              />
              {watchCutoffEnd && watchPayDate && new Date(watchPayDate) < new Date(watchCutoffEnd) && (
                <p className="text-xs text-red-500 mt-1">Pay date must be on or after cutoff end.</p>
              )}
            </FormField>
          </div>
          <div className="flex gap-2">
            <button
              type="submit"
              disabled={isSubmitting || create.isPending}
              className="bg-neutral-900 hover:bg-neutral-800 disabled:opacity-50 disabled:cursor-not-allowed text-white text-sm font-medium px-4 py-2 rounded flex items-center gap-2"
            >
              {create.isPending ? <><Loader2 className="h-4 w-4 animate-spin" /> Creating…</> : 'Create Period'}
            </button>
            <button
              type="button"
              onClick={() => { setShowCreate(false); reset() }}
              className="border border-neutral-300 text-neutral-600 text-sm font-medium px-4 py-2 rounded hover:bg-neutral-50"
            >
              Cancel
            </button>
          </div>
        </form>
      )}

      {/* Filter */}
      <div className="flex items-center gap-3 mb-4">
        <select
          value={statusFilter}
          onChange={(e) => { setStatusFilter(e.target.value); setPage(1) }}
          className="border border-neutral-300 rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-neutral-500"
        >
          <option value="">All statuses</option>
          <option value="open">Open</option>
          <option value="closed">Closed</option>
        </select>
      </div>

      {/* Table */}
      {isLoading ? (
        <SkeletonLoader rows={6} />
      ) : !data || data.data.length === 0 ? (
        <EmptyState
          icon={<Calendar className="h-12 w-12 text-neutral-300" />}
          title="No pay periods found"
          description="Create a pay period to start linking payroll runs."
        />
      ) : (
        <>
          <div className="overflow-x-auto rounded border border-neutral-200">
            <table className="min-w-full divide-y divide-neutral-200 text-sm">
              <thead className="bg-neutral-50">
                <tr>
                  {['Label', 'Frequency', 'Cutoff Start', 'Cutoff End', 'Pay Date', 'Status', ''].map((h) => (
                    <th key={h} className="px-4 py-3 text-left text-xs font-medium text-neutral-500 font-medium whitespace-nowrap">
                      {h}
                    </th>
                  ))}
                </tr>
              </thead>
              <tbody className="bg-white divide-y divide-neutral-100">
                {data.data.map((period) => (
                  <tr key={period.id} className="hover:bg-neutral-50">
                    <td className="px-4 py-3 font-medium text-neutral-900">{period.label}</td>
                    <td className="px-4 py-3 text-neutral-500 capitalize">{period.frequency?.replace('_', '-') || '—'}</td>
                    <td className="px-4 py-3 text-neutral-700">{period.cutoff_start}</td>
                    <td className="px-4 py-3 text-neutral-700">{period.cutoff_end}</td>
                    <td className="px-4 py-3 text-neutral-700">{period.pay_date}</td>
                    <td className="px-4 py-3"><StatusBadge status={period.status}>{period.status?.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase())}</StatusBadge></td>
                    <td className="px-4 py-3">
                      {period.status === 'open' && (
                        <PermissionGuard permission={PERMISSIONS.payroll.approve}>
                          <ConfirmDestructiveDialog
                            title={`Close pay period "${period.label}"?`}
                            description="Closing a pay period prevents new payroll runs from using it. This action cannot be undone."
                            confirmWord="CLOSE"
                            confirmLabel="Close Period"
                            onConfirm={() => handleClose(period)}
                          >
                            <button
                              type="button"
                              disabled={close.isPending}
                              className="inline-flex items-center gap-1 text-xs text-amber-600 hover:text-amber-800 font-medium disabled:opacity-50 disabled:cursor-not-allowed"
                            >
                              {close.isPending ? <Loader2 className="h-3 w-3 animate-spin" /> : <Lock className="h-3 w-3" />}
                              Close
                            </button>
                          </ConfirmDestructiveDialog>
                        </PermissionGuard>
                      )}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>

          {/* Pagination */}
          {data?.meta?.last_page > 1 && (
            <div className="flex items-center justify-between mt-4">
              <p className="text-xs text-neutral-500">
                Page {data.meta?.current_page} of {data.meta?.last_page} — {data.meta?.total} total
              </p>
              <div className="flex gap-1">
                <button
                  type="button"
                  disabled={page <= 1}
                  onClick={() => setPage(p => p - 1)}
                  className="px-3 py-1 text-xs rounded border border-neutral-300 bg-white text-neutral-600 hover:bg-neutral-100 disabled:opacity-40 disabled:cursor-not-allowed"
                >
                  ← Prev
                </button>
                <button
                  type="button"
                  disabled={page >= (data.meta?.last_page ?? 1)}
                  onClick={() => setPage(p => p + 1)}
                  className="px-3 py-1 text-xs rounded border border-neutral-300 bg-white text-neutral-600 hover:bg-neutral-100 disabled:opacity-40 disabled:cursor-not-allowed"
                >
                  Next →
                </button>
              </div>
            </div>
          )}
        </>
      )}
    </div>
  )
}
