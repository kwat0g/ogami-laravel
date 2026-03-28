import { useParams, useNavigate } from 'react-router-dom'
import { ArrowLeft, CheckCircle, XCircle } from 'lucide-react'
import { useSalesOrder, useConfirmSalesOrder } from '@/hooks/useSales'
import { useAuthStore } from '@/stores/authStore'
import { useMutation, useQueryClient } from '@tanstack/react-query'
import api from '@/lib/api'
import { PageHeader } from '@/components/ui/PageHeader'
import { Card } from '@/components/ui/Card'
import StatusBadge from '@/components/ui/StatusBadge'
import SkeletonLoader from '@/components/ui/SkeletonLoader'

function fmt(c: number) { return new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' }).format(c / 100) }

export default function SalesOrderDetailPage() {
  const { ulid } = useParams<{ ulid: string }>()
  const nav = useNavigate()
  const { data: order, isLoading, isError } = useSalesOrder(ulid ?? '')
  const confirmMut = useConfirmSalesOrder(ulid ?? '')
  const qc = useQueryClient()
  const cancelMut = useMutation({
    mutationFn: async () => { const { data } = await api.patch(`/sales/orders/${ulid}/cancel`); return data.data },
    onSuccess: () => { qc.invalidateQueries({ queryKey: ['sales-order', ulid] }) },
  })

  const canConfirm = useAuthStore(s => s.hasPermission('sales.orders.confirm'))
  const canCancel = useAuthStore(s => s.hasPermission('sales.orders.cancel'))

  if (isLoading) return <SkeletonLoader rows={6} />
  if (isError) return <div className="p-6 text-red-600">Failed to load sales order. Please try again.</div>
  if (!order) return <div className="p-6 text-neutral-500">Sales order not found</div>

  return (
    <div className="space-y-6">
      <PageHeader
        title={`Sales Order ${order.order_number}`}
        icon={<button onClick={() => nav('/sales/orders')} className="p-1 hover:bg-neutral-100 rounded"><ArrowLeft className="w-5 h-5" /></button>}
        actions={
          <div className="flex gap-2">
            {order.status === 'draft' && canConfirm && <button className="btn-primary" onClick={() => confirmMut.mutate()} disabled={confirmMut.isPending}><CheckCircle className="w-4 h-4" /> Confirm</button>}
            {canCancel && !['delivered', 'invoiced', 'cancelled'].includes(order.status) && (
              <button className="btn-danger" onClick={() => cancelMut.mutate()} disabled={cancelMut.isPending}><XCircle className="w-4 h-4" /> Cancel</button>
            )}
          </div>
        }
      />
      <div className="grid grid-cols-1 lg:grid-cols-4 gap-4">
        <Card className="p-4"><span className="text-xs text-neutral-500">Status</span><p className="mt-1"><StatusBadge status={order.status} /></p></Card>
        <Card className="p-4"><span className="text-xs text-neutral-500">Total</span><p className="mt-1 text-xl font-bold font-mono">{fmt(order.total_centavos)}</p></Card>
        <Card className="p-4"><span className="text-xs text-neutral-500">Customer</span><p className="mt-1 font-medium">{order.customer?.name}</p></Card>
        <Card className="p-4"><span className="text-xs text-neutral-500">Delivery Date</span><p className="mt-1">{order.promised_delivery_date ? new Date(order.promised_delivery_date).toLocaleDateString() : '-'}</p></Card>
      </div>
      {order.items && order.items.length > 0 && (
        <Card className="overflow-hidden">
          <table className="w-full text-sm">
            <thead className="bg-neutral-50 dark:bg-neutral-800">
              <tr><th className="text-left p-3">Item</th><th className="text-right p-3">Qty</th><th className="text-right p-3">Delivered</th><th className="text-right p-3">Unit Price</th><th className="text-right p-3">Line Total</th></tr>
            </thead>
            <tbody className="divide-y">
              {order.items.map(item => (
                <tr key={item.id}>
                  <td className="p-3">{item.item?.name ?? `Item #${item.item_id}`}</td>
                  <td className="p-3 text-right">{item.quantity}</td>
                  <td className="p-3 text-right">{item.quantity_delivered}</td>
                  <td className="p-3 text-right font-mono">{fmt(item.unit_price_centavos)}</td>
                  <td className="p-3 text-right font-mono">{fmt(item.line_total_centavos)}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </Card>
      )}
    </div>
  )
}
