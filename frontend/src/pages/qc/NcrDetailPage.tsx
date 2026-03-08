import { useState } from 'react'
import { useNavigate, useParams } from 'react-router-dom'
import { ArrowLeft, AlertOctagon, AlertTriangle } from 'lucide-react'
import { toast } from 'sonner'
import { useNcr, useIssueCapa, useCloseNcr, useCompleteCapaAction } from '@/hooks/useQC'
import { usePermission } from '@/hooks/usePermission'
import SkeletonLoader from '@/components/ui/SkeletonLoader'
import type { NcrSeverity, NcrStatus, CapaStatus } from '@/types/qc'

const severityBadge: Record<NcrSeverity, string> = {
  minor:    'bg-neutral-100 text-neutral-700',
  major:    'bg-neutral-100 text-neutral-700',
  critical: 'bg-neutral-100 text-neutral-700',
}

const statusBadge: Record<NcrStatus, string> = {
  open:          'bg-neutral-100 text-neutral-600',
  under_review:  'bg-neutral-100 text-neutral-700',
  capa_issued:   'bg-neutral-100 text-neutral-700',
  closed:        'bg-neutral-100 text-neutral-700',
  voided:        'bg-neutral-100 text-neutral-400',
}

const capaStatusBadge: Record<CapaStatus, string> = {
  open:        'bg-neutral-100 text-neutral-500',
  in_progress: 'bg-neutral-100 text-neutral-700',
  completed:   'bg-neutral-100 text-neutral-700',
  verified:    'bg-neutral-100 text-neutral-700',
}

function InfoRow({ label, value }: { label: string; value: React.ReactNode }) {
  return (
    <div className="flex items-start gap-4 py-2 border-b border-neutral-100 last:border-0">
      <dt className="text-sm text-neutral-500 w-36 flex-shrink-0">{label}</dt>
      <dd className="text-sm text-neutral-900 font-medium">{value ?? '—'}</dd>
    </div>
  )
}

