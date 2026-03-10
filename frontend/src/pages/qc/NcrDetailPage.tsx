import { useState } from 'react'
import { useNavigate, useParams } from 'react-router-dom'
import { AlertOctagon, AlertTriangle, CheckCircle2, Plus } from 'lucide-react'
import { toast } from 'sonner'
import { useNcr, useIssueCapa, useCloseNcr, useCompleteCapaAction } from '@/hooks/useQC'
import { usePermission } from '@/hooks/usePermission'
import { useEmployees } from '@/hooks/useEmployees'
import SkeletonLoader from '@/components/ui/SkeletonLoader'
import StatusBadge from '@/components/ui/StatusBadge'
import { PageHeader } from '@/components/ui/PageHeader'
import { Card, CardHeader, CardBody } from '@/components/ui/Card'
import { InfoRow, InfoList } from '@/components/ui/InfoRow'
import type { CapaStatus } from '@/types/qc'

const _capaStatusBadge: Record<CapaStatus, string> = {
  open:        'bg-neutral-50 text-neutral-500 border-neutral-200',
  in_progress: 'bg-blue-50 text-blue-700 border-blue-200',
  completed:   'bg-emerald-50 text-emerald-700 border-emerald-200',
  verified:    'bg-purple-50 text-purple-700 border-purple-200',
}

