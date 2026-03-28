import { useState } from 'react'
import { Link } from 'react-router-dom'
import { usePerformanceAppraisals, useCreateAppraisal, useAppraisalAction } from '@/hooks/useEnhancements'
import type { PerformanceAppraisal } from '@/hooks/useEnhancements'

const STATUS_COLORS: Record<string, string> = {
  draft: 'bg-gray-100 text-gray-800',
  submitted: 'bg-blue-100 text-blue-800',
  manager_reviewed: 'bg-yellow-100 text-yellow-800',
  hr_approved: 'bg-green-100 text-green-800',
  completed: 'bg-emerald-100 text-emerald-800',
}

const REVIEW_TYPE_LABELS: Record<string, string> = {
  annual: 'Annual Review',
  mid_year: 'Mid-Year Review',
  probationary: 'Probationary',
  project_based: 'Project-Based',
}

export default function PerformanceAppraisalListPage() {
  const [filters, setFilters] = useState<Record<string, unknown>>({})
  const { data, isLoading } = usePerformanceAppraisals(filters)

  const appraisals = data?.data ?? []
  const meta = data?.meta

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold text-gray-900 dark:text-white">Performance Appraisals</h1>
          <p className="text-sm text-gray-500 mt-1">Manage employee performance evaluations with weighted KPI criteria</p>
        </div>
        <Link
          to="/hr/appraisals/create"
          className="inline-flex items-center px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors"
        >
          + New Appraisal
        </Link>
      </div>

      {/* Filters */}
      <div className="flex gap-3 flex-wrap">
        <select
          className="border rounded-lg px-3 py-2 text-sm dark:bg-gray-800 dark:border-gray-700"
          onChange={(e) => setFilters(f => ({ ...f, status: e.target.value || undefined }))}
        >
          <option value="">All Statuses</option>
          <option value="draft">Draft</option>
          <option value="submitted">Submitted</option>
          <option value="manager_reviewed">Manager Reviewed</option>
          <option value="hr_approved">HR Approved</option>
          <option value="completed">Completed</option>
        </select>
        <select
          className="border rounded-lg px-3 py-2 text-sm dark:bg-gray-800 dark:border-gray-700"
          onChange={(e) => setFilters(f => ({ ...f, review_type: e.target.value || undefined }))}
        >
          <option value="">All Types</option>
          <option value="annual">Annual</option>
          <option value="mid_year">Mid-Year</option>
          <option value="probationary">Probationary</option>
          <option value="project_based">Project-Based</option>
        </select>
      </div>

      {/* Table */}
      <div className="bg-white dark:bg-gray-800 rounded-lg shadow overflow-hidden">
        <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
          <thead className="bg-gray-50 dark:bg-gray-900">
            <tr>
              <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Employee</th>
              <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
              <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Period</th>
              <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Rating</th>
              <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
              <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Reviewer</th>
            </tr>
          </thead>
          <tbody className="divide-y divide-gray-200 dark:divide-gray-700">
            {isLoading ? (
              <tr><td colSpan={6} className="px-4 py-8 text-center text-gray-500">Loading...</td></tr>
            ) : appraisals.length === 0 ? (
              <tr><td colSpan={6} className="px-4 py-8 text-center text-gray-500">No appraisals found</td></tr>
            ) : appraisals.map((a: PerformanceAppraisal) => (
              <tr key={a.id} className="hover:bg-gray-50 dark:hover:bg-gray-700/50 cursor-pointer">
                <td className="px-4 py-3 text-sm font-medium">
                  <Link to={`/hr/appraisals/${a.id}`} className="text-indigo-600 hover:underline">
                    {a.employee ? `${a.employee.last_name}, ${a.employee.first_name}` : `Employee #${a.employee_id}`}
                  </Link>
                </td>
                <td className="px-4 py-3 text-sm">{REVIEW_TYPE_LABELS[a.review_type] ?? a.review_type}</td>
                <td className="px-4 py-3 text-sm text-gray-500">
                  {a.review_period_start} - {a.review_period_end}
                </td>
                <td className="px-4 py-3 text-sm">
                  {a.overall_rating_pct !== null ? (
                    <div className="flex items-center gap-2">
                      <div className="w-16 bg-gray-200 rounded-full h-2">
                        <div
                          className={`h-2 rounded-full ${a.overall_rating_pct >= 80 ? 'bg-green-500' : a.overall_rating_pct >= 60 ? 'bg-yellow-500' : 'bg-red-500'}`}
                          style={{ width: `${a.overall_rating_pct}%` }}
                        />
                      </div>
                      <span className="text-xs font-medium">{a.overall_rating_pct}%</span>
                    </div>
                  ) : (
                    <span className="text-gray-400">--</span>
                  )}
                </td>
                <td className="px-4 py-3">
                  <span className={`px-2 py-1 text-xs rounded-full font-medium ${STATUS_COLORS[a.status] ?? 'bg-gray-100'}`}>
                    {a.status.replace(/_/g, ' ')}
                  </span>
                </td>
                <td className="px-4 py-3 text-sm text-gray-500">{a.reviewer?.name ?? '--'}</td>
              </tr>
            ))}
          </tbody>
        </table>

        {meta && meta.last_page > 1 && (
          <div className="px-4 py-3 border-t dark:border-gray-700 text-sm text-gray-500">
            Page {meta.current_page} of {meta.last_page} ({meta.total} total)
          </div>
        )}
      </div>
    </div>
  )
}
