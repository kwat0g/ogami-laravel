import { useState, useCallback } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { AlertTriangle, Plus } from 'lucide-react'
import { useInspections } from '@/hooks/useQC'
import { useAuthStore } from '@/stores/authStore'
import { useQuery } from '@tanstack/react-query'
import ArchiveToggleButton from '@/components/ui/ArchiveToggleButton'
import ArchiveViewBanner from '@/components/ui/ArchiveViewBanner'
import ArchiveRowActions from '@/components/ui/ArchiveRowActions'
import api from '@/lib/api'
import SkeletonLoader from '@/components/ui/SkeletonLoader'
import { PageHeader } from '@/components/ui/PageHeader'
import SearchInput from '@/components/ui/SearchInput'
import Pagination from '@/components/ui/Pagination'
import { DepartmentGuard } from '@/components/ui/guards'
import { ExportButton } from '@/components/ui/ExportButton'
import type { InspectionStage, InspectionStatus } from '@/types/qc'

const stageBadge: Record<InspectionStage, string> = {
  iqc:   'bg-neutral-100 text-neutral-700',
  ipqc:  'bg-neutral-100 text-neutral-700',
  oqc:   'bg-neutral-100 text-neutral-700',
}

const statusBadge: Record<InspectionStatus, string> = {
  open:     'bg-neutral-100 text-neutral-600',
  passed:   'bg-neutral-100 text-neutral-700',
  failed:   'bg-neutral-100 text-neutral-700',
  on_hold:  'bg-neutral-100 text-neutral-700',
  voided:   'bg-neutral-100 text-neutral-400',
}

