import { useState } from 'react'
import { Link } from 'react-router-dom'
import { Plus, Ticket } from 'lucide-react'
import { useTickets } from '@/hooks/useCRM'
import { useAuthStore } from '@/stores/authStore'
import { PERMISSIONS } from '@/lib/permissions'
import type { TicketFilters } from '@/types/crm'
import { PageHeader } from '@/components/ui/PageHeader'
import { Card } from '@/components/ui/Card'
import StatusBadge from '@/components/ui/StatusBadge'
import SkeletonLoader from '@/components/ui/SkeletonLoader'
import EmptyState from '@/components/ui/EmptyState'

const STATUS_OPTIONS = ['', 'open', 'in_progress', 'resolved', 'closed']
const PRIORITY_OPTIONS = ['', 'low', 'normal', 'high', 'critical']
const TYPE_OPTIONS = ['', 'complaint', 'inquiry', 'request']

const PRIORITY_MAP: Record<string, string> = {
  low: 'low',
  normal: 'low',
  high: 'high',
  critical: 'critical',
}

export default function TicketListPage() {
  const [filters, setFilters] = useState<TicketFilters>({ per_page: 20 })
  const { data, isLoading } = useTickets(filters)
  const canCreate = useAuthStore((s) => s.hasPermission(PERMISSIONS.crm.tickets.create))
  
  // Calculate summary stats
  const tickets = data?.data ?? []
  const openCount = tickets.filter(t => t.status === 'open').length
  const inProgressCount = tickets.filter(t => t.status === 'in_progress').length
  const criticalCount = tickets.filter(t => t.priority === 'critical').length

  function setFilter(key: keyof TicketFilters, value: string | number | undefined) {
    setFilters(prev => ({ ...prev, [key]: value || undefined, page: 1 }))
  }

  return (
    <div className="space-y-6">
      <PageHeader
        title="Support Tickets"
        icon={<Ticket className="w-5 h-5 text-neutral-600" />}
        actions={
          canCreate ? (
            <Link to="/crm/tickets/new" className="btn-primary">
              <Plus className="w-3.5 h-3.5" /> New Ticket
            </Link>
          ) : undefined
        }
      />

      {/* Summary Stats */}
      {!isLoading && tickets.length > 0 && (
        <div className="grid grid-cols-4 gap-4">
          <div className="bg-neutral-50 border border-neutral-200 rounded-xl p-4">
            <p className="text-xs font-medium text-neutral-500 uppercase tracking-wide">Total Tickets</p>
            <p className="text-lg font-semibold text-neutral-900 mt-1">{tickets.length}</p>
          </div>
          <div className="bg-blue-50 border border-blue-200 rounded-xl p-4">
            <p className="text-xs font-medium text-blue-600 uppercase tracking-wide">Open</p>
            <p className="text-lg font-semibold text-blue-700 mt-1">{openCount}</p>
          </div>
          <div className="bg-amber-50 border border-amber-200 rounded-xl p-4">
            <p className="text-xs font-medium text-amber-600 uppercase tracking-wide">In Progress</p>
            <p className="text-lg font-semibold text-amber-700 mt-1">{inProgressCount}</p>
          </div>
          <div className={`rounded-xl p-4 border ${criticalCount > 0 ? 'bg-red-50 border-red-200' : 'bg-emerald-50 border-emerald-200'}`}>
            <p className={`text-xs font-medium uppercase tracking-wide ${criticalCount > 0 ? 'text-red-600' : 'text-emerald-600'}`}>
              Critical Priority
            </p>
            <p className={`text-lg font-semibold mt-1 ${criticalCount > 0 ? 'text-red-700' : 'text-emerald-700'}`}>
              {criticalCount}
            </p>
          </div>
        </div>
      )}

      {/* Filters */}
      <div className="flex flex-wrap gap-3">
        <select
          value={filters.status ?? ''}
          onChange={e => setFilter('status', e.target.value)}
          className="border border-neutral-300 rounded px-3 py-1.5 text-sm focus:ring-1 focus:ring-neutral-400 outline-none"
        >
          {STATUS_OPTIONS.map(s => (
            <option key={s} value={s}>{s ? s.replace('_', ' ') : 'All Statuses'}</option>
          ))}
        </select>
        <select
          value={filters.priority ?? ''}
          onChange={e => setFilter('priority', e.target.value)}
          className="border border-neutral-300 rounded px-3 py-1.5 text-sm focus:ring-1 focus:ring-neutral-400 outline-none"
        >
          {PRIORITY_OPTIONS.map(p => (
            <option key={p} value={p}>{p || 'All Priorities'}</option>
          ))}
        </select>
        <select
          value={filters.type ?? ''}
          onChange={e => setFilter('type', e.target.value)}
          className="border border-neutral-300 rounded px-3 py-1.5 text-sm focus:ring-1 focus:ring-neutral-400 outline-none"
        >
          {TYPE_OPTIONS.map(t => (
            <option key={t} value={t}>{t || 'All Types'}</option>
          ))}
        </select>
        <input
          type="search"
          placeholder="Search subject…"
          value={filters.search ?? ''}
          onChange={e => setFilter('search', e.target.value)}
          className="border border-neutral-300 rounded px-3 py-1.5 text-sm focus:ring-1 focus:ring-neutral-400 outline-none"
        />
      </div>

      {/* Table */}
      <Card>
        {isLoading ? (
          <SkeletonLoader rows={8} />
        ) : (data?.data?.length ?? 0) === 0 ? (
          <EmptyState
            title="No tickets found"
            description="Create a new ticket to get started."
            action={
              canCreate ? (
                <Link to="/crm/tickets/new" className="btn-primary">
                  <Plus className="w-3.5 h-3.5" /> New Ticket
                </Link>
              ) : undefined
            }
          />
        ) : (
          <table className="min-w-full divide-y divide-neutral-200">
            <thead className="bg-neutral-50">
              <tr>
                <th className="px-4 py-3 text-left text-xs font-semibold text-neutral-500 uppercase tracking-wider">Ticket #</th>
                <th className="px-4 py-3 text-left text-xs font-semibold text-neutral-500 uppercase tracking-wider">Subject</th>
                <th className="px-4 py-3 text-left text-xs font-semibold text-neutral-500 uppercase tracking-wider">Type</th>
                <th className="px-4 py-3 text-left text-xs font-semibold text-neutral-500 uppercase tracking-wider">Priority</th>
                <th className="px-4 py-3 text-left text-xs font-semibold text-neutral-500 uppercase tracking-wider">Status</th>
                <th className="px-4 py-3 text-left text-xs font-semibold text-neutral-500 uppercase tracking-wider">Assigned To</th>
                <th className="px-4 py-3 text-left text-xs font-semibold text-neutral-500 uppercase tracking-wider">Created</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-neutral-100">
              {(data?.data ?? []).map(ticket => (
                <tr key={ticket.ulid} className={`even:bg-neutral-50 hover:bg-neutral-50 transition-colors ${
                  ticket.priority === 'critical' ? 'bg-red-50/50' : ''
                }`}>
                  <td className="px-4 py-3 text-sm font-mono">
                    <Link to={`/crm/tickets/${ticket.ulid}`} className="text-blue-600 hover:text-blue-800 hover:underline font-medium">
                      {ticket.ticket_number}
                    </Link>
                  </td>
                  <td className="px-4 py-3 text-sm max-w-xs truncate">
                    <span className={`font-medium ${ticket.priority === 'critical' ? 'text-red-700' : 'text-neutral-900'}`}>
                      {ticket.subject}
                    </span>
                  </td>
                  <td className="px-4 py-3 text-sm capitalize text-neutral-600">{ticket.type}</td>
                  <td className="px-4 py-3">
                    <StatusBadge status={PRIORITY_MAP[ticket.priority]}>
                      {ticket.priority}
                    </StatusBadge>
                  </td>
                  <td className="px-4 py-3">
                    <StatusBadge status={ticket.status}>
                      {ticket.status.replace('_', ' ')}
                    </StatusBadge>
                  </td>
                  <td className="px-4 py-3 text-sm">
                    <span className="font-medium text-neutral-700">{ticket.assignedTo?.name ?? '—'}</span>
                  </td>
                  <td className="px-4 py-3 text-sm text-neutral-500">{new Date(ticket.created_at).toLocaleDateString()}</td>
                </tr>
              ))}
            </tbody>
          </table>
        )}

        {/* Pagination */}
        {data?.meta && data.meta.last_page > 1 && (
          <div className="px-4 py-3 border-t border-neutral-100 flex items-center justify-between text-sm text-neutral-600">
            <span>Page {data.meta.current_page} of {data.meta.last_page} ({data.meta.total} total)</span>
            <div className="flex gap-2">
              <button
                onClick={() => setFilter('page', (filters.page ?? 1) - 1)}
                disabled={(filters.page ?? 1) <= 1}
                className="px-3 py-1 border border-neutral-200 rounded hover:bg-neutral-50 disabled:opacity-40 transition-colors"
              >Prev</button>
              <button
                onClick={() => setFilter('page', (filters.page ?? 1) + 1)}
                disabled={(filters.page ?? 1) >= data.meta.last_page}
                className="px-3 py-1 border border-neutral-200 rounded hover:bg-neutral-50 disabled:opacity-40 transition-colors"
              >Next</button>
            </div>
          </div>
        )}
      </Card>
    </div>
  )
}
