import { useState } from 'react'
import { Link } from 'react-router-dom'
import { Plus, AlertTriangle } from 'lucide-react'
import { useItems, useItemCategories } from '@/hooks/useInventory'
import SkeletonLoader from '@/components/ui/SkeletonLoader'
import { useAuthStore } from '@/stores/authStore'
import type { ItemMaster } from '@/types/inventory'

const TYPE_LABELS: Record<ItemMaster['type'], string> = {
  raw_material:   'Raw Material',
  semi_finished:  'Semi-Finished',
  finished_good:  'Finished Good',
  consumable:     'Consumable',
  spare_part:     'Spare Part',
}

const typeBadge: Record<ItemMaster['type'], string> = {
  raw_material:   'bg-neutral-100 text-neutral-700',
  semi_finished:  'bg-neutral-100 text-neutral-700',
  finished_good:  'bg-neutral-100 text-neutral-700',
  consumable:     'bg-neutral-100 text-neutral-700',
  spare_part:     'bg-neutral-100 text-neutral-700',
}

export default function ItemMasterListPage(): React.ReactElement {
  const [search, setSearch]       = useState('')
  const [typeFilter, setType]     = useState('')
  const [catFilter,  setCat]      = useState<number | ''>('')
  const [activeOnly, setActive]   = useState(true)
  const [page, setPage]           = useState(1)
  const [withArchived, setWithArchived] = useState(false)
  const { hasPermission } = useAuthStore()
  const canCreate = hasPermission('inventory.items.create')

  const { data: categories } = useItemCategories()
  const { data, isLoading, isError } = useItems({
    search: search || undefined,
    type: typeFilter || undefined,
    category_id: catFilter || undefined,
    is_active: activeOnly || undefined,
    page,
    per_page: 20,
    with_archived: withArchived || undefined,
  })

  return (
    <div>
      <div className="flex items-center justify-between mb-6">
        <h1 className="text-lg font-semibold text-neutral-900">Item Master</h1>
        {canCreate && (
          <Link
            to="/inventory/items/new"
            className="flex items-center gap-2 px-4 py-2 bg-neutral-900 hover:bg-neutral-800 text-white text-sm font-medium rounded"
          >
            <Plus className="w-4 h-4" />
            New Item
          </Link>
        )}
      </div>

      {/* Filters */}
      <div className="flex flex-wrap items-center gap-3 mb-5">
        <input
          type="text"
          placeholder="Search code or name…"
          value={search}
          onChange={(e) => { setSearch(e.target.value); setPage(1) }}
          className="text-sm border border-neutral-300 rounded px-3 py-2 focus:outline-none focus:ring-1 focus:ring-neutral-400 bg-white w-52"
        />
        <select
          value={typeFilter}
          onChange={(e) => { setType(e.target.value); setPage(1) }}
          className="text-sm border border-neutral-300 rounded px-3 py-2 focus:outline-none focus:ring-1 focus:ring-neutral-400 bg-white"
        >
          <option value="">All Types</option>
          {(Object.keys(TYPE_LABELS) as ItemMaster['type'][]).map((t) => (
            <option key={t} value={t}>{TYPE_LABELS[t]}</option>
          ))}
        </select>
        <select
          value={catFilter}
          onChange={(e) => { setCat(e.target.value ? Number(e.target.value) : ''); setPage(1) }}
          className="text-sm border border-neutral-300 rounded px-3 py-2 focus:outline-none focus:ring-1 focus:ring-neutral-400 bg-white"
        >
          <option value="">All Categories</option>
          {categories?.map((c) => <option key={c.id} value={c.id}>{c.name}</option>)}
        </select>
        <label className="flex items-center gap-2 text-sm text-neutral-600 cursor-pointer">
          <input
            type="checkbox"
            checked={activeOnly}
            onChange={(e) => { setActive(e.target.checked); setPage(1) }}
            className="rounded border-neutral-300"
          />
          Active only
        </label>
        <label className="flex items-center gap-2 text-sm text-neutral-600 cursor-pointer select-none">
          <input type="checkbox" checked={withArchived} onChange={(e) => setWithArchived(e.target.checked)} className="rounded border-neutral-300" />
          <span>Show Archived</span>
        </label>
      </div>

      {isLoading && <SkeletonLoader rows={8} />}
      {isError && (
        <div className="flex items-center gap-2 text-red-600 text-sm">
          <AlertTriangle className="w-4 h-4" /> Failed to load items.
        </div>
      )}

      {!isLoading && !isError && (
        <>
          <div className="bg-white border border-neutral-200 rounded overflow-hidden">
            <table className="min-w-full text-sm">
              <thead className="bg-neutral-50 border-b border-neutral-200">
                <tr>
                  {['Item Code', 'Name', 'Category', 'Type', 'UOM', 'Reorder Pt.', 'IQC', 'Status', ''].map((h) => (
                    <th key={h} className="px-4 py-3 text-left text-xs font-medium text-neutral-600">{h}</th>
                  ))}
                </tr>
              </thead>
              <tbody className="divide-y divide-neutral-100">
                {data?.data?.length === 0 && (
                  <tr>
                    <td colSpan={9} className="px-4 py-8 text-center text-neutral-400 text-sm">No items found.</td>
                  </tr>
                )}
                {data?.data?.map((item) => (
                  <tr key={item.id} className="even:bg-neutral-100 hover:bg-neutral-50">
                    <td className="px-4 py-3 font-mono text-neutral-900 font-medium">{item.item_code}</td>
                    <td className="px-4 py-3 text-neutral-900 font-medium">{item.name}</td>
                    <td className="px-4 py-3 text-neutral-500">{item.category?.name ?? '—'}</td>
                    <td className="px-4 py-3">
                      <span className={`inline-flex px-2 py-0.5 rounded text-xs font-medium ${typeBadge[item.type]}`}>
                        {TYPE_LABELS[item.type]}
                      </span>
                    </td>
                    <td className="px-4 py-3 text-neutral-500">{item.unit_of_measure}</td>
                    <td className="px-4 py-3 text-neutral-500">{item.reorder_point}</td>
                    <td className="px-4 py-3">
                      {item.requires_iqc
                        ? <span className="inline-flex px-2 py-0.5 rounded text-xs font-medium bg-neutral-100 text-neutral-700">Yes</span>
                        : <span className="text-neutral-400 text-xs">No</span>}
                    </td>
                    <td className="px-4 py-3">
                      {item.deleted_at && <span className="inline-flex px-2 py-0.5 rounded text-xs font-medium bg-neutral-100 text-neutral-500 mr-1">Archived</span>}
                      {item.is_active
                        ? <span className="inline-flex px-2 py-0.5 rounded text-xs font-medium bg-neutral-200 text-neutral-800">Active</span>
                        : <span className="inline-flex px-2 py-0.5 rounded text-xs font-medium bg-neutral-100 text-neutral-500">Inactive</span>}
                    </td>
                    <td className="px-4 py-3">
                      <Link to={`/inventory/items/${item.ulid}`} className="inline-block px-2 py-1 text-xs border border-neutral-200 rounded bg-white text-neutral-600 hover:bg-neutral-50 hover:border-neutral-300 hover:text-neutral-900 font-medium">
                        Edit →
                      </Link>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>

          {/* Pagination */}
          {data && data.meta.last_page > 1 && (
            <div className="flex items-center justify-between mt-4 text-sm text-neutral-600">
              <span>Page {data.meta.current_page} of {data.meta.last_page} · {data.meta.total} items</span>
              <div className="flex gap-2">
                <button
                  onClick={() => setPage((p) => Math.max(1, p - 1))}
                  disabled={page === 1}
                  className="px-3 py-1.5 border border-neutral-300 rounded disabled:opacity-40 hover:bg-neutral-50"
                >
                  Previous
                </button>
                <button
                  onClick={() => setPage((p) => p + 1)}
                  disabled={page >= data.meta.last_page}
                  className="px-3 py-1.5 border border-neutral-300 rounded disabled:opacity-40 hover:bg-neutral-50"
                >
                  Next
                </button>
              </div>
            </div>
          )}
        </>
      )}
    </div>
  )
}
