import { useState, useEffect } from 'react'
import { useNavigate, useSearchParams } from 'react-router-dom'
import { useCreatePosting, useRequisitions, useRequisition } from '@/hooks/useRecruitment'
import { toast } from 'sonner'

export default function JobPostingFormPage() {
  const navigate = useNavigate()
  const [searchParams] = useSearchParams()
  const requisitionUlid = searchParams.get('requisition') ?? ''
  const createMutation = useCreatePosting()

  // Fetch approved requisitions for the dropdown
  const { data: requisitionsData } = useRequisitions({ status: 'approved' })
  const requisitions = requisitionsData?.data ?? []

  // If coming from a specific requisition, fetch its details to auto-fill
  const { data: preselected } = useRequisition(requisitionUlid)

  const [form, setForm] = useState({
    job_requisition_id: '',
    title: '',
    description: '',
    requirements: '',
    location: '',
    employment_type: 'regular',
    is_internal: false,
    is_external: true,
    closes_at: '',
  })

  // Auto-fill when preselected requisition loads
  useEffect(() => {
    if (preselected && requisitionUlid) {
      setForm((f) => ({
        ...f,
        job_requisition_id: String(preselected.id ?? ''),
        title: preselected.position?.title ?? '',
        employment_type: preselected.employment_type ?? 'regular',
      }))
    }
  }, [preselected, requisitionUlid])

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault()
    if (!form.job_requisition_id) {
      toast.error('Please select a requisition')
      return
    }
    try {
      await createMutation.mutateAsync({
        ...form,
        job_requisition_id: Number(form.job_requisition_id),
        closes_at: form.closes_at || null,
      })
      toast.success('Job posting created')
      navigate('/hr/recruitment?tab=postings')
    } catch {
      toast.error('Failed to create posting')
    }
  }

  return (
    <div className="mx-auto max-w-2xl p-6">
      <h1 className="mb-6 text-2xl font-bold text-gray-900 dark:text-white">Create Job Posting</h1>

      <form onSubmit={handleSubmit} className="space-y-4">
        {/* GAP-07: Requisition dropdown instead of raw integer ID */}
        <div>
          <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">Requisition</label>
          <select
            value={form.job_requisition_id}
            onChange={(e) => {
              const reqId = e.target.value
              setForm({ ...form, job_requisition_id: reqId })
              // Auto-fill title from selected requisition
              // eslint-disable-next-line @typescript-eslint/no-explicit-any
              const selected = requisitions.find((r: any) => String(r.id) === reqId)
              if (selected) {
                setForm((f) => ({
                  ...f,
                  job_requisition_id: reqId,
                  title: selected.position?.title ?? f.title,
                  employment_type: selected.employment_type ?? f.employment_type,
                }))
              }
            }}
            className="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800"
            required
          >
            <option value="">Select an approved requisition...</option>
            {/* eslint-disable-next-line @typescript-eslint/no-explicit-any */}
            {requisitions.map((r: any) => (
              <option key={r.id} value={r.id}>
                {r.requisition_number} - {r.position?.title} ({r.department?.name})
              </option>
            ))}
          </select>
          {requisitions.length === 0 && (
            <p className="mt-1 text-xs text-amber-600">No approved requisitions found. Approve a requisition first.</p>
          )}
        </div>

        <div>
          <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">Job Title</label>
          <input
            type="text"
            value={form.title}
            onChange={(e) => setForm({ ...form, title: e.target.value })}
            className="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800"
            required
          />
        </div>

        <div>
          <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">Job Description</label>
          <textarea
            value={form.description}
            onChange={(e) => setForm({ ...form, description: e.target.value })}
            rows={5}
            className="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800"
            required
            placeholder="Minimum 50 characters..."
          />
        </div>

        <div>
          <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">Requirements</label>
          <textarea
            value={form.requirements}
            onChange={(e) => setForm({ ...form, requirements: e.target.value })}
            rows={4}
            className="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800"
            required
            placeholder="Minimum 20 characters..."
          />
        </div>

        <div className="grid grid-cols-2 gap-4">
          <div>
            <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">Location</label>
            <input
              type="text"
              value={form.location}
              onChange={(e) => setForm({ ...form, location: e.target.value })}
              className="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800"
            />
          </div>
          <div>
            <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">Closing Date</label>
            <input
              type="date"
              value={form.closes_at}
              onChange={(e) => setForm({ ...form, closes_at: e.target.value })}
              className="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800"
            />
          </div>
        </div>

        <div className="flex gap-6">
          <label className="flex items-center gap-2 text-sm">
            <input
              type="checkbox"
              checked={form.is_internal}
              onChange={(e) => setForm({ ...form, is_internal: e.target.checked })}
              className="rounded border-gray-300"
            />
            Internal posting
          </label>
          <label className="flex items-center gap-2 text-sm">
            <input
              type="checkbox"
              checked={form.is_external}
              onChange={(e) => setForm({ ...form, is_external: e.target.checked })}
              className="rounded border-gray-300"
            />
            External posting
          </label>
        </div>

        <div className="flex justify-end gap-3 pt-4">
          <button type="button" onClick={() => navigate(-1)} className="rounded-md border border-gray-300 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-300">
            Cancel
          </button>
          <button type="submit" disabled={createMutation.isPending} className="rounded-md bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-500 disabled:opacity-50">
            {createMutation.isPending ? 'Creating...' : 'Create Posting'}
          </button>
        </div>
      </form>
    </div>
  )
}