export default function NcrDetailPage(): React.ReactElement {
  const { ulid }    = useParams<{ ulid: string }>()
  const _navigate    = useNavigate()
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

  const { data: employeesData } = useEmployees({ per_page: 200 })
  const activeEmployees = (employeesData?.data ?? []).filter(
    e => e.is_active && e.user_id != null && e.department?.name === 'Quality Control & Assurance'
  )

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
      setCapaData({ type: 'corrective', description: '', due_date: '', assigned_to_id: '' })
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

  const canIssueCapa = canCreate && (ncr.status === 'open' || ncr.status === 'under_review')
  const canCloseNcr = canClose && ncr.status !== 'closed' && ncr.status !== 'voided'

  return (
    <div className="max-w-7xl mx-auto">
      <PageHeader
        title={ncr.ncr_reference}
        subtitle="Non-Conformance Report"
        backTo="/qc/ncrs"
        icon={<AlertOctagon className="w-5 h-5 text-neutral-600" />}
        status={
          <>
            <StatusBadge status={ncr.severity}>{ncr.severity}</StatusBadge>
            <StatusBadge status={ncr.status}>{ncr.status?.replace('_', ' ') || 'Unknown'}</StatusBadge>
          </>
        }
        actions={
          <>
            {canIssueCapa && (
              <button
                onClick={() => setShowCapaForm(true)}
                className="inline-flex items-center gap-2 px-4 py-2 bg-neutral-900 text-white text-sm font-medium rounded-md hover:bg-neutral-800 transition-colors"
              >
                <Plus className="w-4 h-4" />
                Issue CAPA
              </button>
            )}
            {canCloseNcr && (
              <button
                onClick={handleClose}
                disabled={closeNcrMut.isPending}
                className="inline-flex items-center gap-2 px-4 py-2 bg-white text-neutral-700 border border-neutral-300 text-sm font-medium rounded-md hover:bg-neutral-50 hover:border-neutral-400 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
              >
                <CheckCircle2 className="w-4 h-4" />
                Close NCR
              </button>
            )}
          </>
        }
      />

      {/* NCR Details */}
      <Card className="mb-5">
        <CardHeader>NCR Details</CardHeader>
        <CardBody>
          <InfoList>
            <InfoRow label="Title" value={ncr.title} />
            <InfoRow 
              label="Description" 
              value={<p className="text-sm text-neutral-700 whitespace-pre-wrap">{ncr.description}</p>} 
              fullWidth
            />
            <InfoRow 
              label="Related Inspection" 
              value={
                ncr.inspection
                  ? <span className="font-mono text-sm">{ncr.inspection.inspection_reference} ({ncr.inspection.stage.toUpperCase()})</span>
                  : null
              } 
            />
            <InfoRow label="Item" value={ncr.inspection?.item_master?.name} />
            <InfoRow label="Raised By" value={ncr.raised_by?.name} />
            {ncr.closed_at && (
              <InfoRow label="Closed At" value={new Date(ncr.closed_at).toLocaleDateString('en-PH')} />
            )}
          </InfoList>
        </CardBody>
      </Card>

      {/* CAPA Actions */}
      {(ncr.capa_actions ?? []).length > 0 && (
        <Card>
          <CardHeader>CAPA Actions</CardHeader>
          <CardBody>
            <div className="space-y-4">
              {ncr.capa_actions?.map((capa) => (
                <div 
                  key={capa.id} 
                  className="p-4 border border-neutral-200 rounded-lg hover:border-neutral-300 transition-colors"
                >
                  <div className="flex items-start justify-between">
                    <div className="flex-1">
                      <div className="flex items-center gap-3 mb-2">
                        <StatusBadge status={capa.type}>{capa.type?.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase())}</StatusBadge>
                        <span className="text-sm font-medium text-neutral-800">{capa.description}</span>
                      </div>
                      <div className="flex items-center gap-4 text-xs text-neutral-500">
                        <span>Due: {capa.due_date}</span>
                        {capa.assigned_to && <span>Assigned: {capa.assigned_to.name}</span>}
                      </div>
                    </div>
                    <div className="flex items-center gap-3">
                      <StatusBadge status={capa.status}>{capa.status?.replace('_', ' ') || 'Unknown'}</StatusBadge>
                      {canCreate && capa.status === 'open' && (
                        <button
                          onClick={() => completeCapa.mutate(capa.id)}
                          disabled={completeCapa.isPending}
                          className="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium border border-neutral-200 rounded bg-white text-neutral-700 hover:bg-neutral-50 hover:border-neutral-300 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                        >
                          Complete
                        </button>
                      )}
                    </div>
                  </div>
                </div>
              ))}
            </div>
          </CardBody>
        </Card>
      )}

      {/* Issue CAPA Modal */}
      {showCapaForm && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50">
          <div className="bg-white rounded-lg w-full max-w-md max-h-[90vh] overflow-y-auto p-5">
            <h2 className="text-lg font-semibold text-neutral-900 mb-4">Issue CAPA Action</h2>
            
            <div className="space-y-4">
              <div>
                <label className="block text-sm font-medium text-neutral-700 mb-1.5">Type</label>
                <select
                  value={capaData.type}
                  onChange={(e) => setCapaData({ ...capaData, type: e.target.value as 'corrective' | 'preventive' })}
                  className="w-full px-3 py-2 bg-white border border-neutral-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-neutral-200 focus:border-neutral-400"
                >
                  <option value="corrective">Corrective</option>
                  <option value="preventive">Preventive</option>
                </select>
              </div>

              <div>
                <label className="block text-sm font-medium text-neutral-700 mb-1.5">Description *</label>
                <textarea
                  value={capaData.description}
                  onChange={(e) => setCapaData({ ...capaData, description: e.target.value })}
                  className="w-full px-3 py-2 bg-white border border-neutral-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-neutral-200 focus:border-neutral-400 resize-none"
                  rows={3}
                  placeholder="Describe the corrective/preventive action..."
                />
              </div>

              <div>
                <label className="block text-sm font-medium text-neutral-700 mb-1.5">Due Date</label>
                <input
                  type="date"
                  value={capaData.due_date}
                  onChange={(e) => setCapaData({ ...capaData, due_date: e.target.value })}
                  className="w-full px-3 py-2 bg-white border border-neutral-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-neutral-200 focus:border-neutral-400"
                />
              </div>

              <div>
                <label className="block text-sm font-medium text-neutral-700 mb-1.5">Assign To</label>
                <select
                  value={capaData.assigned_to_id}
                  onChange={(e) => setCapaData({ ...capaData, assigned_to_id: e.target.value })}
                  className="w-full px-3 py-2 bg-white border border-neutral-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-neutral-200 focus:border-neutral-400"
                >
                  <option value="">Select employee...</option>
                  {activeEmployees.map((emp) => (
                    <option key={emp.id} value={emp.id}>{emp.full_name}</option>
                  ))}
                </select>
              </div>
            </div>

            <div className="flex justify-end gap-3 mt-6">
              <button
                type="button"
                onClick={() => setShowCapaForm(false)}
                className="px-4 py-2 text-sm font-medium text-neutral-700 border border-neutral-300 rounded-md hover:bg-neutral-50 transition-colors"
              >
                Cancel
              </button>
              <button
                type="button"
                onClick={handleIssueCapa}
                disabled={!capaData.description.trim() || issueCapaMut.isPending}
                className="px-4 py-2 text-sm font-medium text-white bg-neutral-900 rounded-md hover:bg-neutral-800 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
              >
                {issueCapaMut.isPending ? 'Issuing...' : 'Issue CAPA'}
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  )
}
