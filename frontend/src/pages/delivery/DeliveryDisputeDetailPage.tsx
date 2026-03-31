import { useState } from 'react'
import { useParams, useNavigate, Link } from 'react-router-dom'
import { AlertTriangle, ArrowLeft, User, Package, CheckCircle, CreditCard, RefreshCw, ThumbsUp } from 'lucide-react'
import { toast } from 'sonner'
import { useDispute, useAssignDispute, useResolveDispute, useCloseDispute } from '@/hooks/useDeliveryDisputes'
import type { DisputeItem, ResolutionPayload } from '@/hooks/useDeliveryDisputes'
import { useAuthStore } from '@/stores/authStore'
import { PageHeader } from '@/components/ui/PageHeader'
import { Card, CardHeader, CardBody } from '@/components/ui/Card'
import SkeletonLoader from '@/components/ui/SkeletonLoader'
import { firstErrorMessage } from '@/lib/errorHandler'

const STATUS_COLORS: Record<string, string> = {
  open: 'bg-red-100 text-red-700',
  investigating: 'bg-amber-100 text-amber-700',
  pending_resolution: 'bg-blue-100 text-blue-700',
  resolved: 'bg-green-100 text-green-700',
  closed: 'bg-neutral-100 text-neutral-400',
}

const CONDITION_COLORS: Record<string, string> = {
  good: 'text-green-600',
  damaged: 'text-red-600',
  missing: 'text-amber-600',
  wrong_item: 'text-purple-600',
}

const RESOLUTION_ICONS: Record<string, typeof CreditCard> = {
  replace_items: RefreshCw,
  credit_note: CreditCard,
  partial_accept: ThumbsUp,
  full_replacement: RefreshCw,
}

