import { useParams } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import api from '@/lib/api'
import StatusBadge from '@/components/recruitment/StatusBadge'
import { Link } from 'react-router-dom'

export default function CandidateProfilePage() {
  const { ulid } = useParams<{ ulid: string }>()

  const { data: candidate, isLoading } = useQuery({
    queryKey: ['recruitment', 'candidates', ulid],
    queryFn: async () => {
      const { data } = await api.get(`/recruitment/candidates/${ulid}`)
      return data.data
    },
    enabled: !!ulid,
  })

  if (isLoading || !candidate) return <div className="p-6">Loading...</div>

  return (
    <div className="mx-auto max-w-4xl space-y-6 p-6">
      <div className="flex items-start justify-between">
        <div>
          <h1 className="text-2xl font-bold text-gray-900 dark:text-white">{candidate.full_name}</h1>
          <p className="text-sm text-gray-500">{candidate.email}</p>
        </div>
        <div className="flex gap-2">
          {candidate.resume_path && (
            <a
              href={`/api/v1/recruitment/candidates/${ulid}/resume`}
              target="_blank"
              rel="noopener noreferrer"
              className="rounded-md border border-gray-300 px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-300"
            >
              Download Resume
            </a>
          )}
        </div>
      </div>

      <div className="grid grid-cols-2 gap-6 rounded-lg border border-gray-200 bg-white p-6 dark:border-gray-700 dark:bg-gray-800">
        <div>
          <p className="text-xs text-gray-500">Phone</p>
          <p className="text-sm font-medium">{candidate.phone ?? 'Not provided'}</p>
        </div>
        <div>
          <p className="text-xs text-gray-500">Source</p>
          <p className="text-sm font-medium">{candidate.source_label}</p>
        </div>
        <div className="col-span-2">
          <p className="text-xs text-gray-500">Address</p>
          <p className="text-sm">{candidate.address ?? 'Not provided'}</p>
        </div>
        {candidate.linkedin_url && (
          <div className="col-span-2">
            <p className="text-xs text-gray-500">LinkedIn</p>
            <a href={candidate.linkedin_url} target="_blank" rel="noopener noreferrer" className="text-sm text-blue-600 hover:underline">
              {candidate.linkedin_url}
            </a>
          </div>
        )}
        {candidate.notes && (
          <div className="col-span-2">
            <p className="text-xs text-gray-500">Notes</p>
            <p className="text-sm">{candidate.notes}</p>
          </div>
        )}
      </div>

      {/* Application History */}
      <div className="rounded-lg border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800">
        <div className="border-b border-gray-200 px-6 py-4 dark:border-gray-700">
          <h3 className="text-sm font-semibold text-gray-700 dark:text-gray-300">Application History</h3>
        </div>
        <div className="divide-y divide-gray-100 dark:divide-gray-700">
          {candidate.applications?.length > 0 ? (
            // eslint-disable-next-line @typescript-eslint/no-explicit-any
            candidate.applications.map((app: any) => (
              <Link
                key={app.ulid}
                to={`/hr/recruitment/applications/${app.ulid}`}
                className="flex items-center justify-between px-6 py-3 hover:bg-gray-50 dark:hover:bg-gray-700"
              >
                <div>
                  <p className="text-sm font-medium text-gray-900 dark:text-white">{app.application_number}</p>
                  <p className="text-xs text-gray-500">{app.posting?.position ?? 'Unknown position'}</p>
                </div>
                <div className="text-right">
                  <StatusBadge status={app.status} label={app.status_label} />
                  <p className="mt-1 text-xs text-gray-400">{app.application_date}</p>
                </div>
              </Link>
            ))
          ) : (
            <p className="px-6 py-4 text-sm text-gray-400">No applications on record.</p>
          )}
        </div>
      </div>
    </div>
  )
}
