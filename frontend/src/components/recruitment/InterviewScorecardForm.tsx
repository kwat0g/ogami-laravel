import { useState } from 'react'

interface ScorecardFormProps {
  interviewId: number
  onSubmit: (data: Record<string, unknown>) => Promise<void>
  isPending: boolean
}

const DEFAULT_CRITERIA = [
  'Communication Skills',
  'Technical Knowledge',
  'Problem Solving',
  'Culture Fit',
  'Leadership Potential',
]

export default function InterviewScorecardForm({ _interviewId, onSubmit, isPending }: ScorecardFormProps) {
  const [scores, setScores] = useState(
    DEFAULT_CRITERIA.map((c) => ({ criterion: c, score: 0, comments: '' }))
  )
  const [recommendation, setRecommendation] = useState('')
  const [generalRemarks, setGeneralRemarks] = useState('')

  const updateScore = (idx: number, score: number) => {
    setScores((prev) => prev.map((s, i) => (i === idx ? { ...s, score } : s)))
  }

  const updateComments = (idx: number, comments: string) => {
    setScores((prev) => prev.map((s, i) => (i === idx ? { ...s, comments } : s)))
  }

  const avgScore = scores.filter((s) => s.score > 0).length > 0
    ? (scores.reduce((sum, s) => sum + s.score, 0) / scores.filter((s) => s.score > 0).length).toFixed(1)
    : '0.0'

  const canSubmit = scores.every((s) => s.score > 0) && recommendation !== ''

  const handleSubmit = () => {
    if (!canSubmit) return
    onSubmit({
      scorecard: scores,
      recommendation,
      general_remarks: generalRemarks || null,
    })
  }

  return (
    <div className="rounded-lg border border-gray-200 bg-white p-6 dark:border-gray-700 dark:bg-gray-800">
      <div className="mb-4 flex items-center justify-between">
        <h3 className="text-sm font-semibold text-gray-700 dark:text-gray-300">Submit Evaluation</h3>
        <span className="text-lg font-bold text-gray-900 dark:text-white">Avg: {avgScore}/5</span>
      </div>

      <div className="space-y-4">
        {scores.map((item, idx) => (
          <div key={idx} className="rounded-lg border border-gray-100 p-4 dark:border-gray-700">
            <div className="mb-2 flex items-center justify-between">
              <span className="text-sm font-medium text-gray-700 dark:text-gray-300">{item.criterion}</span>
              <div className="flex gap-1">
                {[1, 2, 3, 4, 5].map((star) => (
                  <button
                    key={star}
                    type="button"
                    onClick={() => updateScore(idx, star)}
                    className={`text-xl transition ${star <= item.score ? 'text-amber-400' : 'text-gray-300 hover:text-amber-200'}`}
                  >
                    ★
                  </button>
                ))}
              </div>
            </div>
            <input
              type="text"
              placeholder="Comments (optional)..."
              value={item.comments}
              onChange={(e) => updateComments(idx, e.target.value)}
              className="w-full rounded border border-gray-200 px-3 py-1.5 text-sm dark:border-gray-600 dark:bg-gray-700"
            />
          </div>
        ))}
      </div>

      {/* Recommendation */}
      <div className="mt-6">
        <label className="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Recommendation</label>
        <div className="flex gap-4">
          {[
            { value: 'endorse', label: 'Endorse', color: 'border-green-500 bg-green-50 text-green-700' },
            { value: 'hold', label: 'Hold', color: 'border-amber-500 bg-amber-50 text-amber-700' },
            { value: 'reject', label: 'Reject', color: 'border-red-500 bg-red-50 text-red-700' },
          ].map((opt) => (
            <button
              key={opt.value}
              type="button"
              onClick={() => setRecommendation(opt.value)}
              className={`rounded-lg border-2 px-6 py-2 text-sm font-semibold transition ${
                recommendation === opt.value ? opt.color : 'border-gray-200 bg-white text-gray-500 dark:border-gray-600 dark:bg-gray-800'
              }`}
            >
              {opt.label}
            </button>
          ))}
        </div>
      </div>

      {/* General Remarks */}
      <div className="mt-4">
        <label className="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">General Remarks</label>
        <textarea
          value={generalRemarks}
          onChange={(e) => setGeneralRemarks(e.target.value)}
          rows={3}
          className="w-full rounded-md border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800"
          placeholder="Overall assessment..."
        />
      </div>

      <div className="mt-6 flex justify-end">
        <button
          type="button"
          onClick={handleSubmit}
          disabled={!canSubmit || isPending}
          className="rounded-md bg-blue-600 px-6 py-2 text-sm font-semibold text-white hover:bg-blue-500 disabled:opacity-50"
        >
          {isPending ? 'Submitting...' : 'Submit Evaluation'}
        </button>
      </div>
    </div>
  )
}
