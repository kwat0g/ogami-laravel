import { useNavigate, useParams } from 'react-router-dom'
import { ArrowLeft, ClipboardCheck, AlertTriangle } from 'lucide-react'

import { useInspection } from '@/hooks/useQC'
import SkeletonLoader from '@/components/ui/SkeletonLoader'
import type { InspectionStage, InspectionStatus } from '@/types/qc'

const stageBadge: Record<InspectionStage, string> = {
  iqc:  'bg-blue-100 text-blue-700',
  ipqc: 'bg-amber-100 text-amber-700',
  oqc:  'bg-purple-100 text-purple-700',
}

const statusBadge: Record<InspectionStatus, string> = {
  open:    'bg-gray-100 text-gray-600',
  passed:  'bg-green-100 text-green-700',
  failed:  'bg-red-100 text-red-700',
  on_hold: 'bg-yellow-100 text-yellow-700',
}

function InfoRow({ label, value }: { label: string; value: React.ReactNode }) {
  return (
    <div className="flex items-start gap-4 py-2 border-b border-gray-100 last:border-0">
      <dt className="text-sm text-gray-500 w-36 flex-shrink-0">{label}</dt>
      <dd className="text-sm text-gray-900 font-medium">{value ?? '—'}</dd>
    </div>
  )
}

export default function InspectionDetailPage(): React.ReactElement {
  const { ulid }   = useParams<{ ulid: string }>()
  const navigate   = useNavigate()
  const { data: inspection, isLoading, isError } = useInspection(ulid ?? null)

  if (isLoading) return <SkeletonLoader rows={8} />
  if (isError || !inspection) return (
    <div className="flex items-center gap-2 text-red-600 text-sm">
      <AlertTriangle className="w-4 h-4" /> Failed to load inspection.
    </div>
  )

  return (
    <div className="max-w-3xl">
      <div className="flex items-center gap-3 mb-6">
        <button onClick={() => navigate('/qc/inspections')} className="p-2 hover:bg-gray-100 rounded-lg">
          <ArrowLeft className="w-4 h-4 text-gray-500" />
        </button>
        <div className="w-10 h-10 bg-teal-100 rounded-xl flex items-center justify-center">
          <ClipboardCheck className="w-5 h-5 text-teal-600" />
        </div>
        <div className="flex items-center gap-3">
          <h1 className="text-2xl font-bold text-gray-900 font-mono">{inspection.inspection_reference}</h1>
          <span className={`inline-flex px-2 py-0.5 rounded-full text-xs font-bold uppercase ${stageBadge[inspection.stage]}`}>{inspection.stage}</span>
          <span className={`inline-flex px-2 py-0.5 rounded-full text-xs font-semibold capitalize ${statusBadge[inspection.status]}`}>{inspection.status.replace('_', ' ')}</span>
        </div>
      </div>

      {/* Details */}
      <div className="bg-white border border-gray-200 rounded-xl p-6 mb-5">
        <h2 className="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">Inspection Details</h2>
        <dl>
          <InfoRow label="Item"          value={inspection.item_master ? `${inspection.item_master.item_code} — ${inspection.item_master.name}` : null} />
          <InfoRow label="Lot / Batch"   value={inspection.lot_batch?.batch_number} />
          <InfoRow label="Qty Inspected" value={parseFloat(inspection.qty_inspected).toLocaleString('en-PH', { maximumFractionDigits: 4 })} />
          <InfoRow label="Qty Passed"    value={<span className="text-green-700 font-semibold">{parseFloat(inspection.qty_passed).toLocaleString('en-PH', { maximumFractionDigits: 4 })}</span>} />
          <InfoRow label="Qty Failed"    value={<span className="text-red-600 font-semibold">{parseFloat(inspection.qty_failed).toLocaleString('en-PH', { maximumFractionDigits: 4 })}</span>} />
          <InfoRow label="Date"          value={inspection.inspection_date} />
          <InfoRow label="Inspector"     value={inspection.inspector?.name} />
          <InfoRow label="Remarks"       value={inspection.remarks} />
        </dl>
      </div>

      {/* Results */}
      {(inspection.results ?? []).length > 0 && (
        <div className="bg-white border border-gray-200 rounded-xl p-6 mb-5">
          <h2 className="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">Inspection Results</h2>
          <table className="min-w-full text-sm">
            <thead className="bg-gray-50">
              <tr>
                {['Criterion', 'Actual Value', 'Conforming', 'Remarks'].map((h) => (
                  <th key={h} className="px-3 py-2 text-left text-xs font-semibold text-gray-400 uppercase">{h}</th>
                ))}
              </tr>
            </thead>
            <tbody className="divide-y divide-gray-100">
              {inspection.results?.map((r) => (
                <tr key={r.id}>
                  <td className="px-3 py-2">{r.criterion}</td>
                  <td className="px-3 py-2 text-gray-500">{r.actual_value ?? '—'}</td>
                  <td className="px-3 py-2">
                    {r.is_conforming === null
                      ? <span className="text-gray-400">—</span>
                      : r.is_conforming
                        ? <span className="inline-flex px-2 py-0.5 rounded-full text-xs font-semibold bg-green-100 text-green-700">Yes</span>
                        : <span className="inline-flex px-2 py-0.5 rounded-full text-xs font-semibold bg-red-100 text-red-700">No</span>}
                  </td>
                  <td className="px-3 py-2 text-gray-400 text-xs">{r.remarks ?? '—'}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}

      {/* NCRs */}
      {(inspection.ncrs ?? []).length > 0 && (
        <div className="bg-white border border-gray-200 rounded-xl p-6">
          <h2 className="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">Non-Conformance Reports</h2>
          {inspection.ncrs?.map((ncr) => (
            <div key={ncr.id} className="flex items-center justify-between py-2 border-b border-gray-100 last:border-0">
              <div>
                <span className="font-mono text-sm text-teal-700 font-medium">{ncr.ncr_reference}</span>
                <span className="ml-3 text-sm text-gray-600">{ncr.title}</span>
              </div>
              <span className={`inline-flex px-2 py-0.5 rounded-full text-xs font-semibold capitalize ${
                ncr.severity === 'critical' ? 'bg-red-100 text-red-700' :
                ncr.severity === 'major'    ? 'bg-orange-100 text-orange-700' :
                                              'bg-yellow-100 text-yellow-700'
              }`}>{ncr.severity}</span>
            </div>
          ))}
        </div>
      )}
    </div>
  )
}
