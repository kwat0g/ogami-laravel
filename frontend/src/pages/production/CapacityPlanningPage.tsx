import { useCapacityUtilization } from '@/hooks/useEnhancements'
import type { WorkCenterUtilization } from '@/hooks/useEnhancements'

const STATUS_COLORS: Record<string, string> = {
  available: 'bg-green-500',
  moderate: 'bg-blue-500',
  near_capacity: 'bg-yellow-500',
  overloaded: 'bg-red-500',
}

export default function CapacityPlanningPage() {
  const { data: workCenters, isLoading } = useCapacityUtilization()

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-bold text-gray-900 dark:text-white">Capacity Planning</h1>
        <p className="text-sm text-gray-500 mt-1">Work center utilization and production feasibility</p>
      </div>

      {isLoading ? (
        <div className="text-center py-12 text-gray-500">Loading capacity data...</div>
      ) : (
        <div className="grid gap-4">
          {(workCenters ?? []).map((wc: WorkCenterUtilization) => (
            <div key={wc.work_center_id} className="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
              <div className="flex items-center justify-between mb-3">
                <div>
                  <h3 className="font-medium text-gray-900 dark:text-white">{wc.code} - {wc.name}</h3>
                  <p className="text-xs text-gray-500">{wc.capacity_hours_per_day}h/day capacity | {wc.working_days} working days</p>
                </div>
                <div className="text-right">
                  <div className="text-2xl font-bold">{wc.utilization_pct}%</div>
                  <span className={`text-xs px-2 py-0.5 rounded-full text-white ${STATUS_COLORS[wc.status]}`}>
                    {wc.status.replace(/_/g, ' ')}
                  </span>
                </div>
              </div>
              <div className="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-4">
                <div
                  className={`h-4 rounded-full transition-all ${STATUS_COLORS[wc.status]}`}
                  style={{ width: `${Math.min(100, wc.utilization_pct)}%` }}
                />
              </div>
              <div className="flex justify-between mt-2 text-xs text-gray-500">
                <span>Required: {wc.required_hours}h</span>
                <span>Available: {wc.available_hours}h</span>
                <span>Total: {wc.total_capacity_hours}h</span>
              </div>
            </div>
          ))}
        </div>
      )}
    </div>
  )
}
