import { useState } from 'react'
import { useParams, useNavigate } from 'react-router-dom'
import { toast } from 'sonner'
import { ArrowLeft, Trophy } from 'lucide-react'
import { useVendorRfq, useCloseVendorRfq, useCancelVendorRfq, useRecordQuote, useRecordDecline, useAwardRfq } from '@/hooks/useVendorRfqs'
import { useAuthStore } from '@/stores/authStore'
import ConfirmDialog from '@/components/ui/ConfirmDialog'
import { firstErrorMessage } from '@/lib/errorHandler'

export default function VendorRfqDetailPage(): React.ReactElement {
  const { ulid } = useParams<{ ulid: string }>()
  const navigate = useNavigate()
  const { data: rfq, isLoading, isError } = useVendorRfq(ulid ?? null)
  const closeRfq = useCloseVendorRfq()
  const cancelRfq = useCancelVendorRfq()
  const recordQuote = useRecordQuote()
  const recordDecline = useRecordDecline()
  const awardRfq = useAwardRfq()
  const [quoteVendor, setQuoteVendor] = useState<number | null>(null)
  const [quoteAmount, setQuoteAmount] = useState('')
  const [quoteNotes, setQuoteNotes] = useState('')
  const canManage = useAuthStore((s) => s.hasPermission('procurement.purchase-order.manage'))

  if (isLoading) return <p className="text-sm text-neutral-500 mt-4">Loading…</p>
  if (isError || !rfq) return <p className="text-sm text-red-600 mt-4">RFQ not found.</p>

  async function handleClose() {
    try {
      await closeRfq.mutateAsync(rfq!.ulid)
      toast.success('RFQ closed.')
    } catch (err) {
      const message = firstErrorMessage(err)
      toast.error(message ?? 'Failed to close RFQ.')
    }
  }

  async function handleCancel() {
    try {
      await cancelRfq.mutateAsync(rfq!.ulid)
      toast.success('RFQ cancelled.')
      navigate('/procurement/rfqs')
    } catch (err) {
      const message = firstErrorMessage(err)
      toast.error(message ?? 'Failed to cancel RFQ.')
    }
  }

  async function handleQuote(e: React.FormEvent) {
    e.preventDefault()
    if (!quoteVendor) return
    const amount = Number(quoteAmount)
    if (!amount || amount <= 0) {
      toast.error('Quote amount must be greater than 0.')
      return
    }
    try {
      await recordQuote.mutateAsync({
        ulid: rfq!.ulid,
        vendorId: quoteVendor,
        payload: { quoted_amount: amount, notes: quoteNotes || undefined },
      })
      toast.success('Quote recorded.')
      setQuoteVendor(null)
      setQuoteAmount('')
      setQuoteNotes('')
    } catch (err) {
      const message = firstErrorMessage(err)
      toast.error(message ?? 'Failed to record quote.')
    }
  }

  async function handleDecline(vendorId: number) {
    try {
      await recordDecline.mutateAsync({ ulid: rfq!.ulid, vendorId })
      toast.success('Decline recorded.')
    } catch (err) {
      const message = firstErrorMessage(err)
      toast.error(message ?? 'Failed to record decline.')
    }
  }

  async function handleAward(vendorId: number) {
    try {
      const po = await awardRfq.mutateAsync({ rfqUlid: rfq!.ulid, vendorId })
      toast.success(`RFQ awarded. Draft PO ${po.po_reference} created.`)
      navigate(`/procurement/purchase-orders/${po.ulid}`)
    } catch (err) {
      toast.error(firstErrorMessage(err) ?? 'Failed to award RFQ.')
    }
  }

  return (
    <div className="max-w-5xl mx-auto">
      <button onClick={() => navigate('/procurement/rfqs')} className="flex items-center gap-1 text-sm text-neutral-500 hover:text-neutral-800 mb-4">
        <ArrowLeft className="w-4 h-4" /> Back to RFQs
      </button>

      <div className="bg-white border border-neutral-200 rounded-lg p-6 space-y-4">
        <div className="flex items-center justify-between">
          <div>
            <h1 className="text-xl font-bold text-neutral-900">{rfq.rfq_reference} — {rfq.title}</h1>
            {rfq.description && <p className="text-sm text-neutral-500 mt-1">{rfq.description}</p>}
          </div>
          <div className="flex items-center gap-2">
            <span className={`px-2 py-0.5 rounded text-xs font-medium ${
              rfq.status === 'sent' ? 'bg-blue-100 text-blue-700' :
              rfq.status === 'closed' ? 'bg-emerald-100 text-emerald-700' :
              rfq.status === 'cancelled' ? 'bg-red-100 text-red-600' : 'bg-neutral-100 text-neutral-500'
            }`}>{rfq.status}</span>
            {rfq.status === 'sent' && canManage && (
              <ConfirmDialog
                title="Close RFQ?"
                description="This will close the RFQ and no more quotes will be accepted."
                onConfirm={handleClose}
              >
                <button disabled={closeRfq.isPending}
                  className="text-sm bg-neutral-900 text-white rounded px-3 py-1.5 hover:bg-neutral-800 disabled:opacity-50">Close RFQ</button>
              </ConfirmDialog>
            )}
            {['draft', 'sent'].includes(rfq.status) && canManage && (
              <ConfirmDialog
                title="Cancel RFQ?"
                description="This will cancel the RFQ. This action cannot be undone."
                onConfirm={handleCancel}
              >
                <button disabled={cancelRfq.isPending}
                  className="text-sm text-red-600 border border-red-300 rounded px-3 py-1.5 hover:bg-red-50 disabled:opacity-50">Cancel</button>
              </ConfirmDialog>
            )}
          </div>
        </div>

        <div className="text-sm text-neutral-600">
          {rfq.deadline && <span>Deadline: {rfq.deadline} · </span>}
          Created: {new Date(rfq.created_at).toLocaleDateString('en-PH')} {rfq.created_by && `by ${rfq.created_by.name}`}
        </div>

        <h3 className="text-sm font-semibold text-neutral-800 pt-2 border-t border-neutral-100">Vendor Responses</h3>

        {rfq.vendors.length === 0 ? (
          <p className="text-sm text-neutral-500">No vendors invited.</p>
        ) : (
          <div className="space-y-3">
            {rfq.vendors.map(v => (
              <div key={v.id} className="flex items-center justify-between bg-neutral-50 rounded-lg px-4 py-3">
                <div>
                  <span className="text-sm font-medium text-neutral-800">{v.vendor?.name ?? `Vendor #${v.vendor_id}`}</span>
                  <span className={`ml-2 px-2 py-0.5 rounded text-xs font-medium ${
                    v.status === 'quoted' ? 'bg-emerald-100 text-emerald-700' :
                    v.status === 'declined' ? 'bg-red-100 text-red-600' : 'bg-amber-100 text-amber-700'
                  }`}>{v.status}</span>
                  {v.quoted_amount_centavos !== null && (
                    <span className="ml-2 text-sm text-neutral-700">₱{(v.quoted_amount_centavos / 100).toLocaleString(undefined, { minimumFractionDigits: 2 })}</span>
                  )}
                </div>
                {rfq.status === 'sent' && v.status === 'pending' && canManage && (
                  <div className="flex items-center gap-2">
                    <button onClick={() => setQuoteVendor(v.vendor_id)} className="text-xs text-neutral-600 hover:text-neutral-900 underline">Record Quote</button>
                    <ConfirmDialog
                      title="Record Decline?"
                      description={`Mark vendor "${v.vendor?.name ?? `Vendor #${v.vendor_id}`}" as declined?`}
                      onConfirm={() => handleDecline(v.vendor_id)}
                    >
                      <button disabled={recordDecline.isPending}
                        className="text-xs text-red-500 hover:text-red-700 underline disabled:opacity-50">Decline</button>
                    </ConfirmDialog>
                  </div>
                )}
                {rfq.status === 'quote_received' && v.status === 'quoted' && canManage && (
                  <ConfirmDialog
                    title="Award RFQ & Create PO?"
                    description={`Award this RFQ to ${v.vendor?.name ?? `Vendor #${v.vendor_id}`} and auto-create a draft Purchase Order?`}
                    confirmLabel="Award & Create PO"
                    onConfirm={() => handleAward(v.vendor_id)}
                  >
                    <button
                      disabled={awardRfq.isPending}
                      className="flex items-center gap-1 text-xs bg-emerald-600 text-white rounded px-3 py-1.5 hover:bg-emerald-700 disabled:opacity-50"
                    >
                      <Trophy className="w-3.5 h-3.5" />
                      Award & Create PO
                    </button>
                  </ConfirmDialog>
                )}
              </div>
            ))}
          </div>
        )}

        {/* Quote form */}
        {quoteVendor !== null && (
          <form onSubmit={handleQuote} className="bg-white border border-neutral-200 rounded-lg p-4 mt-4 space-y-3">
            <h4 className="text-sm font-medium text-neutral-800">Record Quote for Vendor #{quoteVendor}</h4>
            <div className="grid grid-cols-2 gap-3">
              <div>
                <label className="block text-xs font-medium text-neutral-600 mb-1">Amount (₱)</label>
                <input type="number" step="0.01" min={0.01} value={quoteAmount} onChange={(e) => setQuoteAmount(e.target.value)} required
                  className="w-full border border-neutral-300 rounded px-3 py-2 text-sm" />
              </div>
              <div>
                <label className="block text-xs font-medium text-neutral-600 mb-1">Notes (optional)</label>
                <input value={quoteNotes} onChange={(e) => setQuoteNotes(e.target.value)}
                  className="w-full border border-neutral-300 rounded px-3 py-2 text-sm" />
              </div>
            </div>
            <div className="flex gap-2">
              <button type="submit" disabled={recordQuote.isPending}
                className="text-sm bg-neutral-900 text-white rounded px-4 py-2 hover:bg-neutral-800 disabled:opacity-50">Save Quote</button>
              <button type="button" onClick={() => setQuoteVendor(null)} className="text-sm text-neutral-500">Cancel</button>
            </div>
          </form>
        )}
      </div>
    </div>
  )
}
