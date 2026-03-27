import { useOrderTracking, type OrderTrackingStage } from '@/hooks/useAnalytics'
import SkeletonLoader from '@/components/ui/SkeletonLoader'
import { CheckCircle2, Clock, AlertCircle, Circle } from 'lucide-react'

function StageIcon({ status }: { status: OrderTrackingStage['status'] }): JSX.Element {
  switch (status) {
    case 'completed':
      return <CheckCircle2 className="h-6 w-6 text-green-600" />
    case 'in_progress':
      return <Clock className="h-6 w-6 text-blue-600 animate-pulse" />
    case 'failed':
      return <AlertCircle className="h-6 w-6 text-red-600" />
    default:
      return <Circle className="h-6 w-6 text-neutral-300" />
  }
}

function StageConnector({ status }: { status: OrderTrackingStage['status'] }): JSX.Element {
  const color = status === 'completed' ? 'bg-green-400' : status === 'in_progress' ? 'bg-blue-400' : 'bg-neutral-200'
  return <div className={`w-0.5 h-8 ml-[11px] ${color}`} />
}

interface Props {
  orderUlid: string
  className?: string
}

export default function OrderTrackingTimeline({ orderUlid, className = '' }: Props): JSX.Element {
  const { data, isLoading, isError } = useOrderTracking(orderUlid)

  if (isLoading) return <SkeletonLoader lines={6} />
  if (isError || !data) return <p className="text-red-600 text-sm">Failed to load tracking information.</p>

  return (
    <div className={`${className}`}>
      <div className="flex items-center gap-2 mb-4">
        <h3 className="font-semibold text-neutral-800">Order Tracking</h3>
        {data.order_number && (
          <span className="text-xs font-mono text-neutral-400">#{data.order_number}</span>
        )}
      </div>

      <div className="space-y-0">
        {data.timeline.map((stage, i) => (
          <div key={stage.stage}>
            <div className="flex items-start gap-3">
              <StageIcon status={stage.status} />
              <div className="flex-1 pb-1">
                <div className="flex items-center gap-2">
                  <span className={`text-sm font-semibold ${
                    stage.status === 'completed' ? 'text-green-700' :
                    stage.status === 'in_progress' ? 'text-blue-700' :
                    stage.status === 'failed' ? 'text-red-700' :
                    'text-neutral-400'
                  }`}>
                    {stage.label}
                  </span>
                  {stage.status === 'in_progress' && (
                    <span className="text-[10px] uppercase tracking-wide bg-blue-100 text-blue-700 px-1.5 py-0.5 rounded-full font-semibold">
                      In Progress
                    </span>
                  )}
                </div>
                {stage.date && (
                  <p className="text-xs text-neutral-400 mt-0.5">
                    {new Date(stage.date).toLocaleDateString('en-PH', {
                      year: 'numeric',
                      month: 'short',
                      day: 'numeric',
                      hour: '2-digit',
                      minute: '2-digit',
                    })}
                  </p>
                )}
                {stage.details && (
                  <p className="text-xs text-neutral-500 mt-0.5">{stage.details}</p>
                )}
              </div>
            </div>
            {i < data.timeline.length - 1 && (
              <StageConnector status={stage.status} />
            )}
          </div>
        ))}
      </div>
    </div>
  )
}
