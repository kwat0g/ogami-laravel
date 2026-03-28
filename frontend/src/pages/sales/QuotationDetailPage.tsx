import { useParams, useNavigate } from 'react-router-dom'
import { ArrowLeft, Send, Check, X, ArrowRight } from 'lucide-react'
import { useQuotation, useSendQuotation, useAcceptQuotation, useConvertQuotationToOrder } from '@/hooks/useSales'
import { PageHeader } from '@/components/ui/PageHeader'
import { Card } from '@/components/ui/Card'
import StatusBadge from '@/components/ui/StatusBadge'
import SkeletonLoader from '@/components/ui/SkeletonLoader'

function fmt(c: number) { return new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' }).format(c / 100) }

export default function QuotationDetailPage() {
  const { ulid } = useParams<{ ulid: string }>()
  const nav = useNavigate()
  const { data: q, isLoading } = useQuotation(ulid ?? '')
  const sendMut = useSendQuotation(ulid ?? '')
  const acceptMut = useAcceptQuotation(ulid ?? '')
  const convertMut = useConvertQuotationToOrder(ulid ?? '')

  if (isLoading) return <SkeletonLoader rows={6} />
  if (!q) return <div className="p-6 text-neutral-500">Quotation not found</div>

  return (
    <div className="space-y-6">
      <PageHeader
        title={`Quotation ${q.quotation_number}`}
        icon={<button onClick={() => nav('/sales/quotations')} className="p-1 hover:bg-neutral-100 rounded"><ArrowLeft className="w-5 h-5" /></button>}
        actions={
          <div className="flex gap-2">
            {q.status === 'draft' && <button className="btn-primary" onClick={() => sendMut.mutate()} disabled={sendMut.isPending}><Send className="w-4 h-4" /> Send</button>}
            {q.status === 'sent' && <button className="btn-primary" onClick={() => acceptMut.mutate()} disabled={acceptMut.isPending}><Check className="w-4 h-4" /> Accept</button>}
            {q.status === 'accepted' && <button className="btn-primary" onClick={() => convertMut.mutate()} disabled={convertMut.isPending}><ArrowRight className="w-4 h-4" /> Convert to Order</button>}
          </div>
        }
      />
      <div className="grid grid-cols-1 lg:grid-cols-3 gap-4">
        <Card className="p-4"><span className="text-xs text-neutral-500">Status</span><p className="mt-1"><StatusBadge status={q.status} /></p></Card>
        <Card className="p-4"><span className="text-xs text-neutral-500">Total</span><p className="mt-1 text-xl font-bold font-mono">{fmt(q.total_centavos)}</p></Card>
        <Card className="p-4"><span className="text-xs text-neutral-500">Valid Until</span><p className="mt-1 font-medium">{new Date(q.validity_date).toLocaleDateString()}</p></Card>
      </div>
      <Card className="p-6">
        <h3 className="font-semibold mb-3">Customer: {q.customer?.name}</h3>
        <div className="text-sm text-neutral-500 space-y-1">
          {q.contact && <p>Contact: {q.contact.first_name} {q.contact.last_name}</p>}
          {q.notes && <p>Notes: {q.notes}</p>}
        </div>
      </Card>
      {q.items && q.items.length > 0 && (
        <Card className="overflow-hidden">
          <table className="w-full text-sm">
            <thead className="bg-neutral-50 dark:bg-neutral-800">
              <tr><th className="text-left p-3">Item</th><th className="text-right p-3">Qty</th><th className="text-right p-3">Unit Price</th><th className="text-right p-3">Line Total</th></tr>
            </thead>
            <tbody className="divide-y">
              {q.items.map(item => (
                <tr key={item.id}>
                  <td className="p-3">{item.item?.name ?? `Item #${item.item_id}`}</td>
                  <td className="p-3 text-right">{item.quantity}</td>
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
