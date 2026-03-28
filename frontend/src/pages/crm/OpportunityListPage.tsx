import { useState } from 'react'
import { Link } from 'react-router-dom'
import { Plus, TrendingUp } from 'lucide-react'
import { useOpportunities, usePipelineSummary } from '@/hooks/useCRM'
import type { OpportunityFilters } from '@/types/crm'
import { PageHeader } from '@/components/ui/PageHeader'
import { Card } from '@/components/ui/Card'
import StatusBadge from '@/components/ui/StatusBadge'
import SkeletonLoader from '@/components/ui/SkeletonLoader'
import EmptyState from '@/components/ui/EmptyState'

const STAGE_OPTIONS = ['', 'prospecting', 'qualification', 'proposal', 'negotiation', 'closed_won', 'closed_lost']

function formatCentavos(c: number) {
  return new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' }).format(c / 100)
}

export default function OpportunityListPage() {
  const [filters, setFilters] = useState<OpportunityFilters>({ per_page: 20 })
  const { data, isLoading } = useOpportunities(filters)
  const { data: pipeline } = usePipelineSummary()
  const opportunities = data?.data ?? []

  return (
    <div className="space-y-6">
      <PageHeader
        title="Sales Pipeline"
        icon={<TrendingUp className="w-5 h-5 text-neutral-600" />}
        actions={
          <Link to="/crm/opportunities/new" className="btn-primary">
            <Plus className="w-3.5 h-3.5" /> New Opportunity
          </Link>
        }
      />

      {/* Pipeline Summary */}
      {pipeline && pipeline.length > 0 && (
        <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
          {pipeline.map(p => (
            <Card key={p.stage} className="p-4">
              <p className="text-xs font-medium text-neutral-500 uppercase tracking-wide capitalize">{p.stage.replace('_', ' ')}</p>
              <p className="text-xl font-bold mt-1">{p.count}</p>
              <p className="text-sm text-neutral-500">{formatCentavos(p.weighted_centavos)} weighted</p>
            </Card>
          ))}
        </div>
      )}

      {/* Filters */}
      <Card className="p-4">
        <div className="flex flex-wrap gap-3">
          <select className="input-sm" value={filters.stage ?? ''} onChange={e => setFilters(p => ({ ...p, stage: e.target.value || undefined, page: 1 }))}>
            <option value="">All Stages</option>
            {STAGE_OPTIONS.filter(Boolean).map(s => <option key={s} value={s}>{s.replace('_', ' ')}</option>)}
          </select>
        </div>
      </Card>

      {/* Table */}
      {isLoading ? <SkeletonLoader rows={8} /> : opportunities.length === 0 ? (
        <EmptyState title="No opportunities" description="Create opportunities to track your sales pipeline." />
      ) : (
        <Card className="overflow-hidden">
          <table className="w-full text-sm">
            <thead className="bg-neutral-50 dark:bg-neutral-800">
              <tr>
                <th className="text-left p-3 font-medium">Title</th>
                <th className="text-left p-3 font-medium">Customer</th>
                <th className="text-left p-3 font-medium">Stage</th>
                <th className="text-right p-3 font-medium">Value</th>
                <th className="text-right p-3 font-medium">Probability</th>
                <th className="text-left p-3 font-medium">Close Date</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-neutral-100 dark:divide-neutral-800">
              {opportunities.map(opp => (
                <tr key={opp.id} className="hover:bg-neutral-50 dark:hover:bg-neutral-800/50">
                  <td className="p-3">
                    <Link to={`/crm/opportunities/${opp.ulid}`} className="font-medium text-blue-600 hover:underline">
                      {opp.title}
                    </Link>
                  </td>
                  <td className="p-3">{opp.customer?.name ?? '-'}</td>
                  <td className="p-3"><StatusBadge status={opp.stage} /></td>
                  <td className="p-3 text-right font-mono">{formatCentavos(opp.expected_value_centavos)}</td>
                  <td className="p-3 text-right">{opp.probability_pct}%</td>
                  <td className="p-3 text-neutral-500">{opp.expected_close_date ? new Date(opp.expected_close_date).toLocaleDateString() : '-'}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </Card>
      )}
    </div>
  )
}
