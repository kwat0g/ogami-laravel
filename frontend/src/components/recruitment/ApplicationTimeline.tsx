import type { Application } from '@/types/recruitment'

interface TimelineStep {
  label: string
  status: 'done' | 'current' | 'pending'
  date?: string
  detail?: string
}

function buildTimeline(app: Application): TimelineStep[] {
  const steps: TimelineStep[] = []

  // Applied
  steps.push({
    label: 'Applied',
    status: 'done',
    date: app.application_date,
  })

  // Under Review
  const reviewed = app.reviewed_at != null
  steps.push({
    label: 'Under Review',
    status: reviewed ? 'done' : app.status === 'under_review' ? 'current' : 'pending',
    date: app.reviewed_at ?? undefined,
    detail: app.reviewer?.name ? `by ${app.reviewer.name}` : undefined,
  })

  // Shortlisted
  const shortlisted = app.status === 'shortlisted' || (app.interviews && app.interviews.length > 0)
  steps.push({
    label: 'Shortlisted',
    status: shortlisted ? 'done' : 'pending',
  })

  // Interviews
  if (app.interviews && app.interviews.length > 0) {
    app.interviews.forEach((interview, _idx) => {
      steps.push({
        label: `Interview R${interview.round}`,
        status: interview.status === 'completed' ? 'done' : interview.status === 'scheduled' ? 'current' : 'pending',
        date: interview.scheduled_at,
        detail: `${interview.type_label} - ${interview.interviewer.name}`,
      })
    })
  } else {
    steps.push({ label: 'Interview', status: 'pending' })
  }

  // Offer
  const hasOffer = app.offer != null
  steps.push({
    label: 'Offer',
    status: hasOffer && app.offer!.status === 'accepted' ? 'done' : hasOffer ? 'current' : 'pending',
    detail: hasOffer ? app.offer!.status_label : undefined,
  })

  // Pre-Employment
  const hasPE = app.pre_employment != null
  steps.push({
    label: 'Pre-Employment',
    status: hasPE && app.pre_employment!.status === 'completed' ? 'done' : hasPE ? 'current' : 'pending',
    detail: hasPE ? `${app.pre_employment!.progress.percentage}% complete` : undefined,
  })

  // Hired
  steps.push({
    label: 'Hired',
    status: app.hiring?.status === 'hired' ? 'done' : 'pending',
    date: app.hiring?.hired_at ?? undefined,
  })

  return steps
}

export default function ApplicationTimeline({ application }: { application: Application }) {
  const steps = buildTimeline(application)

  return (
    <nav aria-label="Application Progress">
      <ol className="relative border-l border-neutral-200 dark:border-neutral-700 ml-3">
        {steps.map((step, idx) => (
          <li key={idx} className="mb-6 ml-6">
            <span
              className={`absolute -left-3 flex h-6 w-6 items-center justify-center rounded-full ring-4 ring-white text-xs font-bold
                ${step.status === 'done' ? 'bg-green-500 text-white' : ''}
                ${step.status === 'current' ? 'bg-blue-500 text-white' : ''}
                ${step.status === 'pending' ? 'bg-neutral-200 text-neutral-500' : ''}
              `}
            >
              {step.status === 'done' ? '\u2713' : idx + 1}
            </span>
            <h3 className={`text-sm font-semibold ${step.status === 'pending' ? 'text-neutral-400' : 'text-neutral-900 dark:text-white'}`}>
              {step.label}
            </h3>
            {step.date && <time className="text-xs text-neutral-500">{step.date}</time>}
            {step.detail && <p className="text-xs text-neutral-500">{step.detail}</p>}
          </li>
        ))}
      </ol>
    </nav>
  )
}
