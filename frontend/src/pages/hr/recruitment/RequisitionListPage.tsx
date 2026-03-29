import { useState } from 'react'
import { Link } from 'react-router-dom'
import { useRequisitions } from '@/hooks/useRecruitment'
import StatusBadge from '@/components/recruitment/StatusBadge'

export default function RequisitionListPage() {
  const [search, setSearch] = useState('')
  const [status, setStatus] = useState('')
  const { data, isLoading } = useRequisitions({
    ...(search && { search }),
    ...(status && { status }),
  })

  return (
    <div className="p-6 space-y-4">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-bold text-gray-900 dark:text-white">Job Requisitions</h1>
        <Link
          to="/hr/recruitment/requisitions/new"
          className="rounded-md bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-500"
        >
          New Requisition
        </Link>
      </div>

      {/* Filters */}
      <div className="flex gap-4">
        <input
          type="text"
          placeholder="Search requisitions..."
          value={search}
          onChange={(e) => setSearch(e.target.value)}
          className="rounded-md border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800"
        />
        <select
          value={status}
          onChange={(e) => setStatus(e.target.value)}
          className="rounded-md border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800"
        >
          <option value="">All Statuses</option>
          <option value="draft">Draft</option>
          <option value="pending_approval">Pending Approval</option>
          <option value="approved">Approved</option>
          <option value="open">Open</option>
          <option value="closed">Closed</option>
          <option value="cancelled">Cancelled</option>
        </select>
      </div>

      {/* Table */}
      {isLoading ? (
        <div>Loading...</div>
      ) : (
        <div className="overflow-hidden rounded-lg border border-gray-200 dark:border-gray-700">
          <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
            <thead className="bg-gray-50 dark:bg-gray-800">
              <tr>
                <th className="px-6 py-3 text-left text-xs font-medium uppercase text-gray-500">Req #</th>
                <th className="px-6 py-3 text-left text-xs font-medium uppercase text-gray-500">Position</th>
                <th className="px-6 py-3 text-left text-xs font-medium uppercase text-gray-500">Department</th>
                <th className="px-6 py-3 text-left text-xs font-medium uppercase text-gray-500">Headcount</th>
                <th className="px-6 py-3 text-left text-xs font-medium uppercase text-gray-500">Status</th>
                <th className="px-6 py-3 text-left text-xs font-medium uppercase text-gray-500">Requested By</th>
                <th className="px-6 py-3 text-left text-xs font-medium uppercase text-gray-500">Date</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-gray-200 bg-white dark:divide-gray-700 dark:bg-gray-900">
              {data?.data?.map((req) => (
                <tr key={req.ulid} className="hover:bg-gray-50 dark:hover:bg-gray-800">
                  <td className="px-6 py-4 text-sm">
                    <Link to={`/hr/recruitment/requisitions/${req.ulid}`} className="font-medium text-blue-600 hover:underline">
                      {req.requisition_number}
                    </Link>
                  </td>
                  <td className="px-6 py-4 text-sm text-gray-700 dark:text-gray-300">{req.position?.title}</td>
                  <td className="px-6 py-4 text-sm text-gray-500">{req.department?.name}</td>
                  <td className="px-6 py-4 text-sm text-gray-500">{req.headcount}</td>
                  <td className="px-6 py-4">
                    <StatusBadge status={req.status} label={req.status_label} />
                  </td>
                  <td className="px-6 py-4 text-sm text-gray-500">{req.requester?.name}</td>
                  <td className="px-6 py-4 text-sm text-gray-500">
                    {req.created_at ? new Date(req.created_at).toLocaleDateString() : ''}
                  </td>
                </tr>
              ))}
              {(!data?.data || data.data.length === 0) && (
                <tr>
                  <td colSpan={7} className="px-6 py-8 text-center text-sm text-gray-400">
                    No requisitions found.
                  </td>
                </tr>
              )}
            </tbody>
          </table>
        </div>
      )}
    </div>
  )
}
