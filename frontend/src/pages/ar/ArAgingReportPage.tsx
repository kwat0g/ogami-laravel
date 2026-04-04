import { useState } from 'react'
import { useArAging, useArAgingDetail, type AgingCustomerRow } from '@/hooks/useAnalytics'
import { PageHeader } from '@/components/ui/PageHeader'
import { Card } from '@/components/ui/Card'
import SkeletonLoader from '@/components/ui/SkeletonLoader'
import { FileDown, ChevronDown, ChevronRight } from 'lucide-react'
import { downloadFile } from '@/lib/api'
import { toast } from 'sonner'

function formatPeso(amount: number): string {
  return '₱' + amount.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

function BucketCell({ value }: { value: number }): JSX.Element {
  return (
    <td className={`text-right px-3 py-2 text-sm font-mono ${value > 0 ? 'text-red-700 font-semibold' : 'text-neutral-400'}`}>
      {value > 0 ? formatPeso(value) : '—'}
    </td>
  )
}

function CustomerDetail({ customerId }: { customerId: number }): JSX.Element {
  const { data, isLoading } = useArAgingDetail(customerId)

  if (isLoading) return <tr><td colSpan={8}><SkeletonLoader lines={3} /></td></tr>
  if (!data || data.length === 0) return <tr><td colSpan={8} className="text-center text-neutral-400 py-2 text-sm">No open invoices.</td></tr>

  return (
    <>
      {data.map((inv) => (
        <tr key={inv.invoice_id} className="bg-blue-50/50">
          <td className="px-3 py-1.5 text-xs text-neutral-500 pl-10">{inv.invoice_number ?? '—'}</td>
          <td className="px-3 py-1.5 text-xs">{inv.invoice_date}</td>
          <td className="px-3 py-1.5 text-xs">{inv.due_date}</td>
          <td className="px-3 py-1.5 text-xs">
            <span className={`inline-block px-1.5 py-0.5 rounded text-[10px] font-semibold ${
              inv.days_past_due > 90 ? 'bg-red-100 text-red-700' :
              inv.days_past_due > 30 ? 'bg-amber-100 text-amber-700' :
              'bg-green-100 text-green-700'
            }`}>
              {inv.days_past_due > 0 ? `${inv.days_past_due}d` : 'Current'}
            </span>
          </td>
          <td className="text-right px-3 py-1.5 text-xs font-mono">{formatPeso(inv.total_amount)}</td>
          <td className="text-right px-3 py-1.5 text-xs font-mono">{formatPeso(inv.total_paid)}</td>
          <td className="text-right px-3 py-1.5 text-xs font-mono font-semibold">{formatPeso(inv.balance_due)}</td>
          <td />
        </tr>
      ))}
    </>
  )
}

export default function ArAgingReportPage(): JSX.Element {
  const [asOf, setAsOf] = useState('')
  const [expandedCustomer, setExpandedCustomer] = useState<number | null>(null)

  const filters = asOf ? { as_of: asOf } : {}
  const { data, isLoading, isError } = useArAging(filters)

  function handleDownloadSoa(customerUlid: string) {
    const url = `/api/v1/ar/customers/${customerUlid}/statement/pdf${asOf ? `?as_of=${asOf}` : ''}`
    downloadFile(url, `statement_${customerUlid}.pdf`).catch(() => {
    })
  }

  return (
    <div className="space-y-6">
      <PageHeader title="AR Aging Report" subtitle="Accounts receivable aging analysis by customer" />

      <Card className="p-4">
        <div className="flex items-end gap-4 mb-4">
          <div>
            <label className="text-xs text-neutral-500 block mb-1">As of Date</label>
            <input
              type="date"
              value={asOf}
              onChange={(e) => setAsOf(e.target.value)}
              className="border border-neutral-300 rounded px-3 py-1.5 text-sm"
            />
          </div>
        </div>

        {/* Aging Totals */}
        {data?.totals && (
          <div className="grid grid-cols-6 gap-3 mb-4">
            {[
              { label: 'Current (0-30)', value: data.totals.current, color: 'bg-green-50 text-green-700' },
              { label: '31-60 Days', value: data.totals.bucket_31_60, color: 'bg-blue-50 text-blue-700' },
              { label: '61-90 Days', value: data.totals.bucket_61_90, color: 'bg-amber-50 text-amber-700' },
              { label: '91-120 Days', value: data.totals.bucket_91_120, color: 'bg-orange-50 text-orange-700' },
              { label: '120+ Days', value: data.totals.over_120, color: 'bg-red-50 text-red-700' },
              { label: 'Grand Total', value: data.totals.grand_total, color: 'bg-neutral-100 text-neutral-900' },
            ].map(({ label, value, color }) => (
              <div key={label} className={`rounded-lg p-3 ${color}`}>
                <p className="text-[10px] uppercase tracking-wide opacity-70">{label}</p>
                <p className="text-lg font-bold">{formatPeso(value)}</p>
              </div>
            ))}
          </div>
        )}

        {isLoading && <SkeletonLoader lines={8} />}
        {isError && <p className="text-red-600">Failed to load aging data.</p>}

        {data?.data && data.data.length > 0 && (
          <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead>
                <tr className="border-b border-neutral-200 bg-neutral-50">
                  <th className="text-left px-3 py-2 font-semibold text-neutral-600">Customer</th>
                  <th className="text-right px-3 py-2 font-semibold text-neutral-600">Current</th>
                  <th className="text-right px-3 py-2 font-semibold text-neutral-600">31-60</th>
                  <th className="text-right px-3 py-2 font-semibold text-neutral-600">61-90</th>
                  <th className="text-right px-3 py-2 font-semibold text-neutral-600">91-120</th>
                  <th className="text-right px-3 py-2 font-semibold text-neutral-600">120+</th>
                  <th className="text-right px-3 py-2 font-semibold text-neutral-600">Total</th>
                  <th className="px-3 py-2 w-16" />
                </tr>
              </thead>
              <tbody>
                {data.data.map((row: AgingCustomerRow) => (
                  <>
                    <tr
                      key={row.customer_id}
                      className="border-b border-neutral-100 hover:bg-neutral-50 cursor-pointer"
                      onClick={() => setExpandedCustomer(expandedCustomer === row.customer_id ? null : row.customer_id)}
                    >
                      <td className="px-3 py-2 font-medium text-neutral-800 flex items-center gap-1">
                        {expandedCustomer === row.customer_id
                          ? <ChevronDown className="h-4 w-4 text-neutral-400" />
                          : <ChevronRight className="h-4 w-4 text-neutral-400" />}
                        {row.customer_name}
                      </td>
                      <BucketCell value={row.current} />
                      <BucketCell value={row.bucket_31_60} />
                      <BucketCell value={row.bucket_61_90} />
                      <BucketCell value={row.bucket_91_120} />
                      <BucketCell value={row.over_120} />
                      <td className="text-right px-3 py-2 text-sm font-mono font-bold text-neutral-900">
                        {formatPeso(row.total_outstanding)}
                      </td>
                      <td className="px-3 py-2">
                        {row.customer_ulid && (
                          <button
                            onClick={(e) => { e.stopPropagation(); handleDownloadSoa(row.customer_ulid!) }}
                            className="text-blue-600 hover:text-blue-800"
                            title="Download Statement of Account"
                          >
                            <FileDown className="h-4 w-4" />
                          </button>
                        )}
                      </td>
                    </tr>
                    {expandedCustomer === row.customer_id && (
                      <CustomerDetail customerId={row.customer_id} />
                    )}
                  </>
                ))}
              </tbody>
            </table>
          </div>
        )}

        {data?.data && data.data.length === 0 && (
          <p className="text-center text-neutral-400 py-8">No outstanding invoices found.</p>
        )}
      </Card>
    </div>
  )
}
