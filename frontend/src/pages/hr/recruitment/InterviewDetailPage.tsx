import { useParams } from 'react-router-dom'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import api from '@/lib/api'
import StatusBadge from '@/components/recruitment/StatusBadge'
import InterviewScorecardForm from '@/components/recruitment/InterviewScorecardForm'
import { useSubmitEvaluation, useInterviewAction } from '@/hooks/useRecruitment'
import { useState } from 'react'
import { toast } from 'sonner'

export default function InterviewDetailPage() {
  const { id } = useParams<{ id: string }>()
  const qc = useQueryClient()

  const { data: interview, isLoading } = useQuery({
    queryKey: ['recruitment', 'interviews', id],
    queryFn: async () => {
      const { data } = await api.get(`/recruitment/interviews/${id}`)
      return data
    },
    enabled: !!id,
  })

  const submitEval = useSubmitEvaluation(interview?.id)
  const interviewAction = useInterviewAction(interview?.id ?? '')
  const [cancelReason, setCancelReason] = useState('')

  if (isLoading || !interview) return <div className="p-6">Loading...</div>

  const handleAction = async (action: string, payload?: Record<string, unknown>) => {
    try {
      await interviewAction.mutateAsync({ action, payload })
      toast.success(`Interview ${action} successfully`)
      qc.invalidateQueries({ queryKey: ['recruitment', 'interviews', id] })
    } catch {
      toast.error(`Failed to ${action} interview`)
    }
  }

  const isScheduled = interview.status === 'scheduled'
  const isCompleted = interview.status === 'completed'
  const isTerminal = interview.status === 'cancelled' || interview.status === 'no_show'

  return (
    <div className="mx-auto max-w-4xl space-y-6 p-6">
      {/* Header */}
      <div className="flex items-start justify-between">
        <div>
          <h1 className="text-2xl font-bold text-gray-900 dark:text-white">
            Interview - Round {interview.round}
          </h1>
          <p className="text-sm text-gray-500">
            {interview.application?.candidate?.full_name} - {interview.type?.replace('_', ' ')}
          </p>
        </div>
        <StatusBadge status={interview.status} label={interview.status?.replace('_', ' ')} />
      </div>

      {/* GAP-06: Interview lifecycle action buttons */}
      {!isTerminal && (
        <div className="flex flex-wrap items-center gap-3">
          {isScheduled && (
            <>
              <button
                onClick={() => handleAction('complete')}
                disabled={interviewAction.isPending}
                className="rounded-md bg-green-600 px-4 py-2 text-sm font-semibold text-white hover:bg-green-500 disabled:opacity-50"
              >
                Mark Completed
              </button>
              <button
                onClick={() => handleAction('no-show')}
                disabled={interviewAction.isPending}
                className="rounded-md bg-amber-600 px-4 py-2 text-sm font-semibold text-white hover:bg-amber-500 disabled:opacity-50"
              >
                Mark No-Show
              </button>
              <div className="flex items-center gap-2">
                <input
                  type="text"
                  placeholder="Cancel reason..."
                  value={cancelReason}
                  onChange={(e) => setCancelReason(e.target.value)}
                  className="rounded-md border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800"
                />
                <button
                  onClick={() => handleAction('cancel', { reason: cancelReason || null })}
                  disabled={interviewAction.isPending}
                  className="rounded-md bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-500 disabled:opacity-50"
                >
                  Cancel Interview
                </button>
              </div>
            </>
          )}
        </div>
      )}

      {/* Details */}
      <div className="grid grid-cols-2 gap-6 rounded-lg border border-gray-200 bg-white p-6 dark:border-gray-700 dark:bg-gray-800">
        <div>
          <p className="text-xs text-gray-500">Scheduled At</p>
          <p className="text-sm font-medium">{new Date(interview.scheduled_at).toLocaleString()}</p>
        </div>
        <div>
          <p className="text-xs text-gray-500">Duration</p>
          <p className="text-sm font-medium">{interview.duration_minutes} minutes</p>
        </div>
        <div>
          <p className="text-xs text-gray-500">Interviewer</p>
          <p className="text-sm font-medium">{interview.interviewer?.name}</p>
        </div>
        <div>
          <p className="text-xs text-gray-500">Location</p>
          <p className="text-sm font-medium">{interview.location ?? 'Not specified'}</p>
        </div>
        {interview.notes && (
          <div className="col-span-2">
            <p className="text-xs text-gray-500">Notes</p>
            <p className="text-sm">{interview.notes}</p>
          </div>
        )}
      </div>

      {/* Evaluation */}
      {interview.evaluation ? (
        <div className="rounded-lg border border-gray-200 bg-white p-6 dark:border-gray-700 dark:bg-gray-800">
          <h3 className="mb-4 text-sm font-semibold text-gray-700 dark:text-gray-300">Evaluation</h3>
          <div className="mb-4 flex items-center gap-4">
            <span className="text-2xl font-bold text-gray-900 dark:text-white">
              {interview.evaluation.overall_score}/5
            </span>
            <StatusBadge
              status={interview.evaluation.recommendation}
              label={interview.evaluation.recommendation?.charAt(0).toUpperCase() + interview.evaluation.recommendation?.slice(1)}
            />
          </div>
          <div className="space-y-2">
            {/* eslint-disable-next-line @typescript-eslint/no-explicit-any */}
            {interview.evaluation.scorecard?.map((item: any, idx: number) => (
              <div key={idx} className="flex items-center justify-between rounded bg-gray-50 px-4 py-2 dark:bg-gray-700">
                <span className="text-sm text-gray-700 dark:text-gray-300">{item.criterion}</span>
                <div className="flex items-center gap-3">
                  <div className="flex gap-0.5">
                    {[1, 2, 3, 4, 5].map((star) => (
                      <span key={star} className={`text-sm ${star <= item.score ? 'text-amber-400' : 'text-gray-300'}`}>
                        ★
                      </span>
                    ))}
                  </div>
                  {item.comments && <span className="text-xs text-gray-500">{item.comments}</span>}
                </div>
              </div>
            ))}
          </div>
          {interview.evaluation.general_remarks && (
            <div className="mt-4">
              <p className="text-xs text-gray-500">General Remarks</p>
              <p className="text-sm text-gray-700 dark:text-gray-300">{interview.evaluation.general_remarks}</p>
            </div>
          )}
        </div>
      ) : (isCompleted || isScheduled) ? (
        <InterviewScorecardForm
          interviewId={interview?.id}
          onSubmit={async (data) => {
            await submitEval.mutateAsync(data)
            qc.invalidateQueries({ queryKey: ['recruitment', 'interviews', id] })
          }}
          isPending={submitEval.isPending}
        />
      ) : null}
    </div>
  )
}
