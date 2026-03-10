import { useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { useCreateTicket } from '@/hooks/useCRM'

export default function ClientNewTicketPage() {
  const navigate = useNavigate()
  const createMutation = useCreateTicket()

  const [form, setForm] = useState({
    subject: '',
    description: '',
    type: 'inquiry' as string,
    priority: 'normal' as string,
  })

  async function handleSubmit(e: React.FormEvent) {
    e.preventDefault()
    const ticket = await createMutation.mutateAsync(form)
    navigate(`/client-portal/tickets/${ticket.ulid}`)
  }

  return (
    <div className="p-6 max-w-2xl mx-auto">
      <button onClick={() => navigate('/client-portal/tickets')} className="text-sm text-neutral-500 hover:text-neutral-700 mb-4">
        &larr; Back
      </button>
      <h1 className="text-2xl font-bold mb-6">Submit a Support Ticket</h1>

      <form onSubmit={handleSubmit} className="bg-white rounded border border-neutral-200 p-6 space-y-4">
        <div>
          <label className="block text-sm font-medium text-neutral-700 mb-1">Subject <span className="text-red-500">*</span></label>
          <input
            type="text"
            required
            value={form.subject}
            onChange={e => setForm(p => ({ ...p, subject: e.target.value }))}
            placeholder="Brief summary of your issue"
            className="w-full px-3 py-2 border rounded text-sm focus:outline-none focus:ring-1 focus:ring-neutral-400"
          />
        </div>

        <div className="flex gap-4">
          <div className="flex-1">
            <label className="block text-sm font-medium text-neutral-700 mb-1">Type</label>
            <select
              value={form.type}
              onChange={e => setForm(p => ({ ...p, type: e.target.value }))}
              className="w-full px-3 py-2 border rounded text-sm focus:outline-none focus:ring-1 focus:ring-neutral-400"
            >
              <option value="inquiry">Inquiry</option>
              <option value="complaint">Complaint</option>
              <option value="request">Request</option>
            </select>
          </div>
          <div className="flex-1">
            <label className="block text-sm font-medium text-neutral-700 mb-1">Priority</label>
            <select
              value={form.priority}
              onChange={e => setForm(p => ({ ...p, priority: e.target.value }))}
              className="w-full px-3 py-2 border rounded text-sm focus:outline-none focus:ring-1 focus:ring-neutral-400"
            >
              <option value="low">Low</option>
              <option value="normal">Normal</option>
              <option value="high">High</option>
              <option value="critical">Critical</option>
            </select>
          </div>
        </div>

        <div>
          <label className="block text-sm font-medium text-neutral-700 mb-1">Description <span className="text-red-500">*</span></label>
          <textarea
            required
            value={form.description}
            onChange={e => setForm(p => ({ ...p, description: e.target.value }))}
            placeholder="Describe your issue in detail. Include any relevant order numbers, dates, or other information."
            rows={6}
            className="w-full px-3 py-2 border rounded text-sm focus:outline-none focus:ring-1 focus:ring-neutral-400 resize-none"
          />
        </div>

        <div className="flex justify-end pt-2">
          <button type="submit" disabled={createMutation.isPending}
            className="px-6 py-2 bg-neutral-900 text-white text-sm font-medium rounded hover:bg-neutral-800 disabled:opacity-50">
            {createMutation.isPending ? 'Submitting…' : 'Submit Ticket'}
          </button>
        </div>
      </form>
    </div>
  )
}
