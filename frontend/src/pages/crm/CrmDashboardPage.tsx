import { BarChart3, AlertTriangle, CheckCircle, Clock } from 'lucide-react'
import { useQuery } from '@tanstack/react-query'
import api from '@/lib/api'
import { PageHeader } from '@/components/ui/PageHeader'
import { Card, CardBody, CardHeader } from '@/components/ui/Card'
import SkeletonLoader from '@/components/ui/SkeletonLoader'

interface CrmDashboardData {
  open_tickets: number
  in_progress_tickets: number
  resolved_today: number
  sla_compliance_pct: number
  sla_breached_count: number
  avg_resolution_hours: number
  tickets_by_priority: { priority: string; count: number }[]
  recent_breaches: { id: number; ulid: string; ticket_number: string; subject: string; created_at: string }[]
}

function useCrmDashboard() {
  return useQuery({
    queryKey: ['crm-dashboard'],
    queryFn: async () => {
      const res = await api.get<{ data: CrmDashboardData }>('/crm/dashboard')
      return res.data.data
    },
  })
}

export default function CrmDashboardPage(): React.ReactElement {
  const { data, isLoading } = useCrmDashboard()

  if (isLoading) return <SkeletonLoader rows={8} />

  const d = data ?? {
    open_tickets: 0, in_progress_tickets: 0, resolved_today: 0,
    sla_compliance_pct: 0, sla_breached_count: 0, avg_resolution_hours: 0,
    tickets_by_priority: [], recent_breaches: [],
  }

  return (
    <div className="max-w-6xl mx-auto space-y-6">
      <PageHeader
        title="CRM / Helpdesk Dashboard"
        icon={<BarChart3 className="w-5 h-5 text-neutral-600" />}
      />

      {/* KPIs */}
      <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
        <Card>
          <CardBody className="py-4">
            <div className="text-xs font-medium text-neutral-500 uppercase tracking-wide">Open Tickets</div>
            <div className="text-lg font-semibold text-neutral-900 mt-1">{d.open_tickets}</div>
          </CardBody>
        </Card>
        <Card>
          <CardBody className="py-4">
            <div className="text-xs font-medium text-neutral-500 uppercase tracking-wide">In Progress</div>
            <div className="text-lg font-semibold text-blue-600 mt-1">{d.in_progress_tickets}</div>
          </CardBody>
        </Card>
        <Card>
          <CardBody className="py-4">
            <div className="text-xs font-medium text-neutral-500 uppercase tracking-wide">Resolved Today</div>
            <div className="text-lg font-semibold text-emerald-600 mt-1 flex items-center gap-1">
              <CheckCircle className="w-5 h-5" /> {d.resolved_today}
            </div>
          </CardBody>
        </Card>
        <Card>
          <CardBody className="py-4">
            <div className="text-xs font-medium text-neutral-500 uppercase tracking-wide">Avg Resolution</div>
            <div className="text-lg font-semibold text-neutral-700 mt-1 flex items-center gap-1">
              <Clock className="w-5 h-5" /> {d.avg_resolution_hours}h
            </div>
          </CardBody>
        </Card>
      </div>

      {/* SLA + Priority */}
      <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
        {/* SLA Compliance */}
        <Card>
          <CardHeader>SLA Compliance</CardHeader>
          <CardBody>
            <div className="flex items-center gap-4">
              <div className={`text-4xl font-bold ${d.sla_compliance_pct >= 90 ? 'text-emerald-600' : d.sla_compliance_pct >= 70 ? 'text-amber-600' : 'text-red-600'}`}>
                {d.sla_compliance_pct}%
              </div>
              <div className="flex-1">
                <div className="w-full bg-neutral-100 rounded-full h-3 overflow-hidden">
                  <div
                    className={`h-3 rounded-full transition-all ${d.sla_compliance_pct >= 90 ? 'bg-emerald-500' : d.sla_compliance_pct >= 70 ? 'bg-amber-500' : 'bg-red-500'}`}
                    style={{ width: `${Math.min(d.sla_compliance_pct, 100)}%` }}
                  />
                </div>
                <p className="text-xs text-neutral-500 mt-1">{d.sla_breached_count} breaches</p>
              </div>
            </div>
          </CardBody>
        </Card>

        {/* By Priority */}
        <Card>
          <CardHeader>Tickets by Priority</CardHeader>
          <CardBody>
            <div className="space-y-2">
              {d.tickets_by_priority.map((p) => (
                <div key={p.priority} className="flex items-center justify-between">
                  <span className="text-sm capitalize text-neutral-600">{p.priority}</span>
                  <span className="text-sm font-bold text-neutral-900">{p.count}</span>
                </div>
              ))}
              {d.tickets_by_priority.length === 0 && (
                <p className="text-sm text-neutral-400">No open tickets.</p>
              )}
            </div>
          </CardBody>
        </Card>
      </div>

      {/* Recent SLA Breaches */}
      {d.recent_breaches.length > 0 && (
        <Card className="border-red-200">
          <CardHeader>
            <span className="flex items-center gap-1.5 text-red-700">
              <AlertTriangle className="w-4 h-4" /> Recent SLA Breaches
            </span>
          </CardHeader>
          <CardBody>
            <div className="divide-y divide-neutral-100">
              {d.recent_breaches.map((b) => (
                <div key={b.id} className="py-2 flex items-center justify-between">
                  <div>
                    <span className="text-xs font-mono text-neutral-400 mr-2">#{b.ticket_number}</span>
                    <span className="text-sm text-neutral-700">{b.subject}</span>
                  </div>
                  <span className="text-xs text-neutral-400">{b.created_at}</span>
                </div>
              ))}
            </div>
          </CardBody>
        </Card>
      )}
    </div>
  )
}
