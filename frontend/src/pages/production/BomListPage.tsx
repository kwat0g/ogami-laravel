import { useState } from 'react'
import { Link } from 'react-router-dom'
import { GitBranch, AlertTriangle, Plus } from 'lucide-react'
import { useBoms } from '@/hooks/useProduction'
import { useAuthStore } from '@/stores/authStore'
import SkeletonLoader from '@/components/ui/SkeletonLoader'

export default function BomListPage(): React.ReactElement {
  const [page, setPage] = useState(1)
  const [withArchived, setWithArchived] = useState(false)
  const { data, isLoading, isError } = useBoms({ per_page: 20, with_archived: withArchived || undefined })
  const { hasPermission } = useAuthStore()
  const canCreate = hasPermission('production.bom.create')

  return (
    <div>
      <div className="flex items-center justify-between mb-6">
        <div className="flex items-center gap-3">
          <div className="w-10 h-10 bg-violet-100 rounded-xl flex items-center justify-center">
            <GitBranch className="w-5 h-5 text-violet-600" />
          </div>
          <div>
            <h1 className="text-2xl font-bold text-gray-900">Bill of Materials</h1>
            <p className="text-sm text-gray-500 mt-0.5">Component recipes for manufactured items</p>
          </div>
        </div>
        {canCreate && (
          <Link
            to="/production/boms/new"
            className="inline-flex items-center gap-1.5 bg-violet-600 hover:bg-violet-700 text-white text-sm font-medium px-4 py-2 rounded-lg transition-colors"
          >
            <Plus className="w-4 h-4" />
            New BOM
          </Link>
        )}
      </div>

      <div className="flex flex-wrap gap-3 mb-5">
        <label className="flex items-center gap-2 text-sm text-gray-600 cursor-pointer select-none">
          <input type="checkbox" checked={withArchived} onChange={(e) => setWithArchived(e.target.checked)} className="rounded border-gray-300 text-violet-600" />
          <span>Show Archived</span>
        </label>
      </div>

      {isLoading && <SkeletonLoader rows={8} />}
      {isError && (
        <div className="flex items-center gap-2 text-red-600 text-sm">
          <AlertTriangle className="w-4 h-4" /> Failed to load BOMs.
        </div>
      )}

      {!isLoading && !isError && (
        <>
          <div className="bg-white border border-gray-200 rounded-xl overflow-hidden">
            <table className="min-w-full text-sm">
              <thead className="bg-gray-50 border-b border-gray-200">
                <tr>
                  {['Product Item', 'Version', 'Components', 'Status', 'Created'].map((h) => (
                    <th key={h} className="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">{h}</th>
                  ))}
                </tr>
              </thead>
              <tbody className="divide-y divide-gray-100">
                {data?.data?.length === 0 && (
                  <tr>
                    <td colSpan={5} className="px-4 py-8 text-center text-gray-400 text-sm">No BOMs found.</td>
                  </tr>
                )}
                {data?.data?.map((bom) => (
                  <tr key={bom.id} className="hover:bg-gray-50">
                    <td className="px-4 py-3">
                      <div className="font-mono text-violet-700 font-medium text-xs">{bom.product_item?.item_code}</div>
                      <div className="text-gray-700 text-sm">{bom.product_item?.name}</div>
                    </td>
                    <td className="px-4 py-3 text-gray-500">v{bom.version}</td>
                    <td className="px-4 py-3 text-gray-500">{bom.components?.length ?? '—'} items</td>
                    <td className="px-4 py-3">
                      {bom.deleted_at && <span className="inline-flex px-2 py-0.5 rounded-full text-xs font-semibold bg-orange-100 text-orange-700 mr-1">Archived</span>}
                      {bom.is_active
                        ? <span className="inline-flex px-2 py-0.5 rounded-full text-xs font-semibold bg-green-100 text-green-700">Active</span>
                        : <span className="inline-flex px-2 py-0.5 rounded-full text-xs font-semibold bg-gray-100 text-gray-500">Inactive</span>}
                    </td>
                    <td className="px-4 py-3 text-gray-400 text-xs">
                      {bom.created_at ? new Date(bom.created_at).toLocaleDateString('en-PH') : '—'}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
          {data && data.meta.last_page > 1 && (
            <div className="flex items-center justify-between mt-4 text-sm text-gray-600">
              <span>Page {data.meta.current_page} of {data.meta.last_page}</span>
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
