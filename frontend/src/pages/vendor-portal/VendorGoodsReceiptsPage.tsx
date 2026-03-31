import { useNavigate } from 'react-router-dom'
import { ClipboardCheck, ChevronRight } from 'lucide-react'
import { useVendorGoodsReceipts, type VendorPortalGoodsReceipt } from '@/hooks/useVendorPortal'
import { PageHeader } from '@/components/ui/PageHeader'
import { Card } from '@/components/ui/Card'
import SkeletonLoader from '@/components/ui/SkeletonLoader'
import { statusBadges } from '@/styles/design-system'

const STATUS_COLORS: Record<string, string> = {
  draft: statusBadges.draft,
  submitted: statusBadges.sent,
  pending_qc: statusBadges.pending,
  qc_passed: statusBadges.passed,
  qc_failed: statusBadges.failed,
  confirmed: statusBadges.approved,
  returned: statusBadges.rejected,
  cancelled: statusBadges.cancelled,
}

const STATUS_LABELS: Record<string, string> = {
  draft: 'Draft',
  submitted: 'Submitted',
  pending_qc: 'QC Pending',
  qc_passed: 'QC Passed',
  qc_failed: 'QC Failed',
  confirmed: 'Confirmed',
  returned: 'Returned',
  cancelled: 'Cancelled',
}

export default function VendorGoodsReceiptsPage(): React.ReactElement {
  const navigate = useNavigate()
  const { data, isLoading } = useVendorGoodsReceipts()

  const receipts: VendorPortalGoodsReceipt[] = data?.data ?? []

  return (
    <div className="space-y-5">
      <PageHeader
        title="Goods Receipts"
        subtitle="View delivery receipts for your purchase orders"
        icon={<ClipboardCheck className="h-5 w-5 text-neutral-600" />}
      />

      {isLoading ? (
        <SkeletonLoader rows={5} />
      ) : receipts.length === 0 ? (
        <div className="text-center py-16">
          <div className="w-16 h-16 bg-neutral-100 rounded-full flex items-center justify-center mx-auto mb-4">
            <ClipboardCheck className="h-8 w-8 text-neutral-400" />
          </div>
          <h3 className="text-base font-medium text-neutral-900 mb-1">No goods receipts found</h3>
          <p className="text-sm text-neutral-500 max-w-sm mx-auto">
            Goods receipts will appear here after deliveries are confirmed.
          </p>
        </div>
      ) : (
        <Card className="overflow-hidden">
          <table className="w-full text-sm">
            <thead className="bg-neutral-50 border-b border-neutral-200">
              <tr>
                <th className="text-left px-4 py-3 text-xs font-semibold text-neutral-600 uppercase tracking-wider">GR Reference</th>
                <th className="text-left px-4 py-3 text-xs font-semibold text-neutral-600 uppercase tracking-wider">PO Reference</th>
                <th className="text-left px-4 py-3 text-xs font-semibold text-neutral-600 uppercase tracking-wider">Received Date</th>
                <th className="text-left px-4 py-3 text-xs font-semibold text-neutral-600 uppercase tracking-wider">Status</th>
                <th className="text-left px-4 py-3 text-xs font-semibold text-neutral-600 uppercase tracking-wider">3-Way Match</th>
                <th className="text-left px-4 py-3 text-xs font-semibold text-neutral-600 uppercase tracking-wider">Invoice Created</th>
                <th className="px-4 py-3 w-10"></th>
              </tr>
            </thead>
            <tbody className="divide-y divide-neutral-100">
              {receipts.map((gr) => (
                <tr
                  key={gr.id}
                  className="hover:bg-neutral-50 cursor-pointer transition-colors"
                  onClick={() => navigate(`/vendor-portal/goods-receipts/${gr.id}`)}
                >
                  <td className="px-4 py-3 font-mono text-xs font-medium text-neutral-800">{gr.gr_reference}</td>
                  <td className="px-4 py-3 text-neutral-700">{gr.purchase_order?.po_reference ?? '\u2014'}</td>
                  <td className="px-4 py-3 text-neutral-600">{gr.received_date}</td>
                  <td className="px-4 py-3">
                    <span className={`inline-flex items-center px-2 py-0.5 rounded text-xs font-medium ${STATUS_COLORS[gr.status] ?? statusBadges.draft}`}>
                      {STATUS_LABELS[gr.status] ?? gr.status}
                    </span>
                  </td>
                  <td className="px-4 py-3">
                    <span className={`inline-flex items-center gap-1 text-xs font-medium ${gr.three_way_match_passed ? 'text-emerald-700' : 'text-neutral-400'}`}>
                      {gr.three_way_match_passed ? '\u2713 Passed' : 'Pending'}
                    </span>
                  </td>
                  <td className="px-4 py-3">
                    <span className={`inline-flex items-center gap-1 text-xs font-medium ${gr.ap_invoice_created ? 'text-emerald-700' : 'text-amber-600'}`}>
                      {gr.ap_invoice_created ? '\u2713 Yes' : 'No'}
                    </span>
                  </td>
                  <td className="px-4 py-3 text-right">
                    <ChevronRight className="h-4 w-4 text-neutral-400" />
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </Card>
      )}
    </div>
  )
}
