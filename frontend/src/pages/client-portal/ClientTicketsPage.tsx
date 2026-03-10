import { Link } from 'react-router-dom'
import { useTickets } from '@/hooks/useCRM'

const statusBadge: Record<string, string> = {
  open: 'bg-neutral-100 text-neutral-700',
  in_progress: 'bg-yellow-100 text-yellow-800',
  resolved: 'bg-neutral-200 text-neutral-800',
  closed: 'bg-neutral-100 text-neutral-600',
}

export default function ClientTicketsPage() {
  const { data, isLoading } = useTickets({ per_page: 20 })

  return (
    <div className="p-6">
      <div className="flex items-center justify-between mb-6">
        <h1 className="text-2xl font-bold">My Support Tickets</h1>
        <Link to="/client-portal/tickets/new" className="px-4 py-2 bg-neutral-900 text-white rounded hover:bg-neutral-800 text-sm font-medium">
          Submit New Ticket
        </Link>
      </div>

      {isLoading ? (
        <div className="text-center text-neutral-500 py-12">Loading…</div>
      ) : (
        <div className="space-y-3">
          {data?.data.length === 0 && (
            <div className="bg-white rounded border border-neutral-200 p-8 text-center text-neutral-400">
              No tickets yet. <Link to="/client-portal/tickets/new" className="text-neutral-700 hover:underline">Submit your first ticket</Link>.
            </div>
          )}
          {data?.data.map(ticket => (
            <Link key={ticket.ulid} to={`/client-portal/tickets/${ticket.ulid}`}
              className="block bg-white rounded border border-neutral-200 p-4 hover:border-neutral-300 hover:shadow-sm transition-all">
              <div className="flex items-start justify-between gap-4">
                <div>
                  <div className="text-xs font-mono text-neutral-400 mb-0.5">{ticket.ticket_number}</div>
                  <div className="font-medium text-neutral-900">{ticket.subject}</div>
                  <div className="text-sm text-neutral-500 mt-0.5 capitalize">{ticket.type}</div>
                </div>
                <div className="flex flex-col items-end gap-1 shrink-0">
                  <span className={`text-xs px-2 py-0.5 rounded-full font-medium capitalize ${statusBadge[ticket.status]}`}>
                    {ticket.status.replace('_', ' ')}
                  </span>
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
