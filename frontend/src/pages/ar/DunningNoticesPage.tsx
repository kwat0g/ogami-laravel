import { useState } from 'react'
import { AlertTriangle, Send } from 'lucide-react'
import { useDunningNotices, useGenerateDunning } from '@/hooks/useDunning'
import { PageHeader } from '@/components/ui/PageHeader'
import { Card } from '@/components/ui/Card'
import StatusBadge from '@/components/ui/StatusBadge'
import SkeletonLoader from '@/components/ui/SkeletonLoader'
import EmptyState from '@/components/ui/EmptyState'

function fmt(c: number) { return new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' }).format(c / 100) }

export default function DunningNoticesPage() {
  const [filters, setFilters] = useState<Record<string, unknown>>({ per_page: 20 })
  const { data, isLoading } = useDunningNotices(filters)
  const generateMut = useGenerateDunning()
  const notices = data?.data ?? []

  return (
    <div className="space-y-6">
      <PageHeader
        title="Collection Reminders"
        icon={<AlertTriangle className="w-5 h-5 text-neutral-600" />}
        actions={
          <button className="btn-primary" onClick={() => generateMut.mutate()} disabled={generateMut.isPending}>
            <Send className="w-3.5 h-3.5" /> Auto-Generate Overdue Reminders
          </button>
        }
      />
      {generateMut.isSuccess && (
        <div className="p-3 bg-green-50 border border-green-200 rounded text-sm text-green-700">
          {/* eslint-disable-next-line @typescript-eslint/no-explicit-any */}
          Generated {(generateMut.data as any)?.generated_count ?? 0} new notices
        </div>
      )}
      <Card className="p-4">
        <select className="input-sm" value={(filters.status as string) ?? ''} onChange={e => setFilters(p => ({ ...p, status: e.target.value || undefined }))}>
          <option value="">All Statuses</option>
          {['generated','sent','acknowledged','escalated','resolved'].map(s => <option key={s} value={s}>{s}</option>)}
        </select>
      </Card>
      {isLoading ? <SkeletonLoader rows={6} /> : notices.length === 0 ? <EmptyState title="No dunning notices" description="Click Generate to create notices for overdue invoices." /> : (
        <Card className="overflow-hidden">
          <table className="w-full text-sm">
            <thead className="bg-neutral-50 dark:bg-neutral-800">
              <tr><th className="text-left p-3">Customer</th><th className="text-left p-3">Level</th><th className="text-right p-3">Amount Due</th><th className="text-right p-3">Days Overdue</th><th className="text-left p-3">Status</th><th className="text-left p-3">Sent</th></tr>
            </thead>
            <tbody className="divide-y divide-neutral-100 dark:divide-neutral-800">
              {/* eslint-disable-next-line @typescript-eslint/no-explicit-any */}
              {notices.map((n: any) => (
                <tr key={n.id} className="hover:bg-neutral-50 dark:hover:bg-neutral-800/50">
                  <td className="p-3">{n.customer?.name ?? '-'}</td>
                  <td className="p-3">{n.dunningLevel?.name ?? `Level ${n.dunning_level_id}`}</td>
                  <td className="p-3 text-right font-mono">{fmt(n.amount_due_centavos)}</td>
                  <td className="p-3 text-right text-red-600 font-medium">{n.days_overdue}</td>
                  <td className="p-3"><StatusBadge status={n.status} /></td>
                  <td className="p-3 text-neutral-500">{n.sent_at ? new Date(n.sent_at).toLocaleDateString() : '-'}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </Card>
      )}
    </div>
  )
}
