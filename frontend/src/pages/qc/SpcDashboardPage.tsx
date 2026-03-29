import { useState } from 'react'
import { Activity } from 'lucide-react'
import { useQuery } from '@tanstack/react-query'
import api from '@/lib/api'
import { PageHeader } from '@/components/ui/PageHeader'
import { Card } from '@/components/ui/Card'
import SkeletonLoader from '@/components/ui/SkeletonLoader'

export default function SpcDashboardPage() {
  const [templateId, setTemplateId] = useState('')
  const [parameter, setParameter] = useState('')
  const [usl, setUsl] = useState('')
  const [lsl, setLsl] = useState('')

  const { data: templates } = useQuery({
    queryKey: ['qc-templates'],
    queryFn: async () => { const { data } = await api.get('/qc/templates', { params: { per_page: 200 } }); return data.data ?? data },
  })

  const { data, isLoading, refetch } = useQuery({
    queryKey: ['spc-chart', templateId, parameter, usl, lsl],
    queryFn: async () => {
      const { data } = await api.get('/qc/spc/control-chart', {
        params: { template_id: templateId, parameter, usl: usl || undefined, lsl: lsl || undefined },
      })
      return data.data
    },
    enabled: false,
  })

  return (
    <div className="space-y-6">
      <PageHeader title="SPC Control Charts & Process Capability" icon={<Activity className="w-5 h-5 text-neutral-600" />} />
      <Card className="p-4">
        <div className="flex flex-wrap gap-3">
          <select
            className="border border-neutral-300 rounded px-3 py-1.5 text-sm bg-white focus:outline-none focus:ring-1 focus:ring-neutral-400 w-52"
            value={templateId}
            onChange={e => setTemplateId(e.target.value)}
          >
            <option value="">Select Inspection Template...</option>
            {/* eslint-disable-next-line @typescript-eslint/no-explicit-any */}
            {(templates ?? []).map((t: any) => (
              <option key={t.id} value={t.id}>{t.name ?? t.code ?? `Template #${t.id}`}</option>
            ))}
          </select>
          <input className="input-sm w-48" placeholder="Parameter name" value={parameter} onChange={e => setParameter(e.target.value)} />
          <input className="input-sm w-24" placeholder="USL" type="number" step="any" value={usl} onChange={e => setUsl(e.target.value)} />
          <input className="input-sm w-24" placeholder="LSL" type="number" step="any" value={lsl} onChange={e => setLsl(e.target.value)} />
          <button className="btn-primary" onClick={() => refetch()} disabled={!templateId || !parameter}>Generate Chart</button>
        </div>
      </Card>

      {isLoading && <SkeletonLoader rows={4} />}

      {data && (
        <>
          <div className="grid grid-cols-2 md:grid-cols-5 gap-4">
            <Card className="p-3"><span className="text-xs text-neutral-500">Mean</span><p className="font-mono font-bold">{data.mean}</p></Card>
            <Card className="p-3"><span className="text-xs text-neutral-500">UCL</span><p className="font-mono text-red-600">{data.ucl}</p></Card>
            <Card className="p-3"><span className="text-xs text-neutral-500">LCL</span><p className="font-mono text-red-600">{data.lcl}</p></Card>
            <Card className="p-3"><span className="text-xs text-neutral-500">Cpk</span><p className="font-mono font-bold">{data.cpk ?? 'N/A'}</p></Card>
            <Card className="p-3"><span className="text-xs text-neutral-500">Out of Control</span><p className="font-bold text-red-600">{data.out_of_control_count} / {data.sample_count}</p></Card>
          </div>

          <Card className="p-4">
            <h3 className="font-semibold mb-3">Control Chart Data Points ({data.sample_count} samples)</h3>
            <div className="overflow-x-auto">
              <table className="w-full text-xs">
                <thead>
                  <tr><th className="text-left p-2">Date</th><th className="text-right p-2">Value</th><th className="text-center p-2">Status</th></tr>
                </thead>
                <tbody className="divide-y">
                  {/* eslint-disable-next-line @typescript-eslint/no-explicit-any */}
                  {(data.data_points ?? []).slice(0, 50).map((pt: any, i: number) => (
                    <tr key={i} className={pt.out_of_control ? 'bg-red-50' : ''}>
                      <td className="p-2">{new Date(pt.date).toLocaleString()}</td>
                      <td className="p-2 text-right font-mono">{pt.value}</td>
                      <td className="p-2 text-center">{pt.out_of_control ? <span className="text-red-600 font-bold">OOC</span> : <span className="text-green-600">OK</span>}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </Card>
        </>
      )}
    </div>
  )
}
