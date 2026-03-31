import { useParams, useNavigate } from 'react-router-dom'
import { ArrowLeft, FileText, CheckCircle, Clock, CreditCard, AlertTriangle } from 'lucide-react'
import { useVendorInvoiceDetail } from '@/hooks/useVendorPortal'
import { PageHeader } from '@/components/ui/PageHeader'
import { Card, CardHeader, CardBody } from '@/components/ui/Card'
import SkeletonLoader from '@/components/ui/SkeletonLoader'

const STATUS_COLORS: Record<string, string> = {
  draft: 'bg-neutral-100 text-neutral-600',
  submitted: 'bg-blue-100 text-blue-700',
  approved: 'bg-green-100 text-green-700',
  partially_paid: 'bg-emerald-100 text-emerald-700',
  paid: 'bg-teal-100 text-teal-700',
  rejected: 'bg-red-100 text-red-700',
  cancelled: 'bg-neutral-100 text-neutral-400',
}

const STATUS_LABELS: Record<string, string> = {
  draft: 'Draft',
  submitted: 'Under Review',
  approved: 'Approved',
  partially_paid: 'Partially Paid',
  paid: 'Fully Paid',
  rejected: 'Rejected',
  cancelled: 'Cancelled',
}

export default function VendorInvoiceDetailPage(): React.ReactElement {
  const { id } = useParams<{ id: string }>()
  const navigate = useNavigate()
  const invoiceId = id ? parseInt(id) : null
  const { data, isLoading, isError } = useVendorInvoiceDetail(invoiceId)

  if (isLoading) return <SkeletonLoader rows={6} />

  if (isError || !data?.data) {
    return (
      <div className="text-center py-16">
        <AlertTriangle className="w-12 h-12 text-red-400 mx-auto mb-4" />
        <h3 className="text-lg font-medium text-neutral-900">Invoice not found</h3>
        <button
          onClick={() => navigate('/vendor-portal/invoices')}
          className="mt-4 inline-flex items-center gap-2 px-4 py-2 bg-neutral-900 text-white rounded-lg text-sm"
        >
          <ArrowLeft className="w-4 h-4" /> Back to Invoices
        </button>
      </div>
    )
  }

  const inv = data.data
  const formatCurrency = (v: number | string) => `₱${Number(v).toLocaleString('en-PH', { minimumFractionDigits: 2 })}`

  return (
    <div className="space-y-5 max-w-5xl mx-auto">
      <button
        onClick={() => navigate('/vendor-portal/invoices')}
        className="inline-flex items-center gap-2 text-sm text-neutral-600 hover:text-neutral-900"
      >
        <ArrowLeft className="h-4 w-4" /> Back to Invoices
      </button>

      <PageHeader
        title={inv.invoice_number ?? 'Draft Invoice'}
        subtitle={`Submitted ${new Date(inv.created_at).toLocaleDateString('en-PH')}`}
        icon={<FileText className="w-5 h-5" />}
        status={
          <span className={`px-2.5 py-1 rounded text-xs font-medium ${STATUS_COLORS[inv.status] ?? 'bg-neutral-100 text-neutral-600'}`}>
            {STATUS_LABELS[inv.status] ?? inv.status}
          </span>
        }
      />

      {/* Status Timeline */}
      <div className="bg-white border border-neutral-200 rounded-lg p-4">
        <div className="flex items-center justify-between">
          {['draft', 'submitted', 'approved', 'paid'].map((step, i) => {
            const stepOrder = ['draft', 'submitted', 'approved', 'partially_paid', 'paid']
            const currentIdx = stepOrder.indexOf(inv.status)
            const stepIdx = stepOrder.indexOf(step)
            const isCompleted = stepIdx <= currentIdx
            const isCurrent = step === inv.status || (step === 'paid' && inv.status === 'partially_paid')

            return (
              <div key={step} className="flex items-center flex-1">
                <div className="flex flex-col items-center">
                  <div className={`w-8 h-8 rounded-full flex items-center justify-center ${
                    isCompleted ? 'bg-green-100 text-green-600' :
                    isCurrent ? 'bg-blue-100 text-blue-600' :
                    'bg-neutral-100 text-neutral-400'
                  }`}>
                    {isCompleted ? <CheckCircle className="w-4 h-4" /> : <Clock className="w-4 h-4" />}
                  </div>
                  <p className={`text-xs mt-1 ${isCompleted ? 'text-green-700 font-medium' : 'text-neutral-400'}`}>
                    {step === 'draft' ? 'Submitted' : step === 'submitted' ? 'Reviewing' : step === 'approved' ? 'Approved' : 'Paid'}
                  </p>
                </div>
                {i < 3 && (
                  <div className={`flex-1 h-0.5 mx-2 ${stepIdx < currentIdx ? 'bg-green-300' : 'bg-neutral-200'}`} />
                )}
              </div>
            )
          })}
        </div>
      </div>

      {/* Amount Summary */}
      <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
        <Card>
          <CardBody className="py-3">
            <p className="text-xs text-neutral-500 uppercase">Net Amount</p>
            <p className="text-lg font-semibold text-neutral-900 mt-1">{formatCurrency(inv.net_amount)}</p>
          </CardBody>
        </Card>
        <Card>
          <CardBody className="py-3">
            <p className="text-xs text-neutral-500 uppercase">VAT</p>
            <p className="text-lg font-semibold text-neutral-900 mt-1">{formatCurrency(inv.vat_amount)}</p>
          </CardBody>
        </Card>
        <Card>
          <CardBody className="py-3">
            <p className="text-xs text-neutral-500 uppercase">Total Paid</p>
            <p className="text-lg font-semibold text-green-600 mt-1">{formatCurrency(inv.total_paid)}</p>
          </CardBody>
        </Card>
        <Card>
          <CardBody className="py-3">
            <p className="text-xs text-neutral-500 uppercase">Balance Due</p>
            <p className={`text-lg font-semibold mt-1 ${inv.balance_due > 0 ? 'text-amber-600' : 'text-green-600'}`}>
              {formatCurrency(inv.balance_due)}
            </p>
          </CardBody>
        </Card>
      </div>

      {/* Invoice Details */}
      <Card>
        <CardHeader>Invoice Details</CardHeader>
        <CardBody>
          <div className="grid sm:grid-cols-2 gap-4 text-sm">
            <div>
              <p className="text-xs text-neutral-500 uppercase mb-1">Invoice Date</p>
              <p className="text-neutral-900">{inv.invoice_date}</p>
            </div>
            <div>
              <p className="text-xs text-neutral-500 uppercase mb-1">Due Date</p>
              <p className="text-neutral-900">{inv.due_date}</p>
            </div>
            {inv.or_number && (
              <div>
                <p className="text-xs text-neutral-500 uppercase mb-1">OR Number</p>
                <p className="text-neutral-900 font-mono">{inv.or_number}</p>
              </div>
            )}
            {inv.ewt_amount && Number(inv.ewt_amount) > 0 && (
              <div>
                <p className="text-xs text-neutral-500 uppercase mb-1">EWT Withheld</p>
                <p className="text-neutral-900">{formatCurrency(inv.ewt_amount)}</p>
              </div>
            )}
            {inv.description && (
              <div className="sm:col-span-2">
                <p className="text-xs text-neutral-500 uppercase mb-1">Description</p>
                <p className="text-neutral-700">{inv.description}</p>
              </div>
            )}
            {inv.approved_at && (
              <div>
                <p className="text-xs text-neutral-500 uppercase mb-1">Approved On</p>
                <p className="text-neutral-900">{new Date(inv.approved_at).toLocaleDateString('en-PH')}</p>
              </div>
            )}
          </div>
        </CardBody>
      </Card>

      {/* Payment History */}
      {inv.payments && inv.payments.length > 0 && (
        <Card>
          <CardHeader>
            <span className="flex items-center gap-2">
              <CreditCard className="h-4 w-4 text-neutral-500" />
              Payment History
            </span>
          </CardHeader>
          <CardBody className="p-0">
            <table className="w-full text-sm">
              <thead className="bg-neutral-50 text-xs text-neutral-500 uppercase">
                <tr>
                  <th className="px-4 py-3 text-left">Date</th>
                  <th className="px-4 py-3 text-right">Amount</th>
                  <th className="px-4 py-3 text-left">Reference</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-neutral-100">
                {inv.payments.map((p) => (
                  <tr key={p.id} className="hover:bg-neutral-50">
                    <td className="px-4 py-3 text-neutral-900">
                      {new Date(p.payment_date).toLocaleDateString('en-PH')}
                    </td>
                    <td className="px-4 py-3 text-right font-medium text-green-600">
                      {formatCurrency(p.amount)}
                    </td>
                    <td className="px-4 py-3 text-neutral-500 font-mono text-xs">
                      {p.reference ?? '-'}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </CardBody>
        </Card>
      )}

      {/* No payments yet */}
      {(!inv.payments || inv.payments.length === 0) && inv.status === 'approved' && (
        <div className="bg-blue-50 border border-blue-200 rounded-lg p-4 flex items-start gap-3">
          <Clock className="w-5 h-5 text-blue-600 mt-0.5" />
          <div>
            <p className="font-medium text-blue-900">Invoice Approved -- Awaiting Payment</p>
            <p className="text-sm text-blue-700 mt-1">
              Your invoice has been approved. Payment will be processed according to the agreed terms.
              You will see payment details here once disbursed.
            </p>
          </div>
        </div>
      )}
    </div>
  )
}
