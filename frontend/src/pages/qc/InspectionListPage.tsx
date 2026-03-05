import { useState } from 'react'
import { Link } from 'react-router-dom'
import { ClipboardCheck, AlertTriangle } from 'lucide-react'
import { useInspections } from '@/hooks/useQC'
import SkeletonLoader from '@/components/ui/SkeletonLoader'
import type { InspectionStage, InspectionStatus } from '@/types/qc'

const stageBadge: Record<InspectionStage, string> = {
  iqc:   'bg-blue-100 text-blue-700',
  ipqc:  'bg-amber-100 text-amber-700',
  oqc:   'bg-purple-100 text-purple-700',
}

const statusBadge: Record<InspectionStatus, string> = {
  open:     'bg-gray-100 text-gray-600',
  passed:   'bg-green-100 text-green-700',
  failed:   'bg-red-100 text-red-700',
  on_hold:  'bg-yellow-100 text-yellow-700',
}

export default function InspectionListPage(): React.ReactElement {
  const [stage, setStage]   = useState('')
  const [status, setStatus] = useState('')
  const [page, setPage]     = useState(1)

  const { data, isLoading, isError } = useInspections({
    stage:  stage || undefined,
    status: status || undefined,
    page,
    per_page: 20,
  })

  return (
    <div>
      <div className="flex items-center gap-3 mb-6">
        <div className="w-10 h-10 bg-teal-100 rounded-xl flex items-center justify-center">
          <ClipboardCheck className="w-5 h-5 text-teal-600" />
        </div>
        <div>
          <h1 className="text-2xl font-bold text-gray-900">Inspections</h1>
          <p className="text-sm text-gray-500 mt-0.5">IQC, IPQC, and OQC inspection records</p>
        </div>
      </div>

      <div className="flex flex-wrap gap-3 mb-5">
        <select
          value={stage}
          onChange={(e) => { setStage(e.target.value); setPage(1) }}
          className="text-sm border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-teal-500 bg-white"
        >
          <option value="">All Stages</option>
          <option value="iqc">IQC (Incoming)</option>
          <option value="ipqc">IPQC (In-Process)</option>
          <option value="oqc">OQC (Outgoing)</option>
        </select>

        <select
          value={status}
          onChange={(e) => { setStatus(e.target.value); setPage(1) }}
          className="text-sm border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-teal-500 bg-white"
        >
          <option value="">All Statuses</option>
          {(['open', 'passed', 'failed', 'on_hold'] as InspectionStatus[]).map((s) => (
            <option key={s} value={s}>{s.replace('_', ' ')}</option>
          ))}
        </select>
      </div>

      {isLoading && <SkeletonLoader rows={8} />}
      {isError && (
        <div className="flex items-center gap-2 text-red-600 text-sm">
          <AlertTriangle className="w-4 h-4" /> Failed to load inspections.
        </div>
      )}

      {!isLoading && !isError && (
        <>
          <div className="bg-white border border-gray-200 rounded-xl overflow-hidden">
            <table className="min-w-full text-sm">
              <thead className="bg-gray-50 border-b border-gray-200">
                <tr>
                  {['Reference', 'Stage', 'Item', 'Qty Inspected', 'Pass / Fail', 'Date', 'Status', ''].map((h) => (
                    <th key={h} className="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">{h}</th>
                  ))}
                </tr>
              </thead>
              <tbody className="divide-y divide-gray-100">
                {data?.data?.length === 0 && (
                  <tr>
                    <td colSpan={8} className="px-4 py-8 text-center text-gray-400 text-sm">No inspections found.</td>
                  </tr>
                )}
                {data?.data?.map((insp) => (
                  <tr key={insp.id} className="hover:bg-gray-50">
                    <td className="px-4 py-3 font-mono text-teal-700 font-medium">{insp.inspection_reference}</td>
                    <td className="px-4 py-3">
                      <span className={`inline-flex px-2 py-0.5 rounded-full text-xs font-bold uppercase ${stageBadge[insp.stage]}`}>{insp.stage}</span>
                    </td>
                    <td className="px-4 py-3">
                      <div className="text-xs font-mono text-gray-400">{insp.item_master?.item_code}</div>
                      <div className="text-sm">{insp.item_master?.name ?? '—'}</div>
                    </td>
                    <td className="px-4 py-3 tabular-nums">{parseFloat(insp.qty_inspected).toLocaleString('en-PH', { maximumFractionDigits: 2 })}</td>
                    <td className="px-4 py-3 text-sm">
                      <span className="text-green-700 font-semibold">{parseFloat(insp.qty_passed).toLocaleString('en-PH', { maximumFractionDigits: 2 })}</span>
                      {' / '}
                      <span className="text-red-600 font-semibold">{parseFloat(insp.qty_failed).toLocaleString('en-PH', { maximumFractionDigits: 2 })}</span>
                    </td>
                    <td className="px-4 py-3 text-gray-500 text-xs">{insp.inspection_date}</td>
                    <td className="px-4 py-3">
                      <span className={`inline-flex px-2 py-0.5 rounded-full text-xs font-semibold capitalize ${statusBadge[insp.status]}`}>
                        {insp.status.replace('_', ' ')}
                      </span>
                    </td>
                    <td className="px-4 py-3">
                      <Link to={`/qc/inspections/${insp.ulid}`} className="text-xs text-teal-600 hover:text-teal-800 font-medium">
                        View →
                      </Link>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
          {data && data.meta.last_page > 1 && (
            <div className="flex items-center justify-between mt-4 text-sm text-gray-600">
              <span>Page {data.meta.current_page} of {data.meta.last_page} · {data.meta.total} inspections</span>
              <div className="flex gap-2">
                <button onClick={() => setPage((p) => Math.max(1, p - 1))} disabled={page === 1} className="px-3 py-1.5 border border-gray-300 rounded-lg disabled:opacity-40 hover:bg-gray-50">Previous</button>
                <button onClick={() => setPage((p) => p + 1)} disabled={page >= data.meta.last_page} className="px-3 py-1.5 border border-gray-300 rounded-lg disabled:opacity-40 hover:bg-gray-50">Next</button>
              </div>
            </div>
          )}
        </>
      )}
    </div>
  )
}
