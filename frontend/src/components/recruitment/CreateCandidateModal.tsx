import { useState } from 'react'
import { useCreateCandidate } from '@/hooks/useRecruitment'
import { toast } from 'sonner'

interface CreateCandidateModalProps {
  onClose: () => void
  onSuccess: () => void
}

export default function CreateCandidateModal({ onClose, onSuccess }: CreateCandidateModalProps) {
  const create = useCreateCandidate()
  const [form, setForm] = useState({
    first_name: '',
    last_name: '',
    email: '',
    phone: '',
    address: '',
    source: 'walk_in',
    linkedin_url: '',
    notes: '',
  })

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault()
    create.mutate(
      {
        ...form,
        phone: form.phone || null,
        address: form.address || null,
        linkedin_url: form.linkedin_url || null,
        notes: form.notes || null,
      },
      {
        onSuccess: () => {
          toast.success('Candidate added to pool')
          onSuccess()
          onClose()
        },
        onError: () => {
          toast.error('Failed to create candidate')
        },
      },
    )
  }

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
      <div className="w-full max-w-md rounded-lg bg-white p-6 shadow-xl dark:bg-neutral-800">
        <h2 className="mb-4 text-xl font-bold text-neutral-900 dark:text-white">Add Candidate to Pool</h2>
        <p className="mb-6 text-sm text-neutral-500">Add a candidate for proactive sourcing or future openings.</p>

        <form onSubmit={handleSubmit} className="space-y-4">
          <div className="grid grid-cols-2 gap-4">
            <div>
              <label className="block text-xs font-medium text-neutral-500 uppercase">First Name</label>
              <input type="text" required value={form.first_name}
                onChange={(e) => setForm({ ...form, first_name: e.target.value })}
                className="mt-1 w-full rounded-md border border-neutral-300 px-3 py-2 text-sm dark:border-neutral-600 dark:bg-neutral-700" />
            </div>
            <div>
              <label className="block text-xs font-medium text-neutral-500 uppercase">Last Name</label>
              <input type="text" required value={form.last_name}
                onChange={(e) => setForm({ ...form, last_name: e.target.value })}
                className="mt-1 w-full rounded-md border border-neutral-300 px-3 py-2 text-sm dark:border-neutral-600 dark:bg-neutral-700" />
            </div>
          </div>

          <div>
            <label className="block text-xs font-medium text-neutral-500 uppercase">Email</label>
            <input type="email" required value={form.email}
              onChange={(e) => setForm({ ...form, email: e.target.value })}
              className="mt-1 w-full rounded-md border border-neutral-300 px-3 py-2 text-sm dark:border-neutral-600 dark:bg-neutral-700" />
          </div>

          <div className="grid grid-cols-2 gap-4">
            <div>
              <label className="block text-xs font-medium text-neutral-500 uppercase">Phone</label>
              <input type="tel" value={form.phone}
                onChange={(e) => setForm({ ...form, phone: e.target.value })}
                className="mt-1 w-full rounded-md border border-neutral-300 px-3 py-2 text-sm dark:border-neutral-600 dark:bg-neutral-700" />
            </div>
            <div>
              <label className="block text-xs font-medium text-neutral-500 uppercase">Source</label>
              <select value={form.source}
                onChange={(e) => setForm({ ...form, source: e.target.value })}
                className="mt-1 w-full rounded-md border border-neutral-300 px-3 py-2 text-sm dark:border-neutral-600 dark:bg-neutral-700">
                <option value="walk_in">Walk-in</option>
                <option value="referral">Referral</option>
                <option value="job_board">Job Board</option>
                <option value="agency">Agency</option>
                <option value="internal">Internal</option>
              </select>
            </div>
          </div>

          <div>
            <label className="block text-xs font-medium text-neutral-500 uppercase">LinkedIn URL</label>
            <input type="url" value={form.linkedin_url}
              onChange={(e) => setForm({ ...form, linkedin_url: e.target.value })}
              className="mt-1 w-full rounded-md border border-neutral-300 px-3 py-2 text-sm dark:border-neutral-600 dark:bg-neutral-700"
              placeholder="https://linkedin.com/in/..." />
          </div>

          <div>
            <label className="block text-xs font-medium text-neutral-500 uppercase">Notes</label>
            <textarea value={form.notes}
              onChange={(e) => setForm({ ...form, notes: e.target.value })}
              className="mt-1 w-full rounded-md border border-neutral-300 px-3 py-2 text-sm dark:border-neutral-600 dark:bg-neutral-700"
              rows={2} placeholder="Skills, experience, referral info..." />
          </div>

          <div className="flex justify-end gap-3 pt-4">
            <button type="button" onClick={onClose}
              className="rounded-md border border-neutral-300 px-4 py-2 text-sm font-medium text-neutral-700 hover:bg-neutral-50 dark:border-neutral-600 dark:text-neutral-300">
              Cancel
            </button>
            <button type="submit" disabled={create.isPending}
              className="rounded-md bg-neutral-900 dark:bg-neutral-100 dark:text-neutral-900 px-4 py-2 text-sm font-semibold text-white hover:bg-neutral-800 dark:hover:bg-neutral-200 disabled:opacity-50">
              {create.isPending ? 'Adding...' : 'Add Candidate'}
            </button>
          </div>
        </form>
      </div>
    </div>
  )
}
