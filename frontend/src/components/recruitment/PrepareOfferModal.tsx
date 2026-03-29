import { useState } from 'react'
import { useCreateOffer } from '@/hooks/useRecruitment'
import { useDepartments, usePositions } from '@/hooks/useEmployees'
import { toast } from 'sonner'

interface PrepareOfferModalProps {
  applicationId: number
  candidateName: string
  defaultDepartmentId?: number
  defaultPositionId?: number
  onClose: () => void
  onSuccess: () => void
}

export default function PrepareOfferModal({
  applicationId,
  candidateName,
  defaultDepartmentId,
  defaultPositionId,
  onClose,
  onSuccess,
}: PrepareOfferModalProps) {
  const createOffer = useCreateOffer()
  const { data: deptsData } = useDepartments(true)
  const departments = deptsData?.data ?? []

  const [departmentId, setDepartmentId] = useState(String(defaultDepartmentId ?? ''))
  const { data: positionsData } = usePositions(departmentId ? Number(departmentId) : undefined)
  const positions = positionsData?.data ?? positionsData ?? []

  const [form, setForm] = useState({
    offered_position_id: String(defaultPositionId ?? ''),
    offered_department_id: String(defaultDepartmentId ?? ''),
    offered_salary: '',
    employment_type: 'regular',
    start_date: '',
    expires_at: '',
  })

  const handleDepartmentChange = (value: string) => {
    setDepartmentId(value)
    setForm({ ...form, offered_department_id: value, offered_position_id: '' })
  }

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault()
    createOffer.mutate(
      {
        application_id: applicationId,
        offered_position_id: Number(form.offered_position_id),
        offered_department_id: Number(form.offered_department_id),
        offered_salary: Number(form.offered_salary),
        employment_type: form.employment_type,
        start_date: form.start_date,
        expires_at: form.expires_at || null,
      },
      {
        onSuccess: () => {
          toast.success('Offer prepared successfully')
          onSuccess()
          onClose()
        },
        onError: () => {
          toast.error('Failed to prepare offer')
        },
      },
    )
  }

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
      <div className="w-full max-w-lg rounded-lg bg-white p-6 shadow-xl dark:bg-gray-800">
        <h2 className="mb-4 text-xl font-bold text-gray-900 dark:text-white">
          Prepare Job Offer
        </h2>
        <p className="mb-6 text-sm text-gray-500">
          Create an offer for {candidateName}.
        </p>

        <form onSubmit={handleSubmit} className="space-y-4">
          <div className="grid grid-cols-2 gap-4">
            <div>
              <label className="block text-xs font-medium text-gray-500 uppercase">Department</label>
              <select
                value={form.offered_department_id}
                onChange={(e) => handleDepartmentChange(e.target.value)}
                className="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-700"
                required
              >
                <option value="">Select department...</option>
                {/* eslint-disable-next-line @typescript-eslint/no-explicit-any */}
                {(Array.isArray(departments) ? departments : []).map((d: any) => (
                  <option key={d.id} value={d.id}>{d.name}</option>
                ))}
              </select>
            </div>
            <div>
              <label className="block text-xs font-medium text-gray-500 uppercase">Position</label>
              <select
                value={form.offered_position_id}
                onChange={(e) => setForm({ ...form, offered_position_id: e.target.value })}
                className="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-700"
                required
                disabled={!departmentId}
              >
                <option value="">{departmentId ? 'Select position...' : 'Select dept first'}</option>
                {/* eslint-disable-next-line @typescript-eslint/no-explicit-any */}
                {(Array.isArray(positions) ? positions : []).map((p: any) => (
                  <option key={p.id} value={p.id}>{p.title}</option>
                ))}
              </select>
            </div>
          </div>

          <div className="grid grid-cols-2 gap-4">
            <div>
              <label className="block text-xs font-medium text-gray-500 uppercase">Monthly Salary (centavos)</label>
              <input
                type="number"
                required
                min="1"
                value={form.offered_salary}
                onChange={(e) => setForm({ ...form, offered_salary: e.target.value })}
                className="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-700"
                placeholder="e.g. 3000000 = PHP 30,000"
              />
              {form.offered_salary && (
                <p className="mt-1 text-xs text-gray-400">
                  = {(Number(form.offered_salary) / 100).toLocaleString('en-PH', { style: 'currency', currency: 'PHP' })}
                </p>
              )}
            </div>
            <div>
              <label className="block text-xs font-medium text-gray-500 uppercase">Employment Type</label>
              <select
                value={form.employment_type}
                onChange={(e) => setForm({ ...form, employment_type: e.target.value })}
                className="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-700"
              >
                <option value="regular">Regular</option>
                <option value="contractual">Contractual</option>
                <option value="project_based">Project-Based</option>
                <option value="part_time">Part-Time</option>
              </select>
            </div>
          </div>

          <div className="grid grid-cols-2 gap-4">
            <div>
              <label className="block text-xs font-medium text-gray-500 uppercase">Start Date</label>
              <input
                type="date"
                required
                value={form.start_date}
                onChange={(e) => setForm({ ...form, start_date: e.target.value })}
                className="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-700"
              />
            </div>
            <div>
              <label className="block text-xs font-medium text-gray-500 uppercase">Expires At</label>
              <input
                type="date"
                value={form.expires_at}
                onChange={(e) => setForm({ ...form, expires_at: e.target.value })}
                className="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-700"
              />
            </div>
          </div>

          <div className="flex justify-end gap-3 pt-4">
            <button
              type="button"
              onClick={onClose}
              className="rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-300"
            >
              Cancel
            </button>
            <button
              type="submit"
              disabled={createOffer.isPending}
              className="rounded-md bg-purple-600 px-4 py-2 text-sm font-semibold text-white hover:bg-purple-500 disabled:opacity-50"
            >
              {createOffer.isPending ? 'Creating...' : 'Prepare Offer'}
            </button>
          </div>
        </form>
      </div>
    </div>
  )
}
