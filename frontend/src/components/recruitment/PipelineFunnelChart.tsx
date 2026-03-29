interface FunnelProps {
  data: {
    requisitions: number
    postings: number
    applications: number
    shortlisted: number
    interviewed: number
    offered: number
    hired: number
  }
}

const stages = [
  { key: 'requisitions', label: 'Requisitions', color: 'bg-blue-500' },
  { key: 'postings', label: 'Postings', color: 'bg-sky-500' },
  { key: 'applications', label: 'Applications', color: 'bg-teal-500' },
  { key: 'shortlisted', label: 'Shortlisted', color: 'bg-amber-500' },
  { key: 'interviewed', label: 'Interviewed', color: 'bg-orange-500' },
  { key: 'offered', label: 'Offered', color: 'bg-purple-500' },
  { key: 'hired', label: 'Hired', color: 'bg-green-500' },
] as const

export default function PipelineFunnelChart({ data }: FunnelProps) {
  const maxVal = Math.max(...Object.values(data), 1)

  return (
    <div className="space-y-3">
      <h3 className="text-sm font-semibold text-neutral-700 dark:text-neutral-300">Recruitment Pipeline</h3>
      {stages.map((stage) => {
        const val = data[stage.key]
        const width = Math.max((val / maxVal) * 100, 5)
        return (
          <div key={stage.key} className="flex items-center gap-3">
            <span className="w-24 text-xs text-neutral-500 text-right">{stage.label}</span>
            <div className="flex-1 h-6 bg-neutral-100 dark:bg-neutral-700 rounded overflow-hidden">
              <div
                className={`h-full ${stage.color} rounded flex items-center justify-end pr-2 transition-all`}
                style={{ width: `${width}%` }}
              >
                <span className="text-xs font-bold text-white">{val}</span>
              </div>
            </div>
          </div>
        )
      })}
    </div>
  )
}
