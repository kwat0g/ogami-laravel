import { useState, useCallback } from 'react'
import { ClipboardCheck, AlertTriangle, Plus, Trash2 } from 'lucide-react'
import SearchInput from '@/components/ui/SearchInput'
import { toast } from 'sonner'
import { useInspectionTemplates, useDeleteInspectionTemplate } from '@/hooks/useQC'
import { useAuthStore } from '@/stores/authStore'
import { useQuery } from '@tanstack/react-query'
import ArchiveToggleButton from '@/components/ui/ArchiveToggleButton'
import ArchiveViewBanner from '@/components/ui/ArchiveViewBanner'
import ArchiveRowActions from '@/components/ui/ArchiveRowActions'
import api from '@/lib/api'
import { firstErrorMessage } from '@/lib/errorHandler'
import SkeletonLoader from '@/components/ui/SkeletonLoader'
import { PageHeader } from '@/components/ui/PageHeader'
import ConfirmDestructiveDialog from '@/components/ui/ConfirmDestructiveDialog'
import type { InspectionStage, InspectionTemplate } from '@/types/qc'

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
  const [isArchiveView, setIsArchiveView] = useState(false)
  const [templateToDelete, setTemplateToDelete] = useState<InspectionTemplate | null>(null)
  const [search, setSearch] = useState('')
  const [debouncedSearch, setDebouncedSearch] = useState('')

  const handleSearch = useCallback((val: string) => {
    setDebouncedSearch(val)
  }, [])

  const { data, isLoading, isError, refetch } = useInspectionTemplates({
    stage: stage || undefined,
    per_page: 50,
    with_archived: undefined,
    ...(debouncedSearch ? { search: debouncedSearch } : {}),
  } as Record<string, unknown>)

  const { data: archivedData, isLoading: archivedLoading, refetch: refetchArchived } = useQuery({
    queryKey: ['qc-templates', 'archived', debouncedSearch, stage],
    queryFn: () => api.get('/qc/templates-archived', { params: { search: debouncedSearch || undefined, stage: stage || undefined, per_page: 50 } }),
    enabled: isArchiveView,
  })

  const currentData = isArchiveView ? (archivedData?.data?.data ?? []) : (data?.data ?? [])
  const currentLoading = isArchiveView ? archivedLoading : isLoading

  const deleteMut = useDeleteInspectionTemplate()

  const handleDelete = async () => {
    if (!templateToDelete) return
    try {
      await deleteMut.mutateAsync(templateToDelete.ulid)
      toast.success(`Template "${templateToDelete.name}" deleted successfully.`)
      setTemplateToDelete(null)
    } catch (err: unknown) {
      toast.error(firstErrorMessage(err))
    }
  }

  return (
    <div>
      <PageHeader
        title="QC Templates"
        actions={
          canManage ? (
            <button
              className="inline-flex items-center gap-1.5 rounded bg-neutral-900 px-3 py-2 text-sm font-medium text-white hover:bg-neutral-800"
              onClick={() => {/* TODO: open create modal */}}
            >
              <Plus size={16} /> New Template
            </button>
          ) : undefined
        }
      />

      <div className="flex flex-wrap gap-3 mb-5 items-center">
        <SearchInput
          value={search}
          onChange={setSearch}
          onSearch={handleSearch}
          placeholder="Search templates..."
          className="w-64"
        />
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
        <ArchiveToggleButton isArchiveView={isArchiveView} onToggle={() => setIsArchiveView(prev => !prev)} />
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
                {['Template Name', 'Stage', '# Criteria', 'Status', 'Created', ''].map((h) => (
                  <th key={h} className="px-4 py-3 text-left text-xs font-medium text-neutral-500">{h}</th>
                ))}
              </tr>
            </thead>
            <tbody className="divide-y divide-neutral-100">
              {data?.data?.length === 0 && (
                <tr>
                  <td colSpan={6} className="px-4 py-10 text-center text-neutral-400 text-sm">
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
                  <td className="px-4 py-3">
                    {canManage && !template.deleted_at && (
                      <button
                        onClick={() => setTemplateToDelete(template)}
                        className="inline-flex items-center gap-1.5 px-2 py-1 text-xs font-medium text-red-600 hover:text-red-700 hover:bg-red-50 rounded transition-colors"
                        title="Delete template"
                      >
                        <Trash2 className="w-3.5 h-3.5" />
                      </button>
                    )}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}

      {/* Delete Confirmation Dialog */}
      {templateToDelete && (
        <ConfirmDestructiveDialog
          title="Delete QC Template?"
          description={`You are about to permanently delete the template "${templateToDelete.name}". This action cannot be undone and may affect existing inspections that use this template.`}
          confirmWord="DELETE"
          confirmLabel="Delete Template"
          onConfirm={handleDelete}
        >
          <span />
        </ConfirmDestructiveDialog>
      )}
    </div>
  )
}
