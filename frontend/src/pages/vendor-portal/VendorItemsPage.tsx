import { useState, useRef } from 'react'
import { Plus, Pencil, Search, Upload, Package } from 'lucide-react'
import {
  useVendorPortalItems,
  useCreateVendorPortalItem,
  useUpdateVendorPortalItem,
  useImportVendorPortalItems,
  type VendorPortalItem,
} from '@/hooks/useVendorPortal'
import { useDebounce } from '@/hooks/useDebounce'
import { PageHeader } from '@/components/ui/PageHeader'
import { Card } from '@/components/ui/Card'
import SkeletonLoader from '@/components/ui/SkeletonLoader'
import { useAuthStore } from '@/stores/authStore'
import { PERMISSIONS } from '@/lib/permissions'
import { toast } from 'sonner'
import { statusBadges } from '@/styles/design-system'

const UOM_OPTIONS = [
  { value: 'pc', label: 'Piece (pc)' },
  { value: 'box', label: 'Box (box)' },
  { value: 'pack', label: 'Pack (pack)' },
  { value: 'set', label: 'Set (set)' },
  { value: 'pair', label: 'Pair (pair)' },
  { value: 'roll', label: 'Roll (roll)' },
  { value: 'kg', label: 'Kilogram (kg)' },
  { value: 'g', label: 'Gram (g)' },
  { value: 'l', label: 'Liter (L)' },
  { value: 'ml', label: 'Milliliter (mL)' },
]

const defaultForm = {
  item_code: '',
  item_name: '',
  description: '',
  unit_of_measure: 'pc',
  unit_price: 0,
  is_active: true,
}

