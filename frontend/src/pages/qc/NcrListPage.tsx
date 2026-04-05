import { useState, useCallback } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { AlertTriangle, Plus } from 'lucide-react'
import { useNcrs } from '@/hooks/useQC'
import { useAuthStore } from '@/stores/authStore'
import { PERMISSIONS } from '@/lib/permissions'
import { useQuery } from '@tanstack/react-query'
import ArchiveToggleButton from '@/components/ui/ArchiveToggleButton'
import api from '@/lib/api'
import SkeletonLoader from '@/components/ui/SkeletonLoader'
import { PageHeader } from '@/components/ui/PageHeader'
import SearchInput from '@/components/ui/SearchInput'
import type { NcrSeverity, NcrStatus } from '@/types/qc'

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

export default function NcrListPage(): React.ReactElement {
  const navigate = useNavigate()
  const [status, setStatus]     = useState('')
  const [severity, setSeverity] = useState('')
  const [page, setPage]         = useState(1)
  const [isArchiveView, setIsArchiveView] = useState(false)
  const [search, setSearch] = useState('')
  const [debouncedSearch, setDebouncedSearch] = useState('')

  const handleSearch = useCallback((val: string) => {
    setDebouncedSearch(val)
    setPage(1)
  }, [])

  const { data, isLoading, isError } = useNcrs({
    status:   status || undefined,
    severity: severity || undefined,
    page,
    per_page: 20,
    with_archived: undefined,
    ...(debouncedSearch ? { search: debouncedSearch } : {}),
  })

  const { data: archivedData, isLoading: archivedLoading, refetch: refetchArchived } = useQuery({
    queryKey: ['qc-ncrs', 'archived'],
    queryFn: () => api.get('/qc/ncrs-archived', { params: { per_page: 20 } }),
    enabled: isArchiveView,
  })
  const { hasPermission } = useAuthStore()
  const canCreate = hasPermission(PERMISSIONS.qc.ncr.create)

  return (
    <div>
      <PageHeader
        title="Non-Conformance Reports"
        actions={
          <div className="flex items-center gap-2">
            <Link to="/qc/capa" className="inline-flex items-center gap-2 bg-white border border-neutral-300 hover:bg-neutral-50 text-neutral-700 text-sm font-medium px-3 py-2 rounded transition-colors">
              CAPA
            </Link>
            {canCreate && (
              <Link
                to="/qc/ncrs/new"
                className="inline-flex items-center gap-1.5 bg-neutral-900 hover:bg-neutral-800 text-white text-sm font-medium px-4 py-2 rounded transition-colors"
              >
                <Plus className="w-4 h-4" />
                New NCR
              </Link>
            )}
          </div>
        }
      />

      <div className="flex flex-wrap gap-3 mb-5 items-center">
        <SearchInput
          value={search}
          onChange={setSearch}
          onSearch={handleSearch}
          placeholder="Search NCRs..."
          className="w-64"
        />
        <select
          value={status}
          onChange={(e) => { setStatus(e.target.value); setPage(1) }}
          className="text-sm border border-neutral-300 rounded px-3 py-2 focus:outline-none focus:ring-1 focus:ring-neutral-400 bg-white"
        >
          <option value="">All Statuses</option>
          {(['open', 'under_review', 'capa_issued', 'closed', 'voided'] as NcrStatus[]).map((s) => (
            <option key={s} value={s}>{s.replace('_', ' ')}</option>
          ))}
        </select>
        <select
          value={severity}
          onChange={(e) => { setSeverity(e.target.value); setPage(1) }}
          className="text-sm border border-neutral-300 rounded px-3 py-2 focus:outline-none focus:ring-1 focus:ring-neutral-400 bg-white"
        >
          <option value="">All Severities</option>
          <option value="minor">Minor</option>
          <option value="major">Major</option>
          <option value="critical">Critical</option>
        </select>
        <ArchiveToggleButton isArchiveView={isArchiveView} onToggle={() => setIsArchiveView(prev => !prev)} />
      </div>

      {isLoading && <SkeletonLoader rows={8} />}
      {isError && (
        <div className="flex items-center gap-2 text-red-600 text-sm">
          <AlertTriangle className="w-4 h-4" /> Failed to load NCRs.
        </div>
      )}

      {!isLoading && !isError && (
        <>
          <div className="bg-white border border-neutral-200 rounded-xl overflow-hidden">
            <table className="min-w-full text-sm">
              <thead className="bg-neutral-50 border-b border-neutral-200">
                <tr>
                  {['NCR Reference', 'Title', 'Item', 'Severity', 'Status', 'Raised'].map((h) => (
                    <th key={h} className="px-4 py-3 text-left text-xs font-medium text-neutral-500">{h}</th>
                  ))}
                </tr>
              </thead>
              <tbody className="divide-y divide-neutral-100">
                {data?.data?.length === 0 && (
                  <tr>
                    <td colSpan={6} className="px-4 py-8 text-center text-neutral-400 text-sm">No NCRs found.</td>
                  </tr>
                )}
                {data?.data?.map((ncr) => (
                  <tr key={ncr.id} className="even:bg-neutral-100 hover:bg-neutral-50 cursor-pointer" onClick={() => navigate(`/qc/ncrs/${ncr.ulid}`)}>
                    <td className="px-4 py-3 font-mono text-neutral-700 font-medium">{ncr.ncr_reference}</td>
                    <td className="px-4 py-3 text-neutral-800 max-w-xs truncate">{ncr.title}</td>
                    <td className="px-4 py-3 text-neutral-500 text-sm">{ncr.inspection?.item_master?.name ?? '—'}</td>
                    <td className="px-4 py-3">
                      <span className={`inline-flex px-2 py-0.5 rounded text-xs font-medium capitalize ${severityBadge[ncr.severity]}`}>
                        {ncr.severity}
                      </span>
                    </td>
                    <td className="px-4 py-3">
                      {ncr.deleted_at && <span className="inline-flex px-2 py-0.5 rounded text-xs font-medium bg-neutral-100 text-neutral-700 mr-1">Archived</span>}
                      <span className={`inline-flex px-2 py-0.5 rounded text-xs font-medium capitalize ${statusBadge[ncr.status]}`}>
                        {ncr.status?.replace('_', ' ') || 'Unknown'}
                      </span>
                    </td>
                    <td className="px-4 py-3 text-neutral-400 text-xs">{new Date(ncr.created_at).toLocaleDateString('en-PH')}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
          {data && data.meta.last_page > 1 && (
            <div className="flex items-center justify-between mt-4 text-sm text-neutral-600">
              <span>Page {data.meta.current_page} of {data.meta.last_page} · {data.meta.total} NCRs</span>
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
