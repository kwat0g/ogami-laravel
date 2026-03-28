import { ShieldCheck } from 'lucide-react'
import { useQuery } from '@tanstack/react-query'
import api from '@/lib/api'
import { PageHeader } from '@/components/ui/PageHeader'
import { Card } from '@/components/ui/Card'
import SkeletonLoader from '@/components/ui/SkeletonLoader'

export default function SupplierQualityPage() {
  const { data, isLoading } = useQuery({
    queryKey: ['supplier-quality'],
    queryFn: async () => { const { data } = await api.get('/qc/supplier-quality'); return data.data },
  })

  return (
    <div className="space-y-6">
      <PageHeader title="Supplier Quality Management" icon={<ShieldCheck className="w-5 h-5 text-neutral-600" />} />
      {isLoading ? <SkeletonLoader rows={6} /> : (
        <Card className="overflow-hidden">
          <table className="w-full text-sm">
            <thead className="bg-neutral-50 dark:bg-neutral-800">
              <tr>
                <th className="text-left p-3">Vendor</th><th className="text-right p-3">Inspections</th>
                <th className="text-right p-3">Passed</th><th className="text-right p-3">Failed</th>
                <th className="text-right p-3">Pass Rate</th><th className="text-right p-3">NCRs</th>
                <th className="text-right p-3">Quality Score</th>
              </tr>
            </thead>
            <tbody className="divide-y">
              {(data ?? []).map((v: any) => (
                <tr key={v.vendor_id} className="hover:bg-neutral-50 dark:hover:bg-neutral-800/50">
                  <td className="p-3 font-medium">{v.vendor_name}</td>
                  <td className="p-3 text-right">{v.total_inspections}</td>
                  <td className="p-3 text-right text-green-600">{v.passed}</td>
                  <td className="p-3 text-right text-red-600">{v.failed}</td>
                  <td className="p-3 text-right"><span className={v.pass_rate_pct >= 90 ? 'text-green-600' : 'text-red-600'}>{v.pass_rate_pct}%</span></td>
                  <td className="p-3 text-right">{v.ncr_count}</td>
                  <td className="p-3 text-right font-bold"><span className={v.quality_score >= 80 ? 'text-green-600' : v.quality_score >= 60 ? 'text-yellow-600' : 'text-red-600'}>{v.quality_score}</span></td>
                </tr>
              ))}
            </tbody>
          </table>
        </Card>
      )}
    </div>
  )
}
