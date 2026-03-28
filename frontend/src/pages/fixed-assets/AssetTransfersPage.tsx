import { ArrowRightLeft } from 'lucide-react'
import { useQuery } from '@tanstack/react-query'
import api from '@/lib/api'
import { PageHeader } from '@/components/ui/PageHeader'
import { Card } from '@/components/ui/Card'
import StatusBadge from '@/components/ui/StatusBadge'
import SkeletonLoader from '@/components/ui/SkeletonLoader'
import EmptyState from '@/components/ui/EmptyState'
import type { AssetTransfer } from '@/types/fixed_assets'

export default function AssetTransfersPage() {
  const { data, isLoading } = useQuery({
    queryKey: ['asset-transfers'],
    queryFn: async () => { const { data } = await api.get<{ data: AssetTransfer[] }>('/fixed-assets/transfers'); return data },
  })
  const transfers = data?.data ?? []

  return (
    <div className="space-y-6">
      <PageHeader title="Asset Department Transfers" icon={<ArrowRightLeft className="w-5 h-5 text-neutral-600" />} />
      {isLoading ? <SkeletonLoader rows={5} /> : transfers.length === 0 ? <EmptyState title="No asset transfers" /> : (
        <Card className="overflow-hidden">
          <table className="w-full text-sm">
            <thead className="bg-neutral-50 dark:bg-neutral-800">
              <tr><th className="text-left p-3">Asset</th><th className="text-left p-3">From Dept</th><th className="text-left p-3">To Dept</th><th className="text-left p-3">Date</th><th className="text-left p-3">Status</th><th className="text-left p-3">Requested By</th></tr>
            </thead>
            <tbody className="divide-y divide-neutral-100 dark:divide-neutral-800">
              {transfers.map((t: AssetTransfer) => (
                <tr key={t.id} className="hover:bg-neutral-50 dark:hover:bg-neutral-800/50">
                  <td className="p-3 font-medium">{t.fixed_asset?.name ?? `Asset #${t.fixed_asset_id}`}</td>
                  <td className="p-3">{t.from_department?.name ?? `Dept #${t.from_department_id}`}</td>
                  <td className="p-3">{t.to_department?.name ?? `Dept #${t.to_department_id}`}</td>
                  <td className="p-3">{new Date(t.transfer_date).toLocaleDateString()}</td>
                  <td className="p-3"><StatusBadge status={t.status} /></td>
                  <td className="p-3">{t.requested_by?.name ?? '-'}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </Card>
      )}
    </div>
  )
}
