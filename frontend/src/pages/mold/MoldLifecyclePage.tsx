import { BarChart3 } from 'lucide-react'
import { useQuery } from '@tanstack/react-query'
import api from '@/lib/api'
import { PageHeader } from '@/components/ui/PageHeader'
import { Card } from '@/components/ui/Card'
import SkeletonLoader from '@/components/ui/SkeletonLoader'

function fmt(c: number) { return new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' }).format(c / 100) }

export default function MoldLifecyclePage() {
  const { data, isLoading } = useQuery({
    queryKey: ['mold-lifecycle'],
    queryFn: async () => { const { data } = await api.get('/mold/analytics/lifecycle'); return data.data },
  })

  return (
    <div className="space-y-6">
      <PageHeader title="Mold Cost Amortization & Lifecycle" icon={<BarChart3 className="w-5 h-5 text-neutral-600" />} />
      {isLoading ? <SkeletonLoader rows={6} /> : (
        <Card className="overflow-hidden">
          <table className="w-full text-sm">
            <thead className="bg-neutral-50 dark:bg-neutral-800">
              <tr>
                <th className="text-left p-3">Mold</th><th className="text-right p-3">Cost</th>
                <th className="text-right p-3">Current Shots</th><th className="text-right p-3">Expected Total</th>
                <th className="text-right p-3">Cost/Shot</th><th className="text-right p-3">Life Remaining</th>
                <th className="text-right p-3">Remaining Value</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-neutral-100 dark:divide-neutral-800">
              {/* eslint-disable-next-line @typescript-eslint/no-explicit-any */}
              {(data ?? []).map((m: any) => (
                <tr key={m.mold_id} className="hover:bg-neutral-50 dark:hover:bg-neutral-800/50">
                  <td className="p-3"><span className="font-medium">{m.mold_code}</span> - {m.mold_name}</td>
                  <td className="p-3 text-right font-mono">{fmt(m.cost_centavos)}</td>
                  <td className="p-3 text-right">{m.current_shots?.toLocaleString()}</td>
                  <td className="p-3 text-right">{m.expected_total_shots?.toLocaleString() ?? '-'}</td>
                  <td className="p-3 text-right font-mono">{fmt(m.cost_per_shot_centavos)}</td>
                  <td className="p-3 text-right">
                    {m.life_remaining_pct !== null ? (
                      <span className={m.life_remaining_pct < 20 ? 'text-red-600 font-bold' : m.life_remaining_pct < 50 ? 'text-yellow-600' : 'text-green-600'}>
                        {m.life_remaining_pct}%
                      </span>
                    ) : '-'}
                  </td>
                  <td className="p-3 text-right font-mono">{fmt(m.remaining_value_centavos)}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </Card>
      )}
    </div>
  )
}
