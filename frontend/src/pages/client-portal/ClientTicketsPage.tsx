import { Link } from 'react-router-dom'
import { Plus, Ticket } from 'lucide-react'
import { useTickets } from '@/hooks/useCRM'
import { PageHeader } from '@/components/ui/PageHeader'
import StatusBadge from '@/components/ui/StatusBadge'
import SkeletonLoader from '@/components/ui/SkeletonLoader'
import EmptyState from '@/components/ui/EmptyState'

export default function ClientTicketsPage() {
  const { data, isLoading } = useTickets({ per_page: 20 })
  
  const tickets = data?.data ?? []
  const openCount = tickets.filter(t => t.status === 'open').length
  const inProgressCount = tickets.filter(t => t.status === 'in_progress').length
  const resolvedCount = tickets.filter(t => t.status === 'resolved' || t.status === 'closed').length

  return (
    <div className="space-y-6">
      <PageHeader
        title="My Support Tickets"
        icon={<Ticket className="w-5 h-5 text-neutral-600" />}
        actions={
          <Link to="/client-portal/tickets/new" className="btn-primary">
            <Plus className="w-3.5 h-3.5" /> Submit New Ticket
          </Link>
        }
      />

      {/* Summary Stats */}
      {!isLoading && tickets.length > 0 && (
        <div className="grid grid-cols-3 gap-4">
          <div className="bg-blue-50 border border-blue-200 rounded-xl p-4">
            <p className="text-xs font-medium text-blue-600 uppercase tracking-wide">Open</p>
            <p className="text-2xl font-bold text-blue-700 mt-1">{openCount}</p>
          </div>
          <div className="bg-amber-50 border border-amber-200 rounded-xl p-4">
            <p className="text-xs font-medium text-amber-600 uppercase tracking-wide">In Progress</p>
            <p className="text-2xl font-bold text-amber-700 mt-1">{inProgressCount}</p>
          </div>
          <div className="bg-emerald-50 border border-emerald-200 rounded-xl p-4">
            <p className="text-xs font-medium text-emerald-600 uppercase tracking-wide">Resolved</p>
            <p className="text-2xl font-bold text-emerald-700 mt-1">{resolvedCount}</p>
          </div>
        </div>
      )}

      {isLoading ? (
        <SkeletonLoader rows={4} />
      ) : (data?.data ?? []).length === 0 ? (
        <EmptyState
          title="No tickets yet"
          description="Submit your first ticket to get support."
          action={
            <Link to="/client-portal/tickets/new" className="btn-primary">
              <Plus className="w-3.5 h-3.5" /> Submit Ticket
            </Link>
          }
        />
      ) : (
        <div className="space-y-3">
          {tickets.map(ticket => (
            <Link key={ticket.ulid} to={`/client-portal/tickets/${ticket.ulid}`}
              className={`block bg-white rounded-xl border p-4 hover:shadow-sm transition-all ${
                ticket.status === 'open' ? 'border-blue-200 hover:border-blue-300' :
                ticket.status === 'in_progress' ? 'border-amber-200 hover:border-amber-300' :
                'border-neutral-200 hover:border-neutral-300'
              }`}>
              <div className="flex items-start justify-between gap-4">
                <div>
                  <div className="text-xs font-mono text-neutral-400 mb-0.5">{ticket.ticket_number}</div>
                  <div className={`font-semibold ${
                    ticket.priority === 'critical' ? 'text-red-700' : 
                    ticket.status === 'resolved' ? 'text-emerald-700' :
                    'text-neutral-900'
                  }`}>{ticket.subject}</div>
                  <div className="text-sm text-neutral-500 mt-0.5 capitalize">{ticket.type}</div>
                </div>
                <div className="flex flex-col items-end gap-1 shrink-0">
                  <StatusBadge status={ticket.status}>
                    {ticket.status.replace('_', ' ')}
                  </StatusBadge>
                  <span className="text-xs text-neutral-400">{new Date(ticket.created_at).toLocaleDateString()}</span>
                </div>
              </div>
            </Link>
          ))}
        </div>
      )}
    </div>
  )
}
