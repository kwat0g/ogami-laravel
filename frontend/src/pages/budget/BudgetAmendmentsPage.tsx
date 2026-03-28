import { useState } from 'react'
import { useBudgetAmendments, useBudgetAmendmentAction } from '@/hooks/useEnhancements'

const STATUS_COLORS: Record<string, string> = {
  draft: 'bg-gray-100 text-gray-800',
  submitted: 'bg-blue-100 text-blue-800',
  approved: 'bg-green-100 text-green-800',
  rejected: 'bg-red-100 text-red-800',
}

const TYPE_LABELS: Record<string, string> = {
  reallocation: 'Reallocation',
  increase: 'Increase',
  decrease: 'Decrease',
}

export default function BudgetAmendmentsPage() {
  const [filters, setFilters] = useState<Record<string, unknown>>({})
  const { data, isLoading } = useBudgetAmendments(filters)
  const action = useBudgetAmendmentAction()

  const amendments = data?.data ?? []

  const handleAction = (id: number, actionType: string) => {
    const remarks = actionType === 'reject' ? prompt('Rejection reason:') : ''
    if (actionType === 'reject' && !remarks) return
    action.mutate({ id, action: actionType, data: { remarks } })
  }

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold text-gray-900 dark:text-white">Budget Amendments</h1>
          <p className="text-sm text-gray-500 mt-1">Mid-year budget revisions with approval workflow</p>
        </div>
      </div>

      <div className="bg-white dark:bg-gray-800 rounded-lg shadow overflow-hidden">
        <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
          <thead className="bg-gray-50 dark:bg-gray-900">
            <tr>
              <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Cost Center</th>
              <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
              <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Amount</th>
              <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Justification</th>
              <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
              <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
            </tr>
          </thead>
          <tbody className="divide-y divide-gray-200 dark:divide-gray-700">
            {isLoading ? (
              <tr><td colSpan={6} className="px-4 py-8 text-center text-gray-500">Loading...</td></tr>
            ) : amendments.map((a) => (
              <tr key={a.id}>
                <td className="px-4 py-3 text-sm">{a.cost_center?.name ?? '--'}</td>
                <td className="px-4 py-3 text-sm">
                  <span className={`px-2 py-1 text-xs rounded ${a.amendment_type === 'increase' ? 'bg-green-50 text-green-700' : a.amendment_type === 'decrease' ? 'bg-red-50 text-red-700' : 'bg-blue-50 text-blue-700'}`}>
                    {TYPE_LABELS[a.amendment_type]}
                  </span>
                </td>
                <td className="px-4 py-3 text-sm font-mono">{(a.amount_centavos / 100).toLocaleString('en-PH', { style: 'currency', currency: 'PHP' })}</td>
                <td className="px-4 py-3 text-sm text-gray-500 max-w-xs truncate">{a.justification}</td>
                <td className="px-4 py-3">
                  <span className={`px-2 py-1 text-xs rounded-full font-medium ${STATUS_COLORS[a.status]}`}>
                    {a.status}
                  </span>
                </td>
                <td className="px-4 py-3 text-sm space-x-2">
                  {a.status === 'draft' && (
                    <button onClick={() => handleAction(a.id, 'submit')} className="text-blue-600 hover:underline text-xs">Submit</button>
                  )}
                  {a.status === 'submitted' && (
                    <>
                      <button onClick={() => handleAction(a.id, 'approve')} className="text-green-600 hover:underline text-xs">Approve</button>
                      <button onClick={() => handleAction(a.id, 'reject')} className="text-red-600 hover:underline text-xs">Reject</button>
                    </>
                  )}
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </div>
  )
}
