import { useState } from 'react'
import { useScheduleInterview } from '@/hooks/useRecruitment'
import { toast } from 'sonner'

interface ScheduleInterviewModalProps {
  applicationId: number
  candidateName: string
  onClose: () => void
  onSuccess: () => void
}

export default function ScheduleInterviewModal({
  applicationId,
  candidateName,
  onClose,
  onSuccess,
}: ScheduleInterviewModalProps) {
  const schedule = useScheduleInterview()
  const [form, setForm] = useState({
    type: 'hr_screening',
    scheduled_at: '',
    duration_minutes: '60',
    location: '',
    interviewer_id: '',
    notes: '',
  })

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault()
    schedule.mutate(
      {
        application_id: applicationId,
        type: form.type,
        scheduled_at: form.scheduled_at,
        duration_minutes: Number(form.duration_minutes),
        location: form.location || null,
        interviewer_id: Number(form.interviewer_id),
        notes: form.notes || null,
      },
      {
        onSuccess: () => {
          toast.success('Interview scheduled successfully')
          onSuccess()
          onClose()
        },
        onError: () => {
          toast.error('Failed to schedule interview')
        },
      },
    )
  }

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
      <div className="w-full max-w-md rounded-lg bg-white p-6 shadow-xl dark:bg-neutral-800">
        <h2 className="mb-4 text-xl font-bold text-neutral-900 dark:text-white">
          Schedule Interview
        </h2>
        <p className="mb-6 text-sm text-neutral-500">
          Schedule an interview for {candidateName}.
        </p>

        <form onSubmit={handleSubmit} className="space-y-4">
          <div>
            <label className="block text-xs font-medium text-neutral-500 uppercase">Interview Type</label>
            <select
              value={form.type}
              onChange={(e) => setForm({ ...form, type: e.target.value })}
              className="mt-1 w-full rounded-md border border-neutral-300 px-3 py-2 text-sm dark:border-neutral-600 dark:bg-neutral-700"
              required
            >
              <option value="hr_screening">HR Screening</option>
              <option value="one_on_one">One-on-One</option>
              <option value="technical">Technical Interview</option>
              <option value="panel">Panel Interview</option>
              <option value="final">Final Interview</option>
            </select>
          </div>

          <div>
            <label className="block text-xs font-medium text-neutral-500 uppercase">Scheduled Date & Time</label>
            <input
              type="datetime-local"
              required
              value={form.scheduled_at}
              onChange={(e) => setForm({ ...form, scheduled_at: e.target.value })}
              className="mt-1 w-full rounded-md border border-neutral-300 px-3 py-2 text-sm dark:border-neutral-600 dark:bg-neutral-700"
            />
          </div>

          <div className="grid grid-cols-2 gap-4">
            <div>
              <label className="block text-xs font-medium text-neutral-500 uppercase">Duration (min)</label>
              <input
                type="number"
                min="15"
                max="480"
                value={form.duration_minutes}
                onChange={(e) => setForm({ ...form, duration_minutes: e.target.value })}
                className="mt-1 w-full rounded-md border border-neutral-300 px-3 py-2 text-sm dark:border-neutral-600 dark:bg-neutral-700"
              />
            </div>
            <div>
              <label className="block text-xs font-medium text-neutral-500 uppercase">Interviewer ID</label>
              <input
                type="number"
                required
                value={form.interviewer_id}
                onChange={(e) => setForm({ ...form, interviewer_id: e.target.value })}
                className="mt-1 w-full rounded-md border border-neutral-300 px-3 py-2 text-sm dark:border-neutral-600 dark:bg-neutral-700"
                placeholder="User ID"
              />
            </div>
          </div>

          <div>
            <label className="block text-xs font-medium text-neutral-500 uppercase">Location</label>
            <input
              type="text"
              value={form.location}
              onChange={(e) => setForm({ ...form, location: e.target.value })}
              className="mt-1 w-full rounded-md border border-neutral-300 px-3 py-2 text-sm dark:border-neutral-600 dark:bg-neutral-700"
              placeholder="e.g. Conference Room A, Zoom link..."
            />
          </div>

          <div>
            <label className="block text-xs font-medium text-neutral-500 uppercase">Notes</label>
            <textarea
              value={form.notes}
              onChange={(e) => setForm({ ...form, notes: e.target.value })}
              className="mt-1 w-full rounded-md border border-neutral-300 px-3 py-2 text-sm dark:border-neutral-600 dark:bg-neutral-700"
              rows={2}
              placeholder="Any additional notes for the interviewer..."
            />
          </div>

          <div className="flex justify-end gap-3 pt-4">
            <button
              type="button"
              onClick={onClose}
              className="rounded-md border border-neutral-300 px-4 py-2 text-sm font-medium text-neutral-700 hover:bg-neutral-50 dark:border-neutral-600 dark:text-neutral-300"
            >
              Cancel
            </button>
            <button
              type="submit"
              disabled={schedule.isPending}
              className="rounded-md bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-500 disabled:opacity-50"
            >
              {schedule.isPending ? 'Scheduling...' : 'Schedule Interview'}
            </button>
          </div>
        </form>
      </div>
    </div>
  )
}
