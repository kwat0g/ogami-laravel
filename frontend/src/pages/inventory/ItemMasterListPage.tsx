import { useState, useCallback } from 'react'
import { Link } from 'react-router-dom'
import { Plus, AlertTriangle, Tags, Trash2, X } from 'lucide-react'
import { useItems, useItemCategories, useCreateItemCategory } from '@/hooks/useInventory'
import SkeletonLoader from '@/components/ui/SkeletonLoader'
import { PageHeader } from '@/components/ui/PageHeader'
import SearchInput from '@/components/ui/SearchInput'
import { useAuthStore } from '@/stores/authStore'
import { PERMISSIONS } from '@/lib/permissions'
import { useQuery } from '@tanstack/react-query'
import ArchiveToggleButton from '@/components/ui/ArchiveToggleButton'
import { Card, CardHeader, CardBody } from '@/components/ui/Card'
import StatusBadge from '@/components/ui/StatusBadge'
import ConfirmDestructiveDialog from '@/components/ui/ConfirmDestructiveDialog'
import api from '@/lib/api'
import { firstErrorMessage } from '@/lib/errorHandler'
import { toast } from 'sonner'
import { ExportButton } from '@/components/ui/ExportButton'
import type { ItemMaster, ItemCategory } from '@/types/inventory'

// ---------------------------------------------------------------------------
// Item Categories Modal
// ---------------------------------------------------------------------------

