import { useParams, useNavigate } from 'react-router-dom'
import { ArrowLeft, Package, CheckCircle, XCircle, Clock, AlertTriangle } from 'lucide-react'
import { useVendorGoodsReceiptDetail } from '@/hooks/useVendorPortal'
import { PageHeader } from '@/components/ui/PageHeader'
import { Card, CardHeader, CardBody } from '@/components/ui/Card'
import SkeletonLoader from '@/components/ui/SkeletonLoader'

const STATUS_COLORS: Record<string, string> = {
  draft: 'bg-neutral-100 text-neutral-600',
  submitted: 'bg-blue-100 text-blue-700',
  pending_qc: 'bg-amber-100 text-amber-700',
  qc_passed: 'bg-green-100 text-green-700',
  qc_failed: 'bg-red-100 text-red-700',
  confirmed: 'bg-emerald-100 text-emerald-700',
  returned: 'bg-red-100 text-red-600',
  cancelled: 'bg-neutral-100 text-neutral-400',
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

const QC_ITEM_COLORS: Record<string, string> = {
  pending: 'bg-neutral-100 text-neutral-600',
  passed: 'bg-green-100 text-green-700',
  failed: 'bg-red-100 text-red-700',
  accepted_with_ncr: 'bg-amber-100 text-amber-700',
}

const QC_ITEM_ICONS: Record<string, typeof CheckCircle> = {
  pending: Clock,
  passed: CheckCircle,
  failed: XCircle,
  accepted_with_ncr: AlertTriangle,
}

export default function VendorGoodsReceiptDetailPage(): React.ReactElement {
  const { id } = useParams<{ id: string }>()
  const navigate = useNavigate()
  const grId = id ? parseInt(id) : null
  const { data, isLoading, isError } = useVendorGoodsReceiptDetail(grId)

  if (isLoading) return <SkeletonLoader rows={6} />

  if (isError || !data?.data) {
    return (
      <div className="text-center py-16">
        <AlertTriangle className="w-12 h-12 text-red-400 mx-auto mb-4" />
        <h3 className="text-lg font-medium text-neutral-900">Goods Receipt not found</h3>
        <button
          onClick={() => navigate('/vendor-portal/goods-receipts')}
          className="mt-4 inline-flex items-center gap-2 px-4 py-2 bg-neutral-900 text-white rounded-lg text-sm"
        >
          <ArrowLeft className="w-4 h-4" /> Back to Goods Receipts
        </button>
      </div>
    )
  }

  const gr = data.data
  const status = gr.status

  return (
    <div className="space-y-5 max-w-5xl mx-auto">
      <button
        onClick={() => navigate('/vendor-portal/goods-receipts')}
        className="inline-flex items-center gap-2 text-sm text-neutral-600 hover:text-neutral-900"
      >
        <ArrowLeft className="h-4 w-4" /> Back to Goods Receipts
      </button>

      <PageHeader
        title={gr.gr_reference}
        subtitle={gr.purchase_order ? `PO: ${gr.purchase_order.po_reference}` : undefined}
        icon={<Package className="w-5 h-5" />}
        status={
          <span className={`px-2.5 py-1 rounded text-xs font-medium ${STATUS_COLORS[status] ?? 'bg-neutral-100 text-neutral-600'}`}>
            {STATUS_LABELS[status] ?? status}
          </span>
        }
      />

      {/* Summary Cards */}
      <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
        <Card>
          <CardBody className="py-3">
            <p className="text-xs text-neutral-500 uppercase">Received Date</p>
            <p className="font-medium text-neutral-900 mt-1">{gr.received_date ?? '-'}</p>
          </CardBody>
        </Card>
        <Card>
          <CardBody className="py-3">
            <p className="text-xs text-neutral-500 uppercase">3-Way Match</p>
            <p className={`font-medium mt-1 ${gr.three_way_match_passed ? 'text-green-600' : 'text-neutral-400'}`}>
              {gr.three_way_match_passed ? 'Passed' : 'Pending'}
            </p>
          </CardBody>
        </Card>
        <Card>
          <CardBody className="py-3">
            <p className="text-xs text-neutral-500 uppercase">Invoice Created</p>
            <p className={`font-medium mt-1 ${gr.ap_invoice_created ? 'text-green-600' : 'text-amber-600'}`}>
              {gr.ap_invoice_created ? 'Yes' : 'Not Yet'}
            </p>
          </CardBody>
        </Card>
        <Card>
          <CardBody className="py-3">
            <p className="text-xs text-neutral-500 uppercase">Confirmed At</p>
            <p className="font-medium text-neutral-900 mt-1">
              {gr.confirmed_at ? new Date(gr.confirmed_at).toLocaleDateString('en-PH') : '-'}
            </p>
          </CardBody>
        </Card>
      </div>

      {/* QC Status Alert */}
      {status === 'qc_failed' && (
        <div className="bg-red-50 border border-red-200 rounded-lg p-4 flex items-start gap-3">
          <XCircle className="w-5 h-5 text-red-600 mt-0.5" />
          <div>
            <p className="font-medium text-red-900">Quality Check Failed</p>
            <p className="text-sm text-red-700 mt-1">
              One or more items in this delivery failed quality inspection. See the item details below for specifics.
              Please contact the purchasing department if you need to arrange replacement or rework.
            </p>
          </div>
        </div>
      )}

      {status === 'pending_qc' && (
        <div className="bg-amber-50 border border-amber-200 rounded-lg p-4 flex items-start gap-3">
          <Clock className="w-5 h-5 text-amber-600 mt-0.5" />
          <div>
            <p className="font-medium text-amber-900">Quality Inspection In Progress</p>
            <p className="text-sm text-amber-700 mt-1">
              Your delivered goods are being inspected by our QC team. You will be able to see the results here once inspection is complete.
            </p>
          </div>
        </div>
      )}

      {status === 'confirmed' && !gr.ap_invoice_created && (
        <div className="bg-green-50 border border-green-200 rounded-lg p-4 flex items-start gap-3">
          <CheckCircle className="w-5 h-5 text-green-600 mt-0.5" />
          <div>
            <p className="font-medium text-green-900">Goods Receipt Confirmed</p>
            <p className="text-sm text-green-700 mt-1">
              Your delivery has been confirmed and accepted. You can now submit your invoice from the Invoices page.
            </p>
          </div>
        </div>
      )}

      {/* Items with QC Results */}
      <Card>
        <CardHeader>
          <span className="flex items-center gap-2">
            <Package className="h-4 w-4 text-neutral-500" />
            Items &amp; QC Results
          </span>
        </CardHeader>
        <CardBody className="p-0">
          <table className="w-full text-sm">
            <thead className="bg-neutral-50 text-xs text-neutral-500 uppercase tracking-wide">
              <tr>
                <th className="px-4 py-3 text-left">Item</th>
                <th className="px-4 py-3 text-right">Ordered</th>
                <th className="px-4 py-3 text-right">Received</th>
                <th className="px-4 py-3 text-right">Accepted</th>
                <th className="px-4 py-3 text-right">Rejected</th>
                <th className="px-4 py-3 text-left">Condition</th>
                <th className="px-4 py-3 text-left">QC Status</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-neutral-100">
              {(gr.items ?? []).length === 0 ? (
                <tr>
                  <td colSpan={7} className="px-4 py-8 text-center text-neutral-400">No items.</td>
                </tr>
              ) : (
                gr.items.map((item) => {
                  const QcIcon = QC_ITEM_ICONS[item.qc_status] ?? Clock
                  return (
                    <tr key={item.id} className="hover:bg-neutral-50">
                      <td className="px-4 py-3">
                        <p className="text-neutral-900 font-medium">{item.item_description}</p>
                        {item.unit_of_measure && (
                          <p className="text-xs text-neutral-400">{item.unit_of_measure}</p>
                        )}
                      </td>
                      <td className="px-4 py-3 text-right text-neutral-600">{item.quantity_ordered ?? '-'}</td>
                      <td className="px-4 py-3 text-right font-medium text-neutral-900">{item.quantity_received}</td>
                      <td className="px-4 py-3 text-right text-green-600">{item.quantity_accepted ?? '-'}</td>
                      <td className="px-4 py-3 text-right text-red-600">{item.quantity_rejected ?? '-'}</td>
                      <td className="px-4 py-3">
                        <span className={`px-2 py-0.5 rounded text-xs font-medium ${
                          item.condition === 'good' ? 'bg-green-100 text-green-700' :
                          item.condition === 'damaged' ? 'bg-red-100 text-red-700' :
                          'bg-neutral-100 text-neutral-600'
                        }`}>
                          {item.condition ?? 'pending'}
                        </span>
                      </td>
                      <td className="px-4 py-3">
                        <span className={`inline-flex items-center gap-1 px-2 py-0.5 rounded text-xs font-medium ${QC_ITEM_COLORS[item.qc_status] ?? 'bg-neutral-100 text-neutral-600'}`}>
                          <QcIcon className="w-3 h-3" />
                          {item.qc_status === 'accepted_with_ncr' ? 'NCR' : item.qc_status}
                        </span>
                        {item.qc_notes && (
                          <p className="text-xs text-neutral-500 mt-1">{item.qc_notes}</p>
                        )}
                      </td>
                    </tr>
                  )
                })
              )}
            </tbody>
          </table>
        </CardBody>
      </Card>
    </div>
  )
}