// ── Resolution Form ────────────────────────────────────────────────────────
function ResolutionForm({
  items,
  onSubmit,
  isLoading,
}: {
  items: DisputeItem[]
  onSubmit: (payload: ResolutionPayload) => void
  isLoading: boolean
}) {
  const [resolutionType, setResolutionType] = useState<ResolutionPayload['resolution_type']>('replace_items')
  const [notes, setNotes] = useState('')
  const [itemActions, setItemActions] = useState<Record<number, { action: string; qty: number }>>(
    () => Object.fromEntries(items.map(i => [i.id, {
      action: i.condition === 'missing' ? 'replace' : i.condition === 'damaged' ? 'replace' : 'accept',
      qty: Math.max(0, Number(i.expected_qty) - Number(i.received_qty)),
    }]))
  )

  const updateItemAction = (itemId: number, field: string, value: string | number) => {
    setItemActions(prev => ({
      ...prev,
      [itemId]: { ...prev[itemId], [field]: value },
    }))
  }

  const handleSubmit = () => {
    const resolutions = items.map(i => ({
      item_id: i.id,
      action: itemActions[i.id]?.action ?? 'accept',
      qty: itemActions[i.id]?.qty ?? 0,
    }))

    // Auto-determine resolution type from item actions
    const hasReplace = resolutions.some(r => r.action === 'replace')
    const hasCredit = resolutions.some(r => r.action === 'credit')
    const allAccept = resolutions.every(r => r.action === 'accept')

    let autoType = resolutionType
    if (allAccept) autoType = 'partial_accept'
    else if (hasReplace && !hasCredit) autoType = 'replace_items'
    else if (hasCredit && !hasReplace) autoType = 'credit_note'

    onSubmit({
      resolution_type: autoType,
      resolution_notes: notes || undefined,
      resolutions,
    })
  }

  return (
    <Card>
      <CardHeader>
        <div className="flex items-center gap-2">
          <CheckCircle className="h-4 w-4 text-green-600" />
          Resolve Dispute
        </div>
      </CardHeader>
      <CardBody>
        <div className="space-y-4">
          {/* Per-item resolution */}
          <div className="text-xs font-medium text-neutral-500 uppercase mb-2">Resolution per item</div>
          <div className="space-y-3">
            {items.map(item => (
              <div key={item.id} className="border border-neutral-200 rounded-lg p-3">
                <div className="flex items-start justify-between gap-3">
                  <div className="flex-1">
                    <p className="text-sm font-medium text-neutral-900">
                      {item.item_master?.name ?? `Item #${item.item_master_id}`}
                    </p>
                    <p className="text-xs text-neutral-500 mt-0.5">
                      Expected: {item.expected_qty} | Received: {item.received_qty} |{' '}
                      <span className={CONDITION_COLORS[item.condition] ?? ''}>
                        {item.condition.replace('_', ' ')}
                      </span>
                    </p>
                    {item.notes && <p className="text-xs text-neutral-400 mt-1 italic">"{item.notes}"</p>}
                  </div>
                </div>
                <div className="grid grid-cols-2 gap-3 mt-3">
                  <div>
                    <label className="block text-xs text-neutral-500 mb-1">Action</label>
                    <select
                      value={itemActions[item.id]?.action ?? 'accept'}
                      onChange={e => updateItemAction(item.id, 'action', e.target.value)}
                      className="w-full border border-neutral-200 rounded-lg px-3 py-2 text-sm bg-white"
                    >
                      <option value="replace">Replace -- send new items</option>
                      <option value="credit">Credit -- issue credit note</option>
                      <option value="accept">Accept -- client keeps as-is</option>
                    </select>
                  </div>
                  <div>
                    <label className="block text-xs text-neutral-500 mb-1">
                      {itemActions[item.id]?.action === 'replace' ? 'Qty to Replace' :
                       itemActions[item.id]?.action === 'credit' ? 'Qty to Credit' : 'Accepted Qty'}
                    </label>
                    <input
                      type="number"
                      min="0"
                      step="1"
                      value={itemActions[item.id]?.qty ?? 0}
                      onChange={e => updateItemAction(item.id, 'qty', Number(e.target.value))}
                      className="w-full border border-neutral-200 rounded-lg px-3 py-2 text-sm"
                    />
                  </div>
                </div>
              </div>
            ))}
          </div>

          {/* Overall resolution type */}
          <div>
            <label className="block text-xs text-neutral-500 mb-1">Overall Resolution Type</label>
            <select
              value={resolutionType}
              onChange={e => setResolutionType(e.target.value as ResolutionPayload['resolution_type'])}
              className="w-full border border-neutral-200 rounded-lg px-3 py-2 text-sm bg-white"
            >
              <option value="replace_items">Replace Items -- send replacement delivery</option>
              <option value="credit_note">Credit Note -- issue credit to customer</option>
              <option value="partial_accept">Partial Accept -- client keeps partial delivery</option>
              <option value="full_replacement">Full Replacement -- redo entire delivery</option>
            </select>
          </div>

          {/* Resolution notes */}
          <div>
            <label className="block text-xs text-neutral-500 mb-1">Resolution Notes</label>
            <textarea
              value={notes}
              onChange={e => setNotes(e.target.value)}
              rows={3}
              placeholder="Describe the resolution details, actions taken, and any follow-up needed..."
              className="w-full border border-neutral-200 rounded-lg px-3 py-2 text-sm"
            />
          </div>

          <button
            onClick={handleSubmit}
            disabled={isLoading}
            className="w-full py-2.5 bg-green-600 text-white font-medium rounded-lg hover:bg-green-700 text-sm disabled:opacity-50"
          >
            {isLoading ? 'Resolving...' : 'Submit Resolution'}
          </button>
        </div>
      </CardBody>
    </Card>
  )
}