function ItemCategoriesModal({ onClose }: { onClose: () => void }) {
  const { data: categories, isLoading, refetch } = useItemCategories()
  const { mutate: create, isPending } = useCreateItemCategory()
  const canCreate = useAuthStore(s => s.hasPermission(PERMISSIONS.inventory.items.create))
  const canDelete = useAuthStore(s => s.hasPermission(PERMISSIONS.inventory.items.delete))
  const [form, setForm] = useState({ code: '', name: '', description: '' })
  const [showForm, setShowForm] = useState(false)

  const inputCls = 'w-full border border-neutral-300 rounded px-3 py-1.5 text-sm focus:ring-1 focus:ring-neutral-400 focus:outline-none'

  function handleCreate(e: React.FormEvent) {
    e.preventDefault()
    create(
      { code: form.name.trim().toUpperCase().replace(/[^A-Z0-9]+/g, '-').replace(/^-|-$/g, '').slice(0, 10), name: form.name.trim(), description: form.description.trim() || undefined },
      {
        onSuccess: () => {
          toast.success('Category created.')
          setForm({ code: '', name: '', description: '' })
          setShowForm(false)
        },
        onError: (err) => toast.error(firstErrorMessage(err)),
      },
    )
  }

  async function handleDelete(id: number) {
    try {
      await api.delete(`/inventory/items/categories/${id}`)
      toast.success('Category deleted.')
      refetch()
    } catch (err) {
      toast.error(firstErrorMessage(err))
    }
  }

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50">
      <div className="bg-white rounded-lg border border-neutral-200 w-full max-w-2xl max-h-[85vh] flex flex-col">
        <div className="flex items-center justify-between px-5 py-4 border-b border-neutral-200">
          <h2 className="text-base font-semibold text-neutral-900 flex items-center gap-2">
            <Tags className="w-4 h-4 text-neutral-500" /> Item Categories
          </h2>
          <button onClick={onClose} className="p-1 rounded hover:bg-neutral-100 text-neutral-500">
            <X className="w-4 h-4" />
          </button>
        </div>

        <div className="overflow-y-auto flex-1 p-5 space-y-4">
          {isLoading ? (
            <SkeletonLoader rows={4} />
          ) : (
            <table className="w-full text-sm">
              <thead className="bg-neutral-50 border-b border-neutral-200">
                <tr>
                  <th className="px-3 py-2 text-left text-xs font-medium text-neutral-600">Code</th>
                  <th className="px-3 py-2 text-left text-xs font-medium text-neutral-600">Name</th>
                  <th className="px-3 py-2 text-left text-xs font-medium text-neutral-600">Description</th>
                  {canDelete && <th className="px-3 py-2" />}
                </tr>
              </thead>
              <tbody className="divide-y divide-neutral-100">
                {(!categories || categories.length === 0) && (
                  <tr><td colSpan={4} className="px-3 py-6 text-center text-neutral-400 text-sm">No categories yet.</td></tr>
                )}
                {categories?.map((cat: ItemCategory) => (
                  <tr key={cat.id} className="hover:bg-neutral-50">
                    <td className="px-3 py-2 font-mono font-medium text-neutral-900">{cat.code}</td>
                    <td className="px-3 py-2 font-medium text-neutral-900">{cat.name}</td>
                    <td className="px-3 py-2 text-neutral-500">{cat.description ?? '—'}</td>
                    {canDelete && (
                      <td className="px-3 py-2">
                        <ConfirmDestructiveDialog
                          title="Delete category?"
                          description={`This will permanently delete "${cat.name}". Items using this category must be reassigned first.`}
                          confirmWord="DELETE"
                          confirmLabel="Delete"
                          onConfirm={() => handleDelete(cat.id)}
                        >
                          <button type="button" className="p-1 rounded hover:bg-red-50 text-neutral-400 hover:text-red-500">
                            <Trash2 className="w-3.5 h-3.5" />
                          </button>
                        </ConfirmDestructiveDialog>
                      </td>
                    )}
                  </tr>
                ))}
              </tbody>
            </table>
          )}

          {canCreate && (
            showForm ? (
              <form onSubmit={handleCreate} className="border border-neutral-200 rounded p-3 space-y-3 bg-neutral-50">
                <p className="text-xs font-semibold text-neutral-600 uppercase tracking-wide">New Category</p>
                <div className="grid grid-cols-2 gap-3">
                  <div>
                    <label className="block text-xs font-medium text-neutral-600 mb-1">Code <span className="text-neutral-400 text-[10px]">(auto from name)</span></label>
                    <input className={`${inputCls} bg-neutral-50 text-neutral-500 font-mono`} value={form.name ? form.name.toUpperCase().replace(/[^A-Z0-9]+/g, '-').replace(/^-|-$/g, '').slice(0, 10) : ''} readOnly disabled />
                  </div>
                  <div>
                    <label className="block text-xs font-medium text-neutral-600 mb-1">Name <span className="text-red-500">*</span></label>
                    <input className={inputCls} value={form.name} onChange={e => setForm(f => ({ ...f, name: e.target.value }))} placeholder="e.g. Raw Materials" required minLength={2} />
                  </div>
                </div>
                <div>
                  <label className="block text-xs font-medium text-neutral-600 mb-1">Description</label>
                  <input className={inputCls} value={form.description} onChange={e => setForm(f => ({ ...f, description: e.target.value }))} placeholder="Optional" />
                </div>
                <div className="flex gap-2">
                  <button type="submit" disabled={isPending} className="px-3 py-1.5 bg-neutral-900 text-white text-xs font-medium rounded hover:bg-neutral-800 disabled:opacity-50">
                    {isPending ? 'Saving…' : 'Save'}
                  </button>
                  <button type="button" onClick={() => setShowForm(false)} className="px-3 py-1.5 border border-neutral-300 text-xs rounded hover:bg-neutral-50">Cancel</button>
                </div>
              </form>
            ) : (
              <button onClick={() => setShowForm(true)} className="flex items-center gap-1.5 text-sm text-neutral-600 hover:text-neutral-900">
                <Plus className="w-4 h-4" /> Add Category
              </button>
            )
          )}
        </div>
      </div>
    </div>
  )
}

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
  const [debouncedSearch, setDebouncedSearch] = useState('')
  const [typeFilter, setType]     = useState('')
  const [catFilter,  setCat]      = useState<number | ''>('')
  const [activeOnly, setActive]   = useState(false)
  const [page, setPage]           = useState(1)
  const [isArchiveView, setIsArchiveView] = useState(false)

  const handleSearch = useCallback((val: string) => {
    setDebouncedSearch(val)
    setPage(1)
  }, [])
  const [showCategories, setShowCategories] = useState(false)
  const { hasPermission } = useAuthStore()
  const canCreate = hasPermission(PERMISSIONS.inventory.items.create)
  const canEdit   = hasPermission(PERMISSIONS.inventory.items.edit)

  const { data: categories } = useItemCategories()
  const { data, isLoading, isError } = useItems({
    search: debouncedSearch || undefined,
    type: typeFilter || undefined,
    category_id: catFilter || undefined,
    is_active: activeOnly || undefined,
    page,
    per_page: 20,
    with_archived: undefined,
  })

  return (
    <div>
      <PageHeader
        title="Item Master"
        actions={
          <div className="flex items-center gap-2">
            <ExportButton
              data={data?.data ?? []}
              columns={[
                { key: 'item_code', label: 'Code' },
                { key: 'name', label: 'Name' },
                { key: 'category.name', label: 'Category' },
                { key: 'uom', label: 'UOM' },
                { key: 'reorder_point', label: 'Reorder Point' },
                { key: 'is_active', label: 'Active', format: (v: unknown) => v ? 'Yes' : 'No' },
              ]}
              filename="inventory-items"
            />
            <button
              onClick={() => setShowCategories(true)}
              className="flex items-center gap-2 px-3 py-2 border border-neutral-300 text-neutral-700 text-sm rounded hover:bg-neutral-50"
            >
              <Tags className="w-4 h-4" />
              Categories
            </button>
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
        }
      />

      {/* Filters */}
      <div className="flex flex-wrap items-center gap-3 mb-5">
        <SearchInput
          value={search}
          onChange={setSearch}
          onSearch={handleSearch}
          placeholder="Search code or name..."
          className="w-52"
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
        <ArchiveToggleButton isArchiveView={isArchiveView} onToggle={() => setIsArchiveView(prev => !prev)} />
      </div>

      {isLoading && <SkeletonLoader rows={8} />}
      {isError && (
        <div className="flex items-center gap-2 text-red-600 text-sm">
          <AlertTriangle className="w-4 h-4" /> Failed to load items.
        </div>
      )}

      {!isLoading && !isError && (
        <>
          <Card>
            <CardHeader>Item Master</CardHeader>
            <CardBody className="p-0">
              <table className="min-w-full text-sm">
                <thead className="bg-neutral-50 border-b border-neutral-200">
                  <tr>
                    {['Item Code', 'Name', 'Category', 'Type', 'UOM', 'Std Price', 'Reorder Pt.', 'IQC', 'Status', ''].map((h) => (
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
                    <tr key={item.id} className="hover:bg-neutral-50/50 transition-colors">
                      <td className="px-4 py-3 font-mono text-neutral-900 font-medium">{item.item_code}</td>
                      <td className="px-4 py-3 text-neutral-900 font-medium">{item.name}</td>
                      <td className="px-4 py-3 text-neutral-500">{item.category?.name ?? '—'}</td>
                      <td className="px-4 py-3">
                        <StatusBadge className={typeBadge[item.type]}>
                          {TYPE_LABELS[item.type]}
                        </StatusBadge>
                      </td>
                      <td className="px-4 py-3 text-neutral-500">{item.unit_of_measure}</td>
                      <td className="px-4 py-3 text-neutral-700 font-mono tabular-nums text-xs">
                        {(item.standard_price_centavos ?? 0) > 0
                          ? new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' }).format((item.standard_price_centavos ?? 0) / 100)
                          : <span className="text-neutral-300">--</span>}
                      </td>
                      <td className="px-4 py-3 text-neutral-500">{item.reorder_point}</td>
                      <td className="px-4 py-3">
                        {item.requires_iqc
                          ? <StatusBadge>Yes</StatusBadge>
                          : <span className="text-neutral-400 text-xs">No</span>}
                      </td>
                      <td className="px-4 py-3">
                        {item.deleted_at && <StatusBadge className="bg-neutral-100 text-neutral-500 mr-1">Archived</StatusBadge>}
                        {item.is_active
                          ? <StatusBadge className="bg-neutral-200 text-neutral-800">Active</StatusBadge>
                          : <StatusBadge className="bg-neutral-100 text-neutral-500">Inactive</StatusBadge>}
                      </td>
                      <td className="px-4 py-3">
                        {canEdit ? (
                          <Link to={`/inventory/items/${item.ulid}`} className="inline-block px-2 py-1 text-xs border border-neutral-300 rounded bg-white text-neutral-600 hover:bg-neutral-50 hover:border-neutral-400 hover:text-neutral-900 font-medium">
                            Edit →
                          </Link>
                        ) : (
                          <span className="text-neutral-400 text-xs italic">View Only</span>
                        )}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </CardBody>
          </Card>

          {/* Pagination */}
          {data && data.meta.last_page > 1 && (
            <div className="flex items-center justify-between mt-4 text-sm text-neutral-600">
              <span>Page {data.meta.current_page} of {data.meta.last_page} · {data.meta.total} items</span>
              <div className="flex gap-2">
                <button
                  onClick={() => setPage((p) => Math.max(1, p - 1))}
                  disabled={page === 1}
                  className="px-3 py-1.5 border border-neutral-300 rounded disabled:opacity-40 disabled:cursor-not-allowed hover:bg-neutral-50"
                >
                  Previous
                </button>
                <button
                  onClick={() => setPage((p) => p + 1)}
                  disabled={page >= data.meta.last_page}
                  className="px-3 py-1.5 border border-neutral-300 rounded disabled:opacity-40 disabled:cursor-not-allowed hover:bg-neutral-50"
                >
                  Next
                </button>
              </div>
            </div>
          )}
        </>
      )}

      {showCategories && <ItemCategoriesModal onClose={() => setShowCategories(false)} />}
    </div>
  )
}
