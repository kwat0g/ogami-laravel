import { useVendorGoodsReceipts, type VendorPortalGoodsReceipt } from '@/hooks/useVendorPortal'
import { PageHeader } from '@/components/ui/PageHeader'

export default function VendorGoodsReceiptsPage(): React.ReactElement {
  const { data, isLoading } = useVendorGoodsReceipts()

  if (isLoading) return <p className="text-sm text-neutral-500 mt-4">Loading receipts…</p>

  const receipts: VendorPortalGoodsReceipt[] = data?.data ?? []

  return (
    <div className="space-y-6">
      <PageHeader
        title="Goods Receipts"
        subtitle="View delivery receipts for your purchase orders"
      />

      {receipts.length === 0 ? (
        <div className="bg-white border border-neutral-200 rounded-lg px-6 py-12 text-center">
          <p className="text-neutral-500 text-sm">No goods receipts found.</p>
        </div>
      ) : (
        <div className="bg-white border border-neutral-200 rounded-lg overflow-hidden">
          <table className="w-full text-sm">
            <thead className="bg-neutral-50 border-b border-neutral-200">
              <tr>
                <th className="text-left px-4 py-2 font-medium text-neutral-600 text-xs uppercase">GR Reference</th>
                <th className="text-left px-4 py-2 font-medium text-neutral-600 text-xs uppercase">PO Reference</th>
                <th className="text-left px-4 py-2 font-medium text-neutral-600 text-xs uppercase">Received Date</th>
                <th className="text-left px-4 py-2 font-medium text-neutral-600 text-xs uppercase">Status</th>
                <th className="text-left px-4 py-2 font-medium text-neutral-600 text-xs uppercase">3-Way Match</th>
                <th className="text-left px-4 py-2 font-medium text-neutral-600 text-xs uppercase">Invoice Created</th>
              </tr>
            </thead>
            <tbody>
              {receipts.map((gr) => (
                <tr key={gr.id} className="border-b border-neutral-100 last:border-0 hover:bg-neutral-50">
                  <td className="px-4 py-3 font-mono text-xs text-neutral-700">{gr.gr_reference}</td>
                  <td className="px-4 py-3 text-neutral-700">{gr.purchase_order?.po_reference ?? '—'}</td>
                  <td className="px-4 py-3 text-neutral-600">{gr.received_date}</td>
                  <td className="px-4 py-3">
                    <StatusBadge status={gr.status} />
                  </td>
                  <td className="px-4 py-3">
                    <span className={`text-xs font-medium ${gr.three_way_match_passed ? 'text-emerald-600' : 'text-neutral-400'}`}>
                      {gr.three_way_match_passed ? '✓ Passed' : 'Pending'}
                    </span>
                  </td>
                  <td className="px-4 py-3">
                    <span className={`text-xs font-medium ${gr.ap_invoice_created ? 'text-emerald-600' : 'text-amber-600'}`}>
                      {gr.ap_invoice_created ? '✓ Yes' : 'No'}
                    </span>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </div>
  )
}

function StatusBadge({ status }: { status: string }): React.ReactElement {
  const colors: Record<string, string> = {
    draft: 'bg-neutral-100 text-neutral-600',
    confirmed: 'bg-emerald-100 text-emerald-700',
  }
  return (
    <span className={`px-2 py-0.5 rounded text-xs font-medium ${colors[status] ?? 'bg-neutral-100 text-neutral-600'}`}>
      {status}
    </span>
  )
}
