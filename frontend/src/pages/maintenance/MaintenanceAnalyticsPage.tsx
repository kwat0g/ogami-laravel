import { Activity } from 'lucide-react'
import { useAllEquipmentMetrics, useEquipmentCosts } from '@/hooks/useMaintenanceAnalytics'
import { PageHeader } from '@/components/ui/PageHeader'
import { Card } from '@/components/ui/Card'
import SkeletonLoader from '@/components/ui/SkeletonLoader'

function fmt(c: number) { return new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' }).format(c / 100) }

export default function MaintenanceAnalyticsPage() {
  const { data: metrics, isLoading: metricsLoading } = useAllEquipmentMetrics()
  const { data: costs, isLoading: costsLoading } = useEquipmentCosts()

  return (
    <div className="space-y-6">
      <PageHeader title="Equipment Reliability & Cost Analytics" icon={<Activity className="w-5 h-5 text-neutral-600" />} />
      <h3 className="font-semibold text-lg">Equipment Reliability (MTBF/MTTR)</h3>
      {metricsLoading ? <SkeletonLoader rows={5} /> : (
        <Card className="overflow-hidden">
          <table className="w-full text-sm">
            <thead className="bg-neutral-50 dark:bg-neutral-800">
              <tr><th className="text-left p-3">Equipment</th><th className="text-right p-3">MTBF (hrs)</th><th className="text-right p-3">MTTR (hrs)</th><th className="text-right p-3">Failures</th><th className="text-right p-3">Availability %</th></tr>
            </thead>
            <tbody className="divide-y divide-neutral-100 dark:divide-neutral-800">
              {(metrics ?? []).map((m: any) => (
                <tr key={m.equipment_id}>
                  <td className="p-3"><span className="font-medium">{m.equipment_code}</span> - {m.equipment_name}</td>
                  <td className="p-3 text-right">{m.mtbf_hours}</td>
                  <td className="p-3 text-right">{m.mttr_hours}</td>
                  <td className="p-3 text-right">{m.total_failures}</td>
                  <td className="p-3 text-right"><span className={m.availability_pct >= 90 ? 'text-green-600' : 'text-red-600'}>{m.availability_pct}%</span></td>
                </tr>
              ))}
            </tbody>
          </table>
        </Card>
      )}
      <h3 className="font-semibold text-lg mt-8">Maintenance Cost per Equipment</h3>
      {costsLoading ? <SkeletonLoader rows={5} /> : (
        <Card className="overflow-hidden">
          <table className="w-full text-sm">
            <thead className="bg-neutral-50 dark:bg-neutral-800">
              <tr><th className="text-left p-3">Equipment</th><th className="text-right p-3">Labor Cost</th><th className="text-right p-3">Parts Cost</th><th className="text-right p-3">Total</th><th className="text-right p-3">WOs</th></tr>
            </thead>
            <tbody className="divide-y divide-neutral-100 dark:divide-neutral-800">
              {(costs ?? []).map((c: any) => (
                <tr key={c.equipment_id}>
                  <td className="p-3"><span className="font-medium">{c.equipment_code}</span> - {c.equipment_name}</td>
                  <td className="p-3 text-right font-mono">{fmt(c.labor_cost_centavos)}</td>
                  <td className="p-3 text-right font-mono">{fmt(c.parts_cost_centavos)}</td>
                  <td className="p-3 text-right font-mono font-bold">{fmt(c.total_cost_centavos)}</td>
                  <td className="p-3 text-right">{c.work_order_count}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </Card>
      )}
    </div>
  )
}
