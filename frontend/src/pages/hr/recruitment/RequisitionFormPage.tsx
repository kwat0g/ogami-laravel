import { useState } from 'react'
import { useNavigate, useParams } from 'react-router-dom'
import { useCreateRequisition, useRequisition, useUpdateRequisition } from '@/hooks/useRecruitment'

export default function RequisitionFormPage() {
  const { ulid } = useParams<{ ulid: string }>()
  const isEdit = !!ulid
  const navigate = useNavigate()
  const { data: existing } = useRequisition(ulid ?? '')
  const createMutation = useCreateRequisition()
  const updateMutation = useUpdateRequisition(ulid ?? '')

  const [form, setForm] = useState({
    department_id: '',
    position_id: '',
    employment_type: 'regular',
    headcount: '1',
    reason: '',
    justification: '',
    salary_range_min: '',
    salary_range_max: '',
    target_start_date: '',
  })

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault()
    const payload = {
      ...form,
      department_id: Number(form.department_id),
      position_id: Number(form.position_id),
      headcount: Number(form.headcount),
      salary_range_min: form.salary_range_min ? Number(form.salary_range_min) : null,
      salary_range_max: form.salary_range_max ? Number(form.salary_range_max) : null,
      target_start_date: form.target_start_date || null,
    }

    if (isEdit) {
      await updateMutation.mutateAsync(payload)
      navigate(`/hr/recruitment/requisitions/${ulid}`)
    } else {
      await createMutation.mutateAsync(payload)
      navigate('/hr/recruitment/requisitions')
    }
  }

  const isPending = createMutation.isPending || updateMutation.isPending

  return (
    <div className="mx-auto max-w-2xl p-6">
      <h1 className="mb-6 text-2xl font-bold text-gray-900 dark:text-white">
        {isEdit ? 'Edit Requisition' : 'New Job Requisition'}
      </h1>

      <form onSubmit={handleSubmit} className="space-y-4">
        <div className="grid grid-cols-2 gap-4">
          <div>
            <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">Department ID</label>
            <input
              type="number"
              value={form.department_id}
              onChange={(e) => setForm({ ...form, department_id: e.target.value })}
              className="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800"
              required
            />
          </div>
          <div>
            <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">Position ID</label>
            <input
              type="number"
              value={form.position_id}
              onChange={(e) => setForm({ ...form, position_id: e.target.value })}
              className="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800"
              required
            />
          </div>
        </div>

        <div className="grid grid-cols-2 gap-4">
          <div>
            <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">Employment Type</label>
            <select
              value={form.employment_type}
              onChange={(e) => setForm({ ...form, employment_type: e.target.value })}
              className="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800"
            >
              <option value="regular">Regular</option>
              <option value="contractual">Contractual</option>
              <option value="project_based">Project-Based</option>
              <option value="part_time">Part-Time</option>
            </select>
          </div>
          <div>
            <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">Headcount</label>
            <input
              type="number"
              min="1"
              value={form.headcount}
              onChange={(e) => setForm({ ...form, headcount: e.target.value })}
              className="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800"
              required
            />
          </div>
        </div>

        <div>
          <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">Reason</label>
          <textarea
            value={form.reason}
            onChange={(e) => setForm({ ...form, reason: e.target.value })}
            rows={3}
            className="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800"
            required
          />
        </div>

        <div>
          <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">Justification</label>
          <textarea
            value={form.justification}
            onChange={(e) => setForm({ ...form, justification: e.target.value })}
            rows={2}
            className="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800"
          />
        </div>

        <div>
          <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">Target Start Date</label>
          <input
            type="date"
            value={form.target_start_date}
            onChange={(e) => setForm({ ...form, target_start_date: e.target.value })}
            className="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800"
          />
        </div>

        <div className="flex justify-end gap-3 pt-4">
          <button
            type="button"
            onClick={() => navigate(-1)}
            className="rounded-md border border-gray-300 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-300"
          >
            Cancel
          </button>
          <button
            type="submit"
            disabled={isPending}
            className="rounded-md bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-500 disabled:opacity-50"
          >
            {isPending ? 'Saving...' : isEdit ? 'Update' : 'Create Requisition'}
          </button>
        </div>
      </form>
    </div>
  )
}
