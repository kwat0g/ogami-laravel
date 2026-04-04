import { Link, useParams } from 'react-router-dom'
import {
  useApplication,
  useApplicationAction,
} from '@/hooks/useRecruitment'
import StatusBadge from '@/components/recruitment/StatusBadge'
import ApplicationTimeline from '@/components/recruitment/ApplicationTimeline'
import HiringModal from '@/components/recruitment/HiringModal'
import ScheduleInterviewModal from '@/components/recruitment/ScheduleInterviewModal'
import PermissionGuard from '@/components/ui/PermissionGuard'
import { useAuthStore } from '@/stores/authStore'
import { useState } from 'react'
import { toast } from 'sonner'

export default function ApplicationDetailPage() {
  const { ulid } = useParams<{ ulid: string }>()
  const { data: app, isLoading, refetch } = useApplication(ulid ?? '')
  const action = useApplicationAction(ulid ?? '')
  const [tab, setTab] = useState<'profile' | 'interviews'>('profile')
  const [rejectReason, setRejectReason] = useState('')
  const [withdrawReason, setWithdrawReason] = useState('')
  const [showHiringModal, setShowHiringModal] = useState(false)
  const [showInterviewModal, setShowInterviewModal] = useState(false)
  const hasRole = useAuthStore((state) => state.hasRole)
  const primaryDepartmentCode = useAuthStore((state) => state.primaryDepartmentCode)
  const isHrManager = hasRole('manager') && primaryDepartmentCode() === 'HR'

  if (isLoading || !app) return <div className="p-6">Loading...</div>

  const hasActiveInterview = app.interviews.some((interview) => interview.status === 'scheduled' || interview.status === 'in_progress')
  const hasCompletedInterview = app.interviews.some((interview) => interview.status === 'completed')
  const canScheduleInterview = app.status === 'shortlisted' && isHrManager && !hasActiveInterview && !hasCompletedInterview
  const canHire = hasCompletedInterview && app.status !== 'hired' && isHrManager
  const isTerminal = app.status === 'rejected' || app.status === 'withdrawn' || app.status === 'hired'

  const handleAction = async (act: string, payload?: Record<string, unknown>) => {
    try {
      await action.mutateAsync({ action: act, payload })
      toast.success(`Application ${act} successfully`)
    } catch {
    }
  }

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex items-start justify-between">
        <div>
          <p className="text-sm text-neutral-500">{app.application_number}</p>
          <h1 className="text-lg font-semibold text-neutral-900 dark:text-white">
            {app.candidate?.full_name}
          </h1>
          <p className="text-sm text-neutral-500">
            {app.posting?.position || app.posting?.requisition?.position} - {app.posting?.department || app.posting?.requisition?.department}
          </p>
        </div>
        <div className="flex items-center gap-3">
          <StatusBadge status={app.status} label={app.status_label} />

          {/* GAP-08: Review button */}
          <PermissionGuard permission="recruitment.applications.review|hr.full_access">
            {app.status === 'new' && (
              <button
                onClick={() => handleAction('review')}
                disabled={action.isPending}
                className="rounded-md bg-amber-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-amber-500 disabled:opacity-50"
              >
                Start Review
              </button>
            )}
          </PermissionGuard>

          {/* Shortlist button */}
          <PermissionGuard permission="recruitment.applications.shortlist|hr.full_access">
            {app.status === 'under_review' && (
              <button
                onClick={() => handleAction('shortlist')}
                disabled={action.isPending}
                className="rounded-md bg-teal-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-teal-500 disabled:opacity-50"
              >
                Shortlist
              </button>
            )}
          </PermissionGuard>

          {/* GAP-02: Schedule Interview button */}
          <PermissionGuard permission="recruitment.interviews.schedule|hr.full_access">
            {canScheduleInterview && (
              <button
                onClick={() => setShowInterviewModal(true)}
                className="rounded-md bg-blue-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-blue-500"
              >
                Schedule Interview
              </button>
            )}
          </PermissionGuard>

          {/* Hire button */}
          <PermissionGuard permission="recruitment.hiring.execute|hr.full_access">
            {canHire && (
              <button
                onClick={() => setShowHiringModal(true)}
                className="rounded-md bg-green-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-green-500"
              >
                Hire Applicant
              </button>
            )}
          </PermissionGuard>
        </div>
      </div>

      {app.hiring?.status === 'hired' && (
        <div className="rounded-lg border border-green-200 bg-green-50 p-4 dark:border-green-800 dark:bg-green-900/20">
          <p className="text-sm font-semibold text-green-800 dark:text-green-400">Candidate Successfully Hired</p>
          <p className="text-xs text-green-700 dark:text-green-500">
            Employee record has been created and hiring is complete.
          </p>
          {app.hiring.employee_ulid && (
            <Link
              to={`/hr/employees/${app.hiring.employee_ulid}`}
              className="mt-2 inline-flex rounded-md bg-green-700 px-3 py-1.5 text-xs font-semibold text-white hover:bg-green-600"
            >
              Open Employee Record
            </Link>
          )}
        </div>
      )}

      <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
        {/* Left: Timeline */}
        <div className="rounded-lg border border-neutral-200 bg-white p-6 dark:border-neutral-700 dark:bg-neutral-800">
          <h3 className="mb-4 text-sm font-semibold text-neutral-700 dark:text-neutral-300">Application Journey</h3>
          <ApplicationTimeline application={app} />
        </div>

        {/* Right: Tabs */}
        <div className="lg:col-span-2 space-y-4">
          {/* Tab Navigation */}
          <div className="flex gap-1 border-b border-neutral-200 dark:border-neutral-700">
            {(['profile', 'interviews'] as const).map((t) => (
              <button
                key={t}
                onClick={() => setTab(t)}
                className={`px-4 py-2 text-sm font-medium border-b-2 transition ${tab === t
                    ? 'border-blue-600 text-blue-600'
                    : 'border-transparent text-neutral-500 hover:text-neutral-700'
                  }`}
              >
                {t.charAt(0).toUpperCase() + t.slice(1)}
              </button>
            ))}
          </div>

          {/* Tab Content */}
          <div className="rounded-lg border border-neutral-200 bg-white p-6 dark:border-neutral-700 dark:bg-neutral-800">
            {tab === 'profile' && (
              <div className="space-y-3">
                <div className="grid grid-cols-2 gap-4">
                  <div>
                    <p className="text-xs text-neutral-500">Email</p>
                    <p className="text-sm">{app.candidate?.email}</p>
                  </div>
                  <div>
                    <p className="text-xs text-neutral-500">Source</p>
                    <p className="text-sm">{app.source_label}</p>
                  </div>
                  <div>
                    <p className="text-xs text-neutral-500">Applied On</p>
                    <p className="text-sm">{app.application_date}</p>
                  </div>
                  {app.reviewer && (
                    <div>
                      <p className="text-xs text-neutral-500">Reviewed By</p>
                      <p className="text-sm">{app.reviewer.name}</p>
                    </div>
                  )}
                </div>
                {app.cover_letter && (
                  <div>
                    <p className="text-xs text-neutral-500">Cover Letter</p>
                    <p className="mt-1 text-sm whitespace-pre-line">{app.cover_letter}</p>
                  </div>
                )}

                {app.resume_download_url && (
                  <div>
                    <p className="text-xs text-neutral-500">Resume</p>
                    <a
                      href={app.resume_download_url}
                      target="_blank"
                      rel="noreferrer"
                      className="mt-1 inline-flex rounded-md bg-neutral-900 px-3 py-1.5 text-xs font-semibold text-white hover:bg-neutral-800"
                    >
                      Download Resume (PDF)
                    </a>
                  </div>
                )}

                {/* Reject action */}
                <PermissionGuard permission="recruitment.applications.reject|hr.full_access">
                  {!isTerminal && (
                    <div className="mt-4 border-t pt-4">
                      <textarea
                        placeholder="Rejection reason..."
                        value={rejectReason}
                        onChange={(e) => setRejectReason(e.target.value)}
                        className="w-full rounded-md border border-neutral-300 px-3 py-2 text-sm dark:border-neutral-600 dark:bg-neutral-800"
                        rows={2}
                      />
                      <button
                        onClick={() => {
                          if (rejectReason.trim()) {
                            handleAction('reject', { reason: rejectReason })
                          }
                        }}
                        disabled={action.isPending || !rejectReason.trim()}
                        className="mt-2 rounded-md bg-red-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-red-500 disabled:opacity-50"
                      >
                        Reject Application
                      </button>
                    </div>
                  )}
                </PermissionGuard>

                {/* GAP-09: Withdraw action */}
                <PermissionGuard permission="recruitment.applications.reject|hr.full_access">
                  {!isTerminal && (
                    <div className="mt-4 border-t pt-4">
                      <textarea
                        placeholder="Withdrawal reason..."
                        value={withdrawReason}
                        onChange={(e) => setWithdrawReason(e.target.value)}
                        className="w-full rounded-md border border-neutral-300 px-3 py-2 text-sm dark:border-neutral-600 dark:bg-neutral-800"
                        rows={2}
                      />
                      <button
                        onClick={() => {
                          if (withdrawReason.trim()) {
                            handleAction('withdraw', { reason: withdrawReason })
                          }
                        }}
                        disabled={action.isPending || !withdrawReason.trim()}
                        className="mt-2 rounded-md bg-neutral-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-neutral-500 disabled:opacity-50"
                      >
                        Withdraw Application
                      </button>
                    </div>
                  )}
                </PermissionGuard>
              </div>
            )}

            {tab === 'interviews' && (
              <div className="space-y-4">
                {/* GAP-02: Schedule Interview button in tab */}
                <PermissionGuard permission="recruitment.interviews.schedule|hr.full_access">
                  {canScheduleInterview && (
                    <div className="flex justify-end">
                      <button
                        onClick={() => setShowInterviewModal(true)}
                        className="rounded-md bg-blue-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-blue-500"
                      >
                        + Schedule Interview
                      </button>
                    </div>
                  )}
                </PermissionGuard>
                {app.interviews.length === 0 ? (
                  <p className="text-sm text-neutral-400">No interviews scheduled.</p>
                ) : (
                  app.interviews.map((interview: any) => (
                    <div key={interview.id} className="rounded-lg border border-neutral-100 p-4">
                      <div className="flex items-center justify-between">
                        <div>
                          <p className="text-sm font-medium">Round {interview.round} - {interview.type_label}</p>
                          <p className="text-xs text-neutral-500">
                            {new Date(interview.scheduled_at).toLocaleString()} - {interview.duration_minutes} min
                          </p>
                          <p className="text-xs text-neutral-500">Interviewer: {interview.interviewer?.name ?? 'Unassigned'}</p>
                        </div>
                        <StatusBadge status={interview.status} label={interview.status_label} />
                      </div>
                      <div className="mt-3 flex justify-end">
                        <Link
                          to={`/hr/recruitment/interviews/${interview.id}`}
                          className="rounded-md border border-neutral-300 px-3 py-1.5 text-xs font-semibold text-neutral-700 hover:bg-neutral-50"
                        >
                          Open Interview
                        </Link>
                      </div>
                      {interview.evaluation && (
                        <div className="mt-3 rounded bg-neutral-50 p-3 dark:bg-neutral-700">
                          <div className="flex items-center gap-4">
                            <span className="text-sm font-bold">Score: {interview.evaluation.overall_score}/5</span>
                            <StatusBadge
                              status={interview.evaluation.recommendation}
                              label={interview.evaluation.recommendation_label}
                            />
                          </div>
                          {interview.evaluation.general_remarks && (
                            <p className="mt-1 text-xs text-neutral-600 dark:text-neutral-400">{interview.evaluation.general_remarks}</p>
                          )}
                        </div>
                      )}
                    </div>
                  ))
                )}
              </div>
            )}

          </div>
        </div>
      </div>

      {/* Modals */}
      {showHiringModal && (
        <HiringModal
          applicationUlid={ulid!}
          candidateName={app.candidate?.full_name || ''}
          postingTitle={app.posting?.title || ''}
          salaryGradeId={app.posting?.salary_grade_id ?? app.posting?.salary_grade?.id ?? null}
          salaryGradeLabel={app.posting?.salary_grade?.code || app.posting?.salary_grade?.name || null}
          defaultStartDate={app.offer?.start_date}
          defaultFirstName={app.candidate?.first_name || ''}
          defaultLastName={app.candidate?.last_name || ''}
          defaultEmail={app.candidate?.email || ''}
          defaultAddress={app.candidate?.address || ''}
          defaultPhone={app.candidate?.phone || ''}
          onClose={() => setShowHiringModal(false)}
          onSuccess={() => refetch()}
        />
      )}

      {showInterviewModal && (
        <ScheduleInterviewModal
          applicationId={app.id}
          candidateName={app.candidate?.full_name || ''}
          onClose={() => setShowInterviewModal(false)}
          onSuccess={() => refetch()}
        />
      )}
    </div>
  )
}
