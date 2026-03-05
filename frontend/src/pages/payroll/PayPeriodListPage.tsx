import { useState } from 'react'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { z } from 'zod'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { Plus, Lock, Calendar } from 'lucide-react'
import api from '@/lib/api'
import { parseApiError } from '@/lib/errorHandler'
import { PERMISSIONS } from '@/lib/permissions'
import PageHeader from '@/components/ui/PageHeader'
import StatusBadge from '@/components/ui/StatusBadge'
import SkeletonLoader from '@/components/ui/SkeletonLoader'
import EmptyState from '@/components/ui/EmptyState'
import FormField from '@/components/ui/FormField'
import PermissionGuard from '@/components/ui/PermissionGuard'
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
  cutoff_start: z.string().min(1, 'Required'),
  cutoff_end:   z.string().min(1, 'Required'),
  pay_date:     z.string().min(1, 'Required'),
  frequency:    z.enum(['semi_monthly', 'monthly', 'weekly']),
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
  } = useForm<CreateFormValues>({
    resolver: zodResolver(createSchema),
    defaultValues: { frequency: 'semi_monthly' },
  })

  const onCreateSubmit = async (values: CreateFormValues) => {
    try {
      await create.mutateAsync(values)
      toast.success('Pay period created.')
      setShowCreate(false)
      reset()
    } catch (err) {
      toast.error(parseApiError(err).message)
    }
  }

  const handleClose = async (period: PayPeriod) => {
    if (!confirm(`Close pay period "${period.label}"? This cannot be undone.`)) return
    try {
      await close.mutateAsync(period.id)
      toast.success('Pay period closed.')
    } catch (err) {
      toast.error(parseApiError(err).message)
    }
  }

  const inputCls = 'w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500'

  return (
    <div>
      <PageHeader
        title="Pay Periods"
        subtitle="Manage payroll cutoff windows and release dates."
        actions={
          <PermissionGuard permission={PERMISSIONS.payroll.initiate}>
            <button
              type="button"
              onClick={() => setShowCreate(!showCreate)}
              className="inline-flex items-center gap-1.5 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium px-4 py-2 rounded-lg"
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
          className="bg-white rounded-xl border border-blue-200 p-5 mb-5 space-y-4"
        >
          <h3 className="text-sm font-semibold text-gray-700">Create Pay Period</h3>
          <div className="grid grid-cols-2 gap-4">
            <FormField label="Label" required error={errors.label?.message} htmlFor="pp_label">
              <input id="pp_label" className={inputCls} placeholder="e.g. Feb 2026 1st" {...register('label')} />
            </FormField>
            <FormField label="Frequency" required error={errors.frequency?.message} htmlFor="pp_freq">
              <select id="pp_freq" className={inputCls} {...register('frequency')}>
                <option value="semi_monthly">Semi-Monthly</option>
                <option value="monthly">Monthly</option>
                <option value="weekly">Weekly</option>
              </select>
            </FormField>
            <FormField label="Cutoff Start" required error={errors.cutoff_start?.message} htmlFor="pp_cs">
              <input id="pp_cs" type="date" className={inputCls} {...register('cutoff_start')} />
            </FormField>
            <FormField label="Cutoff End" required error={errors.cutoff_end?.message} htmlFor="pp_ce">
              <input id="pp_ce" type="date" className={inputCls} {...register('cutoff_end')} />
            </FormField>
            <FormField label="Pay Date" required error={errors.pay_date?.message} htmlFor="pp_pd">
              <input id="pp_pd" type="date" className={inputCls} {...register('pay_date')} />
            </FormField>
          </div>
          <div className="flex gap-2">
            <button
              type="submit"
              disabled={isSubmitting}
              className="bg-blue-600 hover:bg-blue-700 disabled:opacity-50 text-white text-sm font-medium px-4 py-2 rounded-lg"
            >
              {isSubmitting ? 'Creating…' : 'Create Period'}
            </button>
            <button
              type="button"
              onClick={() => { setShowCreate(false); reset() }}
              className="border border-gray-300 text-gray-600 text-sm font-medium px-4 py-2 rounded-lg hover:bg-gray-50"
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
          className="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
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
          icon={<Calendar className="h-12 w-12 text-gray-300" />}
          title="No pay periods found"
          description="Create a pay period to start linking payroll runs."
        />
      ) : (
        <>
          <div className="overflow-x-auto rounded-lg border border-gray-200">
            <table className="min-w-full divide-y divide-gray-200 text-sm">
              <thead className="bg-gray-50">
                <tr>
                  {['Label', 'Frequency', 'Cutoff Start', 'Cutoff End', 'Pay Date', 'Status', ''].map((h) => (
                    <th key={h} className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">
                      {h}
                    </th>
                  ))}
                </tr>
              </thead>
              <tbody className="bg-white divide-y divide-gray-100">
                {data.data.map((period) => (
                  <tr key={period.id} className="hover:bg-gray-50">
                    <td className="px-4 py-3 font-medium text-gray-900">{period.label}</td>
                    <td className="px-4 py-3 text-gray-500 capitalize">{period.frequency.replace('_', '-')}</td>
                    <td className="px-4 py-3 text-gray-700">{period.cutoff_start}</td>
                    <td className="px-4 py-3 text-gray-700">{period.cutoff_end}</td>
                    <td className="px-4 py-3 text-gray-700">{period.pay_date}</td>
                    <td className="px-4 py-3"><StatusBadge label={period.status} /></td>
                    <td className="px-4 py-3">
                      {period.status === 'open' && (
                        <PermissionGuard permission={PERMISSIONS.payroll.approve}>
                          <button
                            type="button"
                            onClick={() => handleClose(period)}
                            disabled={close.isPending}
                            className="inline-flex items-center gap-1 text-xs text-amber-600 hover:text-amber-800 font-medium disabled:opacity-50"
                          >
                            <Lock className="h-3 w-3" /> Close
                          </button>
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
              <p className="text-xs text-gray-500">
                Page {data.meta?.current_page} of {data.meta?.last_page} — {data.meta?.total} total
              </p>
              <div className="flex gap-1">
                <button
                  type="button"
                  disabled={page <= 1}
                  onClick={() => setPage(p => p - 1)}
                  className="px-3 py-1 text-xs rounded border border-gray-300 bg-white text-gray-600 hover:bg-gray-100 disabled:opacity-40"
                >
                  ← Prev
                </button>
                <button
                  type="button"
                  disabled={page >= (data.meta?.last_page ?? 1)}
                  onClick={() => setPage(p => p + 1)}
                  className="px-3 py-1 text-xs rounded border border-gray-300 bg-white text-gray-600 hover:bg-gray-100 disabled:opacity-40"
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
