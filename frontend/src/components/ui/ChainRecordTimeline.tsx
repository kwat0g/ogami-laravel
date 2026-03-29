import { Link } from 'react-router-dom'
import { useChainRecord, type ChainNode } from '@/hooks/useChainRecord'
import SkeletonLoader from '@/components/ui/SkeletonLoader'

const TYPE_LABELS: Record<string, string> = {
  client_order: 'Client Order',
  delivery_schedule: 'Delivery Schedule',
  production_order: 'Production Order',
  material_requisition: 'Material Requisition',
  purchase_request: 'Purchase Request',
  purchase_order: 'Purchase Order',
  goods_receipt: 'Goods Receipt',
  vendor_invoice: 'Vendor Invoice',
  inspection: 'QC Inspection',
  delivery_receipt: 'Delivery Receipt',
  customer_invoice: 'Customer Invoice',
}

const TYPE_COLORS: Record<string, string> = {
  client_order: 'bg-blue-500',
  delivery_schedule: 'bg-cyan-500',
  production_order: 'bg-purple-500',
  material_requisition: 'bg-amber-500',
  purchase_request: 'bg-orange-500',
  purchase_order: 'bg-red-500',
  goods_receipt: 'bg-green-500',
  vendor_invoice: 'bg-rose-500',
  inspection: 'bg-teal-500',
  delivery_receipt: 'bg-indigo-500',
  customer_invoice: 'bg-emerald-500',
}

const STATUS_STYLES: Record<string, string> = {
  draft: 'bg-neutral-100 text-neutral-700',
  pending: 'bg-yellow-100 text-yellow-700',
  pending_review: 'bg-yellow-100 text-yellow-700',
  approved: 'bg-green-100 text-green-700',
  completed: 'bg-green-100 text-green-700',
  confirmed: 'bg-green-100 text-green-700',
  passed: 'bg-green-100 text-green-700',
  fully_received: 'bg-green-100 text-green-700',
  converted_to_po: 'bg-blue-100 text-blue-700',
  sent: 'bg-blue-100 text-blue-700',
  in_transit: 'bg-blue-100 text-blue-700',
  ready: 'bg-emerald-100 text-emerald-700',
  closed: 'bg-neutral-100 text-neutral-600',
  cancelled: 'bg-red-100 text-red-700',
  rejected: 'bg-red-100 text-red-700',
  failed: 'bg-red-100 text-red-700',
}

function formatDate(dateStr: string): string {
  try {
    return new Date(dateStr).toLocaleDateString('en-PH', {
      month: 'short',
      day: 'numeric',
      year: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
    })
  } catch {
    return dateStr
  }
}

interface Props {
  documentType: string
  documentId: number
  title?: string
}

export default function ChainRecordTimeline({ documentType, documentId, title }: Props) {
  const { data: chain, isLoading, isError } = useChainRecord(documentType, documentId)

  if (isLoading) return <SkeletonLoader rows={4} />
  if (isError) return <div className="text-sm text-red-500 py-4">Failed to load chain record.</div>
  if (!chain || chain.length === 0) return <div className="text-sm text-neutral-400 py-4">No linked documents found.</div>

  return (
    <div className="space-y-3">
      {title && (
        <h3 className="text-sm font-semibold text-neutral-700 dark:text-neutral-300 uppercase tracking-wide">
          {title}
        </h3>
      )}
      <div className="relative">
        {/* Vertical line */}
        <div className="absolute left-[15px] top-2 bottom-2 w-0.5 bg-neutral-200 dark:bg-neutral-700" />

        <div className="space-y-0">
          {chain.map((node: ChainNode, idx: number) => (
            <div key={`${node.type}-${node.id}`} className="relative flex items-start gap-3 py-2">
              {/* Dot */}
              <div className={`relative z-10 w-[9px] h-[9px] mt-1.5 rounded-full ring-2 ring-white dark:ring-neutral-900 ${TYPE_COLORS[node.type] ?? 'bg-neutral-400'}`} style={{ marginLeft: '11px' }} />

              {/* Content */}
              <div className="flex-1 min-w-0">
                <div className="flex items-center gap-2 flex-wrap">
                  <span className="text-[10px] font-semibold uppercase tracking-wider text-neutral-400">
                    {TYPE_LABELS[node.type] ?? node.type}
                  </span>
                  <span className={`inline-block px-1.5 py-0.5 text-[10px] font-medium rounded ${STATUS_STYLES[node.status] ?? 'bg-neutral-100 text-neutral-600'}`}>
                    {node.status.replace(/_/g, ' ')}
                  </span>
                </div>
                <div className="flex items-center gap-2 mt-0.5">
                  {node.url ? (
                    <Link
                      to={node.url}
                      className="text-sm font-medium text-blue-600 hover:text-blue-800 hover:underline dark:text-blue-400"
                    >
                      {node.reference}
                    </Link>
                  ) : (
                    <span className="text-sm font-medium text-neutral-800 dark:text-neutral-200">
                      {node.reference}
                    </span>
                  )}
                  <span className="text-xs text-neutral-400">
                    {formatDate(node.date)}
                  </span>
                </div>
                {node.actor && (
                  <div className="text-xs text-neutral-500 mt-0.5">
                    by {node.actor}
                  </div>
                )}
              </div>

              {/* Step number */}
              <span className="text-[10px] font-mono text-neutral-300 mt-1">
                {idx + 1}
              </span>
            </div>
          ))}
        </div>
      </div>
    </div>
  )
}
