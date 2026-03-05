import { useState } from 'react'
import { Link } from 'react-router-dom'
import { Package, Plus, AlertTriangle } from 'lucide-react'
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
  raw_material:   'bg-blue-100 text-blue-700',
  semi_finished:  'bg-purple-100 text-purple-700',
  finished_good:  'bg-green-100 text-green-700',
  consumable:     'bg-yellow-100 text-yellow-700',
  spare_part:     'bg-orange-100 text-orange-700',
}

export default function ItemMasterListPage(): React.ReactElement {
  const [search, setSearch]       = useState('')
  const [typeFilter, setType]     = useState('')
  const [catFilter,  setCat]      = useState<number | ''>('')
  const [activeOnly, setActive]   = useState(true)
  const [page, setPage]           = useState(1)
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
  })

  return (
    <div>
      {/* Header */}
      <div className="flex items-center justify-between mb-6">
        <div className="flex items-center gap-3">
          <div className="w-10 h-10 bg-teal-100 rounded-xl flex items-center justify-center">
            <Package className="w-5 h-5 text-teal-600" />
          </div>
          <div>
            <h1 className="text-2xl font-bold text-gray-900">Item Master</h1>
            <p className="text-sm text-gray-500 mt-0.5">Manage inventory items and materials</p>
          </div>
        </div>
        {canCreate && (
          <Link
            to="/inventory/items/new"
            className="flex items-center gap-2 px-4 py-2.5 bg-teal-600 hover:bg-teal-700 text-white text-sm font-medium rounded-xl transition-colors"
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
          className="text-sm border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-teal-500 bg-white w-52"
        />
        <select
          value={typeFilter}
          onChange={(e) => { setType(e.target.value); setPage(1) }}
          className="text-sm border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-teal-500 bg-white"
        >
          <option value="">All Types</option>
          {(Object.keys(TYPE_LABELS) as ItemMaster['type'][]).map((t) => (
            <option key={t} value={t}>{TYPE_LABELS[t]}</option>
          ))}
        </select>
        <select
          value={catFilter}
          onChange={(e) => { setCat(e.target.value ? Number(e.target.value) : ''); setPage(1) }}
          className="text-sm border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-teal-500 bg-white"
        >
          <option value="">All Categories</option>
          {categories?.map((c) => <option key={c.id} value={c.id}>{c.name}</option>)}
        </select>
        <label className="flex items-center gap-2 text-sm text-gray-600 cursor-pointer">
          <input
            type="checkbox"
            checked={activeOnly}
            onChange={(e) => { setActive(e.target.checked); setPage(1) }}
            className="rounded border-gray-300 text-teal-600 focus:ring-teal-500"
          />
          Active only
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
          <div className="bg-white border border-gray-200 rounded-xl overflow-hidden">
            <table className="min-w-full text-sm">
              <thead className="bg-gray-50 border-b border-gray-200">
                <tr>
                  {['Item Code', 'Name', 'Category', 'Type', 'UOM', 'Reorder Pt.', 'IQC', 'Status', ''].map((h) => (
                    <th key={h} className="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">{h}</th>
                  ))}
                </tr>
              </thead>
              <tbody className="divide-y divide-gray-100">
                {data?.data?.length === 0 && (
                  <tr>
                    <td colSpan={9} className="px-4 py-8 text-center text-gray-400 text-sm">No items found.</td>
                  </tr>
                )}
                {data?.data?.map((item) => (
                  <tr key={item.id} className="hover:bg-gray-50">
                    <td className="px-4 py-3 font-mono text-teal-700 font-medium">{item.item_code}</td>
                    <td className="px-4 py-3 text-gray-900 font-medium">{item.name}</td>
                    <td className="px-4 py-3 text-gray-500">{item.category?.name ?? '—'}</td>
                    <td className="px-4 py-3">
                      <span className={`inline-flex px-2 py-0.5 rounded-full text-xs font-semibold ${typeBadge[item.type]}`}>
                        {TYPE_LABELS[item.type]}
                      </span>
                    </td>
                    <td className="px-4 py-3 text-gray-500">{item.unit_of_measure}</td>
                    <td className="px-4 py-3 text-gray-500">{item.reorder_point}</td>
                    <td className="px-4 py-3">
                      {item.requires_iqc
                        ? <span className="inline-flex px-2 py-0.5 rounded-full text-xs font-semibold bg-blue-100 text-blue-700">Yes</span>
                        : <span className="text-gray-400 text-xs">No</span>}
                    </td>
                    <td className="px-4 py-3">
                      {item.is_active
                        ? <span className="inline-flex px-2 py-0.5 rounded-full text-xs font-semibold bg-green-100 text-green-700">Active</span>
                        : <span className="inline-flex px-2 py-0.5 rounded-full text-xs font-semibold bg-gray-100 text-gray-500">Inactive</span>}
                    </td>
                    <td className="px-4 py-3">
                      <Link to={`/inventory/items/${item.ulid}`} className="text-xs text-teal-600 hover:text-teal-800 font-medium">
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
            <div className="flex items-center justify-between mt-4 text-sm text-gray-600">
              <span>Page {data.meta.current_page} of {data.meta.last_page} · {data.meta.total} items</span>
              <div className="flex gap-2">
                <button
                  onClick={() => setPage((p) => Math.max(1, p - 1))}
                  disabled={page === 1}
                  className="px-3 py-1.5 border border-gray-300 rounded-lg disabled:opacity-40 hover:bg-gray-50"
                >
                  Previous
                </button>
                <button
                  onClick={() => setPage((p) => p + 1)}
                  disabled={page >= data.meta.last_page}
                  className="px-3 py-1.5 border border-gray-300 rounded-lg disabled:opacity-40 hover:bg-gray-50"
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
