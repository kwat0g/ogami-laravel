import { useTimePhasedMrp } from '@/hooks/useEnhancements'

const URGENCY_COLORS: Record<string, string> = {
  overdue: 'bg-red-500 text-white',
  urgent: 'bg-orange-500 text-white',
  soon: 'bg-yellow-100 text-yellow-800',
  planned: 'bg-blue-100 text-blue-800',
}

export default function TimePhasedMrpPage() {
  const { data: requirements, isLoading } = useTimePhasedMrp()

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-bold text-gray-900 dark:text-white">Time-Phased MRP</h1>
        <p className="text-sm text-gray-500 mt-1">Material requirements with lead-time-adjusted order release dates</p>
      </div>

      {isLoading ? (
        <div className="text-center py-12 text-gray-500">Computing MRP explosion...</div>
      ) : !requirements || requirements.length === 0 ? (
        <div className="bg-green-50 border border-green-200 rounded-lg p-6 text-center text-green-700">
          No material shortages detected. All production orders have sufficient stock.
        </div>
      ) : (
        <div className="space-y-4">
          {requirements.map((req: any) => (
            <div key={req.component_item_id} className="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
              <div className="flex items-center justify-between mb-3">
                <div>
                  <h3 className="font-medium text-gray-900 dark:text-white">{req.component_code} - {req.component_name}</h3>
                  <p className="text-xs text-gray-500">
                    Stock: {req.current_stock} | Required: {req.total_required} | Shortage: <span className="text-red-600 font-medium">{req.shortage}</span>
                  </p>
                </div>
                <div className="text-right">
                  <span className={`text-xs px-2 py-1 rounded-full font-medium ${URGENCY_COLORS[req.urgency] ?? 'bg-gray-100'}`}>
                    {req.urgency}
                  </span>
                  <div className="text-xs text-gray-500 mt-1">Lead time: {req.lead_time_days} days</div>
                </div>
              </div>

              <div className="grid grid-cols-2 gap-4 text-sm">
                <div>
                  <span className="text-gray-500">Order Release Date:</span>
                  <span className="ml-2 font-medium text-gray-900 dark:text-white">{req.order_release_date}</span>
                  {req.days_until_order > 0 && (
                    <span className="ml-1 text-xs text-gray-400">({req.days_until_order} days)</span>
                  )}
                </div>
                <div>
                  <span className="text-gray-500">Need By:</span>
                  <span className="ml-2 font-medium">{req.need_by_date}</span>
                </div>
              </div>

              {req.weekly_demand && req.weekly_demand.length > 0 && (
                <div className="mt-3 pt-3 border-t dark:border-gray-700">
                  <div className="text-xs text-gray-500 mb-1">Weekly Demand Breakdown:</div>
                  <div className="flex gap-2 flex-wrap">
                    {req.weekly_demand.map((w: any) => (
                      <div key={w.week} className="px-2 py-1 bg-gray-50 dark:bg-gray-700 rounded text-xs">
                        <span className="text-gray-500">W{w.week}:</span> {w.qty_required} ({w.order_count} orders)
                      </div>
                    ))}
                  </div>
                </div>
              )}
            </div>
          ))}
        </div>
      )}
    </div>
  )
}