export default function InspectionListPage(): React.ReactElement {
  const navigate = useNavigate()
  const [stage, setStage]   = useState('')
  const [status, setStatus] = useState('')
  const [page, setPage]     = useState(1)
  const [isArchiveView, setIsArchiveView] = useState(false)
  const [search, setSearch] = useState('')
  const [debouncedSearch, setDebouncedSearch] = useState('')

  const handleSearch = useCallback((val: string) => {
    setDebouncedSearch(val)
    setPage(1)
  }, [])

  const { data, isLoading, isError } = useInspections({
    stage:  stage || undefined,
    status: status || undefined,
    page,
    per_page: 20,
    with_archived: undefined,
    ...(debouncedSearch ? { search: debouncedSearch } : {}),
  })

  const { data: archivedData, isLoading: archivedLoading, refetch: refetchArchived } = useQuery({
    queryKey: ['qc-inspections', 'archived'],
    queryFn: () => api.get('/qc/inspections-archived', { params: { per_page: 20 } }),
    enabled: isArchiveView,
  })
  const { hasPermission } = useAuthStore()
  const canCreate = hasPermission('qc.inspections.create')

  return (
    <div>
      <PageHeader
        title="Inspections"
        actions={
          <div className="flex items-center gap-2">
            <ExportButton
              data={data?.data ?? []}
              columns={[
                { key: 'reference', label: 'Reference' },
                { key: 'stage', label: 'Stage' },
                { key: 'status', label: 'Status' },
                { key: 'inspected_qty', label: 'Inspected' },
                { key: 'passed_qty', label: 'Passed' },
                { key: 'failed_qty', label: 'Failed' },
                { key: 'inspection_date', label: 'Date' },
              ]}
              filename="inspections"
            />
            <Link to="/qc/templates" className="inline-flex items-center gap-2 bg-white border border-neutral-300 hover:bg-neutral-50 text-neutral-700 text-sm font-medium px-3 py-2 rounded transition-colors">
              Templates
            </Link>
            <Link to="/qc/defect-rate" className="inline-flex items-center gap-2 bg-white border border-neutral-300 hover:bg-neutral-50 text-neutral-700 text-sm font-medium px-3 py-2 rounded transition-colors">
              Defect Rate
            </Link>
            <DepartmentGuard module="inspections">
              {canCreate ? (
                <Link
                  to="/qc/inspections/new"
                  className="inline-flex items-center gap-1.5 bg-neutral-900 hover:bg-neutral-800 text-white text-sm font-medium px-4 py-2 rounded transition-colors"
                >
                  <Plus className="w-4 h-4" />
                  New Inspection
                </Link>
              ) : undefined}
            </DepartmentGuard>
          </div>
        }
      />

      <div className="flex flex-wrap gap-3 mb-5 items-center">
        <SearchInput
          value={search}
          onChange={setSearch}
          onSearch={handleSearch}
          placeholder="Search inspections..."
          className="w-64"
        />
        <select
          value={stage}
          onChange={(e) => { setStage(e.target.value); setPage(1) }}
          className="text-sm border border-neutral-300 rounded px-3 py-2 focus:outline-none focus:ring-1 focus:ring-neutral-400 bg-white"
        >
          <option value="">All Stages</option>
          <option value="iqc">IQC (Incoming)</option>
          <option value="ipqc">IPQC (In-Process)</option>
          <option value="oqc">OQC (Outgoing)</option>
        </select>

        <select
          value={status}
          onChange={(e) => { setStatus(e.target.value); setPage(1) }}
          className="text-sm border border-neutral-300 rounded px-3 py-2 focus:outline-none focus:ring-1 focus:ring-neutral-400 bg-white"
        >
          <option value="">All Statuses</option>
          {(['open', 'passed', 'failed', 'on_hold'] as InspectionStatus[]).map((s) => (
            <option key={s} value={s}>{s.replace('_', ' ')}</option>
          ))}
        </select>
        <ArchiveToggleButton isArchiveView={isArchiveView} onToggle={() => setIsArchiveView(prev => !prev)} />
      </div>

      {isLoading && <SkeletonLoader rows={8} />}
      {isError && (
        <div className="flex items-center gap-2 text-red-600 text-sm">
          <AlertTriangle className="w-4 h-4" /> Failed to load inspections.
        </div>
      )}

      {!isLoading && !isError && (
        <>
          <div className="bg-white border border-neutral-200 rounded-lg overflow-hidden">
            <table className="min-w-full text-sm">
              <thead className="bg-neutral-50 border-b border-neutral-200">
                <tr>
                  {['Reference', 'Stage', 'Item', 'Qty Inspected', 'Pass / Fail', 'Date', 'Status'].map((h) => (
                    <th key={h} className="px-4 py-3 text-left text-xs font-medium text-neutral-500">{h}</th>
                  ))}
                </tr>
              </thead>
              <tbody className="divide-y divide-neutral-100">
                {data?.data?.length === 0 && (
                  <tr>
                    <td colSpan={7} className="px-4 py-8 text-center text-neutral-400 text-sm">No inspections found.</td>
                  </tr>
                )}
                {data?.data?.map((insp) => (
                  <tr key={insp.id} className="even:bg-neutral-100 hover:bg-neutral-50 cursor-pointer" onClick={() => navigate(`/qc/inspections/${insp.ulid}`)}>
                    <td className="px-4 py-3 font-mono text-neutral-700 font-medium">{insp.inspection_reference}</td>
                    <td className="px-4 py-3">
                      <span className={`inline-flex px-2 py-0.5 rounded text-xs font-medium ${stageBadge[insp.stage]}`}>{insp.stage}</span>
                    </td>
                    <td className="px-4 py-3">
                      <div className="text-xs font-mono text-neutral-400">{insp.item_master?.item_code}</div>
                      <div className="text-sm">{insp.item_master?.name ?? '—'}</div>
                    </td>
                    <td className="px-4 py-3 tabular-nums">{parseFloat(insp.qty_inspected).toLocaleString('en-PH', { maximumFractionDigits: 2 })}</td>
                    <td className="px-4 py-3 text-sm">
                      <span className="text-neutral-700 font-medium">{parseFloat(insp.qty_passed).toLocaleString('en-PH', { maximumFractionDigits: 2 })}</span>
                      {' / '}
                      <span className="text-neutral-600 font-medium">{parseFloat(insp.qty_failed).toLocaleString('en-PH', { maximumFractionDigits: 2 })}</span>
                    </td>
                    <td className="px-4 py-3 text-neutral-500 text-xs">{insp.inspection_date}</td>
                    <td className="px-4 py-3">
                      {insp.deleted_at && <span className="inline-flex px-2 py-0.5 rounded text-xs font-medium bg-neutral-100 text-neutral-700 mr-1">Archived</span>}
                      <span className={`inline-flex px-2 py-0.5 rounded text-xs font-medium capitalize ${statusBadge[insp.status]}`}>
                        {insp.status?.replace('_', ' ') || 'Unknown'}
                      </span>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
          {data && data.meta.last_page > 1 && (
            <div className="flex items-center justify-between mt-4 text-sm text-neutral-600">
              <span>Page {data.meta.current_page} of {data.meta.last_page} · {data.meta.total} inspections</span>
              <div className="flex gap-2">
                <button onClick={() => setPage((p) => Math.max(1, p - 1))} disabled={page === 1} className="px-3 py-1.5 border border-neutral-300 rounded disabled:opacity-40 disabled:cursor-not-allowed hover:bg-neutral-50">Previous</button>
                <button onClick={() => setPage((p) => p + 1)} disabled={page >= data.meta.last_page} className="px-3 py-1.5 border border-neutral-300 rounded disabled:opacity-40 disabled:cursor-not-allowed hover:bg-neutral-50">Next</button>
              </div>
            </div>
          )}
        </>
      )}
    </div>
  )
}
