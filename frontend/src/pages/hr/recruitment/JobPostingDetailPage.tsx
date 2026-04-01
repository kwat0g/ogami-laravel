import { useParams, Link } from 'react-router-dom'
import { usePosting, usePostingAction } from '@/hooks/useRecruitment'
import StatusBadge from '@/components/recruitment/StatusBadge'
import PermissionGuard from '@/components/ui/PermissionGuard'
import { toast } from 'sonner'

export default function JobPostingDetailPage() {
  const { ulid } = useParams<{ ulid: string }>()
  const { data: posting, isLoading } = usePosting(ulid ?? '')
  const action = usePostingAction(ulid ?? '')

  if (isLoading || !posting) return <div className="p-6">Loading...</div>

  const handleAction = async (act: string) => {
    try {
      await action.mutateAsync({ action: act })
      toast.success(`Posting ${act} successfully`)
    } catch {
      toast.error(`Failed to ${act} posting`)
    }
  }

  return (
    <div className="mx-auto max-w-4xl space-y-6 p-6">
      {/* Header */}
      <div className="flex items-start justify-between">
        <div>
          <p className="text-sm text-neutral-500">{posting.posting_number}</p>
          <h1 className="text-lg font-semibold text-neutral-900 dark:text-white">{posting.title}</h1>
          <p className="text-sm text-neutral-500">{posting.requisition?.department} - {posting.requisition?.position}</p>
        </div>
        <StatusBadge status={posting.status} label={posting.status_label} />
      </div>

      {/* Actions */}
      <div className="flex gap-3">
        <PermissionGuard permission="recruitment.postings.publish">
          {posting.status === 'draft' && (
            <button
              onClick={() => { if (confirm('Publish this posting? It will be visible to applicants.')) handleAction('publish') }}
              disabled={action.isPending}
              className="rounded-md bg-green-600 px-4 py-2 text-sm font-semibold text-white hover:bg-green-500 disabled:opacity-50"
            >
              {action.isPending ? 'Publishing...' : 'Publish Posting'}
            </button>
          )}
        </PermissionGuard>
        <PermissionGuard permission="recruitment.postings.close">
          {posting.status === 'published' && (
            <button
              onClick={() => { if (confirm('Close this posting? No more applications will be accepted.')) handleAction('close') }}
              disabled={action.isPending}
              className="rounded-md bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-500 disabled:opacity-50"
            >
              {action.isPending ? 'Closing...' : 'Close Posting'}
            </button>
          )}
        </PermissionGuard>
        {/* GAP-30: Reopen closed/expired postings */}
        <PermissionGuard permission="recruitment.postings.publish">
          {(posting.status === 'closed' || posting.status === 'expired') && (
            <button
              onClick={() => { if (confirm('Reopen this posting? It will be published again.')) handleAction('reopen') }}
              disabled={action.isPending}
              className="rounded-md bg-green-600 px-4 py-2 text-sm font-semibold text-white hover:bg-green-500 disabled:opacity-50"
            >
              {action.isPending ? 'Reopening...' : 'Reopen Posting'}
            </button>
          )}
        </PermissionGuard>
        {/* GAP-23: Edit button for draft/published postings */}
        <PermissionGuard permission="recruitment.postings.create">
          {(posting.status === 'draft' || posting.status === 'published') && (
            <Link
              to={`/hr/recruitment/postings/${ulid}/edit`}
              className="rounded-md border border-neutral-300 px-4 py-2 text-sm text-neutral-700 hover:bg-neutral-50 dark:border-neutral-600 dark:text-neutral-300"
            >
              Edit Posting
            </Link>
          )}
        </PermissionGuard>
        <Link
          to={`/hr/recruitment/applications?posting=${ulid}`}
          className="rounded-md border border-neutral-300 px-4 py-2 text-sm text-neutral-700 hover:bg-neutral-50 dark:border-neutral-600 dark:text-neutral-300"
        >
          View Applications ({posting.applications_count ?? 0})
        </Link>
        {/* GAP-24: Add Application from posting context */}
        <PermissionGuard permission="recruitment.applications.review">
          {posting.status === 'published' && (
            <Link
              to={`/hr/recruitment/applications/new?posting=${ulid}`}
              className="rounded-md bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-500"
            >
              Add Application
            </Link>
          )}
        </PermissionGuard>
      </div>

      {/* Details */}
      <div className="grid grid-cols-2 gap-6 rounded-lg border border-neutral-200 bg-white p-6 dark:border-neutral-700 dark:bg-neutral-800">
        <div>
          <p className="text-xs text-neutral-500">Employment Type</p>
          <p className="text-sm font-medium">{posting.employment_type_label}</p>
        </div>
        <div>
          <p className="text-xs text-neutral-500">Location</p>
          <p className="text-sm font-medium">{posting.location ?? 'Not specified'}</p>
        </div>
        <div>
          <p className="text-xs text-neutral-500">Visibility</p>
          <p className="text-sm font-medium">
            {[posting.is_internal && 'Internal', posting.is_external && 'External'].filter(Boolean).join(' + ')}
          </p>
        </div>
        <div>
          <p className="text-xs text-neutral-500">Views</p>
          <p className="text-sm font-medium">{posting.views_count}</p>
        </div>
        <div>
          <p className="text-xs text-neutral-500">Published</p>
          <p className="text-sm">{posting.published_at ? new Date(posting.published_at).toLocaleDateString() : 'Not yet'}</p>
        </div>
        <div>
          <p className="text-xs text-neutral-500">Closes</p>
          <p className="text-sm">{posting.closes_at ? new Date(posting.closes_at).toLocaleDateString() : 'Open-ended'}</p>
        </div>
      </div>

      {/* Description */}
      <div className="rounded-lg border border-neutral-200 bg-white p-6 dark:border-neutral-700 dark:bg-neutral-800">
        <h3 className="mb-3 text-sm font-semibold text-neutral-700 dark:text-neutral-300">Job Description</h3>
        <div className="prose prose-sm max-w-none text-neutral-700 dark:text-neutral-300 whitespace-pre-line">
          {posting.description}
        </div>
      </div>

      {/* Requirements */}
      <div className="rounded-lg border border-neutral-200 bg-white p-6 dark:border-neutral-700 dark:bg-neutral-800">
        <h3 className="mb-3 text-sm font-semibold text-neutral-700 dark:text-neutral-300">Requirements</h3>
        <div className="prose prose-sm max-w-none text-neutral-700 dark:text-neutral-300 whitespace-pre-line">
          {posting.requirements}
        </div>
      </div>
    </div>
  )
}
