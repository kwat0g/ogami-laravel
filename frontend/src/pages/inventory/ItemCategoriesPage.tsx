import { useState } from 'react'
import { useItemCategories, useCreateItemCategory } from '@/hooks/useInventory'
import SkeletonLoader from '@/components/ui/SkeletonLoader'
import type { ItemCategory } from '@/types/inventory'

// ---------------------------------------------------------------------------
// Create form modal
// ---------------------------------------------------------------------------

type CreatePayload = { code: string; name: string; description?: string }

const EMPTY: CreatePayload = { code: '', name: '', description: '' }

function CategoryFormModal({ onClose }: { onClose: () => void }) {
  const [form, setForm] = useState<CreatePayload>(EMPTY)
  const { mutate: create, isPending, error } = useCreateItemCategory()

  function handleSubmit(e: React.FormEvent) {
    e.preventDefault()
    create(
      { code: form.code, name: form.name, description: form.description || undefined },
      { onSuccess: onClose },
    )
  }

  const inputCls = 'w-full border border-neutral-300 rounded px-3 py-2 text-sm focus:ring-1 focus:ring-neutral-400 focus:outline-none'

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50">
      <form onSubmit={handleSubmit} className="bg-white rounded border border-neutral-200 p-4 sm:p-6 w-full max-w-md max-h-[90vh] overflow-y-auto space-y-4">
        <h2 className="text-lg font-semibold text-neutral-900">New Item Category</h2>

        {error && (
          <p className="text-sm text-red-600 rounded bg-red-50 px-3 py-2 border border-red-200">
            {(error as Error).message}
          </p>
        )}

        <div className="flex flex-col gap-1">
          <label className="text-xs font-medium text-neutral-600">Code *</label>
          <input
            className={inputCls}
            value={form.code}
            onChange={e => setForm(f => ({ ...f, code: e.target.value }))}
            placeholder="e.g. RAW-MAT"
            required
          />
        </div>

        <div className="flex flex-col gap-1">
          <label className="text-xs font-medium text-neutral-600">Name *</label>
          <input
            className={inputCls}
            value={form.name}
            onChange={e => setForm(f => ({ ...f, name: e.target.value }))}
            placeholder="e.g. Raw Materials"
            required
          />
        </div>

        <div className="flex flex-col gap-1">
          <label className="text-xs font-medium text-neutral-600">Description</label>
          <textarea
            rows={2}
            className={inputCls}
            value={form.description}
            onChange={e => setForm(f => ({ ...f, description: e.target.value }))}
            placeholder="Optional description"
          />
        </div>

        <div className="flex flex-col-reverse sm:flex-row gap-2 sm:gap-3 pt-2">
          <button
            type="submit"
            disabled={isPending}
            className="flex-1 py-2 rounded bg-neutral-900 text-white text-sm font-medium hover:bg-neutral-800 disabled:opacity-50"
          >
            {isPending ? 'Saving…' : 'Save'}
          </button>
          <button
            type="button"
            onClick={onClose}
            className="flex-1 py-2 rounded border border-neutral-300 text-sm text-neutral-700 hover:bg-neutral-50"
          >
            Cancel
          </button>
        </div>
      </form>
    </div>
  )
}

// ---------------------------------------------------------------------------
// Page
// ---------------------------------------------------------------------------

export default function ItemCategoriesPage(): React.ReactElement {
  const { data: categories, isLoading } = useItemCategories()
  const [showForm, setShowForm] = useState(false)

  return (
    <div className="p-6 space-y-6">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold text-gray-900">Item Categories</h1>
          <p className="text-sm text-gray-500 mt-0.5">Classify inventory items by category</p>
        </div>
        <button
          type="button"
          onClick={() => setShowForm(true)}
          className="px-4 py-2 rounded-lg bg-teal-600 text-white text-sm font-medium hover:bg-teal-700"
        >
          + New Category
        </button>
      </div>

      {isLoading && <SkeletonLoader rows={5} />}

      <div className="bg-white border border-gray-200 rounded-xl overflow-auto">
        <table className="w-full text-sm">
          <thead className="bg-gray-50 border-b border-gray-200">
            <tr>
              <th className="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Code</th>
              <th className="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Name</th>
              <th className="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Description</th>
            </tr>
          </thead>
          <tbody className="divide-y divide-gray-100">
            {(!categories || categories.length === 0) && (
              <tr>
                <td colSpan={3} className="px-4 py-8 text-center text-gray-400 text-sm">
                  No categories yet. Create one above.
                </td>
              </tr>
            )}
            {categories?.map((cat: ItemCategory) => (
              <tr key={cat.id} className="hover:bg-gray-50">
                <td className="px-4 py-3 font-mono text-gray-700">{cat.code}</td>
                <td className="px-4 py-3 font-medium text-gray-900">{cat.name}</td>
                <td className="px-4 py-3 text-gray-500">{cat.description ?? '—'}</td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>

      {showForm && <CategoryFormModal onClose={() => setShowForm(false)} />}
    </div>
  )
}
