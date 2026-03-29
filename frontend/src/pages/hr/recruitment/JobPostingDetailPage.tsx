import { useParams, Link } from 'react-router-dom'
import { usePosting, usePostingAction } from '@/hooks/useRecruitment'
import StatusBadge from '@/components/recruitment/StatusBadge'

export default function JobPostingDetailPage() {
  const { ulid } = useParams<{ ulid: string }>()
  const { data: posting, isLoading } = usePosting(ulid ?? '')
  const action = usePostingAction(ulid ?? '')

  if (isLoading || !posting) return <div className="p-6">Loading...</div>

  return (
    <div className="mx-auto max-w-4xl space-y-6 p-6">
      {/* Header */}
      <div className="flex items-start justify-between">
        <div>
          <p className="text-sm text-gray-500">{posting.posting_number}</p>
          <h1 className="text-2xl font-bold text-gray-900 dark:text-white">{posting.title}</h1>
          <p className="text-sm text-gray-500">{posting.requisition?.department} - {posting.requisition?.position}</p>
        </div>
        <StatusBadge status={posting.status} label={posting.status_label} />
      </div>

      {/* Actions */}
      <div className="flex gap-3">
        {posting.status === 'draft' && (
          <button
            onClick={() => action.mutate({ action: 'publish' })}
            disabled={action.isPending}
            className="rounded-md bg-green-600 px-4 py-2 text-sm font-semibold text-white hover:bg-green-500 disabled:opacity-50"
          >
            Publish Posting
          </button>
        )}
        {posting.status === 'published' && (
          <button
            onClick={() => action.mutate({ action: 'close' })}
            disabled={action.isPending}
            className="rounded-md bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-500 disabled:opacity-50"
          >
            Close Posting
          </button>
        )}
        <Link
          to={`/hr/recruitment/applications?posting=${ulid}`}
          className="rounded-md border border-gray-300 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-300"
        >
          View Applications ({posting.applications_count ?? 0})
        </Link>
      </div>

      {/* Details */}
      <div className="grid grid-cols-2 gap-6 rounded-lg border border-gray-200 bg-white p-6 dark:border-gray-700 dark:bg-gray-800">
        <div>
          <p className="text-xs text-gray-500">Employment Type</p>
          <p className="text-sm font-medium">{posting.employment_type_label}</p>
        </div>
        <div>
          <p className="text-xs text-gray-500">Location</p>
          <p className="text-sm font-medium">{posting.location ?? 'Not specified'}</p>
        </div>
        <div>
          <p className="text-xs text-gray-500">Visibility</p>
          <p className="text-sm font-medium">
            {[posting.is_internal && 'Internal', posting.is_external && 'External'].filter(Boolean).join(' + ')}
          </p>
        </div>
        <div>
          <p className="text-xs text-gray-500">Views</p>
          <p className="text-sm font-medium">{posting.views_count}</p>
        </div>
        <div>
          <p className="text-xs text-gray-500">Published</p>
          <p className="text-sm">{posting.published_at ? new Date(posting.published_at).toLocaleDateString() : 'Not yet'}</p>
        </div>
        <div>
          <p className="text-xs text-gray-500">Closes</p>
          <p className="text-sm">{posting.closes_at ? new Date(posting.closes_at).toLocaleDateString() : 'Open-ended'}</p>
        </div>
      </div>

      {/* Description */}
      <div className="rounded-lg border border-gray-200 bg-white p-6 dark:border-gray-700 dark:bg-gray-800">
        <h3 className="mb-3 text-sm font-semibold text-gray-700 dark:text-gray-300">Job Description</h3>
        <div className="prose prose-sm max-w-none text-gray-700 dark:text-gray-300 whitespace-pre-line">
          {posting.description}
        </div>
      </div>

      {/* Requirements */}
      <div className="rounded-lg border border-gray-200 bg-white p-6 dark:border-gray-700 dark:bg-gray-800">
        <h3 className="mb-3 text-sm font-semibold text-gray-700 dark:text-gray-300">Requirements</h3>
        <div className="prose prose-sm max-w-none text-gray-700 dark:text-gray-300 whitespace-pre-line">
          {posting.requirements}
        </div>
      </div>
    </div>
  )
}
