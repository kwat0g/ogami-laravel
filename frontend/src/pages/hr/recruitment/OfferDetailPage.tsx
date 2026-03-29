import { useParams } from 'react-router-dom'
import { useOffer, useOfferAction } from '@/hooks/useRecruitment'
import StatusBadge from '@/components/recruitment/StatusBadge'
import OfferLetterPreview from '@/components/recruitment/OfferLetterPreview'
import { useState } from 'react'
import { toast } from 'sonner'

export default function OfferDetailPage() {
  const { ulid } = useParams<{ ulid: string }>()
  const { data: offer, isLoading } = useOffer(ulid ?? '')
  const action = useOfferAction(ulid ?? '')
  const [showPreview, setShowPreview] = useState(false)
  const [rejectReason, setRejectReason] = useState('')

  if (isLoading || !offer) return <div className="p-6">Loading...</div>

  const handleAction = async (act: string, payload?: Record<string, unknown>) => {
    try {
      await action.mutateAsync({ action: act, payload })
      toast.success(`Offer ${act} successfully`)
    } catch {
      toast.error(`Failed to ${act} offer`)
    }
  }

  const daysUntilExpiry = offer.expires_at
    ? Math.ceil((new Date(offer.expires_at).getTime() - Date.now()) / (1000 * 60 * 60 * 24))
    : null

  return (
    <div className="mx-auto max-w-4xl space-y-6 p-6">
      {/* Header */}
      <div className="flex items-start justify-between">
        <div>
          <p className="text-sm text-gray-500">{offer.offer_number}</p>
          <h1 className="text-2xl font-bold text-gray-900 dark:text-white">
            Offer for {offer.application?.candidate?.full_name}
          </h1>
          <p className="text-sm text-gray-500">{offer.offered_position?.title} - {offer.offered_department?.name}</p>
        </div>
        <StatusBadge status={offer.status} label={offer.status_label} />
      </div>

      {/* Expiry warning */}
      {offer.status === 'sent' && daysUntilExpiry !== null && (
        <div className={`rounded-lg border p-4 ${daysUntilExpiry <= 2 ? 'border-red-200 bg-red-50' : 'border-amber-200 bg-amber-50'}`}>
          <p className={`text-sm font-medium ${daysUntilExpiry <= 2 ? 'text-red-800' : 'text-amber-800'}`}>
            {daysUntilExpiry > 0
              ? `Awaiting response - expires in ${daysUntilExpiry} day${daysUntilExpiry !== 1 ? 's' : ''}`
              : 'This offer has expired'}
          </p>
        </div>
      )}

      {/* Actions */}
      <div className="flex gap-3">
        {offer.status === 'draft' && (
          <>
            <button
              onClick={() => setShowPreview(true)}
              className="rounded-md border border-gray-300 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-300"
            >
              Preview Offer Letter
            </button>
            <button
              onClick={() => { if (confirm('Send this offer to the candidate?')) handleAction('send') }}
              disabled={action.isPending}
              className="rounded-md bg-purple-600 px-4 py-2 text-sm font-semibold text-white hover:bg-purple-500 disabled:opacity-50"
            >
              {action.isPending ? 'Sending...' : 'Send Offer'}
            </button>
          </>
        )}
        {offer.status === 'sent' && (
          <>
            <button
              onClick={() => { if (confirm('Mark this offer as accepted?')) handleAction('accept') }}
              disabled={action.isPending}
              className="rounded-md bg-green-600 px-4 py-2 text-sm font-semibold text-white hover:bg-green-500 disabled:opacity-50"
            >
              {action.isPending ? 'Processing...' : 'Mark as Accepted'}
            </button>
            <button
              onClick={() => {
                if (rejectReason.trim() && confirm('Reject this offer?')) {
                  handleAction('reject', { reason: rejectReason })
                }
              }}
              disabled={action.isPending || !rejectReason.trim()}
              className="rounded-md bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-500 disabled:opacity-50"
            >
              {action.isPending ? 'Processing...' : 'Mark as Rejected'}
            </button>
            <button
              onClick={() => { if (confirm('Withdraw this offer? This cannot be undone.')) handleAction('withdraw') }}
              disabled={action.isPending}
              className="rounded-md border border-gray-300 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50"
            >
              {action.isPending ? 'Processing...' : 'Withdraw Offer'}
            </button>
          </>
        )}
      </div>

      {/* Rejection reason input */}
      {offer.status === 'sent' && (
        <input
          type="text"
          placeholder="Rejection reason (required to reject)..."
          value={rejectReason}
          onChange={(e) => setRejectReason(e.target.value)}
          className="w-full rounded-md border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800"
        />
      )}

      {/* Details */}
      <div className="grid grid-cols-2 gap-6 rounded-lg border border-gray-200 bg-white p-6 dark:border-gray-700 dark:bg-gray-800">
        <div>
          <p className="text-xs text-gray-500">Offered Salary</p>
          <p className="text-xl font-bold text-gray-900 dark:text-white">
            {(offer.offered_salary / 100).toLocaleString('en-PH', { style: 'currency', currency: 'PHP' })}
          </p>
        </div>
        <div>
          <p className="text-xs text-gray-500">Employment Type</p>
          <p className="text-sm font-medium">{offer.employment_type_label}</p>
        </div>
        <div>
          <p className="text-xs text-gray-500">Start Date</p>
          <p className="text-sm font-medium">{offer.start_date}</p>
        </div>
        <div>
          <p className="text-xs text-gray-500">Prepared By</p>
          <p className="text-sm font-medium">{offer.preparer?.name ?? '-'}</p>
        </div>
        {offer.sent_at && (
          <div>
            <p className="text-xs text-gray-500">Sent At</p>
            <p className="text-sm">{new Date(offer.sent_at).toLocaleString()}</p>
          </div>
        )}
        {offer.responded_at && (
          <div>
            <p className="text-xs text-gray-500">Responded At</p>
            <p className="text-sm">{new Date(offer.responded_at).toLocaleString()}</p>
          </div>
        )}
        {offer.rejection_reason && (
          <div className="col-span-2">
            <p className="text-xs text-gray-500">Rejection Reason</p>
            <p className="text-sm text-red-600">{offer.rejection_reason}</p>
          </div>
        )}
      </div>

      {/* Offer Letter Preview Modal */}
      {showPreview && offer && (
        <OfferLetterPreview
          candidateName={offer.application?.candidate?.full_name ?? ''}
          positionTitle={offer.offered_position?.title ?? ''}
          departmentName={offer.offered_department?.name ?? ''}
          salary={offer.offered_salary}
          startDate={offer.start_date}
          employmentType={offer.employment_type_label}
          onClose={() => setShowPreview(false)}
        />
      )}
    </div>
  )
}
