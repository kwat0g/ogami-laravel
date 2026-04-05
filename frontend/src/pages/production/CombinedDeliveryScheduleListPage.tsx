import { useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { Truck, Package, Calendar, AlertCircle, ChevronRight, CheckCircle, Clock, AlertTriangle } from 'lucide-react'
import { PageHeader } from '@/components/ui/PageHeader'
import { Card, CardBody } from '@/components/ui/Card'
import { useCombinedDeliverySchedules } from '@/hooks/useCombinedDeliverySchedules'
import { useAuthStore } from '@/stores/authStore'
import { PERMISSIONS } from '@/lib/permissions'
import SkeletonLoader from '@/components/ui/SkeletonLoader'
import type { CombinedDeliverySchedule, CombinedDeliveryScheduleStatus } from '@/types/production'

const STATUS_CONFIG: Record<CombinedDeliveryScheduleStatus, { color: string; icon: typeof Clock; label: string }> = {
  planning: { color: 'bg-neutral-100 text-neutral-600', icon: Clock, label: 'Planning' },
  ready: { color: 'bg-green-100 text-green-700', icon: CheckCircle, label: 'Ready for Delivery' },
  partially_ready: { color: 'bg-amber-100 text-amber-700', icon: AlertTriangle, label: 'Partially Ready' },
  dispatched: { color: 'bg-blue-100 text-blue-700', icon: Truck, label: 'Dispatched' },
  delivered: { color: 'bg-emerald-100 text-emerald-700', icon: Package, label: 'Delivered' },
  cancelled: { color: 'bg-red-100 text-red-700', icon: AlertCircle, label: 'Cancelled' },
}

const STATUS_OPTIONS = [
  { value: '', label: 'All Statuses' },
  { value: 'planning', label: 'Planning' },
  { value: 'ready', label: 'Ready for Delivery' },
  { value: 'partially_ready', label: 'Partially Ready' },
  { value: 'dispatched', label: 'Dispatched' },
  { value: 'delivered', label: 'Delivered' },
]

export default function CombinedDeliveryScheduleListPage(): JSX.Element {
  const navigate = useNavigate()
  const { hasPermission } = useAuthStore()
  const [status, setStatus] = useState('')
  const [dateFrom, setDateFrom] = useState('')
  const [dateTo, setDateTo] = useState('')

  const { data, isLoading } = useCombinedDeliverySchedules({
    status: status || undefined,
    date_from: dateFrom || undefined,
    date_to: dateTo || undefined,
    per_page: 20,
  })

  const canManage = hasPermission(PERMISSIONS.production.delivery_schedule.manage)

  if (isLoading) {
    return <SkeletonLoader rows={5} />
  }

  const schedules = data?.data || []

  return (
    <div className="space-y-5">
      <PageHeader
        title="Combined Delivery Schedules"
        subtitle="Manage multi-item deliveries grouped by customer order"
        icon={<Truck className="w-5 h-5 text-neutral-500" />}
      />

      {/* Filters */}
      <Card>
        <CardBody>
          <div className="flex flex-wrap gap-4 items-end">
            <div>
              <label className="block text-xs text-neutral-500 uppercase tracking-wide mb-1.5">Status</label>
              <select
                value={status}
                onChange={(e) => setStatus(e.target.value)}
                className="border border-neutral-200 rounded-lg px-3 py-2 focus:border-neutral-400 focus:ring-2 focus:ring-neutral-100 outline-none text-sm bg-white"
              >
                {STATUS_OPTIONS.map(opt => (
                  <option key={opt.value} value={opt.value}>{opt.label}</option>
                ))}
              </select>
            </div>
            <div>
              <label className="block text-xs text-neutral-500 uppercase tracking-wide mb-1.5">From Date</label>
              <input
                type="date"
                value={dateFrom}
                onChange={(e) => setDateFrom(e.target.value)}
                className="border border-neutral-200 rounded-lg px-3 py-2 focus:border-neutral-400 focus:ring-2 focus:ring-neutral-100 outline-none text-sm"
              />
            </div>
            <div>
              <label className="block text-xs text-neutral-500 uppercase tracking-wide mb-1.5">To Date</label>
              <input
                type="date"
                value={dateTo}
                onChange={(e) => setDateTo(e.target.value)}
                className="border border-neutral-200 rounded-lg px-3 py-2 focus:border-neutral-400 focus:ring-2 focus:ring-neutral-100 outline-none text-sm"
              />
            </div>
            <button
              onClick={() => { setStatus(''); setDateFrom(''); setDateTo('') }}
              className="text-sm text-neutral-600 hover:text-neutral-900"
            >
              Clear Filters
            </button>
          </div>
        </CardBody>
      </Card>

      {/* Stats */}
      <div className="grid sm:grid-cols-4 gap-4">
        {[
          { label: 'Ready for Delivery', value: schedules.filter((s: CombinedDeliverySchedule) => s.status === 'ready').length, color: 'text-green-600' },
          { label: 'Partially Ready', value: schedules.filter((s: CombinedDeliverySchedule) => s.status === 'partially_ready').length, color: 'text-amber-600' },
          { label: 'Dispatched', value: schedules.filter((s: CombinedDeliverySchedule) => s.status === 'dispatched').length, color: 'text-blue-600' },
          { label: 'Total Active', value: schedules.filter((s: CombinedDeliverySchedule) => s.status !== 'delivered' && s.status !== 'cancelled').length, color: 'text-neutral-900' },
        ].map(stat => (
          <Card key={stat.label}>
            <CardBody>
              <p className="text-sm text-neutral-500">{stat.label}</p>
              <p className={`text-2xl font-semibold ${stat.color}`}>{stat.value}</p>
            </CardBody>
          </Card>
        ))}
      </div>

      {/* Schedule Cards */}
      <div className="space-y-3">
        {schedules.length === 0 && (
          <div className="text-center py-12 text-neutral-400">
            <Truck className="w-12 h-12 mx-auto mb-3 opacity-30" />
            <p>No delivery schedules found</p>
          </div>
        )}

        {schedules.map((schedule: CombinedDeliverySchedule) => {
          const statusConfig = STATUS_CONFIG[schedule.status]
          const StatusIcon = statusConfig.icon

          return (
            <Card
              key={schedule.id}
              className="hover:shadow-md transition-shadow cursor-pointer"
              onClick={() => navigate(`/production/combined-delivery-schedules/${schedule.ulid}`)}
            >
              <CardBody>
                <div className="flex items-start justify-between">
                  <div className="flex-1">
                    <div className="flex items-center gap-3">
                      <h3 className="font-semibold text-neutral-900">{schedule.cds_reference}</h3>
                      <span className={`inline-flex items-center gap-1 px-2 py-0.5 rounded text-xs font-medium ${statusConfig.color}`}>
                        <StatusIcon className="w-3 h-3" />
                        {statusConfig.label}
                      </span>
                    </div>
                    
                    <p className="text-sm text-neutral-600 mt-1">
                      Order: {schedule.client_order?.order_reference}
                    </p>
                    
                    <div className="flex items-center gap-4 mt-3 text-sm text-neutral-500">
                      <span className="flex items-center gap-1">
                        <Calendar className="w-4 h-4" />
                        Target: {new Date(schedule.target_delivery_date).toLocaleDateString('en-PH')}
                      </span>
                      <span className="flex items-center gap-1">
                        <Package className="w-4 h-4" />
                        {schedule.ready_items}/{schedule.total_items} items ready
                      </span>
                    </div>

                    {/* Progress Bar */}
                    <div className="mt-3">
                      <div className="flex justify-between text-xs text-neutral-500 mb-1">
                        <span>Progress</span>
                        <span>{schedule.progress_percentage}%</span>
                      </div>
                      <div className="h-2 bg-neutral-100 rounded-full overflow-hidden">
                        <div
                          className={`h-full rounded-full transition-all ${
                            schedule.progress_percentage === 100 ? 'bg-green-500' :
                            schedule.progress_percentage >= 50 ? 'bg-blue-500' : 'bg-amber-500'
                          }`}
                          style={{ width: `${schedule.progress_percentage}%` }}
                        />
                      </div>
                    </div>
                  </div>

                  <div className="flex items-center gap-2 text-right">
                    <div>
                      <p className="font-medium text-neutral-900">{schedule.customer?.name}</p>
                      <p className="text-sm text-neutral-500">{schedule.customer?.email}</p>
                    </div>
                    <ChevronRight className="w-5 h-5 text-neutral-400" />
                  </div>
                </div>

                {/* Quick Actions */}
                {canManage && schedule.can_dispatch && (
                  <div className="mt-4 pt-4 border-t border-neutral-100 flex gap-2">
                    <button
                      onClick={(e) => {
                        e.stopPropagation()
                        navigate(`/production/combined-delivery-schedules/${schedule.ulid}?action=dispatch`)
                      }}
                      className="inline-flex items-center gap-1.5 px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded transition-colors"
                    >
                      <Truck className="w-4 h-4" />
                      Dispatch
                    </button>
                    {schedule.status === 'partially_ready' && (
                      <button
                        onClick={(e) => {
                          e.stopPropagation()
                          navigate(`/production/combined-delivery-schedules/${schedule.ulid}?action=notify`)
                        }}
                        className="inline-flex items-center gap-1.5 px-3 py-1.5 border border-amber-300 text-amber-700 hover:bg-amber-50 text-sm font-medium rounded transition-colors"
                      >
                        <AlertTriangle className="w-4 h-4" />
                        Notify Missing
                      </button>
                    )}
                  </div>
                )}
              </CardBody>
            </Card>
          )
        })}
      </div>
    </div>
  )
}
