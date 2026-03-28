import { useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { useCreateAppraisal } from '@/hooks/useEnhancements'
import { useEmployees } from '@/hooks/useEmployees'

interface CriteriaRow {
  criteria_name: string
  description: string
  weight_pct: number
}

const DEFAULT_CRITERIA: CriteriaRow[] = [
  { criteria_name: 'Job Knowledge & Skills', description: 'Demonstrates competency in required skills', weight_pct: 25 },
  { criteria_name: 'Quality of Work', description: 'Accuracy, thoroughness, and reliability of output', weight_pct: 25 },
  { criteria_name: 'Productivity & Efficiency', description: 'Volume of work and time management', weight_pct: 20 },
  { criteria_name: 'Teamwork & Communication', description: 'Collaboration and interpersonal skills', weight_pct: 15 },
  { criteria_name: 'Initiative & Innovation', description: 'Proactive problem-solving and improvement ideas', weight_pct: 15 },
]

export default function CreatePerformanceAppraisalPage() {
  const navigate = useNavigate()
  const createAppraisal = useCreateAppraisal()
  const { data: employeesData } = useEmployees({ per_page: 200 })

  const [form, setForm] = useState({
    employee_id: '',
    reviewer_id: '',
    review_type: 'annual' as string,
    review_period_start: '',
    review_period_end: '',
    employee_comments: '',
  })

  const [criteria, setCriteria] = useState<CriteriaRow[]>(DEFAULT_CRITERIA)

  const totalWeight = criteria.reduce((sum, c) => sum + c.weight_pct, 0)

  const addCriteria = () => {
    setCriteria([...criteria, { criteria_name: '', description: '', weight_pct: 0 }])
  }

  const removeCriteria = (index: number) => {
    setCriteria(criteria.filter((_, i) => i !== index))
  }

  const updateCriteria = (index: number, field: keyof CriteriaRow, value: string | number) => {
    const updated = [...criteria]
    updated[index] = { ...updated[index], [field]: value }
    setCriteria(updated)
  }

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault()

    createAppraisal.mutate(
      {
        employee_id: Number(form.employee_id),
        reviewer_id: Number(form.reviewer_id),
        review_type: form.review_type,
        review_period_start: form.review_period_start,
        review_period_end: form.review_period_end,
        employee_comments: form.employee_comments || undefined,
        criteria: criteria.filter(c => c.criteria_name.trim()),
      },
      {
        onSuccess: () => navigate('/hr/appraisals'),
      },
    )
  }

  const employees = employeesData?.data ?? []

  return (
    <div className="max-w-3xl mx-auto space-y-6">
      <div>
        <h1 className="text-2xl font-bold text-gray-900 dark:text-white">New Performance Appraisal</h1>
        <p className="text-sm text-gray-500 mt-1">Create a performance evaluation with weighted KPI criteria</p>
      </div>

      <form onSubmit={handleSubmit} className="space-y-6">
        {/* Basic Info */}
        <div className="bg-white dark:bg-gray-800 rounded-lg shadow p-6 space-y-4">
          <h2 className="font-semibold text-gray-900 dark:text-white">Evaluation Details</h2>

          <div className="grid grid-cols-2 gap-4">
            <div>
              <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Employee</label>
              <select
                required
                value={form.employee_id}
                onChange={e => setForm({ ...form, employee_id: e.target.value })}
                className="w-full border rounded-lg px-3 py-2 text-sm dark:bg-gray-700 dark:border-gray-600"
              >
                <option value="">Select employee...</option>
                {employees.map((emp: { id: number; first_name: string; last_name: string }) => (
                  <option key={emp.id} value={emp.id}>{emp.last_name}, {emp.first_name}</option>
                ))}
              </select>
            </div>

            <div>
              <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Review Type</label>
              <select
                value={form.review_type}
                onChange={e => setForm({ ...form, review_type: e.target.value })}
                className="w-full border rounded-lg px-3 py-2 text-sm dark:bg-gray-700 dark:border-gray-600"
              >
                <option value="annual">Annual Review</option>
                <option value="mid_year">Mid-Year Review</option>
                <option value="probationary">Probationary</option>
                <option value="project_based">Project-Based</option>
              </select>
            </div>

            <div>
              <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Period Start</label>
              <input type="date" required value={form.review_period_start}
                onChange={e => setForm({ ...form, review_period_start: e.target.value })}
                className="w-full border rounded-lg px-3 py-2 text-sm dark:bg-gray-700 dark:border-gray-600" />
            </div>

            <div>
              <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Period End</label>
              <input type="date" required value={form.review_period_end}
                onChange={e => setForm({ ...form, review_period_end: e.target.value })}
                className="w-full border rounded-lg px-3 py-2 text-sm dark:bg-gray-700 dark:border-gray-600" />
            </div>
          </div>

          <div>
            <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Employee Self-Assessment Comments</label>
            <textarea rows={3} value={form.employee_comments}
              onChange={e => setForm({ ...form, employee_comments: e.target.value })}
              className="w-full border rounded-lg px-3 py-2 text-sm dark:bg-gray-700 dark:border-gray-600"
              placeholder="Optional: employee self-assessment..." />
          </div>
        </div>

        {/* Criteria */}
        <div className="bg-white dark:bg-gray-800 rounded-lg shadow p-6 space-y-4">
          <div className="flex items-center justify-between">
            <h2 className="font-semibold text-gray-900 dark:text-white">Evaluation Criteria</h2>
            <div className="flex items-center gap-3">
              <span className={`text-sm font-medium ${totalWeight === 100 ? 'text-green-600' : 'text-red-600'}`}>
                Total: {totalWeight}% {totalWeight === 100 ? '' : '(must be 100%)'}
              </span>
              <button type="button" onClick={addCriteria} className="text-sm text-indigo-600 hover:underline">+ Add Criteria</button>
            </div>
          </div>

          {criteria.map((c, i) => (
            <div key={i} className="grid grid-cols-12 gap-3 items-start border-b pb-3 dark:border-gray-700">
              <div className="col-span-4">
                <input placeholder="Criteria name" value={c.criteria_name}
                  onChange={e => updateCriteria(i, 'criteria_name', e.target.value)}
                  className="w-full border rounded px-2 py-1.5 text-sm dark:bg-gray-700 dark:border-gray-600" />
              </div>
              <div className="col-span-5">
                <input placeholder="Description" value={c.description}
                  onChange={e => updateCriteria(i, 'description', e.target.value)}
                  className="w-full border rounded px-2 py-1.5 text-sm dark:bg-gray-700 dark:border-gray-600" />
              </div>
              <div className="col-span-2">
                <input type="number" min={0} max={100} placeholder="Weight %" value={c.weight_pct}
                  onChange={e => updateCriteria(i, 'weight_pct', Number(e.target.value))}
                  className="w-full border rounded px-2 py-1.5 text-sm dark:bg-gray-700 dark:border-gray-600" />
              </div>
              <div className="col-span-1 flex justify-center pt-1">
                <button type="button" onClick={() => removeCriteria(i)} className="text-red-500 hover:text-red-700 text-sm">X</button>
              </div>
            </div>
          ))}
        </div>

        {/* Actions */}
        <div className="flex justify-end gap-3">
          <button type="button" onClick={() => navigate('/hr/appraisals')}
            className="px-4 py-2 border rounded-lg text-sm hover:bg-gray-50 dark:hover:bg-gray-700">
            Cancel
          </button>
          <button type="submit" disabled={totalWeight !== 100 || createAppraisal.isPending}
            className="px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm hover:bg-indigo-700 disabled:opacity-50">
            {createAppraisal.isPending ? 'Creating...' : 'Create Appraisal'}
          </button>
        </div>

        {createAppraisal.isError && (
          <div className="bg-red-50 border border-red-200 rounded-lg p-3 text-red-700 text-sm">
            {(createAppraisal.error as Error)?.message ?? 'Failed to create appraisal'}
          </div>
        )}
      </form>
    </div>
  )
}
