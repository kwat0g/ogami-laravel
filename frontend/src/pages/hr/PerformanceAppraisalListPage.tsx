import { useState } from 'react'
import { Link } from 'react-router-dom'
import { PageHeader } from '@/components/ui/PageHeader'
import { usePerformanceAppraisals, useCreateAppraisal, type PerformanceAppraisal } from '@/hooks/useEnhancements'
import { useEmployees } from '@/hooks/useEmployees'
import { toast } from 'sonner'

export default function PerformanceAppraisalListPage() {
  const [filters, setFilters] = useState<Record<string, unknown>>({})
  const { data, isLoading } = usePerformanceAppraisals(filters)
  const { data: employeesData } = useEmployees({ per_page: 200, employment_status: 'active' })
  const createMut = useCreateAppraisal()
  const [showForm, setShowForm] = useState(false)
  const [form, setForm] = useState({ employee_id: '', review_period_start: '', review_period_end: '', type: 'annual' })

  const appraisals: PerformanceAppraisal[] = data?.data ?? []
  const employees = employeesData?.data ?? []

  const statusColors: Record<string, string> = {
    draft: 'bg-neutral-100 text-neutral-600',
    self_review: 'bg-yellow-100 text-yellow-700',
    manager_review: 'bg-blue-100 text-blue-700',
    hr_review: 'bg-indigo-100 text-indigo-700',
    completed: 'bg-green-100 text-green-700',
  }

  const handleCreate = async (e: React.FormEvent) => {
    e.preventDefault()
    try {
      await createMut.mutateAsync({
        employee_id: Number(form.employee_id),
        review_period_start: form.review_period_start,
        review_period_end: form.review_period_end,
        type: form.type,
      })
      toast.success('Performance appraisal created.')
      setShowForm(false)
  }

  return (
    <div className="p-6 space-y-6">
      <div className="flex items-center justify-between">
        <PageHeader title="Performance Appraisals" />
        <button onClick={() => setShowForm(!showForm)} className="px-4 py-2 bg-blue-600 text-white text-sm rounded hover:bg-blue-700">
          {showForm ? 'Cancel' : '+ New Appraisal'}
        </button>
      </div>

      {/* Filters */}
      <div className="flex gap-3">
        <select onChange={e => setFilters({...filters, status: e.target.value || undefined})} className="border rounded px-3 py-2 text-sm">
          <option value="">All Statuses</option>
          <option value="draft">Draft</option>
          <option value="self_review">Self Review</option>
          <option value="manager_review">Manager Review</option>
          <option value="hr_review">HR Review</option>
          <option value="completed">Completed</option>
        </select>
      </div>

      {showForm && (
        <form onSubmit={handleCreate} className="bg-white dark:bg-neutral-800 rounded-lg border p-5 grid grid-cols-2 gap-4">
          <div>
            <label className="block text-sm font-medium mb-1">Employee *</label>
            <select value={form.employee_id} onChange={e => setForm({...form, employee_id: e.target.value})} required className="w-full border rounded px-3 py-2 text-sm">
              <option value="">Select Employee</option>
              {employees.map((emp: { id: number; full_name: string }) => <option key={emp.id} value={emp.id}>{emp.full_name}</option>)}
            </select>
          </div>
          <div>
            <label className="block text-sm font-medium mb-1">Type</label>
            <select value={form.type} onChange={e => setForm({...form, type: e.target.value})} className="w-full border rounded px-3 py-2 text-sm">
              <option value="annual">Annual</option>
              <option value="probationary">Probationary</option>
              <option value="mid_year">Mid-Year</option>
            </select>
          </div>
          <div>
            <label className="block text-sm font-medium mb-1">Period Start *</label>
            <input type="date" value={form.review_period_start} onChange={e => setForm({...form, review_period_start: e.target.value})} required className="w-full border rounded px-3 py-2 text-sm" />
          </div>
          <div>
            <label className="block text-sm font-medium mb-1">Period End *</label>
            <input type="date" value={form.review_period_end} onChange={e => setForm({...form, review_period_end: e.target.value})} required className="w-full border rounded px-3 py-2 text-sm" />
          </div>
          <div className="col-span-2 flex justify-end">
            <button type="submit" disabled={createMut.isPending} className="px-4 py-2 bg-green-600 text-white text-sm rounded hover:bg-green-700 disabled:opacity-50">
              {createMut.isPending ? 'Creating...' : 'Create Appraisal'}
            </button>
          </div>
        </form>
      )}

      {isLoading ? (
        <div className="animate-pulse space-y-3">{[1,2,3].map(i => <div key={i} className="h-16 bg-neutral-200 rounded" />)}</div>
      ) : (
        <div className="bg-white dark:bg-neutral-800 rounded-lg border overflow-hidden">
          <table className="w-full text-sm">
            <thead className="bg-neutral-50 dark:bg-neutral-700">
              <tr>
                <th className="text-left px-4 py-3 font-medium">Employee</th>
                <th className="text-left px-4 py-3 font-medium">Type</th>
                <th className="text-left px-4 py-3 font-medium">Period</th>
                <th className="text-center px-4 py-3 font-medium">Score</th>
                <th className="text-center px-4 py-3 font-medium">Status</th>
                <th className="text-center px-4 py-3 font-medium">Actions</th>
              </tr>
            </thead>
            <tbody className="divide-y">
              {appraisals.map(a => (
                <tr key={a.id} className="hover:bg-neutral-50 dark:hover:bg-neutral-700/50">
                  <td className="px-4 py-3 font-medium">{a.employee?.full_name ?? `#${a.employee_id}`}</td>
                  <td className="px-4 py-3 capitalize">{a.type?.replace(/_/g, ' ')}</td>
                  <td className="px-4 py-3 text-xs">{a.review_period_start} - {a.review_period_end}</td>
                  <td className="px-4 py-3 text-center font-mono">{a.overall_score != null ? a.overall_score.toFixed(1) : '-'}</td>
                  <td className="px-4 py-3 text-center">
                    <span className={`px-2 py-0.5 rounded-full text-xs ${statusColors[a.status] ?? 'bg-neutral-100'}`}>
                      {a.status?.replace(/_/g, ' ')}
                    </span>
                  </td>
                  <td className="px-4 py-3 text-center">
                    <Link to={`/hr/appraisals/${a.id}`} className="text-xs text-blue-600 hover:underline">View</Link>
                  </td>
                </tr>
              ))}
              {appraisals.length === 0 && (
                <tr><td colSpan={6} className="px-4 py-8 text-center text-neutral-500">No appraisals found.</td></tr>
              )}
            </tbody>
          </table>
        </div>
      )}
    </div>
  )
}
