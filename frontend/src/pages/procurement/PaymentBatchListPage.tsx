import { useState } from 'react'
import { DollarSign } from 'lucide-react'
import { usePaymentBatches } from '@/hooks/usePaymentBatches'
import { PageHeader } from '@/components/ui/PageHeader'
import { Card } from '@/components/ui/Card'
import StatusBadge from '@/components/ui/StatusBadge'
import SkeletonLoader from '@/components/ui/SkeletonLoader'
import EmptyState from '@/components/ui/EmptyState'

function fmt(c: number) { return new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' }).format(c / 100) }

export default function PaymentBatchListPage() {
  const [filters, setFilters] = useState<Record<string, unknown>>({ per_page: 20 })
  const { data, isLoading } = usePaymentBatches(filters)
  const batches = data?.data ?? []

  return (
    <div className="space-y-6">
      <PageHeader title="Vendor Payment Batches" icon={<DollarSign className="w-5 h-5 text-neutral-600" />} />
      <Card className="p-4">
        <select className="input-sm" value={(filters.status as string) ?? ''} onChange={e => setFilters(p => ({ ...p, status: e.target.value || undefined }))}>
          <option value="">All Statuses</option>
          {['draft','submitted','approved','processing','completed','cancelled'].map(s => <option key={s} value={s}>{s}</option>)}
        </select>
      </Card>
      {isLoading ? <SkeletonLoader rows={6} /> : batches.length === 0 ? <EmptyState title="No payment batches" /> : (
        <Card className="overflow-hidden">
          <table className="w-full text-sm">
            <thead className="bg-neutral-50 dark:bg-neutral-800">
              <tr><th className="text-left p-3">Batch #</th><th className="text-left p-3">Status</th><th className="text-left p-3">Payment Date</th><th className="text-right p-3">Total</th><th className="text-right p-3">Count</th></tr>
            </thead>
            <tbody className="divide-y divide-neutral-100 dark:divide-neutral-800">
              {batches.map((b: any) => (
                <tr key={b.id} className="hover:bg-neutral-50 dark:hover:bg-neutral-800/50">
                  <td className="p-3 font-medium">{b.batch_number}</td>
                  <td className="p-3"><StatusBadge status={b.status} /></td>
                  <td className="p-3">{new Date(b.payment_date).toLocaleDateString()}</td>
                  <td className="p-3 text-right font-mono">{fmt(b.total_amount_centavos)}</td>
                  <td className="p-3 text-right">{b.payment_count}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </Card>
      )}
    </div>
  )
}
