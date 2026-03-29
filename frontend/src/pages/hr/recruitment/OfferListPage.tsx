import { useState } from 'react'
import { Link } from 'react-router-dom'
import { useOffers } from '@/hooks/useRecruitment'
import StatusBadge from '@/components/recruitment/StatusBadge'

export default function OfferListPage() {
  const [status, setStatus] = useState('')
  const { data, isLoading } = useOffers({
    ...(status && { status }),
  })

  return (
    <div className="p-6 space-y-4">
      <h1 className="text-2xl font-bold text-gray-900 dark:text-white">Job Offers</h1>

      <div className="flex gap-4">
        <select
          value={status}
          onChange={(e) => setStatus(e.target.value)}
          className="rounded-md border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800"
        >
          <option value="">All Statuses</option>
          <option value="draft">Draft</option>
          <option value="sent">Sent</option>
          <option value="accepted">Accepted</option>
          <option value="rejected">Rejected</option>
          <option value="expired">Expired</option>
          <option value="withdrawn">Withdrawn</option>
        </select>
      </div>

      {isLoading ? (
        <div className="py-8 text-center text-gray-400">Loading...</div>
      ) : (
        <div className="overflow-hidden rounded-lg border border-gray-200 dark:border-gray-700">
          <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
            <thead className="bg-gray-50 dark:bg-gray-800">
              <tr>
                <th className="px-6 py-3 text-left text-xs font-medium uppercase text-gray-500">Offer #</th>
                <th className="px-6 py-3 text-left text-xs font-medium uppercase text-gray-500">Candidate</th>
                <th className="px-6 py-3 text-left text-xs font-medium uppercase text-gray-500">Position</th>
                <th className="px-6 py-3 text-left text-xs font-medium uppercase text-gray-500">Salary</th>
                <th className="px-6 py-3 text-left text-xs font-medium uppercase text-gray-500">Start Date</th>
                <th className="px-6 py-3 text-left text-xs font-medium uppercase text-gray-500">Status</th>
                <th className="px-6 py-3 text-left text-xs font-medium uppercase text-gray-500">Expires</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-gray-200 bg-white dark:divide-gray-700 dark:bg-gray-900">
              {data?.data?.map((offer) => (
                <tr key={offer.ulid} className="hover:bg-gray-50 dark:hover:bg-gray-800">
                  <td className="px-6 py-4 text-sm">
                    <Link to={`/hr/recruitment/offers/${offer.ulid}`} className="font-medium text-blue-600 hover:underline">
                      {offer.offer_number}
                    </Link>
                  </td>
                  <td className="px-6 py-4 text-sm text-gray-700 dark:text-gray-300">
                    {offer.application?.candidate?.full_name ?? '-'}
                  </td>
                  <td className="px-6 py-4 text-sm text-gray-500">{offer.offered_position?.title ?? '-'}</td>
                  <td className="px-6 py-4 text-sm text-gray-500">
                    {(offer.offered_salary / 100).toLocaleString('en-PH', { style: 'currency', currency: 'PHP' })}
                  </td>
                  <td className="px-6 py-4 text-sm text-gray-500">{offer.start_date}</td>
                  <td className="px-6 py-4">
                    <StatusBadge status={offer.status} label={offer.status_label} />
                  </td>
                  <td className="px-6 py-4 text-sm text-gray-500">
                    {offer.expires_at ? new Date(offer.expires_at).toLocaleDateString() : '-'}
                  </td>
                </tr>
              ))}
              {(!data?.data || data.data.length === 0) && (
                <tr>
                  <td colSpan={7} className="px-6 py-8 text-center text-sm text-gray-400">
                    No offers found.
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
