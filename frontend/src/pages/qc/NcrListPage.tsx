import { useState } from 'react'
import { Link } from 'react-router-dom'
import { AlertOctagon, AlertTriangle, Plus } from 'lucide-react'
import { useNcrs } from '@/hooks/useQC'
import { useAuthStore } from '@/stores/authStore'
import SkeletonLoader from '@/components/ui/SkeletonLoader'
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
  const [status, setStatus]     = useState('')
  const [severity, setSeverity] = useState('')
  const [page, setPage]         = useState(1)
  const [withArchived, setWithArchived] = useState(false)

  const { data, isLoading, isError } = useNcrs({
    status:   status || undefined,
    severity: severity || undefined,
    page,
    per_page: 20,
    with_archived: withArchived || undefined,
  })
  const { hasPermission } = useAuthStore()
  const canCreate = hasPermission('qc.ncr.create')

  return (
    <div>
      <div className="flex items-center justify-between mb-6">
        <div className="flex items-center gap-3">
          <div className="w-10 h-10 bg-neutral-100 rounded-lg flex items-center justify-center">
            <AlertOctagon className="w-5 h-5 text-neutral-600" />
          </div>
          <div>
            <h1 className="text-lg font-semibold text-neutral-900 mb-6">Non-Conformance Reports</h1>
            <p className="text-sm text-neutral-500 mt-0.5">Track quality failures and corrective actions</p>
          </div>
        </div>
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

      <div className="flex flex-wrap gap-3 mb-5">
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
        <label className="flex items-center gap-2 text-sm text-neutral-600 cursor-pointer select-none">
          <input type="checkbox" checked={withArchived} onChange={(e) => setWithArchived(e.target.checked)} className="rounded border-neutral-300 text-neutral-600" />
          <span>Show Archived</span>
        </label>
      </div>

      {isLoading && <SkeletonLoader rows={8} />}
      {isError && (
        <div className="flex items-center gap-2 text-red-600 text-sm">
          <AlertTriangle className="w-4 h-4" /> Failed to load NCRs.
        </div>
      )}

      {!isLoading && !isError && (
        <>
          <div className="bg-white border border-neutral-200 rounded-lg overflow-hidden">
            <table className="min-w-full text-sm">
              <thead className="bg-neutral-50 border-b border-neutral-200">
                <tr>
                  {['NCR Reference', 'Title', 'Item', 'Severity', 'Status', 'Raised', ''].map((h) => (
                    <th key={h} className="px-4 py-3 text-left text-xs font-medium text-neutral-500">{h}</th>
                  ))}
                </tr>
              </thead>
              <tbody className="divide-y divide-neutral-100">
                {data?.data?.length === 0 && (
                  <tr>
                    <td colSpan={7} className="px-4 py-8 text-center text-neutral-400 text-sm">No NCRs found.</td>
                  </tr>
                )}
                {data?.data?.map((ncr) => (
                  <tr key={ncr.id} className="even:bg-neutral-100 hover:bg-neutral-50">
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
                    <td className="px-4 py-3">
                      <Link to={`/qc/ncrs/${ncr.ulid}`} className="inline-block px-2 py-1 text-xs border border-neutral-200 rounded bg-white text-neutral-600 hover:bg-neutral-50 hover:border-neutral-300 hover:text-neutral-900 font-medium">
                        View →
                      </Link>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
          {data && data.meta.last_page > 1 && (
            <div className="flex items-center justify-between mt-4 text-sm text-neutral-600">
              <span>Page {data.meta.current_page} of {data.meta.last_page} · {data.meta.total} NCRs</span>
              <div className="flex gap-2">
                <button onClick={() => setPage((p) => Math.max(1, p - 1))} disabled={page === 1} className="px-3 py-1.5 border border-neutral-300 rounded disabled:opacity-40 hover:bg-neutral-50">Previous</button>
                <button onClick={() => setPage((p) => p + 1)} disabled={page >= data.meta.last_page} className="px-3 py-1.5 border border-neutral-300 rounded disabled:opacity-40 hover:bg-neutral-50">Next</button>
              </div>
            </div>
          )}
        </>
      )}
    </div>
  )
}
