import { useParams, useNavigate } from 'react-router-dom'
import { ArrowLeft, CheckCircle, XCircle } from 'lucide-react'
import { useOpportunity } from '@/hooks/useCRM'
import { useMutation, useQueryClient } from '@tanstack/react-query'
import api from '@/lib/api'
import { PageHeader } from '@/components/ui/PageHeader'
import { Card } from '@/components/ui/Card'
import StatusBadge from '@/components/ui/StatusBadge'
import SkeletonLoader from '@/components/ui/SkeletonLoader'

function fmt(c: number) { return new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' }).format(c / 100) }

export default function OpportunityDetailPage() {
  const { ulid } = useParams<{ ulid: string }>()
  const nav = useNavigate()
  const qc = useQueryClient()
  const { data: opp, isLoading } = useOpportunity(ulid ?? '')

  const closeWon = useMutation({
    mutationFn: async () => { const { data } = await api.patch(`/crm/opportunities/${ulid}/close-won`); return data.data },
    onSuccess: () => { qc.invalidateQueries({ queryKey: ['crm-opportunity', ulid] }) },
  })
  const closeLost = useMutation({
    mutationFn: async (reason: string) => { const { data } = await api.patch(`/crm/opportunities/${ulid}/close-lost`, { reason }); return data.data },
    onSuccess: () => { qc.invalidateQueries({ queryKey: ['crm-opportunity', ulid] }) },
  })

  if (isLoading) return <SkeletonLoader rows={6} />
  if (!opp) return <div className="p-6 text-neutral-500">Opportunity not found</div>

  const isClosed = ['closed_won', 'closed_lost'].includes(opp.stage)

  return (
    <div className="space-y-6">
      <PageHeader
        title={opp.title}
        icon={<button onClick={() => nav('/crm/opportunities')} className="p-1 hover:bg-neutral-100 rounded"><ArrowLeft className="w-5 h-5" /></button>}
        actions={!isClosed ? (
          <div className="flex gap-2">
            <button className="btn-primary" onClick={() => closeWon.mutate()} disabled={closeWon.isPending}>
              <CheckCircle className="w-4 h-4" /> Mark as Won
            </button>
            <button className="btn-danger" onClick={() => { const r = prompt('Loss reason:'); if (r) closeLost.mutate(r) }} disabled={closeLost.isPending}>
              <XCircle className="w-4 h-4" /> Mark as Lost
            </button>
          </div>
        ) : undefined}
      />
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <Card className="p-6 space-y-3">
          <h3 className="font-semibold">Details</h3>
          <div className="grid grid-cols-2 gap-3 text-sm">
            <div><span className="text-neutral-500">Customer</span><p className="font-medium">{opp.customer?.name ?? '-'}</p></div>
            <div><span className="text-neutral-500">Stage</span><p><StatusBadge status={opp.stage} /></p></div>
            <div><span className="text-neutral-500">Expected Value</span><p className="font-mono">{fmt(opp.expected_value_centavos)}</p></div>
            <div><span className="text-neutral-500">Probability</span><p>{opp.probability_pct}%</p></div>
            <div><span className="text-neutral-500">Weighted Value</span><p className="font-mono">{fmt(Math.round(opp.expected_value_centavos * opp.probability_pct / 100))}</p></div>
            <div><span className="text-neutral-500">Expected Close</span><p>{opp.expected_close_date ? new Date(opp.expected_close_date).toLocaleDateString() : '-'}</p></div>
            <div><span className="text-neutral-500">Assigned To</span><p>{opp.assignedTo?.name ?? '-'}</p></div>
            <div><span className="text-neutral-500">Created</span><p>{new Date(opp.created_at).toLocaleDateString()}</p></div>
          </div>
          {opp.notes && <div><span className="text-neutral-500 text-sm">Notes</span><p className="text-sm mt-1 whitespace-pre-wrap">{opp.notes}</p></div>}
          {opp.loss_reason && <div className="p-3 bg-red-50 rounded text-sm"><span className="font-medium text-red-700">Loss Reason:</span> {opp.loss_reason}</div>}
        </Card>
      </div>
    </div>
  )
}
