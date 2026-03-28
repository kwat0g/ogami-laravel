import { MapPin } from 'lucide-react'
import { useQuery } from '@tanstack/react-query'
import api from '@/lib/api'
import { PageHeader } from '@/components/ui/PageHeader'
import { Card } from '@/components/ui/Card'
import StatusBadge from '@/components/ui/StatusBadge'
import SkeletonLoader from '@/components/ui/SkeletonLoader'
import EmptyState from '@/components/ui/EmptyState'

export default function DeliveryRoutesPage() {
  const { data, isLoading } = useQuery({
    queryKey: ['delivery-routes'],
    queryFn: async () => { const { data } = await api.get('/delivery/routes'); return data },
  })
  const routes = data?.data ?? []

  return (
    <div className="space-y-6">
      <PageHeader title="Delivery Routes" icon={<MapPin className="w-5 h-5 text-neutral-600" />} />
      {isLoading ? <SkeletonLoader rows={5} /> : routes.length === 0 ? <EmptyState title="No delivery routes" /> : (
        <Card className="overflow-hidden">
          <table className="w-full text-sm">
            <thead className="bg-neutral-50 dark:bg-neutral-800">
              <tr><th className="text-left p-3">Route #</th><th className="text-left p-3">Planned Date</th><th className="text-left p-3">Status</th><th className="text-right p-3">Stops</th></tr>
            </thead>
            <tbody className="divide-y">
              {routes.map((r: any) => (
                <tr key={r.id} className="hover:bg-neutral-50 dark:hover:bg-neutral-800/50">
                  <td className="p-3 font-medium">{r.route_number}</td>
                  <td className="p-3">{new Date(r.planned_date).toLocaleDateString()}</td>
                  <td className="p-3"><StatusBadge status={r.status} /></td>
                  <td className="p-3 text-right">{r.stop_count}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </Card>
      )}
    </div>
  )
}