export default function VendorItemsPage(): React.ReactElement {
  const [showAll, setShowAll] = useState(false)
  const [showForm, setShowForm] = useState(false)
  const [editing, setEditing] = useState<VendorPortalItem | null>(null)
  const [form, setForm] = useState(defaultForm)
  const [searchValue, setSearchValue] = useState('')
  const debouncedSearch = useDebounce(searchValue, 450)

  const { data: items, isLoading, isFetching } = useVendorPortalItems({ activeOnly: !showAll, search: debouncedSearch })
  const create = useCreateVendorPortalItem()
  const update = useUpdateVendorPortalItem()
  const importItems = useImportVendorPortalItems()
  const fileInputRef = useRef<HTMLInputElement>(null)
  const searchInputRef = useRef<HTMLInputElement>(null)
  const canManage = useAuthStore((s) => s.hasPermission(PERMISSIONS.vendor_portal.manage_items))
  const hasSearch = debouncedSearch.trim().length > 0
  const listItems = items ?? []
  const isInitialLoading = isLoading && listItems.length === 0

  function handleImport(e: React.ChangeEvent<HTMLInputElement>) {
    const file = e.target.files?.[0]
    if (!file) return
    importItems.mutate(file, {
      onSuccess: (result) => {
        toast.success(result.message)
        if (fileInputRef.current) fileInputRef.current.value = ''
      },
      onError: () => {
        if (fileInputRef.current) fileInputRef.current.value = ''
      },
    })
  }

  function openCreate() {
    setEditing(null)
    setForm(defaultForm)
    setShowForm(true)
  }

  function openEdit(item: VendorPortalItem) {
    setEditing(item)
    setForm({
      item_code: item.item_code,
      item_name: item.item_name,
      description: item.description ?? '',
      unit_of_measure: item.unit_of_measure,
      unit_price: item.unit_price / 100,
      is_active: item.is_active,
    })
    setShowForm(true)
  }

  function handleSubmit(e: React.FormEvent) {
    e.preventDefault()
    const payload = { ...form, unit_price: form.unit_price }

    if (editing) {
      update.mutate(
        { id: editing.id, ...payload },
        {
          onSuccess: () => { toast.success('Item updated.'); setShowForm(false) },
        }
      )
    } else {
      create.mutate(payload as Omit<VendorPortalItem, 'id' | 'ulid'>, {
        onSuccess: () => { toast.success('Item created.'); setShowForm(false) },
      })
    }
  }

  return (
    <div className="space-y-5">
      <PageHeader
        title="My Catalog"
        subtitle="Manage your product and service listings"
        icon={<Package className="h-5 w-5 text-neutral-600" />}
      />
      <div className="space-y-3">
        <div className="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
          <div className="flex items-center gap-2 w-full md:max-w-md">
            <div className="relative flex-1">
              <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-neutral-400" />
              <input
                ref={searchInputRef}
                type="text"
                placeholder="Search item name…"
                value={searchValue}
                onChange={(e) => setSearchValue(e.target.value)}
                className="input pl-9 pr-9"
              />
              {isFetching && (
                <div className="absolute right-3 top-1/2 -translate-y-1/2">
                  <div className="h-4 w-4 border-2 border-neutral-400 border-t-transparent rounded-full animate-spin" />
                </div>
              )}
            </div>
            {searchValue && (
              <button
                onClick={() => setSearchValue('')}
                className="px-3 py-2 text-sm bg-neutral-100 hover:bg-neutral-200 rounded text-neutral-700 transition-colors"
              >
                Clear
              </button>
            )}
          </div>
          <div className="flex flex-wrap items-center gap-2">
            <label className="flex items-center gap-1.5 text-sm text-neutral-600 cursor-pointer">
              <input
                type="checkbox"
                checked={showAll}
                onChange={(e) => setShowAll(e.target.checked)}
                className="rounded"
              />
              Show inactive
            </label>
            {canManage && (
              <>
                <input
                  ref={fileInputRef}
                  type="file"
                  accept=".csv,.xlsx,.xls"
                  onChange={handleImport}
                  className="hidden"
                />
                <button
                  onClick={() => fileInputRef.current?.click()}
                  disabled={importItems.isPending}
                  className="flex items-center gap-1.5 text-sm bg-white text-neutral-700 border border-neutral-300 rounded-md px-3 py-1.5 hover:bg-neutral-50 disabled:opacity-50"
                >
                  <Upload className="w-3.5 h-3.5" />
                  {importItems.isPending ? 'Importing…' : 'Import CSV'}
                </button>
                <button
                  onClick={openCreate}
                  className="flex items-center gap-1.5 text-sm bg-neutral-900 text-white rounded-md px-3 py-1.5 hover:bg-neutral-800"
                >
                  <Plus className="w-3.5 h-3.5" />
                  Add Item
                </button>
              </>
            )}
          </div>
        </div>
      </div>

      {showForm && canManage && (
        <div className="bg-white border border-neutral-200 rounded-lg mb-6 overflow-hidden">
          <div className="flex items-start justify-between px-5 py-4 border-b border-neutral-200">
            <div>
              <h2 className="text-sm font-semibold text-neutral-900">
                {editing ? 'Edit Item' : 'New Item'}
              </h2>
              <p className="text-xs text-neutral-500 mt-1">
                Keep item codes consistent for cleaner procurement matching.
              </p>
            </div>
          </div>
          <form onSubmit={handleSubmit}>
            <div className="grid grid-cols-1 lg:grid-cols-[minmax(0,2fr)_minmax(0,1fr)] gap-6 p-5">
              <div className="space-y-4">
                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <Field label="Item Code">
                    <input
                      value={form.item_code}
                      onChange={(e) => setForm((f) => ({ ...f, item_code: e.target.value }))}
                      required
                      disabled={!!editing}
                      className="input"
                      placeholder="SKU-0001"
                    />
                  </Field>
                  <Field label="Item Name">
                    <input
                      value={form.item_name}
                      onChange={(e) => setForm((f) => ({ ...f, item_name: e.target.value }))}
                      required
                      className="input"
                      placeholder="Product name"
                    />
                  </Field>
                </div>
                <Field label="Description">
                  <textarea
                    value={form.description}
                    onChange={(e) => setForm((f) => ({ ...f, description: e.target.value }))}
                    rows={4}
                    className="input resize-none"
                    placeholder="Short description (optional)"
                  />
                </Field>
              </div>
              <div className="bg-neutral-50 border border-neutral-200 rounded-lg p-4 space-y-4">
                <Field label="Unit of Measure">
                  <select
                    value={form.unit_of_measure}
                    onChange={(e) => setForm((f) => ({ ...f, unit_of_measure: e.target.value }))}
                    required
                    className="input"
                  >
                    {!UOM_OPTIONS.some((opt) => opt.value === form.unit_of_measure) && form.unit_of_measure ? (
                      <option value={form.unit_of_measure}>{form.unit_of_measure} (custom)</option>
                    ) : null}
                    {UOM_OPTIONS.map((option) => (
                      <option key={option.value} value={option.value}>{option.label}</option>
                    ))}
                  </select>
                </Field>
                <Field label="Unit Price (₱)">
                  <input
                    type="number"
                    min={0}
                    step="0.01"
                    value={form.unit_price}
                    onChange={(e) => setForm((f) => ({ ...f, unit_price: Number(e.target.value) }))}
                    required
                    className="input"
                  />
                </Field>
                <label className="flex items-center gap-2 text-sm text-neutral-600 cursor-pointer">
                  <input
                    type="checkbox"
                    checked={form.is_active}
                    onChange={(e) => setForm((f) => ({ ...f, is_active: e.target.checked }))}
                  />
                  Active
                </label>
              </div>
            </div>
            <div className="flex items-center justify-end gap-2 px-5 py-4 border-t border-neutral-200 bg-neutral-50/70">
              <button
                type="button"
                onClick={() => setShowForm(false)}
                className="text-sm text-neutral-500 hover:text-neutral-700"
              >
                Cancel
              </button>
              <button
                type="submit"
                disabled={create.isPending || update.isPending}
                className="text-sm bg-neutral-900 text-white rounded-md px-4 py-2 hover:bg-neutral-800 disabled:opacity-50"
              >
                {create.isPending || update.isPending ? 'Saving…' : editing ? 'Save Changes' : 'Create Item'}
              </button>
            </div>
          </form>
        </div>
      )}

      {isInitialLoading ? (
        <SkeletonLoader rows={5} />
      ) : listItems.length === 0 ? (
        <div className="text-center py-16">
          <div className="w-16 h-16 bg-neutral-100 rounded-full flex items-center justify-center mx-auto mb-4">
            <Package className="h-8 w-8 text-neutral-400" />
          </div>
          <h3 className="text-base font-medium text-neutral-900 mb-1">
            {hasSearch ? 'No items match your search' : 'No items in your catalog yet'}
          </h3>
          <p className="text-sm text-neutral-500 max-w-sm mx-auto">
            {hasSearch ? 'Try adjusting your search terms.' : 'Add your first item to start building your catalog.'}
          </p>
        </div>
      ) : (
        <Card className="overflow-hidden">
          <table className="w-full text-sm">
            <thead className="bg-neutral-50 border-b border-neutral-200">
              <tr>
                <th className="text-left px-4 py-3 text-xs font-semibold text-neutral-600 uppercase tracking-wider">Code</th>
                <th className="text-left px-4 py-3 text-xs font-semibold text-neutral-600 uppercase tracking-wider">Name</th>
                <th className="text-left px-4 py-3 text-xs font-semibold text-neutral-600 uppercase tracking-wider">UOM</th>
                <th className="text-right px-4 py-3 text-xs font-semibold text-neutral-600 uppercase tracking-wider">Unit Price</th>
                <th className="text-left px-4 py-3 text-xs font-semibold text-neutral-600 uppercase tracking-wider">Status</th>
                <th className="px-4 py-3 w-10"></th>
              </tr>
            </thead>
            <tbody className="divide-y divide-neutral-100">
              {listItems.map((item) => (
                <tr key={item.id} className="hover:bg-neutral-50 transition-colors">
                  <td className="px-4 py-3 font-mono text-xs font-medium text-neutral-700">{item.item_code}</td>
                  <td className="px-4 py-3 text-neutral-800">{item.item_name}</td>
                  <td className="px-4 py-3 text-neutral-600">{item.unit_of_measure}</td>
                  <td className="px-4 py-3 text-right font-medium text-neutral-800">
                    &#8369;{(item.unit_price / 100).toLocaleString(undefined, { minimumFractionDigits: 2 })}
                  </td>
                  <td className="px-4 py-3">
                    <span className={`inline-flex items-center px-2 py-0.5 rounded text-xs font-medium ${item.is_active ? statusBadges.active : statusBadges.inactive}`}>
                      {item.is_active ? 'Active' : 'Inactive'}
                    </span>
                  </td>
                  <td className="px-4 py-3 text-right">
                    {canManage && (
                      <button
                        onClick={() => openEdit(item)}
                        className="p-1.5 text-neutral-400 hover:text-neutral-700 hover:bg-neutral-100 rounded transition-colors"
                      >
                        <Pencil className="w-3.5 h-3.5" />
                      </button>
                    )}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </Card>
      )}
    </div>
  )
}

function Field({
  label,
  children,
  className = '',
}: {
  label: string
  children: React.ReactNode
  className?: string
}): React.ReactElement {
  return (
    <div className={className}>
      <label className="block text-xs font-medium text-neutral-600 mb-1">{label}</label>
      {children}
    </div>
  )
}
