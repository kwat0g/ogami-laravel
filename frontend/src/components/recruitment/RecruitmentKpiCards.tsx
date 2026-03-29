interface KpiCardsProps {
  kpis: {
    open_requisitions: number
    active_applications: number
    interviews_this_week: number
    pending_offers: number
  }
}

const cards = [
  { key: 'open_requisitions', label: 'Open Requisitions', color: 'text-blue-600', bg: 'bg-blue-50' },
  { key: 'active_applications', label: 'Active Applications', color: 'text-green-600', bg: 'bg-green-50' },
  { key: 'interviews_this_week', label: 'Interviews This Week', color: 'text-amber-600', bg: 'bg-amber-50' },
  { key: 'pending_offers', label: 'Pending Offers', color: 'text-purple-600', bg: 'bg-purple-50' },
] as const

export default function RecruitmentKpiCards({ kpis }: KpiCardsProps) {
  return (
    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
      {cards.map((card) => (
        <div key={card.key} className={`rounded-lg ${card.bg} p-6`}>
          <p className="text-sm font-medium text-gray-600">{card.label}</p>
          <p className={`mt-2 text-3xl font-bold ${card.color}`}>
            {kpis[card.key]}
          </p>
        </div>
      ))}
    </div>
  )
}