export default function NcrDetailPage(): React.ReactElement {
  const { ulid }    = useParams<{ ulid: string }>()
  const navigate    = useNavigate()
  const [showCapaForm, setShowCapaForm] = useState(false)
  const [capaData, setCapaData]         = useState({
    type:           'corrective' as 'corrective' | 'preventive',
    description:    '',
    due_date:       '',
    assigned_to_id: '',
  })

  const { data: ncr, isLoading, isError } = useNcr(ulid ?? null)
  const canCreate = usePermission('qc.ncr.create')
  const canClose  = usePermission('qc.ncr.close')

  const issueCapaMut   = useIssueCapa(ulid ?? '')
  const closeNcrMut    = useCloseNcr(ulid ?? '')
  const completeCapa   = useCompleteCapaAction()

  const handleIssueCapa = async () => {
    try {
      await issueCapaMut.mutateAsync({
        type:           capaData.type,
        description:    capaData.description,
        due_date:       capaData.due_date,
        assigned_to_id: capaData.assigned_to_id ? parseInt(capaData.assigned_to_id) : undefined,
      })
      toast.success('CAPA action issued.')
      setShowCapaForm(false)
    } catch {
      toast.error('Failed to issue CAPA action.')
    }
  }

  const handleClose = async () => {
    try {
      await closeNcrMut.mutateAsync()
      toast.success('NCR closed.')
    } catch {
      toast.error('Failed to close NCR.')
    }
  }

  if (isLoading) return <SkeletonLoader rows={8} />
  if (isError || !ncr) return (
    <div className="flex items-center gap-2 text-red-600 text-sm">
      <AlertTriangle className="w-4 h-4" /> Failed to load NCR.
    </div>
  )

  return (
    <div className="max-w-3xl">
      <div className="flex items-center gap-3 mb-6">
        <button onClick={() => navigate('/qc/ncrs')} className="p-2 hover:bg-neutral-100 rounded-lg">
          <ArrowLeft className="w-4 h-4 text-neutral-500" />
        </button>
        <div className="w-10 h-10 bg-neutral-100 rounded-lg flex items-center justify-center">
          <AlertOctagon className="w-5 h-5 text-neutral-600" />
        </div>
        <div className="flex items-center gap-3">
          <h1 className="text-lg font-semibold text-neutral-900 font-mono">{ncr.ncr_reference}</h1>
          <span className={`inline-flex px-2.5 py-1 rounded text-xs font-medium capitalize ${severityBadge[ncr.severity]}`}>{ncr.severity}</span>
          <span className={`inline-flex px-2.5 py-1 rounded text-xs font-medium capitalize ${statusBadge[ncr.status]}`}>{ncr.status.replace('_', ' ')}</span>
        </div>
      </div>

      {/* Details */}
      <div className="bg-white border border-neutral-200 rounded-lg p-6 mb-5">
        <h2 className="text-sm font-medium text-neutral-900 mb-3">NCR Details</h2>
        <dl>
          <InfoRow label="Title"       value={ncr.title} />
          <InfoRow label="Description" value={<p className="text-sm text-neutral-700 whitespace-pre-wrap">{ncr.description}</p>} />
          <InfoRow label="Related Inspection" value={
            ncr.inspection
              ? <span className="font-mono text-sm">{ncr.inspection.inspection_reference} ({ncr.inspection.stage.toUpperCase()})</span>
              : null
          } />
          <InfoRow label="Item"        value={ncr.inspection?.item_master?.name} />
          <InfoRow label="Raised By"   value={ncr.raised_by?.name} />
          {ncr.closed_at && <InfoRow label="Closed At" value={new Date(ncr.closed_at).toLocaleDateString('en-PH')} />}
        </dl>
      </div>

      {/* CAPA Actions */}
      {(ncr.capa_actions ?? []).length > 0 && (
        <div className="bg-white border border-neutral-200 rounded-lg p-6 mb-5">
          <h2 className="text-sm font-medium text-neutral-900 mb-3">CAPA Actions</h2>
          {ncr.capa_actions?.map((capa) => (
            <div key={capa.id} className="py-3 border-b border-neutral-100 last:border-0">
              <div className="flex items-start justify-between">
                <div>
                  <span className={`inline-flex px-2 py-0.5 rounded text-xs font-medium capitalize mr-2 ${capa.type === 'corrective' ? 'bg-neutral-100 text-neutral-700' : 'bg-neutral-100 text-neutral-700'}`}>
                    {capa.type}
                  </span>
                  <span className="text-sm font-medium text-neutral-800">{capa.description}</span>
                </div>
                <span className={`inline-flex px-2 py-0.5 rounded text-xs font-medium capitalize ${capaStatusBadge[capa.status]}`}>
                  {capa.status.replace('_', ' ')}
                </span>
              </div>
              <div className="mt-1 flex items-center gap-4 text-xs text-neutral-400">
                <span>Due: {capa.due_date}</span>
                {capa.assigned_to && <span>Assigned: {capa.assigned_to.name}</span>}
                {canCreate && capa.status === 'open' && (
                  <button
                    onClick={() => completeCapa.mutate(capa.id)}
                    disabled={completeCapa.isPending}
                    className="ml-2 text-neutral-600 hover:text-neutral-800 font-medium"
                  >
                    Mark Complete
                  </button>
                )}
              </div>
            </div>
          ))}
        </div>
      )}

      {/* Actions */}
      <div className="bg-white border border-neutral-200 rounded-lg p-6">
        <h2 className="text-sm font-medium text-neutral-900 mb-4">Actions</h2>

        {showCapaForm && (
          <div className="bg-neutral-50 border border-neutral-200 rounded-lg p-4 mb-4 space-y-3">
            <h3 className="text-sm font-medium text-neutral-700">Issue CAPA Action</h3>
            <div className="grid grid-cols-2 gap-3">
              <div>
                <label className="block text-xs font-medium text-neutral-600 mb-1">Type</label>
                <select
                  value={capaData.type}
                  onChange={(e) => setCapaData((d) => ({ ...d, type: e.target.value as 'corrective' | 'preventive' }))}
                  className="w-full text-sm border border-neutral-300 rounded px-3 py-2 focus:outline-none focus:ring-1 focus:ring-neutral-400"
                >
                  <option value="corrective">Corrective</option>
                  <option value="preventive">Preventive</option>
                </select>
              </div>
              <div>
                <label className="block text-xs font-medium text-neutral-600 mb-1">Due Date</label>
                <input
                  type="date"
                  value={capaData.due_date}
                  onChange={(e) => setCapaData((d) => ({ ...d, due_date: e.target.value }))}
                  className="w-full text-sm border border-neutral-300 rounded px-3 py-2 focus:outline-none focus:ring-1 focus:ring-neutral-400"
                />
              </div>
              <div className="col-span-2">
                <label className="block text-xs font-medium text-neutral-600 mb-1">Description</label>
                <textarea
                  rows={3}
                  value={capaData.description}
                  onChange={(e) => setCapaData((d) => ({ ...d, description: e.target.value }))}
                  className="w-full text-sm border border-neutral-300 rounded px-3 py-2 focus:outline-none focus:ring-1 focus:ring-neutral-400 resize-none"
                />
              </div>
              <div>
                <label className="block text-xs font-medium text-neutral-600 mb-1">Assign To (User ID)</label>
                <input
                  type="number"
                  value={capaData.assigned_to_id}
                  onChange={(e) => setCapaData((d) => ({ ...d, assigned_to_id: e.target.value }))}
                  className="w-full text-sm border border-neutral-300 rounded px-3 py-2 focus:outline-none focus:ring-1 focus:ring-neutral-400"
                />
              </div>
            </div>
            <div className="flex gap-2">
              <button
                onClick={handleIssueCapa}
                disabled={issueCapaMut.isPending || !capaData.description || !capaData.due_date}
                className="px-4 py-2 bg-neutral-900 hover:bg-neutral-800 text-white text-sm font-medium rounded disabled:opacity-50"
              >
                Issue CAPA
              </button>
              <button onClick={() => setShowCapaForm(false)} className="px-4 py-2 border border-neutral-300 text-neutral-600 text-sm rounded hover:bg-neutral-50">Cancel</button>
            </div>
          </div>
        )}

        <div className="flex flex-wrap gap-2">
          {['open', 'under_review'].includes(ncr.status) && canCreate && !showCapaForm && (
            <button onClick={() => setShowCapaForm(true)} className="px-4 py-2 text-sm font-medium border border-neutral-300 text-neutral-700 hover:bg-neutral-50 rounded">
              Issue CAPA
            </button>
          )}
          {!['closed', 'voided'].includes(ncr.status) && canClose && (
            <button
              onClick={handleClose}
              disabled={closeNcrMut.isPending}
              className="px-4 py-2 text-sm font-medium bg-neutral-900 hover:bg-neutral-800 text-white rounded disabled:opacity-50"
            >
              Close NCR
            </button>
          )}
        </div>
      </div>
    </div>
  )
}
