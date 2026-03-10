import { useState } from 'react'
import { useParams, useNavigate } from 'react-router-dom'
import { useTicket, useReplyToTicket, useAssignTicket, useResolveTicket, useCloseTicket, useReopenTicket } from '@/hooks/useCRM'

const statusBadge: Record<string, string> = {
  open: 'bg-neutral-100 text-neutral-700',
  in_progress: 'bg-yellow-100 text-yellow-800',
  resolved: 'bg-neutral-200 text-neutral-800',
  closed: 'bg-neutral-100 text-neutral-600',
}

export default function TicketDetailPage() {
  const { ulid = '' } = useParams<{ ulid: string }>()
  const navigate = useNavigate()

  const { data: ticket, isLoading } = useTicket(ulid)
  const replyMutation = useReplyToTicket(ulid)
  const assignMutation = useAssignTicket(ulid)
  const resolveMutation = useResolveTicket(ulid)
  const closeMutation = useCloseTicket(ulid)
  const reopenMutation = useReopenTicket(ulid)

  const [replyBody, setReplyBody] = useState('')
  const [isInternal, setIsInternal] = useState(false)
  const [assigneeId, setAssigneeId] = useState('')
  const [resolutionNote, setResolutionNote] = useState('')
  const [reopenReason, setReopenReason] = useState('')
  const [activePanel, setActivePanel] = useState<'reply' | 'assign' | 'resolve' | 'reopen' | null>(null)

  if (isLoading) return <div className="p-8 text-center text-neutral-500">Loading…</div>
  if (!ticket) return <div className="p-8 text-center text-neutral-500">Ticket not found.</div>

  async function submitReply() {
    if (!replyBody.trim()) return
    await replyMutation.mutateAsync({ body: replyBody, is_internal: isInternal })
    setReplyBody('')
    setActivePanel(null)
  }

  async function submitAssign() {
    if (!assigneeId) return
    await assignMutation.mutateAsync({ assigned_to_id: Number(assigneeId) })
    setAssigneeId('')
    setActivePanel(null)
  }

  async function submitResolve() {
    await resolveMutation.mutateAsync({ resolution_note: resolutionNote })
    setResolutionNote('')
    setActivePanel(null)
  }

  async function submitClose() {
    await closeMutation.mutateAsync()
  }

  async function submitReopen() {
    await reopenMutation.mutateAsync({ reason: reopenReason })
    setReopenReason('')
    setActivePanel(null)
  }

  const canReply = ['open', 'in_progress'].includes(ticket.status)
  const canResolve = ['open', 'in_progress'].includes(ticket.status)
  const canClose = ['resolved'].includes(ticket.status)
  const canReopen = ['resolved', 'closed'].includes(ticket.status)

  return (
    <div className="p-6 max-w-4xl mx-auto">
      {/* Back */}
      <button onClick={() => navigate('/crm/tickets')} className="text-sm text-neutral-500 hover:text-neutral-700 mb-4">&larr; Back to tickets</button>

      {/* Header */}
      <div className="bg-white rounded border border-neutral-200 p-6 mb-4">
        <div className="flex items-start justify-between gap-4">
          <div>
            <div className="text-sm font-mono text-neutral-400 mb-1">{ticket.ticket_number}</div>
            <h1 className="text-xl font-bold text-neutral-900 mb-2">{ticket.subject}</h1>
            <div className="flex flex-wrap gap-2 text-xs">
              <span className={`px-2 py-0.5 rounded-full font-medium capitalize ${statusBadge[ticket.status]}`}>
                {ticket.status.replace('_', ' ')}
              </span>
              <span className="px-2 py-0.5 rounded-full bg-neutral-100 text-neutral-700 capitalize">{ticket.priority}</span>
              <span className="px-2 py-0.5 rounded-full bg-neutral-100 text-neutral-700 capitalize">{ticket.type}</span>
            </div>
          </div>
          <div className="text-right text-sm text-neutral-500 shrink-0">
            <div>Created {new Date(ticket.created_at).toLocaleDateString()}</div>
            {ticket.assignedTo && <div className="mt-1">Assigned: <strong>{ticket.assignedTo.name}</strong></div>}
            {ticket.customer && <div className="mt-1">Customer: <strong>{ticket.customer.name}</strong></div>}
          </div>
        </div>

        <div className="mt-4 p-4 bg-neutral-50 rounded text-sm text-neutral-700 whitespace-pre-wrap">
          {ticket.description}
        </div>

        {ticket.resolution_note && (
          <div className="mt-4 p-4 bg-neutral-50 border border-neutral-200 rounded">
            <div className="text-xs font-semibold text-neutral-700 mb-1">Resolution Note</div>
            <div className="text-sm text-neutral-900">{ticket.resolution_note}</div>
          </div>
        )}
      </div>

      {/* Action Buttons */}
      <div className="flex flex-wrap gap-2 mb-4">
        {canReply && (
          <button onClick={() => setActivePanel(activePanel === 'reply' ? null : 'reply')}
            className="px-4 py-2 bg-neutral-900 text-white text-sm rounded hover:bg-neutral-800">
            Reply
          </button>
        )}
        <button onClick={() => setActivePanel(activePanel === 'assign' ? null : 'assign')}
          className="px-4 py-2 bg-neutral-100 text-neutral-800 text-sm rounded hover:bg-neutral-200">
          Assign
        </button>
        {canResolve && (
          <button onClick={() => setActivePanel(activePanel === 'resolve' ? null : 'resolve')}
            className="px-4 py-2 bg-neutral-900 text-white text-sm rounded hover:bg-neutral-800">
            Resolve
          </button>
        )}
        {canClose && (
          <button onClick={submitClose} disabled={closeMutation.isPending}
            className="px-4 py-2 bg-neutral-500 text-white text-sm rounded hover:bg-neutral-600">
            {closeMutation.isPending ? 'Closing…' : 'Close'}
          </button>
        )}
        {canReopen && (
          <button onClick={() => setActivePanel(activePanel === 'reopen' ? null : 'reopen')}
            className="px-4 py-2 bg-neutral-700 text-white text-sm rounded hover:bg-neutral-600">
            Reopen
          </button>
        )}
      </div>

      {/* Action Panels */}
      {activePanel === 'reply' && (
        <div className="bg-white rounded border border-neutral-200 p-4 mb-4">
          <h3 className="font-semibold text-sm mb-2">Add Reply</h3>
          <textarea
            value={replyBody}
            onChange={e => setReplyBody(e.target.value)}
            placeholder="Type your reply…"
            rows={4}
            className="w-full px-3 py-2 border rounded text-sm focus:outline-none focus:ring-1 focus:ring-neutral-400 resize-none"
          />
          <div className="flex items-center justify-between mt-2">
            <label className="flex items-center gap-2 text-sm text-neutral-600 cursor-pointer">
              <input type="checkbox" checked={isInternal} onChange={e => setIsInternal(e.target.checked)} />
              Internal note (not visible to client)
            </label>
            <button onClick={submitReply} disabled={replyMutation.isPending || !replyBody.trim()}
              className="px-4 py-2 bg-neutral-900 text-white text-sm rounded hover:bg-neutral-800 disabled:opacity-50">
              {replyMutation.isPending ? 'Sending…' : 'Send Reply'}
            </button>
          </div>
        </div>
      )}

      {activePanel === 'assign' && (
        <div className="bg-white rounded border border-neutral-200 p-4 mb-4">
          <h3 className="font-semibold text-sm mb-2">Assign Ticket</h3>
          <div className="flex gap-2">
            <input
              type="number"
              value={assigneeId}
              onChange={e => setAssigneeId(e.target.value)}
              placeholder="User ID"
              className="px-3 py-2 border rounded text-sm focus:outline-none focus:ring-1 focus:ring-neutral-400 w-32"
            />
            <button onClick={submitAssign} disabled={assignMutation.isPending || !assigneeId}
              className="px-4 py-2 bg-neutral-900 text-white text-sm rounded hover:bg-neutral-800 disabled:opacity-50">
              {assignMutation.isPending ? 'Assigning…' : 'Assign'}
            </button>
          </div>
        </div>
      )}

      {activePanel === 'resolve' && (
        <div className="bg-white rounded border border-neutral-200 p-4 mb-4">
          <h3 className="font-semibold text-sm mb-2">Resolve Ticket</h3>
          <textarea
            value={resolutionNote}
            onChange={e => setResolutionNote(e.target.value)}
            placeholder="Optional resolution note…"
            rows={3}
            className="w-full px-3 py-2 border rounded text-sm focus:outline-none focus:ring-1 focus:ring-neutral-400 resize-none"
          />
          <button onClick={submitResolve} disabled={resolveMutation.isPending}
            className="mt-2 px-4 py-2 bg-neutral-900 text-white text-sm rounded hover:bg-neutral-800 disabled:opacity-50">
            {resolveMutation.isPending ? 'Resolving…' : 'Mark Resolved'}
          </button>
        </div>
      )}

      {activePanel === 'reopen' && (
        <div className="bg-white rounded border border-neutral-200 p-4 mb-4">
          <h3 className="font-semibold text-sm mb-2">Reopen Ticket</h3>
          <input
            type="text"
            value={reopenReason}
            onChange={e => setReopenReason(e.target.value)}
            placeholder="Reason for reopening (optional)…"
            className="w-full px-3 py-2 border rounded text-sm focus:outline-none focus:ring-1 focus:ring-neutral-400"
          />
          <button onClick={submitReopen} disabled={reopenMutation.isPending}
            className="mt-2 px-4 py-2 bg-neutral-700 text-white text-sm rounded hover:bg-neutral-600 disabled:opacity-50">
            {reopenMutation.isPending ? 'Reopening…' : 'Reopen'}
          </button>
        </div>
      )}

      {/* Message Thread */}
      <div className="space-y-3">
        <h2 className="font-semibold text-sm text-neutral-700 uppercase tracking-wide">Conversation</h2>
        {ticket.messages?.length === 0 && (
          <div className="bg-white rounded border border-neutral-200 p-6 text-center text-sm text-neutral-400">No messages yet.</div>
        )}
        {ticket.messages?.map(msg => (
          <div key={msg.id} className={`rounded border border-neutral-200 p-4 ${msg.is_internal ? 'bg-yellow-50 border-yellow-200' : 'bg-white'}`}>
            <div className="flex items-center justify-between mb-2">
              <span className="font-medium text-sm">{msg.author?.name ?? 'Unknown'}</span>
              <div className="flex items-center gap-2 text-xs text-neutral-400">
                {msg.is_internal && <span className="px-1.5 py-0.5 bg-yellow-100 text-yellow-700 rounded-full">Internal</span>}
                {new Date(msg.created_at).toLocaleString()}
              </div>
            </div>
            <div className="text-sm text-neutral-700 whitespace-pre-wrap">{msg.body}</div>
          </div>
        ))}
      </div>
    </div>
  )
}
