import { useState, useRef } from 'react'
import { Plus, Pencil, Upload } from 'lucide-react'
import {
  useVendorPortalItems,
  useCreateVendorPortalItem,
  useUpdateVendorPortalItem,
  useImportVendorPortalItems,
  type VendorPortalItem,
} from '@/hooks/useVendorPortal'
import { toast } from 'sonner'

const defaultForm = {
  item_code: '',
  item_name: '',
  description: '',
  unit_of_measure: '',
  unit_price: 0,
  is_active: true,
}

export default function VendorItemsPage(): React.ReactElement {
  const [showAll, setShowAll] = useState(false)
  const [showForm, setShowForm] = useState(false)
  const [editing, setEditing] = useState<VendorPortalItem | null>(null)
  const [form, setForm] = useState(defaultForm)

  const { data: items, isLoading } = useVendorPortalItems(!showAll)
  const create = useCreateVendorPortalItem()
  const update = useUpdateVendorPortalItem()
  const importItems = useImportVendorPortalItems()
  const fileInputRef = useRef<HTMLInputElement>(null)

  function handleImport(e: React.ChangeEvent<HTMLInputElement>) {
    const file = e.target.files?.[0]
    if (!file) return
    importItems.mutate(file, {
      onSuccess: (result) => {
        toast.success(result.message)
        if (fileInputRef.current) fileInputRef.current.value = ''
      },
      onError: () => {
        toast.error('Import failed. Check your file format.')
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
          onError: () => toast.error('Failed to update item.'),
        }
      )
    } else {
      create.mutate(payload as Omit<VendorPortalItem, 'id' | 'ulid'>, {
        onSuccess: () => { toast.success('Item created.'); setShowForm(false) },
        onError: () => toast.error('Failed to create item.'),
      })
    }
  }

  if (isLoading) return <p className="text-sm text-neutral-500 mt-4">Loading catalog…</p>

  return (
    <div>
      <div className="flex items-center justify-between mb-4">
        <div>
          <h1 className="text-2xl font-bold text-neutral-900">My Catalog</h1>
          <p className="text-sm text-neutral-500">Manage your product and service listings.</p>
        </div>
        <div className="flex items-center gap-2">
          <label className="flex items-center gap-1.5 text-sm text-neutral-600 cursor-pointer">
            <input
              type="checkbox"
              checked={showAll}
              onChange={(e) => setShowAll(e.target.checked)}
              className="rounded"
            />
            Show inactive
          </label>
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
        </div>
      </div>

      {showForm && (
        <div className="bg-white border border-neutral-200 rounded-lg p-5 mb-6">
          <h2 className="text-sm font-semibold text-neutral-800 mb-4">
            {editing ? 'Edit Item' : 'New Item'}
          </h2>
          <form onSubmit={handleSubmit} className="grid grid-cols-2 gap-4">
            <Field label="Item Code">
              <input
                value={form.item_code}
                onChange={(e) => setForm((f) => ({ ...f, item_code: e.target.value }))}
                required
                disabled={!!editing}
                className="input"
              />
            </Field>
            <Field label="Item Name">
              <input
                value={form.item_name}
                onChange={(e) => setForm((f) => ({ ...f, item_name: e.target.value }))}
                required
                className="input"
              />
            </Field>
            <Field label="Unit of Measure">
              <input
                value={form.unit_of_measure}
                onChange={(e) => setForm((f) => ({ ...f, unit_of_measure: e.target.value }))}
                required
                className="input"
                placeholder="pcs, kg, box…"
              />
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
            <Field label="Description" className="col-span-2">
              <textarea
                value={form.description}
                onChange={(e) => setForm((f) => ({ ...f, description: e.target.value }))}
                rows={2}
                className="input resize-none"
              />
            </Field>
            <div className="col-span-2 flex items-center gap-4">
              <label className="flex items-center gap-2 text-sm text-neutral-600 cursor-pointer">
                <input
                  type="checkbox"
                  checked={form.is_active}
                  onChange={(e) => setForm((f) => ({ ...f, is_active: e.target.checked }))}
                />
                Active
              </label>
              <div className="flex-1" />
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

      {(items?.length ?? 0) === 0 ? (
        <div className="bg-white border border-neutral-200 rounded-lg px-6 py-12 text-center">
          <p className="text-neutral-500 text-sm">No items in your catalog yet.</p>
        </div>
      ) : (
        <div className="bg-white border border-neutral-200 rounded-lg overflow-hidden">
          <table className="w-full text-sm">
            <thead className="bg-neutral-50 border-b border-neutral-200">
              <tr>
                <th className="text-left px-4 py-2 font-medium text-neutral-600 text-xs uppercase">Code</th>
                <th className="text-left px-4 py-2 font-medium text-neutral-600 text-xs uppercase">Name</th>
                <th className="text-left px-4 py-2 font-medium text-neutral-600 text-xs uppercase">UOM</th>
                <th className="text-right px-4 py-2 font-medium text-neutral-600 text-xs uppercase">Unit Price</th>
                <th className="text-left px-4 py-2 font-medium text-neutral-600 text-xs uppercase">Status</th>
                <th className="px-4 py-2"></th>
              </tr>
            </thead>
            <tbody>
              {items?.map((item) => (
                <tr key={item.id} className="border-b border-neutral-100 last:border-0 hover:bg-neutral-50">
                  <td className="px-4 py-3 font-mono text-xs text-neutral-700">{item.item_code}</td>
                  <td className="px-4 py-3 text-neutral-800">{item.item_name}</td>
                  <td className="px-4 py-3 text-neutral-600">{item.unit_of_measure}</td>
                  <td className="px-4 py-3 text-right font-medium text-neutral-800">
                    ₱{(item.unit_price / 100).toLocaleString(undefined, { minimumFractionDigits: 2 })}
                  </td>
                  <td className="px-4 py-3">
                    <span className={`px-2 py-0.5 rounded text-xs font-medium ${item.is_active ? 'bg-neutral-200 text-neutral-700' : 'bg-neutral-100 text-neutral-400'}`}>
                      {item.is_active ? 'Active' : 'Inactive'}
                    </span>
                  </td>
                  <td className="px-4 py-3 text-right">
                    <button
                      onClick={() => openEdit(item)}
                      className="text-neutral-400 hover:text-neutral-700"
                    >
                      <Pencil className="w-3.5 h-3.5" />
                    </button>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
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
