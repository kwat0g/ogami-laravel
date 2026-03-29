import { useParams } from 'react-router-dom'
import { useApplication, useApplicationAction } from '@/hooks/useRecruitment'
import StatusBadge from '@/components/recruitment/StatusBadge'
import ApplicationTimeline from '@/components/recruitment/ApplicationTimeline'
import HiringModal from '@/components/recruitment/HiringModal'
import { useState } from 'react'

export default function ApplicationDetailPage() {
  const { ulid } = useParams<{ ulid: string }>()
  const { data: app, isLoading } = useApplication(ulid ?? '')
  const action = useApplicationAction(ulid ?? '')
  const [tab, setTab] = useState<'profile' | 'interviews' | 'offer' | 'documents' | 'history'>('profile')
  const [rejectReason, setRejectReason] = useState('')
  const [showHiringModal, setShowHiringModal] = useState(false)

  if (isLoading || !app) return <div className="p-6">Loading...</div>

  return (
    <div className="p-6 space-y-6">
      {/* Header */}
      <div className="flex items-start justify-between">
        <div>
          <p className="text-sm text-gray-500">{app.application_number}</p>
          <h1 className="text-2xl font-bold text-gray-900 dark:text-white">
            {app.candidate?.full_name}
          </h1>
          <p className="text-sm text-gray-500">
            {app.posting?.position} - {app.posting?.department}
          </p>
        </div>
        <div className="flex items-center gap-3">
          <StatusBadge status={app.status} label={app.status_label} />
          {/* Quick Actions */}
          {(app.status === 'new' || app.status === 'under_review') && (
            <button
              onClick={() => action.mutate({ action: 'shortlist' })}
              disabled={action.isPending}
              className="rounded-md bg-teal-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-teal-500"
            >
              Shortlist
            </button>
          )}

          {app.offer?.status === 'accepted' && app.status !== 'hired' && (
            <button
              onClick={() => setShowHiringModal(true)}
              className="rounded-md bg-green-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-green-500"
            >
              Hire Candidate
            </button>
          )}
        </div>
      </div>

      <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
        {/* Left: Timeline */}
        <div className="rounded-lg border border-gray-200 bg-white p-6 dark:border-gray-700 dark:bg-gray-800">
          <h3 className="mb-4 text-sm font-semibold text-gray-700 dark:text-gray-300">Application Journey</h3>
          <ApplicationTimeline application={app} />
        </div>

        {/* Right: Tabs */}
        <div className="lg:col-span-2 space-y-4">
          {/* Tab Navigation */}
          <div className="flex gap-1 border-b border-gray-200 dark:border-gray-700">
            {(['profile', 'interviews', 'offer', 'documents', 'history'] as const).map((t) => (
              <button
                key={t}
                onClick={() => setTab(t)}
                className={`px-4 py-2 text-sm font-medium border-b-2 transition ${tab === t
                    ? 'border-blue-600 text-blue-600'
                    : 'border-transparent text-gray-500 hover:text-gray-700'
                  }`}
              >
                {t.charAt(0).toUpperCase() + t.slice(1)}
              </button>
            ))}
          </div>

          {/* Tab Content */}
          <div className="rounded-lg border border-gray-200 bg-white p-6 dark:border-gray-700 dark:bg-gray-800">
            {tab === 'profile' && (
              <div className="space-y-3">
                <div className="grid grid-cols-2 gap-4">
                  <div>
                    <p className="text-xs text-gray-500">Email</p>
                    <p className="text-sm">{app.candidate?.email}</p>
                  </div>
                  <div>
                    <p className="text-xs text-gray-500">Source</p>
                    <p className="text-sm">{app.source_label}</p>
                  </div>
                  <div>
                    <p className="text-xs text-gray-500">Applied On</p>
                    <p className="text-sm">{app.application_date}</p>
                  </div>
                  {app.reviewer && (
                    <div>
                      <p className="text-xs text-gray-500">Reviewed By</p>
                      <p className="text-sm">{app.reviewer.name}</p>
                    </div>
                  )}
                </div>
                {app.cover_letter && (
                  <div>
                    <p className="text-xs text-gray-500">Cover Letter</p>
                    <p className="mt-1 text-sm whitespace-pre-line">{app.cover_letter}</p>
                  </div>
                )}

                {/* Reject action */}
                {app.status !== 'rejected' && app.status !== 'withdrawn' && (
                  <div className="mt-4 border-t pt-4">
                    <textarea
                      placeholder="Rejection reason..."
                      value={rejectReason}
                      onChange={(e) => setRejectReason(e.target.value)}
                      className="w-full rounded-md border border-gray-300 px-3 py-2 text-sm"
                      rows={2}
                    />
                    <button
                      onClick={() => {
                        if (rejectReason.trim()) {
                          action.mutate({ action: 'reject', payload: { reason: rejectReason } })
                        }
                      }}
                      disabled={action.isPending || !rejectReason.trim()}
                      className="mt-2 rounded-md bg-red-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-red-500 disabled:opacity-50"
                    >
                      Reject Application
                    </button>
                  </div>
                )}
              </div>
            )}

            {tab === 'interviews' && (
              <div className="space-y-4">
                {app.interviews.length === 0 ? (
                  <p className="text-sm text-gray-400">No interviews scheduled.</p>
                ) : (
                  app.interviews.map((interview) => (
                    <div key={interview.id} className="rounded-lg border border-gray-100 p-4">
                      <div className="flex items-center justify-between">
                        <div>
                          <p className="text-sm font-medium">Round {interview.round} - {interview.type_label}</p>
                          <p className="text-xs text-gray-500">
                            {new Date(interview.scheduled_at).toLocaleString()} - {interview.duration_minutes} min
                          </p>
                          <p className="text-xs text-gray-500">Interviewer: {interview.interviewer.name}</p>
                        </div>
                        <StatusBadge status={interview.status} label={interview.status_label} />
                      </div>
                      {interview.evaluation && (
                        <div className="mt-3 rounded bg-gray-50 p-3">
                          <div className="flex items-center gap-4">
                            <span className="text-sm font-bold">Score: {interview.evaluation.overall_score}/5</span>
                            <StatusBadge
                              status={interview.evaluation.recommendation}
                              label={interview.evaluation.recommendation_label}
                            />
                          </div>
                          {interview.evaluation.general_remarks && (
                            <p className="mt-1 text-xs text-gray-600">{interview.evaluation.general_remarks}</p>
                          )}
                        </div>
                      )}
                    </div>
                  ))
                )}
              </div>
            )}

            {tab === 'offer' && (
              <div>
                {app.offer ? (
                  <div className="space-y-3">
                    <div className="grid grid-cols-2 gap-4">
                      <div>
                        <p className="text-xs text-gray-500">Offer Number</p>
                        <p className="text-sm font-medium">{app.offer.offer_number}</p>
                      </div>
                      <div>
                        <p className="text-xs text-gray-500">Status</p>
                        <StatusBadge status={app.offer.status} label={app.offer.status_label} />
                      </div>
                      <div>
                        <p className="text-xs text-gray-500">Salary (centavos)</p>
                        <p className="text-sm font-medium">{(app.offer.offered_salary / 100).toLocaleString('en-PH', { style: 'currency', currency: 'PHP' })}</p>
                      </div>
                      <div>
                        <p className="text-xs text-gray-500">Start Date</p>
                        <p className="text-sm">{app.offer.start_date}</p>
                      </div>
                      <div>
                        <p className="text-xs text-gray-500">Position</p>
                        <p className="text-sm">{app.offer.offered_position?.title}</p>
                      </div>
                      <div>
                        <p className="text-xs text-gray-500">Department</p>
                        <p className="text-sm">{app.offer.offered_department?.name}</p>
                      </div>
                    </div>
                    {app.offer.expires_at && app.offer.status === 'sent' && (
                      <p className="text-xs text-amber-600">
                        Expires: {new Date(app.offer.expires_at).toLocaleDateString()}
                      </p>
                    )}
                  </div>
                ) : (
                  <p className="text-sm text-gray-400">No offer created yet.</p>
                )}
              </div>
            )}

            {tab === 'documents' && (
              <div>
                {app.pre_employment ? (
                  <div className="space-y-3">
                    <div className="flex items-center justify-between">
                      <StatusBadge status={app.pre_employment.status} label={app.pre_employment.status_label} />
                      <span className="text-sm font-medium">
                        {app.pre_employment.progress.completed}/{app.pre_employment.progress.total} ({app.pre_employment.progress.percentage}%)
                      </span>
                    </div>
                    <div className="w-full bg-gray-200 rounded-full h-2">
                      <div
                        className="bg-green-500 h-2 rounded-full transition-all"
                        style={{ width: `${app.pre_employment.progress.percentage}%` }}
                      />
                    </div>
                    <div className="divide-y">
                      {app.pre_employment.requirements.map((req) => (
                        <div key={req.id} className="flex items-center justify-between py-2">
                          <div>
                            <p className="text-sm">{req.label}</p>
                            {!req.is_required && <span className="text-xs text-gray-400">(Optional)</span>}
                          </div>
                          <StatusBadge status={req.status} label={req.status_label} />
                        </div>
                      ))}
                    </div>
                  </div>
                ) : (
                  <div className="space-y-2">
                    {app.documents.length > 0 ? (
                      app.documents.map((doc) => (
                        <div key={doc.id} className="flex items-center justify-between rounded border p-3">
                          <span className="text-sm">{doc.label}</span>
                          <span className="text-xs text-gray-500">{doc.mime_type}</span>
                        </div>
                      ))
                    ) : (
                      <p className="text-sm text-gray-400">No documents uploaded.</p>
                    )}
                  </div>
                )}
              </div>
            )}

            {tab === 'history' && (
              <p className="text-sm text-gray-400">Audit trail is available via the system audit log.</p>
            )}
          </div>
        </div>
      </div>

      {showHiringModal && (
        <HiringModal
          applicationUlid={ulid!}
          candidateName={app.candidate?.full_name || ''}
          defaultStartDate={app.offer?.start_date}
          onClose={() => setShowHiringModal(false)}
          onSuccess={() => {
            // Success handler if needed, useApplication will probably refetch via query invalidation in useHire
          }}
        />
      )}
    </div>
  )
}
