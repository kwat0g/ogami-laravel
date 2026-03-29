import { useBlanketPOs } from '@/hooks/useEnhancements'
import { PageHeader } from '@/components/ui/PageHeader'
import { Card, CardBody } from '@/components/ui/Card'

export default function BlanketPurchaseOrdersPage() {
  const { data, isLoading } = useBlanketPOs()
  const blanketPOs = data?.data ?? []

  return (
    <div className="space-y-6">
      <PageHeader
        title="Blanket Purchase Orders"
        subtitle="Long-term vendor agreements with committed amounts and release tracking"
      />

      <Card>
        <CardBody className="p-0">
          <table className="min-w-full divide-y divide-neutral-200 dark:divide-neutral-700">
            <thead className="bg-neutral-50 dark:bg-neutral-900">
              <tr>
                <th className="px-4 py-3 text-left text-xs font-medium text-neutral-500 uppercase">Reference</th>
                <th className="px-4 py-3 text-left text-xs font-medium text-neutral-500 uppercase">Vendor</th>
                <th className="px-4 py-3 text-left text-xs font-medium text-neutral-500 uppercase">Period</th>
                <th className="px-4 py-3 text-left text-xs font-medium text-neutral-500 uppercase">Committed</th>
                <th className="px-4 py-3 text-left text-xs font-medium text-neutral-500 uppercase">Released</th>
                <th className="px-4 py-3 text-left text-xs font-medium text-neutral-500 uppercase">Utilization</th>
                <th className="px-4 py-3 text-left text-xs font-medium text-neutral-500 uppercase">Status</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-neutral-200 dark:divide-neutral-700">
              {isLoading ? (
                <tr><td colSpan={7} className="px-4 py-8 text-center text-neutral-500">Loading...</td></tr>
              ) : blanketPOs.length === 0 ? (
                <tr><td colSpan={7} className="px-4 py-8 text-center text-neutral-500">No blanket purchase orders</td></tr>
              ) : blanketPOs.map((bpo: any) => {
                const utilPct = bpo.committed_amount_centavos > 0
                  ? Math.round((bpo.released_amount_centavos / bpo.committed_amount_centavos) * 100)
                  : 0
                return (
                  <tr key={bpo.id}>
                    <td className="px-4 py-3 text-sm font-medium">{bpo.bpo_reference}</td>
                    <td className="px-4 py-3 text-sm">{bpo.vendor?.name ?? '--'}</td>
                    <td className="px-4 py-3 text-sm text-neutral-500">{bpo.start_date} - {bpo.end_date}</td>
                    <td className="px-4 py-3 text-sm font-mono">{(bpo.committed_amount_centavos / 100).toLocaleString('en-PH', { style: 'currency', currency: 'PHP' })}</td>
                    <td className="px-4 py-3 text-sm font-mono">{(bpo.released_amount_centavos / 100).toLocaleString('en-PH', { style: 'currency', currency: 'PHP' })}</td>
                    <td className="px-4 py-3">
                      <div className="flex items-center gap-2">
                        <div className="w-16 bg-neutral-200 rounded-full h-2">
                          <div className={`h-2 rounded-full ${utilPct >= 90 ? 'bg-red-500' : utilPct >= 70 ? 'bg-yellow-500' : 'bg-green-500'}`} style={{ width: `${Math.min(100, utilPct)}%` }} />
                        </div>
                        <span className="text-xs">{utilPct}%</span>
                      </div>
                    </td>
                    <td className="px-4 py-3">
                      <span className={`px-2 py-1 text-xs rounded-full font-medium ${bpo.status === 'active' ? 'bg-green-100 text-green-800' : bpo.status === 'draft' ? 'bg-neutral-100 text-neutral-800' : 'bg-red-100 text-red-800'}`}>
                        {bpo.status}
                      </span>
                    </td>
                  </tr>
                )
              })}
            </tbody>
          </table>
        </CardBody>
      </Card>
    </div>
  )
}
