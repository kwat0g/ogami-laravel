import { useState, useCallback } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { AlertTriangle, Plus, Eye } from 'lucide-react'
import { PageHeader } from '@/components/ui/PageHeader'
import SearchInput from '@/components/ui/SearchInput'
import Pagination from '@/components/ui/Pagination'
import { useBoms } from '@/hooks/useProduction'
import { useAuthStore } from '@/stores/authStore'
import SkeletonLoader from '@/components/ui/SkeletonLoader'
import { ExportButton } from '@/components/ui/ExportButton'

export default function BomListPage(): React.ReactElement {
  const [page, setPage] = useState(1)
  const [withArchived, setWithArchived] = useState(false)
  const [search, setSearch] = useState('')
  const [debouncedSearch, setDebouncedSearch] = useState('')

  const handleSearch = useCallback((val: string) => {
    setDebouncedSearch(val)
    setPage(1)
  }, [])

  const { data, isLoading, isError } = useBoms({ per_page: 20, with_archived: withArchived || undefined, ...(debouncedSearch ? { search: debouncedSearch } : {}) })
  const { hasPermission } = useAuthStore()
  const canCreate = hasPermission('production.bom.manage')
  const navigate  = useNavigate()

  return (
    <div>
      <PageHeader
        title="Bill of Materials"
        actions={
          <div className="flex items-center gap-2">
            <ExportButton
              data={data?.data ?? []}
              columns={[
                { key: 'product_item.item_code', label: 'Item Code' },
                { key: 'product_item.name', label: 'Product' },
                { key: 'version', label: 'Version' },
                { key: 'is_active', label: 'Active', format: (v: unknown) => v ? 'Yes' : 'No' },
              ]}
              filename="bill-of-materials"
            />
            {canCreate && (
              <Link
                to="/production/boms/new"
                className="inline-flex items-center gap-1.5 bg-neutral-900 hover:bg-neutral-800 text-white text-sm font-medium px-4 py-2 rounded transition-colors"
              >
                <Plus className="w-4 h-4" />
                New BOM
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
          placeholder="Search BOMs..."
          className="w-64"
        />
        <label className="flex items-center gap-2 text-sm text-neutral-600 cursor-pointer select-none">
          <input type="checkbox" checked={withArchived} onChange={(e) => setWithArchived(e.target.checked)} className="rounded border-neutral-300" />
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
          <div className="bg-white border border-neutral-200 rounded overflow-hidden">
            <table className="min-w-full text-sm">
              <thead className="bg-neutral-50 border-b border-neutral-200">
                <tr>
                  {['Product Item', 'Version', 'Components', 'Status', 'Created', ''].map((h) => (
                    <th key={h} className="px-4 py-3 text-left text-xs font-medium text-neutral-600">{h}</th>
                  ))}
                </tr>
              </thead>
              <tbody className="divide-y divide-neutral-100">
                {data?.data?.length === 0 && (
                  <tr>
                    <td colSpan={6} className="px-4 py-8 text-center text-neutral-400 text-sm">No BOMs found.</td>
                  </tr>
                )}
                {data?.data?.map((bom) => (
                  <tr
                    key={bom.id}
                    onClick={() => navigate(`/production/boms/${bom.ulid}`)}
                    className="hover:bg-neutral-50 cursor-pointer transition-colors"
                  >
                    <td className="px-4 py-3">
                      <div className="font-mono text-neutral-900 font-medium text-xs">{bom.product_item?.item_code}</div>
                      <div className="text-neutral-700 text-sm">{bom.product_item?.name}</div>
                    </td>
                    <td className="px-4 py-3 text-neutral-500">v{bom.version}</td>
                    <td className="px-4 py-3 text-neutral-500">{bom.components?.length ?? '—'} items</td>
                    <td className="px-4 py-3">
                      {bom.deleted_at && <span className="inline-flex px-2 py-0.5 rounded text-xs font-medium bg-neutral-100 text-neutral-500 mr-1">Archived</span>}
                      {bom.is_active
                        ? <span className="inline-flex px-2 py-0.5 rounded text-xs font-medium bg-neutral-200 text-neutral-800">Active</span>
                        : <span className="inline-flex px-2 py-0.5 rounded text-xs font-medium bg-neutral-100 text-neutral-500">Inactive</span>}
                    </td>
                    <td className="px-4 py-3 text-neutral-400 text-xs">
                      {bom.created_at ? new Date(bom.created_at).toLocaleDateString('en-PH') : '—'}
                    </td>
                    <td className="px-4 py-3 text-right">
                      <Link
                        to={`/production/boms/${bom.ulid}`}
                        onClick={(e) => e.stopPropagation()}
                        className="inline-flex items-center gap-1 px-2.5 py-1 text-xs border border-neutral-200 rounded bg-white text-neutral-600 hover:bg-neutral-50 hover:border-neutral-300 hover:text-neutral-900 font-medium"
                      >
                        <Eye className="w-3.5 h-3.5" /> View
                      </Link>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
          {data?.meta && <Pagination meta={data.meta} onPageChange={setPage} />}
        </>
      )}
    </div>
  )
}
