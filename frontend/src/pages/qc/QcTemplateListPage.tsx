import { useState } from 'react'
import { ClipboardCheck, AlertTriangle, Plus } from 'lucide-react'
import { useInspectionTemplates } from '@/hooks/useQC'
import { useAuthStore } from '@/stores/authStore'
import SkeletonLoader from '@/components/ui/SkeletonLoader'
import type { InspectionStage } from '@/types/qc'

const STAGE_COLORS: Record<InspectionStage, string> = {
  iqc:  'bg-neutral-100 text-neutral-700',
  ipqc: 'bg-neutral-100 text-neutral-700',
  oqc:  'bg-neutral-100 text-neutral-700',
}

const STAGE_LABELS: Record<InspectionStage, string> = {
  iqc:  'Incoming (IQC)',
  ipqc: 'In-Process (IPQC)',
  oqc:  'Outgoing (OQC)',
}

export default function QcTemplateListPage(): React.ReactElement {
  const { hasPermission } = useAuthStore()
  const canManage = hasPermission('qc.templates.manage')
  const [stage, setStage] = useState('')
  const [withArchived, setWithArchived] = useState(false)

  const { data, isLoading, isError } = useInspectionTemplates({
    stage: stage || undefined,
    per_page: 50,
    with_archived: withArchived || undefined,
  })

  return (
    <div>
      <div className="flex items-center justify-between mb-6">
        <div className="flex items-center gap-3">
          <div className="w-10 h-10 bg-neutral-100 rounded-lg flex items-center justify-center">
            <ClipboardCheck className="w-5 h-5 text-neutral-600" />
          </div>
          <div>
            <h1 className="text-lg font-semibold text-neutral-900 mb-6">Inspection Templates</h1>
            <p className="text-sm text-neutral-500 mt-0.5">Define inspection criteria for IQC, IPQC, and OQC stages</p>
          </div>
        </div>
        {canManage && (
          <button
            className="inline-flex items-center gap-1.5 rounded bg-neutral-900 px-3 py-2 text-sm font-medium text-white hover:bg-neutral-800"
            onClick={() => {/* TODO: open create modal */}}
          >
            <Plus size={16} /> New Template
          </button>
        )}
      </div>

      <div className="flex flex-wrap gap-3 mb-5">
        <select
          value={stage}
          onChange={(e) => setStage(e.target.value)}
          className="text-sm border border-neutral-300 rounded px-3 py-2 focus:outline-none focus:ring-1 focus:ring-neutral-400 bg-white"
        >
          <option value="">All Stages</option>
          <option value="iqc">Incoming (IQC)</option>
          <option value="ipqc">In-Process (IPQC)</option>
          <option value="oqc">Outgoing (OQC)</option>
        </select>
        <label className="flex items-center gap-2 text-sm text-neutral-600 cursor-pointer select-none">
          <input type="checkbox" checked={withArchived} onChange={(e) => setWithArchived(e.target.checked)} className="rounded border-neutral-300 text-neutral-600" />
          <span>Show Archived</span>
        </label>
      </div>

      {isLoading && <SkeletonLoader rows={6} />}
      {isError && (
        <div className="flex items-center gap-2 text-red-600 text-sm">
          <AlertTriangle className="w-4 h-4" /> Failed to load inspection templates.
        </div>
      )}

      {!isLoading && !isError && (
        <div className="bg-white border border-neutral-200 rounded-lg overflow-hidden">
          <table className="min-w-full text-sm">
            <thead className="bg-neutral-50 border-b border-neutral-200">
              <tr>
                {['Template Name', 'Stage', '# Criteria', 'Status', 'Created'].map((h) => (
                  <th key={h} className="px-4 py-3 text-left text-xs font-medium text-neutral-500">{h}</th>
                ))}
              </tr>
            </thead>
            <tbody className="divide-y divide-neutral-100">
              {data?.data?.length === 0 && (
                <tr>
                  <td colSpan={5} className="px-4 py-10 text-center text-neutral-400 text-sm">
                    <ClipboardCheck size={32} className="mx-auto mb-2 opacity-30" />
                    No inspection templates found.
                    {canManage && <span className="block mt-1 text-xs">Click <strong>New Template</strong> to create one.</span>}
                  </td>
                </tr>
              )}
              {data?.data?.map((template) => (
                <tr key={template.id} className="even:bg-neutral-100 hover:bg-neutral-50">
                  <td className="px-4 py-3 font-medium text-neutral-900">{template.name}</td>
                  <td className="px-4 py-3">
                    <span className={`inline-flex px-2 py-0.5 rounded text-xs font-medium ${STAGE_COLORS[template.stage]}`}>
                      {STAGE_LABELS[template.stage]}
                    </span>
                  </td>
                  <td className="px-4 py-3 text-neutral-600">
                    {template.items ? template.items.length : <span className="text-neutral-400">—</span>}
                  </td>
                  <td className="px-4 py-3">
                    {template.deleted_at && <span className="inline-flex px-2 py-0.5 rounded text-xs font-medium bg-neutral-100 text-neutral-700 mr-1">Archived</span>}
                    {template.is_active ? (
                      <span className="inline-flex px-2 py-0.5 rounded text-xs font-medium bg-neutral-100 text-neutral-700">Active</span>
                    ) : (
                      <span className="inline-flex px-2 py-0.5 rounded text-xs font-medium bg-neutral-100 text-neutral-500">Inactive</span>
                    )}
                  </td>
                  <td className="px-4 py-3 text-neutral-500 text-xs">{template.created_at.slice(0, 10)}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </div>
  )
}