// ── Main Page ──────────────────────────────────────────────────────────────
export default function DeliveryDisputeDetailPage() {
  const { ulid } = useParams<{ ulid: string }>()
  const nav = useNavigate()
  const { data, isLoading } = useDispute(ulid ?? null)
  const dispute = data?.data
  const canManage = useAuthStore(s => s.hasPermission('delivery.manage'))

  const assignMut = useAssignDispute(ulid ?? '')
  const resolveMut = useResolveDispute(ulid ?? '')
  const closeMut = useCloseDispute(ulid ?? '')

  if (isLoading) return <SkeletonLoader rows={8} />
  if (!dispute) return <p className="text-neutral-500 p-6">Dispute not found.</p>

  const isOpen = ['open', 'investigating'].includes(dispute.status)
  const isPending = dispute.status === 'pending_resolution'
  const isResolved = dispute.status === 'resolved'
  const ResIcon = RESOLUTION_ICONS[dispute.resolution_type ?? ''] ?? AlertTriangle

  return (
    <div className="space-y-6">
      <PageHeader
        title={`Dispute ${dispute.dispute_reference}`}
        icon={
          <button onClick={() => nav('/delivery/disputes')} className="p-1 hover:bg-neutral-100 rounded">
            <ArrowLeft className="w-5 h-5" />
          </button>
        }
        actions={
          <>
            {isResolved && canManage && (
              <button
                onClick={() => closeMut.mutate(undefined, {
                  onSuccess: () => toast.success('Dispute closed'),
                  onError: (err) => toast.error(firstErrorMessage(err)),
                })}
                disabled={closeMut.isPending}
                className="text-sm px-4 py-2 bg-neutral-900 text-white rounded font-medium hover:bg-neutral-800 disabled:opacity-50"
              >
                Close Dispute
              </button>
            )}
          </>
        }
      />

      {/* Status + Info Cards */}
      <div className="grid grid-cols-1 lg:grid-cols-3 gap-4">
        <Card>
          <CardBody>
            <p className="text-xs text-neutral-500 uppercase font-medium">Status</p>
            <span className={`inline-block mt-1 px-3 py-1 rounded text-sm font-medium ${STATUS_COLORS[dispute.status] ?? 'bg-neutral-100'}`}>
              {dispute.status.replace('_', ' ')}
            </span>
            {dispute.resolution_type && (
              <div className="mt-3 flex items-center gap-2 text-sm text-neutral-700">
                <ResIcon className="h-4 w-4" />
                <span className="capitalize">{dispute.resolution_type.replace('_', ' ')}</span>
              </div>
            )}
          </CardBody>
        </Card>

        <Card>
          <CardBody>
            <p className="text-xs text-neutral-500 uppercase font-medium">Customer</p>
            <p className="text-sm font-medium text-neutral-900 mt-1">{dispute.customer?.name ?? '-'}</p>
            {dispute.client_order && (
              <Link to={`/sales/client-orders/${dispute.client_order.ulid}`} className="text-xs text-blue-600 hover:underline mt-1 block">
                Order: {dispute.client_order.order_reference}
              </Link>
            )}
          </CardBody>
        </Card>

        <Card>
          <CardBody>
            <p className="text-xs text-neutral-500 uppercase font-medium">People</p>
            <div className="mt-1 space-y-1 text-sm">
              <p className="flex items-center gap-1.5">
                <User className="h-3 w-3 text-neutral-400" />
                Reported by: {dispute.reported_by?.name ?? '-'}
              </p>
              <p className="flex items-center gap-1.5">
                <User className="h-3 w-3 text-neutral-400" />
                Assigned to: {dispute.assigned_to?.name ?? <span className="text-amber-500">Unassigned</span>}
              </p>
              {dispute.resolved_by && (
                <p className="flex items-center gap-1.5">
                  <CheckCircle className="h-3 w-3 text-green-500" />
                  Resolved by: {dispute.resolved_by.name}
                </p>
              )}
            </div>
          </CardBody>
        </Card>
      </div>

      {/* Client Notes */}
      {dispute.client_notes && (
        <Card>
          <CardBody>
            <p className="text-xs text-neutral-500 uppercase font-medium mb-2">Client Notes</p>
            <p className="text-sm text-neutral-700 bg-amber-50 border border-amber-100 rounded-lg p-3 italic">
              "{dispute.client_notes}"
            </p>
          </CardBody>
        </Card>
      )}

      {/* Disputed Items */}
      <Card>
        <CardHeader>
          <div className="flex items-center gap-2">
            <Package className="h-4 w-4 text-neutral-500" />
            Disputed Items ({dispute.items?.length ?? 0})
          </div>
        </CardHeader>
        <CardBody>
          <table className="w-full text-sm">
            <thead className="border-b border-neutral-200">
              <tr className="text-neutral-500 text-xs">
                <th className="text-left py-2 pr-3 font-medium">Item</th>
                <th className="text-right py-2 px-3 font-medium">Expected</th>
                <th className="text-right py-2 px-3 font-medium">Received</th>
                <th className="text-left py-2 px-3 font-medium">Condition</th>
                <th className="text-left py-2 px-3 font-medium">Notes</th>
                {(isResolved || isPending || dispute.status === 'closed') && (
                  <>
                    <th className="text-left py-2 px-3 font-medium">Resolution</th>
                    <th className="text-right py-2 font-medium">Qty</th>
                  </>
                )}
              </tr>
            </thead>
            <tbody className="divide-y divide-neutral-100">
              {dispute.items?.map(item => (
                <tr key={item.id}>
                  <td className="py-2 pr-3 font-medium text-neutral-900">
                    {item.item_master?.name ?? `Item #${item.item_master_id}`}
                  </td>
                  <td className="py-2 px-3 text-right text-neutral-700">{item.expected_qty}</td>
                  <td className="py-2 px-3 text-right text-neutral-700">{item.received_qty}</td>
                  <td className="py-2 px-3">
                    <span className={`font-medium capitalize ${CONDITION_COLORS[item.condition] ?? 'text-neutral-600'}`}>
                      {item.condition.replace('_', ' ')}
                    </span>
                  </td>
                  <td className="py-2 px-3 text-neutral-500 text-xs">{item.notes ?? '-'}</td>
                  {(isResolved || isPending || dispute.status === 'closed') && (
                    <>
                      <td className="py-2 px-3 capitalize text-neutral-600">{item.resolution_action ?? '-'}</td>
                      <td className="py-2 text-right text-neutral-700">{item.resolution_qty ?? '-'}</td>
                    </>
                  )}
                </tr>
              ))}
            </tbody>
          </table>
        </CardBody>
      </Card>

      {/* Resolution Notes */}
      {dispute.resolution_notes && (
        <Card>
          <CardBody>
            <p className="text-xs text-neutral-500 uppercase font-medium mb-2">Resolution Notes</p>
            <p className="text-sm text-neutral-700">{dispute.resolution_notes}</p>
          </CardBody>
        </Card>
      )}

      {/* Linked Records */}
      {(dispute.credit_note || dispute.replacement_schedule) && (
        <Card>
          <CardHeader>Linked Records</CardHeader>
          <CardBody>
            <div className="space-y-2 text-sm">
              {dispute.credit_note && (
                <p className="flex items-center gap-2">
                  <CreditCard className="h-4 w-4 text-green-600" />
                  Credit Note: <span className="font-medium">{dispute.credit_note.cn_reference}</span>
                  <span className="text-neutral-500">
                    (PHP {(dispute.credit_note.amount_centavos / 100).toLocaleString('en-PH', { minimumFractionDigits: 2 })})
                  </span>
                  <span className={`px-1.5 py-0.5 rounded text-xs ${dispute.credit_note.status === 'posted' ? 'bg-green-100 text-green-700' : 'bg-amber-100 text-amber-700'}`}>
                    {dispute.credit_note.status}
                  </span>
                </p>
              )}
              {dispute.replacement_schedule && (
                <p className="flex items-center gap-2">
                  <RefreshCw className="h-4 w-4 text-blue-600" />
                  Replacement:
                  <Link to={`/production/delivery-schedules/${dispute.replacement_schedule.ulid}`} className="text-blue-600 hover:underline font-medium">
                    {dispute.replacement_schedule.cds_reference}
                  </Link>
                  <span className={`px-1.5 py-0.5 rounded text-xs bg-blue-100 text-blue-700`}>
                    {dispute.replacement_schedule.status}
                  </span>
                </p>
              )}
            </div>
          </CardBody>
        </Card>
      )}

      {/* Resolution Form -- only for open/investigating disputes */}
      {isOpen && canManage && (
        <ResolutionForm
          items={dispute.items ?? []}
          onSubmit={(payload) => {
            resolveMut.mutate(payload, {
              onSuccess: () => toast.success('Dispute resolved'),
              onError: (err) => toast.error(firstErrorMessage(err)),
            })
          }}
          isLoading={resolveMut.isPending}
        />
      )}
    </div>
  )
}
