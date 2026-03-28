import { useState } from 'react'
import { Link } from 'react-router-dom'
import { Plus, UserPlus } from 'lucide-react'
import { useLeads } from '@/hooks/useCRM'
import type { LeadFilters } from '@/types/crm'
import { PageHeader } from '@/components/ui/PageHeader'
import { Card } from '@/components/ui/Card'
import StatusBadge from '@/components/ui/StatusBadge'
import SkeletonLoader from '@/components/ui/SkeletonLoader'
import EmptyState from '@/components/ui/EmptyState'

const STATUS_OPTIONS = ['', 'new', 'contacted', 'qualified', 'converted', 'disqualified']
const SOURCE_OPTIONS = ['', 'website', 'referral', 'trade_show', 'cold_call', 'social_media', 'other']

export default function LeadListPage() {
  const [filters, setFilters] = useState<LeadFilters>({ per_page: 20 })
  const { data, isLoading } = useLeads(filters)
  const leads = data?.data ?? []

  function setFilter(key: keyof LeadFilters, value: string | number | undefined) {
    setFilters(prev => ({ ...prev, [key]: value || undefined, page: 1 }))
  }

  return (
    <div className="space-y-6">
      <PageHeader
        title="Leads"
        icon={<UserPlus className="w-5 h-5 text-neutral-600" />}
        actions={
          <Link to="/crm/leads/new" className="btn-primary">
            <Plus className="w-3.5 h-3.5" /> New Lead
          </Link>
        }
      />

      {/* Filters */}
      <Card className="p-4">
        <div className="flex flex-wrap gap-3">
          <select className="input-sm" value={filters.status ?? ''} onChange={e => setFilter('status', e.target.value)}>
            <option value="">All Statuses</option>
            {STATUS_OPTIONS.filter(Boolean).map(s => <option key={s} value={s}>{s.replace('_', ' ')}</option>)}
          </select>
          <select className="input-sm" value={filters.source ?? ''} onChange={e => setFilter('source', e.target.value)}>
            <option value="">All Sources</option>
            {SOURCE_OPTIONS.filter(Boolean).map(s => <option key={s} value={s}>{s.replace('_', ' ')}</option>)}
          </select>
          <input
            className="input-sm flex-1 min-w-[200px]"
            placeholder="Search company, contact, email..."
            value={filters.search ?? ''}
            onChange={e => setFilter('search', e.target.value)}
          />
        </div>
      </Card>

      {/* Table */}
      {isLoading ? <SkeletonLoader rows={8} /> : leads.length === 0 ? (
        <EmptyState title="No leads found" description="Create your first lead to start tracking prospects." />
      ) : (
        <Card className="overflow-hidden">
          <table className="w-full text-sm">
            <thead className="bg-neutral-50 dark:bg-neutral-800">
              <tr>
                <th className="text-left p-3 font-medium">Company</th>
                <th className="text-left p-3 font-medium">Contact</th>
                <th className="text-left p-3 font-medium">Source</th>
                <th className="text-left p-3 font-medium">Status</th>
                <th className="text-left p-3 font-medium">Assigned To</th>
                <th className="text-left p-3 font-medium">Created</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-neutral-100 dark:divide-neutral-800">
              {leads.map(lead => (
                <tr key={lead.id} className="hover:bg-neutral-50 dark:hover:bg-neutral-800/50">
                  <td className="p-3">
                    <Link to={`/crm/leads/${lead.ulid}`} className="font-medium text-blue-600 hover:underline">
                      {lead.company_name}
                    </Link>
                  </td>
                  <td className="p-3">{lead.contact_name}</td>
                  <td className="p-3 capitalize">{lead.source.replace('_', ' ')}</td>
                  <td className="p-3"><StatusBadge status={lead.status} /></td>
                  <td className="p-3 text-neutral-500">{lead.assignedTo?.name ?? '-'}</td>
                  <td className="p-3 text-neutral-500">{new Date(lead.created_at).toLocaleDateString()}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </Card>
      )}

      {/* Pagination */}
      {data?.meta && data.meta.last_page > 1 && (
        <div className="flex justify-center gap-2">
          {Array.from({ length: data.meta.last_page }, (_, i) => (
            <button
              key={i + 1}
              className={`px-3 py-1 rounded text-sm ${filters.page === i + 1 ? 'bg-neutral-900 text-white' : 'bg-neutral-100 hover:bg-neutral-200'}`}
              onClick={() => setFilters(p => ({ ...p, page: i + 1 }))}
            >
              {i + 1}
            </button>
          ))}
        </div>
      )}
    </div>
  )
}
