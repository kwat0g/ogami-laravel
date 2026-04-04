import { useParams } from 'react-router-dom'
import {
  useApplication,
  useApplicationAction,
  useInitPreEmployment,
  usePreEmploymentAction,
  usePreEmploymentUpload,
  useCompletePreEmployment,
} from '@/hooks/useRecruitment'
import StatusBadge from '@/components/recruitment/StatusBadge'
import ApplicationTimeline from '@/components/recruitment/ApplicationTimeline'
import HiringModal from '@/components/recruitment/HiringModal'
import ScheduleInterviewModal from '@/components/recruitment/ScheduleInterviewModal'
import PrepareOfferModal from '@/components/recruitment/PrepareOfferModal'
import PermissionGuard from '@/components/ui/PermissionGuard'
import { PERMISSIONS } from '@/lib/permissions'
import { useState, useRef } from 'react'
import { toast } from 'sonner'

export default function ApplicationDetailPage() {
  const { ulid } = useParams<{ ulid: string }>()
  const { data: app, isLoading, refetch } = useApplication(ulid ?? '')
  const action = useApplicationAction(ulid ?? '')
  const [tab, setTab] = useState<'profile' | 'interviews' | 'offer' | 'documents' | 'history'>('profile')
  const [rejectReason, setRejectReason] = useState('')
  const [withdrawReason, setWithdrawReason] = useState('')
  const [showHiringModal, setShowHiringModal] = useState(false)
  const [showInterviewModal, setShowInterviewModal] = useState(false)
  const [showOfferModal, setShowOfferModal] = useState(false)

  // Pre-employment hooks
  const initPreEmployment = useInitPreEmployment(ulid ?? '')
  const preEmploymentAction = usePreEmploymentAction()
  const preEmploymentUpload = usePreEmploymentUpload()
  const completePreEmployment = useCompletePreEmployment()

  if (isLoading || !app) return <div className="p-6">Loading...</div>

  const canScheduleInterview = app.status === 'shortlisted' || app.status === 'under_review'
  const canPrepareOffer = app.status === 'shortlisted' && !app.offer
  const canInitPreEmployment = app.offer?.status === 'accepted' && !app.pre_employment
  const canHire = app.offer?.status === 'accepted' && app.status !== 'hired'
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
            {app.posting?.position} - {app.posting?.department}
          </p>
        </div>
        <div className="flex items-center gap-3">
          <StatusBadge status={app.status} label={app.status_label} />

          {/* GAP-08: Review button */}
          <PermissionGuard permission={PERMISSIONS.hr.full_access}>
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
          <PermissionGuard permission={PERMISSIONS.hr.full_access}>
            {(app.status === 'new' || app.status === 'under_review') && (
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
          <PermissionGuard permission={PERMISSIONS.hr.full_access}>
            {canScheduleInterview && (
              <button
                onClick={() => setShowInterviewModal(true)}
                className="rounded-md bg-blue-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-blue-500"
              >
                Schedule Interview
              </button>
            )}
          </PermissionGuard>

          {/* GAP-03: Prepare Offer button */}
          <PermissionGuard permission={PERMISSIONS.hr.full_access}>
            {canPrepareOffer && (
              <button
                onClick={() => setShowOfferModal(true)}
                className="rounded-md bg-purple-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-purple-500"
              >
                Prepare Offer
              </button>
            )}
          </PermissionGuard>

          {/* Hire button */}
          <PermissionGuard permission={PERMISSIONS.hr.full_access}>
            {canHire && (
              <button
                onClick={() => setShowHiringModal(true)}
                className="rounded-md bg-green-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-green-500"
              >
                Hire Candidate
              </button>
            )}
          </PermissionGuard>
        </div>
      </div>

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
            {(['profile', 'interviews', 'offer', 'documents', 'history'] as const).map((t) => (
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

                {/* Reject action */}
                <PermissionGuard permission={PERMISSIONS.hr.full_access}>
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
                <PermissionGuard permission={PERMISSIONS.hr.full_access}>
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
                <PermissionGuard permission={PERMISSIONS.hr.full_access}>
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
                          <p className="text-xs text-neutral-500">Interviewer: {interview.interviewer.name}</p>
                        </div>
                        <StatusBadge status={interview.status} label={interview.status_label} />
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

            {tab === 'offer' && (
              <div>
                {app.offer ? (
                  <div className="space-y-3">
                    <div className="grid grid-cols-2 gap-4">
                      <div>
                        <p className="text-xs text-neutral-500">Offer Number</p>
                        <p className="text-sm font-medium">{app.offer.offer_number}</p>
                      </div>
                      <div>
                        <p className="text-xs text-neutral-500">Status</p>
                        <StatusBadge status={app.offer.status} label={app.offer.status_label} />
                      </div>
                      <div>
                        <p className="text-xs text-neutral-500">Salary</p>
                        <p className="text-sm font-medium">{(app.offer.offered_salary / 100).toLocaleString('en-PH', { style: 'currency', currency: 'PHP' })}</p>
                      </div>
                      <div>
                        <p className="text-xs text-neutral-500">Start Date</p>
                        <p className="text-sm">{app.offer.start_date}</p>
                      </div>
                      <div>
                        <p className="text-xs text-neutral-500">Position</p>
                        <p className="text-sm">{app.offer.offered_position?.title}</p>
                      </div>
                      <div>
                        <p className="text-xs text-neutral-500">Department</p>
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
                  <div className="space-y-3">
                    <p className="text-sm text-neutral-400">No offer created yet.</p>
                    {/* GAP-03: Create Offer button */}
                    <PermissionGuard permission={PERMISSIONS.hr.full_access}>
                      {canPrepareOffer && (
                        <button
                          onClick={() => setShowOfferModal(true)}
                          className="rounded-md bg-purple-600 px-4 py-2 text-sm font-semibold text-white hover:bg-purple-500"
                        >
                          Prepare Offer
                        </button>
                      )}
                    </PermissionGuard>
                  </div>
                )}
              </div>
            )}

            {tab === 'documents' && (
              <PreEmploymentTab
                app={app}
                ulid={ulid!}
                canInit={canInitPreEmployment}
                initPreEmployment={initPreEmployment}
                preEmploymentAction={preEmploymentAction}
                preEmploymentUpload={preEmploymentUpload}
                completePreEmployment={completePreEmployment}
                onRefetch={refetch}
              />
            )}

            {tab === 'history' && (
              <p className="text-sm text-neutral-400">Audit trail is available via the system audit log.</p>
            )}
          </div>
        </div>
      </div>

      {/* Modals */}
      {showHiringModal && (
        <HiringModal
          applicationUlid={ulid!}
          candidateName={app.candidate?.full_name || ''}
          defaultStartDate={app.offer?.start_date}
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

      {showOfferModal && (
        <PrepareOfferModal
          applicationId={app.id}
          candidateName={app.candidate?.full_name || ''}
          defaultDepartmentId={app.posting?.requisition?.department?.id}
          defaultPositionId={app.posting?.requisition?.position?.id}
          onClose={() => setShowOfferModal(false)}
          onSuccess={() => refetch()}
        />
      )}
    </div>
  )
}

// ── Pre-Employment Tab (GAP-04, GAP-05) ──────────────────────────────────────

function PreEmploymentTab({
  app,
  ulid,
  canInit,
  initPreEmployment,
  preEmploymentAction,
  preEmploymentUpload,
  completePreEmployment,
  onRefetch,
}: {
  app: any
  ulid: string
  canInit: boolean
  initPreEmployment: any
  preEmploymentAction: any
  preEmploymentUpload: any
  completePreEmployment: any
  onRefetch: () => void
}) {
  const [rejectRemarks, setRejectRemarks] = useState<Record<number, string>>({})
  const fileInputRefs = useRef<Record<number, HTMLInputElement | null>>({})

  const handleInit = async () => {
    try {
      await initPreEmployment.mutateAsync()
      toast.success('Pre-employment checklist initialized')
      onRefetch()
    } catch {
    }
  }

  const handleUpload = async (requirementId: number, file: File) => {
    try {
      await preEmploymentUpload.mutateAsync({ requirementId, file })
      toast.success('Document uploaded')
      onRefetch()
    } catch {
    }
  }

  const handleVerify = async (requirementId: number) => {
    try {
      await preEmploymentAction.mutateAsync({ requirementId, action: 'verify' })
      toast.success('Document verified')
      onRefetch()
    } catch {
    }
  }

  const handleReject = async (requirementId: number) => {
    const remarks = rejectRemarks[requirementId]
    if (!remarks?.trim()) {
      return
    }
    try {
      await preEmploymentAction.mutateAsync({
        requirementId,
        action: 'reject',
        payload: { remarks },
      })
      toast.success('Document rejected')
      onRefetch()
    } catch {
    }
  }

  const handleWaive = async (requirementId: number) => {
    try {
      await preEmploymentAction.mutateAsync({ requirementId, action: 'waive' })
      toast.success('Requirement waived')
      onRefetch()
    } catch {
    }
  }

  const handleComplete = async (checklistId: number) => {
    try {
      await completePreEmployment.mutateAsync(checklistId)
      toast.success('Pre-employment checklist completed')
      onRefetch()
    } catch {
    }
  }

  if (app.pre_employment) {
    return (
      <div className="space-y-3">
        <div className="flex items-center justify-between">
          <StatusBadge status={app.pre_employment.status} label={app.pre_employment.status_label} />
          <span className="text-sm font-medium">
            {app.pre_employment.progress.completed}/{app.pre_employment.progress.total} ({app.pre_employment.progress.percentage}%)
          </span>
        </div>
        <div className="w-full bg-neutral-200 rounded-full h-2">
          <div
            className="bg-green-500 h-2 rounded-full transition-all"
            style={{ width: `${app.pre_employment.progress.percentage}%` }}
          />
        </div>

        {/* Mark Complete button */}
        <PermissionGuard permission={PERMISSIONS.hr.full_access}>
          {app.pre_employment.status !== 'completed' && app.pre_employment.status !== 'waived' && (
            <div className="flex justify-end">
              <button
                onClick={() => handleComplete(app.pre_employment.id)}
                disabled={completePreEmployment.isPending}
                className="rounded-md bg-green-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-green-500 disabled:opacity-50"
              >
                Mark Checklist Complete
              </button>
            </div>
          )}
        </PermissionGuard>

        <div className="divide-y">
          {app.pre_employment.requirements.map((req: any) => (
            <div key={req.id} className="py-3 space-y-2">
              <div className="flex items-center justify-between">
                <div>
                  <p className="text-sm">{req.label}</p>
                  {!req.is_required && <span className="text-xs text-neutral-400">(Optional)</span>}
                </div>
                <StatusBadge status={req.status} label={req.status_label} />
              </div>

              {/* GAP-05: Action buttons per requirement */}
              <PermissionGuard permission={PERMISSIONS.hr.full_access}>
                <div className="flex flex-wrap items-center gap-2">
                  {/* Upload button - shown when pending or rejected */}
                  {(req.status === 'pending' || req.status === 'rejected') && (
                    <>
                      <input
                        type="file"
                        ref={(el) => { fileInputRefs.current[req.id] = el }}
                        className="hidden"
                        accept=".pdf,.doc,.docx,.jpg,.jpeg,.png"
                        onChange={(e) => {
                          const file = e.target.files?.[0]
                          if (file) handleUpload(req.id, file)
                        }}
                      />
                      <button
                        onClick={() => fileInputRefs.current[req.id]?.click()}
                        disabled={preEmploymentUpload.isPending}
                        className="rounded bg-blue-100 px-2 py-1 text-xs font-medium text-blue-700 hover:bg-blue-200 disabled:opacity-50"
                      >
                        Upload Document
                      </button>
                    </>
                  )}

                  {/* Verify button - shown when submitted */}
                  {req.status === 'submitted' && (
                    <button
                      onClick={() => handleVerify(req.id)}
                      disabled={preEmploymentAction.isPending}
                      className="rounded bg-green-100 px-2 py-1 text-xs font-medium text-green-700 hover:bg-green-200 disabled:opacity-50"
                    >
                      Verify
                    </button>
                  )}

                  {/* Reject button with remarks - shown when submitted */}
                  {req.status === 'submitted' && (
                    <div className="flex items-center gap-1">
                      <input
                        type="text"
                        placeholder="Remarks..."
                        value={rejectRemarks[req.id] || ''}
                        onChange={(e) => setRejectRemarks({ ...rejectRemarks, [req.id]: e.target.value })}
                        className="rounded border border-neutral-300 px-2 py-1 text-xs dark:border-neutral-600 dark:bg-neutral-700"
                      />
                      <button
                        onClick={() => handleReject(req.id)}
                        disabled={preEmploymentAction.isPending}
                        className="rounded bg-red-100 px-2 py-1 text-xs font-medium text-red-700 hover:bg-red-200 disabled:opacity-50"
                      >
                        Reject
                      </button>
                    </div>
                  )}

                  {/* Waive button - shown when pending */}
                  {req.status === 'pending' && (
                    <button
                      onClick={() => handleWaive(req.id)}
                      disabled={preEmploymentAction.isPending}
                      className="rounded bg-neutral-100 px-2 py-1 text-xs font-medium text-neutral-700 hover:bg-neutral-200 disabled:opacity-50"
                    >
                      Waive
                    </button>
                  )}
                </div>
              </PermissionGuard>
            </div>
          ))}
        </div>
      </div>
    )
  }

  return (
    <div className="space-y-3">
      {app.documents?.length > 0 ? (
        app.documents.map((doc: any) => (
          <div key={doc.id} className="flex items-center justify-between rounded border p-3">
            <span className="text-sm">{doc.label}</span>
            <span className="text-xs text-neutral-500">{doc.mime_type}</span>
          </div>
        ))
      ) : (
        <p className="text-sm text-neutral-400">No documents uploaded.</p>
      )}

      {/* GAP-04: Init Pre-Employment Checklist button */}
      {canInit && (
        <button
          onClick={handleInit}
          disabled={initPreEmployment.isPending}
          className="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500 disabled:opacity-50"
        >
          {initPreEmployment.isPending ? 'Initializing...' : 'Initialize Pre-Employment Checklist'}
        </button>
      )}
    </div>
  )
}
