import { useState } from 'react'
import { useParams, useNavigate } from 'react-router-dom'
import { toast } from 'sonner'
import { firstErrorMessage } from '@/lib/errorHandler'
import { Ticket } from 'lucide-react'
import { useTicket, useReplyToTicket, useAssignTicket, useResolveTicket, useCloseTicket, useReopenTicket } from '@/hooks/useCRM'
import { PageHeader } from '@/components/ui/PageHeader'
import { Card, CardHeader, CardBody } from '@/components/ui/Card'
import StatusBadge from '@/components/ui/StatusBadge'
import SkeletonLoader from '@/components/ui/SkeletonLoader'

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
  const [touched, setTouched] = useState<Record<string, boolean>>({})

  if (isLoading) return <SkeletonLoader rows={6} />
  if (!ticket) return (
    <div className="max-w-4xl mx-auto py-16 text-center">
      <p className="text-neutral-500">Ticket not found.</p>
      <button onClick={() => navigate('/crm/tickets')} className="btn-secondary mt-4">
        Back to Tickets
      </button>
    </div>
  )

  // Validation
  const replyError = touched.reply && !replyBody.trim() ? 'Reply message is required.' : undefined
  const assignError = touched.assign && !assigneeId ? 'Assignee ID is required.' : undefined
  const reopenError = touched.reopen && !reopenReason.trim() ? 'Reason is required to reopen.' : undefined

  async function submitReply() {
    setTouched(prev => ({ ...prev, reply: true }))
    if (!replyBody.trim()) {
      toast.error('Please enter a reply message.')
      return
    }
    try {
      await replyMutation.mutateAsync({ body: replyBody, is_internal: isInternal })
      toast.success('Reply sent successfully.')
      setReplyBody('')
      setIsInternal(false)
      setActivePanel(null)
      setTouched(prev => ({ ...prev, reply: false }))
    } catch (_err) {
      toast.error(firstErrorMessage(err, 'Failed to send reply.'))
    }
  }

  async function submitAssign() {
    setTouched(prev => ({ ...prev, assign: true }))
    if (!assigneeId) {
      toast.error('Please enter an assignee ID.')
      return
    }
    try {
      await assignMutation.mutateAsync({ assigned_to_id: Number(assigneeId) })
      toast.success('Ticket assigned successfully.')
      setAssigneeId('')
      setActivePanel(null)
      setTouched(prev => ({ ...prev, assign: false }))
    } catch (_err) {
      toast.error(firstErrorMessage(err, 'Failed to assign ticket.'))
    }
  }

  async function submitResolve() {
    try {
      await resolveMutation.mutateAsync({ resolution_note: resolutionNote })
      toast.success('Ticket resolved successfully.')
      setResolutionNote('')
      setActivePanel(null)
    } catch (_err) {
      toast.error(firstErrorMessage(err, 'Failed to resolve ticket.'))
    }
  }

  async function submitClose() {
    try {
      await closeMutation.mutateAsync()
      toast.success('Ticket closed successfully.')
    } catch (_err) {
      toast.error(firstErrorMessage(err, 'Failed to close ticket.'))
    }
  }

  async function submitReopen() {
    setTouched(prev => ({ ...prev, reopen: true }))
    if (!reopenReason.trim()) {
      toast.error('Please provide a reason for reopening.')
      return
    }
    try {
      await reopenMutation.mutateAsync({ reason: reopenReason })
      toast.success('Ticket reopened successfully.')
      setReopenReason('')
      setActivePanel(null)
      setTouched(prev => ({ ...prev, reopen: false }))
    } catch (_err) {
      toast.error(firstErrorMessage(err, 'Failed to reopen ticket.'))
    }
  }

  const canReply = ['open', 'in_progress'].includes(ticket.status)
  const canResolve = ['open', 'in_progress'].includes(ticket.status)
  const canClose = ['resolved'].includes(ticket.status)
  const canReopen = ['resolved', 'closed'].includes(ticket.status)

  return (
    <div className="max-w-4xl mx-auto space-y-6">
      <PageHeader
        title={ticket.subject}
        subtitle={`Ticket #${ticket.ticket_number}`}
        backTo="/crm/tickets"
        icon={<Ticket className="w-5 h-5 text-neutral-600" />}
        status={
          <div className="flex flex-wrap gap-2">
            <StatusBadge status={ticket.status}>{ticket.status.replace('_', ' ')}</StatusBadge>
            <StatusBadge status={ticket.priority}>{ticket.priority}</StatusBadge>
            <span className="inline-flex items-center px-2.5 py-1 rounded-lg text-xs font-medium border capitalize shadow-sm bg-neutral-50 text-neutral-700 border-neutral-200">
              {ticket.type}
            </span>
          </div>
        }
      />

      {/* Ticket Info */}
      <Card>
        <CardBody>
          <div className="grid grid-cols-3 gap-4 text-sm mb-4">
            <div>
              <span className="text-neutral-500">Created:</span>{' '}
              <span className="text-neutral-900">{new Date(ticket.created_at).toLocaleDateString()}</span>
            </div>
            {ticket.assignedTo && (
              <div>
                <span className="text-neutral-500">Assigned:</span>{' '}
                <span className="text-neutral-900">{ticket.assignedTo.name}</span>
              </div>
            )}
            {ticket.customer && (
              <div>
                <span className="text-neutral-500">Customer:</span>{' '}
                <span className="text-neutral-900">{ticket.customer.name}</span>
              </div>
            )}
          </div>

          <div className="p-4 bg-neutral-50 rounded-lg text-sm text-neutral-800 whitespace-pre-wrap border border-neutral-100">
            {ticket.description}
          </div>

          {ticket.resolution_note && (
            <div className="mt-4 p-4 bg-emerald-50 rounded-lg border border-emerald-100">
              <div className="text-xs font-semibold text-emerald-800 mb-1">Resolution Note</div>
              <div className="text-sm text-emerald-900">{ticket.resolution_note}</div>
            </div>
          )}
        </CardBody>
      </Card>

      {/* Action Buttons */}
      <div className="flex flex-wrap gap-2">
        {canReply && (
          <button onClick={() => setActivePanel(activePanel === 'reply' ? null : 'reply')}
            className="btn-primary">
            Reply
          </button>
        )}
        <button onClick={() => setActivePanel(activePanel === 'assign' ? null : 'assign')}
          className="btn-secondary">
          Assign
        </button>
        {canResolve && (
          <button onClick={() => setActivePanel(activePanel === 'resolve' ? null : 'resolve')}
            className="btn-primary">
            Resolve
          </button>
        )}
        {canClose && (
          <button onClick={submitClose} disabled={closeMutation.isPending}
            className="btn-secondary">
            {closeMutation.isPending ? 'Closing…' : 'Close'}
          </button>
        )}
        {canReopen && (
          <button onClick={() => setActivePanel(activePanel === 'reopen' ? null : 'reopen')}
            className="btn-secondary">
            Reopen
          </button>
        )}
      </div>

      {/* Action Panels */}
      {activePanel === 'reply' && (
        <Card>
          <CardHeader>Add Reply</CardHeader>
          <CardBody>
            <textarea
              value={replyBody}
              onChange={e => setReplyBody(e.target.value)}
              onBlur={() => setTouched(prev => ({ ...prev, reply: true }))}
              placeholder="Type your reply…"
              rows={4}
              className={`w-full px-3 py-2 border rounded-lg text-sm focus:ring-1 focus:ring-neutral-400 outline-none resize-none ${
                replyError ? 'border-red-400' : 'border-neutral-300'
              }`}
            />
            {replyError && <p className="mt-1 text-xs text-red-600">{replyError}</p>}
            <div className="flex items-center justify-between mt-3">
              <label className="flex items-center gap-2 text-sm text-neutral-600 cursor-pointer">
                <input type="checkbox" checked={isInternal} onChange={e => setIsInternal(e.target.checked)} className="rounded border-neutral-300" />
                Internal note (not visible to client)
              </label>
              <button onClick={submitReply} disabled={replyMutation.isPending}
                className="btn-primary disabled:opacity-50">
                {replyMutation.isPending ? 'Sending…' : 'Send Reply'}
              </button>
            </div>
          </CardBody>
        </Card>
      )}

      {activePanel === 'assign' && (
        <Card>
          <CardHeader>Assign Ticket</CardHeader>
          <CardBody>
            <div className="flex gap-2">
              <input
                type="number"
                value={assigneeId}
                onChange={e => setAssigneeId(e.target.value)}
                onBlur={() => setTouched(prev => ({ ...prev, assign: true }))}
                placeholder="User ID"
                className={`px-3 py-2 border rounded-lg text-sm focus:ring-1 focus:ring-neutral-400 outline-none w-32 ${
                  assignError ? 'border-red-400' : 'border-neutral-300'
                }`}
              />
              <button onClick={submitAssign} disabled={assignMutation.isPending}
                className="btn-primary disabled:opacity-50">
                {assignMutation.isPending ? 'Assigning…' : 'Assign'}
              </button>
            </div>
            {assignError && <p className="mt-1 text-xs text-red-600">{assignError}</p>}
          </CardBody>
        </Card>
      )}

      {activePanel === 'resolve' && (
        <Card>
          <CardHeader>Resolve Ticket</CardHeader>
          <CardBody>
            <textarea
              value={resolutionNote}
              onChange={e => setResolutionNote(e.target.value)}
              placeholder="Optional resolution note…"
              rows={3}
              className="w-full px-3 py-2 border border-neutral-300 rounded-lg text-sm focus:ring-1 focus:ring-neutral-400 outline-none resize-none"
            />
            <button onClick={submitResolve} disabled={resolveMutation.isPending}
              className="mt-3 btn-primary disabled:opacity-50">
              {resolveMutation.isPending ? 'Resolving…' : 'Mark Resolved'}
            </button>
          </CardBody>
        </Card>
      )}

      {activePanel === 'reopen' && (
        <Card>
          <CardHeader>Reopen Ticket</CardHeader>
          <CardBody>
            <input
              type="text"
              value={reopenReason}
              onChange={e => setReopenReason(e.target.value)}
              onBlur={() => setTouched(prev => ({ ...prev, reopen: true }))}
              placeholder="Reason for reopening (required)…"
              className={`w-full px-3 py-2 border rounded-lg text-sm focus:ring-1 focus:ring-neutral-400 outline-none ${
                reopenError ? 'border-red-400' : 'border-neutral-300'
              }`}
            />
            {reopenError && <p className="mt-1 text-xs text-red-600">{reopenError}</p>}
            <button onClick={submitReopen} disabled={reopenMutation.isPending}
              className="mt-3 btn-secondary disabled:opacity-50">
              {reopenMutation.isPending ? 'Reopening…' : 'Reopen'}
            </button>
          </CardBody>
        </Card>
      )}

      {/* Message Thread */}
      <div className="space-y-3">
        <h2 className="font-semibold text-sm text-neutral-700 uppercase tracking-wide">Conversation</h2>
        {ticket.messages?.length === 0 && (
          <Card>
            <div className="p-8 text-center text-sm text-neutral-400">No messages yet.</div>
          </Card>
        )}
        {ticket.messages?.map(msg => (
          <div key={msg.id} className={`rounded-xl border p-4 ${msg.is_internal ? 'bg-amber-50 border-amber-200' : 'bg-white border-neutral-200'}`}>
            <div className="flex items-center justify-between mb-2">
              <span className="font-medium text-sm text-neutral-900">{msg.author?.name ?? 'Unknown'}</span>
              <div className="flex items-center gap-2 text-xs text-neutral-500">
                {msg.is_internal && <span className="px-1.5 py-0.5 bg-amber-100 text-amber-700 rounded-full text-xs">Internal</span>}
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
