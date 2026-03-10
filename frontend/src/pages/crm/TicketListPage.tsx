import { useState } from 'react'
import { Link } from 'react-router-dom'
import { useTickets } from '@/hooks/useCRM'
import type { TicketFilters } from '@/types/crm'

const STATUS_OPTIONS = ['', 'open', 'in_progress', 'resolved', 'closed']
const PRIORITY_OPTIONS = ['', 'low', 'normal', 'high', 'critical']
const TYPE_OPTIONS = ['', 'complaint', 'inquiry', 'request']

const statusBadge: Record<string, string> = {
  open: 'bg-neutral-100 text-neutral-700',
  in_progress: 'bg-yellow-100 text-yellow-800',
  resolved: 'bg-neutral-200 text-neutral-800',
  closed: 'bg-neutral-100 text-neutral-600',
}

const priorityBadge: Record<string, string> = {
  low: 'bg-neutral-100 text-neutral-600',
  normal: 'bg-neutral-100 text-neutral-700',
  high: 'bg-neutral-100 text-neutral-700',
  critical: 'bg-red-100 text-red-700',
}

export default function TicketListPage() {
  const [filters, setFilters] = useState<TicketFilters>({ per_page: 20 })
  const { data, isLoading } = useTickets(filters)

  function setFilter(key: keyof TicketFilters, value: string | number | undefined) {
    setFilters(prev => ({ ...prev, [key]: value || undefined, page: 1 }))
  }

  return (
    <div className="p-6">
      <div className="flex items-center justify-between mb-6">
        <h1 className="text-2xl font-bold">Support Tickets</h1>
        <Link to="/crm/tickets/new" className="px-4 py-2 bg-neutral-900 text-white rounded hover:bg-neutral-800 text-sm font-medium">
          New Ticket
        </Link>
      </div>

      {/* Filters */}
      <div className="flex flex-wrap gap-3 mb-6">
        <select
          value={filters.status ?? ''}
          onChange={e => setFilter('status', e.target.value)}
          className="px-3 py-2 border rounded text-sm focus:outline-none focus:ring-1 focus:ring-neutral-400"
        >
          {STATUS_OPTIONS.map(s => (
            <option key={s} value={s}>{s ? s.replace('_', ' ') : 'All Statuses'}</option>
          ))}
        </select>
        <select
          value={filters.priority ?? ''}
          onChange={e => setFilter('priority', e.target.value)}
          className="px-3 py-2 border rounded text-sm focus:outline-none focus:ring-1 focus:ring-neutral-400"
        >
          {PRIORITY_OPTIONS.map(p => (
            <option key={p} value={p}>{p || 'All Priorities'}</option>
          ))}
        </select>
        <select
          value={filters.type ?? ''}
          onChange={e => setFilter('type', e.target.value)}
          className="px-3 py-2 border rounded text-sm focus:outline-none focus:ring-1 focus:ring-neutral-400"
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
          className="px-3 py-2 border rounded text-sm focus:outline-none focus:ring-1 focus:ring-neutral-400"
        />
      </div>

      {/* Table */}
      <div className="bg-white rounded border border-neutral-200 overflow-hidden">
        {isLoading ? (
          <div className="p-8 text-center text-neutral-500">Loading tickets…</div>
        ) : (
          <table className="min-w-full divide-y divide-neutral-200">
            <thead className="bg-neutral-50">
              <tr>
                <th className="px-4 py-3 text-left text-xs font-medium text-neutral-500 uppercase">Ticket #</th>
                <th className="px-4 py-3 text-left text-xs font-medium text-neutral-500 uppercase">Subject</th>
                <th className="px-4 py-3 text-left text-xs font-medium text-neutral-500 uppercase">Type</th>
                <th className="px-4 py-3 text-left text-xs font-medium text-neutral-500 uppercase">Priority</th>
                <th className="px-4 py-3 text-left text-xs font-medium text-neutral-500 uppercase">Status</th>
                <th className="px-4 py-3 text-left text-xs font-medium text-neutral-500 uppercase">Assigned To</th>
                <th className="px-4 py-3 text-left text-xs font-medium text-neutral-500 uppercase">Created</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-neutral-100">
              {data?.data.map(ticket => (
                <tr key={ticket.ulid} className="hover:bg-neutral-50 transition-colors">
                  <td className="px-4 py-3 text-sm font-mono">
                    <Link to={`/crm/tickets/${ticket.ulid}`} className="text-neutral-700 hover:underline">
                      {ticket.ticket_number}
                    </Link>
                  </td>
                  <td className="px-4 py-3 text-sm max-w-xs truncate">{ticket.subject}</td>
                  <td className="px-4 py-3 text-sm capitalize">{ticket.type}</td>
                  <td className="px-4 py-3">
                    <span className={`text-xs px-2 py-0.5 rounded-full font-medium capitalize ${priorityBadge[ticket.priority] ?? ''}`}>
                      {ticket.priority}
                    </span>
                  </td>
                  <td className="px-4 py-3">
                    <span className={`text-xs px-2 py-0.5 rounded-full font-medium capitalize ${statusBadge[ticket.status] ?? ''}`}>
                      {ticket.status.replace('_', ' ')}
                    </span>
                  </td>
                  <td className="px-4 py-3 text-sm text-neutral-600">{ticket.assignedTo?.name ?? '—'}</td>
                  <td className="px-4 py-3 text-sm text-neutral-500">{new Date(ticket.created_at).toLocaleDateString()}</td>
                </tr>
              ))}
              {data?.data.length === 0 && (
                <tr><td colSpan={7} className="px-4 py-8 text-center text-neutral-500">No tickets found.</td></tr>
              )}
            </tbody>
          </table>
        )}

        {/* Pagination */}
        {data?.meta && data.meta.last_page > 1 && (
          <div className="px-4 py-3 border-t flex items-center justify-between text-sm text-neutral-600">
            <span>Page {data.meta.current_page} of {data.meta.last_page} ({data.meta.total} total)</span>
            <div className="flex gap-2">
              <button
                onClick={() => setFilter('page', (filters.page ?? 1) - 1)}
                disabled={(filters.page ?? 1) <= 1}
                className="px-3 py-1 border rounded disabled:opacity-40"
              >Prev</button>
              <button
                onClick={() => setFilter('page', (filters.page ?? 1) + 1)}
                disabled={(filters.page ?? 1) >= data.meta.last_page}
                className="px-3 py-1 border rounded disabled:opacity-40"
              >Next</button>
            </div>
          </div>
        )}
      </div>
    </div>
  )
}
