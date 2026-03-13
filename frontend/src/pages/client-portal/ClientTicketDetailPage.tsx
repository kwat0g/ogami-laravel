import { useState } from 'react'
import { useParams, useNavigate } from 'react-router-dom'
import { useTicket, useReplyToTicket, useReopenTicket } from '@/hooks/useCRM'
import { useAuthStore } from '@/stores/authStore'

const statusBadge: Record<string, string> = {
  open: 'bg-neutral-100 text-neutral-700',
  in_progress: 'bg-yellow-100 text-yellow-800',
  resolved: 'bg-neutral-200 text-neutral-800',
  closed: 'bg-neutral-100 text-neutral-600',
}

export default function ClientTicketDetailPage() {
  const { ulid = '' } = useParams<{ ulid: string }>()
  const navigate = useNavigate()

  const { data: ticket, isLoading } = useTicket(ulid)
  const replyMutation = useReplyToTicket(ulid)
  const reopenMutation = useReopenTicket(ulid)
  const canReplyPermission = useAuthStore((s) => s.hasPermission('crm.tickets.reply'))

  const [replyBody, setReplyBody] = useState('')
  const [showReopen, setShowReopen] = useState(false)
  const [reopenReason, setReopenReason] = useState('')

  if (isLoading) return <div className="p-8 text-center text-neutral-500">Loading…</div>
  if (!ticket) return <div className="p-8 text-center text-neutral-500">Ticket not found.</div>

  const canReply = ['open', 'in_progress'].includes(ticket.status) && canReplyPermission
  const canReopen = ['resolved', 'closed'].includes(ticket.status) && canReplyPermission

  async function submitReply() {
    if (!replyBody.trim()) return
    await replyMutation.mutateAsync({ body: replyBody, is_internal: false })
    setReplyBody('')
  }

  async function submitReopen() {
    await reopenMutation.mutateAsync({ reason: reopenReason })
    setReopenReason('')
    setShowReopen(false)
  }

  // Only show public messages to clients
  const publicMessages = ticket.messages?.filter(m => !m.is_internal) ?? []

  return (
    <div className="p-6 max-w-3xl mx-auto">
      <button onClick={() => navigate('/client-portal/tickets')} className="text-sm text-neutral-500 hover:text-neutral-700 mb-4">
        &larr; Back to my tickets
      </button>

      {/* Header */}
      <div className="bg-white rounded border border-neutral-200 p-6 mb-4">
        <div className="flex items-start justify-between gap-4">
          <div>
            <div className="text-xs font-mono text-neutral-400 mb-1">{ticket.ticket_number}</div>
            <h1 className="text-xl font-bold text-neutral-900 mb-2">{ticket.subject}</h1>
            <div className="flex flex-wrap gap-2 text-xs">
              <span className={`px-2 py-0.5 rounded-full font-medium capitalize ${statusBadge[ticket.status]}`}>
                {ticket.status.replace('_', ' ')}
              </span>
              <span className="px-2 py-0.5 rounded-full bg-neutral-100 text-neutral-700 capitalize">{ticket.type}</span>
            </div>
          </div>
          <div className="text-right text-sm text-neutral-400 shrink-0">
            {new Date(ticket.created_at).toLocaleDateString()}
          </div>
        </div>

        <div className="mt-4 p-4 bg-neutral-50 rounded text-sm text-neutral-700 whitespace-pre-wrap">
          {ticket.description}
        </div>

        {ticket.resolution_note && (
          <div className="mt-4 p-4 bg-neutral-50 border border-neutral-200 rounded">
            <div className="text-xs font-semibold text-neutral-700 mb-1">Resolution</div>
            <div className="text-sm text-neutral-900">{ticket.resolution_note}</div>
          </div>
        )}
      </div>

      {/* Actions */}
      {canReopen && (
        <div className="mb-4">
          {!showReopen ? (
            <button onClick={() => setShowReopen(true)} className="px-4 py-2 bg-neutral-100 text-neutral-700 text-sm rounded hover:bg-neutral-200">
              Reopen Ticket
            </button>
          ) : (
            <div className="bg-white rounded border border-neutral-200 p-4">
              <h3 className="font-semibold text-sm mb-2">Reopen Ticket</h3>
              <input type="text" value={reopenReason} onChange={e => setReopenReason(e.target.value)}
                placeholder="Why are you reopening this ticket?" className="w-full px-3 py-2 border rounded text-sm mb-2 focus:outline-none focus:ring-1 focus:ring-neutral-400" />
              <div className="flex gap-2">
                <button onClick={submitReopen} disabled={reopenMutation.isPending}
                  className="px-4 py-2 bg-neutral-700 text-white text-sm rounded hover:bg-neutral-600 disabled:opacity-50">
                  {reopenMutation.isPending ? 'Reopening…' : 'Confirm Reopen'}
                </button>
                <button onClick={() => setShowReopen(false)} className="px-4 py-2 bg-neutral-100 text-neutral-700 text-sm rounded hover:bg-neutral-200">
                  Cancel
                </button>
              </div>
            </div>
          )}
        </div>
      )}

      {/* Thread */}
      <div className="space-y-3 mb-4">
        <h2 className="font-semibold text-sm text-neutral-700 uppercase tracking-wide">Messages</h2>
        {publicMessages.length === 0 && (
          <div className="bg-white rounded border border-neutral-200 p-6 text-center text-sm text-neutral-400">No messages yet.</div>
        )}
        {publicMessages.map(msg => (
          <div key={msg.id} className="bg-white rounded border border-neutral-200 p-4">
            <div className="flex items-center justify-between mb-2">
              <span className="font-medium text-sm">{msg.author?.name ?? 'Support'}</span>
              <span className="text-xs text-neutral-400">{new Date(msg.created_at).toLocaleString()}</span>
            </div>
            <div className="text-sm text-neutral-700 whitespace-pre-wrap">{msg.body}</div>
          </div>
        ))}
      </div>

      {/* Reply form */}
      {canReply && (
        <div className="bg-white rounded border border-neutral-200 p-4">
          <h3 className="font-semibold text-sm mb-2">Add a Reply</h3>
          <textarea value={replyBody} onChange={e => setReplyBody(e.target.value)}
            placeholder="Describe your issue or response in detail…" rows={4}
            className="w-full px-3 py-2 border rounded text-sm focus:outline-none focus:ring-1 focus:ring-neutral-400 resize-none" />
          <div className="flex justify-end mt-2">
            <button onClick={submitReply} disabled={replyMutation.isPending || !replyBody.trim()}
              className="px-4 py-2 bg-neutral-900 text-white text-sm rounded hover:bg-neutral-800 disabled:opacity-50">
              {replyMutation.isPending ? 'Sending…' : 'Send Reply'}
            </button>
          </div>
        </div>
      )}
    </div>
  )
}
