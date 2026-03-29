import { useState } from 'react'
import { Plus, Trash2 } from 'lucide-react'
import { toast } from 'sonner'
import { PageHeader } from '@/components/ui/PageHeader'
import { useItemCategories, useCreateItemCategory } from '@/hooks/useInventory'
import SkeletonLoader from '@/components/ui/SkeletonLoader'
import { Card, CardHeader, CardBody } from '@/components/ui/Card'
import ConfirmDestructiveDialog from '@/components/ui/ConfirmDestructiveDialog'
import { useAuthStore } from '@/stores/authStore'
import api from '@/lib/api'
import { firstErrorMessage } from '@/lib/errorHandler'
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
    if (!form.code.trim() || form.code.trim().length < 2) {
      toast.error('Code is required (min 2 characters)')
      return
    }
    if (!form.name.trim() || form.name.trim().length < 2) {
      toast.error('Name is required (min 2 characters)')
      return
    }
    create(
      { code: form.code.trim(), name: form.name.trim(), description: form.description?.trim() || undefined },
      { 
        onSuccess: () => {
          toast.success('Category created successfully.')
          onClose()
        },
        onError: (err) => {
          toast.error(firstErrorMessage(err))
        }
      },
    )
  }

  const inputCls = 'w-full border border-neutral-300 rounded px-3 py-1.5 text-sm focus:ring-1 focus:ring-neutral-400 focus:outline-none'

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50">
      <form onSubmit={handleSubmit} className="bg-white rounded-lg border border-neutral-200 p-4 w-full max-w-md max-h-[90vh] overflow-y-auto space-y-3">
        <h2 className="text-base font-semibold text-neutral-900">New Item Category</h2>

        {error && (
          <p className="text-sm text-red-600 rounded bg-red-50 px-3 py-2 border border-red-200">
            {(error as Error).message}
          </p>
        )}

        <div className="flex flex-col gap-1">
          <label className="text-sm font-medium text-neutral-700">Code <span className="text-red-500">*</span></label>
          <input
            className={inputCls}
            value={form.code}
            onChange={e => setForm(f => ({ ...f, code: e.target.value }))}
            placeholder="e.g. RAW-MAT"
            required
            minLength={2}
          />
        </div>

        <div className="flex flex-col gap-1">
          <label className="text-sm font-medium text-neutral-700">Name <span className="text-red-500">*</span></label>
          <input
            className={inputCls}
            value={form.name}
            onChange={e => setForm(f => ({ ...f, name: e.target.value }))}
            placeholder="e.g. Raw Materials"
            required
            minLength={2}
          />
        </div>

        <div className="flex flex-col gap-1">
          <label className="text-sm font-medium text-neutral-700">Description</label>
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
            type="button"
            onClick={onClose}
            className="flex-1 py-1.5 rounded border border-neutral-300 text-sm text-neutral-700 hover:bg-neutral-50"
          >
            Cancel
          </button>
          <button
            type="submit"
            disabled={isPending}
            className="flex-1 py-1.5 rounded bg-neutral-900 text-white text-sm font-medium hover:bg-neutral-800 disabled:opacity-50 disabled:cursor-not-allowed"
          >
            {isPending ? 'Saving…' : 'Save'}
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
  const { data: categories, isLoading, refetch } = useItemCategories()
  const [showForm, setShowForm] = useState(false)
  const [, setDeletingId] = useState<number | null>(null)
  const canCreate = useAuthStore(s => s.hasPermission('inventory.items.create'))
  const canDelete = useAuthStore(s => s.hasPermission('inventory.items.delete'))
  useAuthStore(s => s.user)

  const handleDelete = async (id: number) => {
    try {
      await api.delete(`/inventory/items/categories/${id}`)
      toast.success('Category deleted successfully.')
      refetch()
    } catch (_err) {
      toast.error(firstErrorMessage(err))
    } finally {
      setDeletingId(null)
    }
  }

  return (
    <div>
      <PageHeader title="Item Categories" />
      {isLoading && <SkeletonLoader rows={5} />}

      {!isLoading && (
        <Card>
          <CardHeader
            action={
              canCreate && (
                <button
                  type="button"
                  onClick={() => setShowForm(true)}
                  className="flex items-center gap-2 px-4 py-1.5 bg-neutral-900 hover:bg-neutral-800 text-white text-sm font-medium rounded"
                >
                  <Plus className="w-4 h-4" />
                  New Category
                </button>
              )
            }
          >
            Item Categories
          </CardHeader>
          <CardBody className="p-0">
            <table className="min-w-full text-sm">
              <thead className="bg-neutral-50 border-b border-neutral-200">
                <tr>
                  <th className="px-4 py-3 text-left text-xs font-medium text-neutral-600">Code</th>
                  <th className="px-4 py-3 text-left text-xs font-medium text-neutral-600">Name</th>
                  <th className="px-4 py-3 text-left text-xs font-medium text-neutral-600">Description</th>
                  <th className="px-4 py-3 text-left text-xs font-medium text-neutral-600">Actions</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-neutral-100">
                {(!categories || categories.length === 0) && (
                  <tr>
                    <td colSpan={4} className="px-4 py-8 text-center text-neutral-400 text-sm">
                      No categories yet. Create one above.
                    </td>
                  </tr>
                )}
                {categories?.map((cat: ItemCategory) => (
                  <tr key={cat.id} className="hover:bg-neutral-50/50 transition-colors">
                    <td className="px-4 py-3 font-mono text-neutral-900 font-medium">{cat.code}</td>
                    <td className="px-4 py-3 text-neutral-900 font-medium">{cat.name}</td>
                    <td className="px-4 py-3 text-neutral-500">{cat.description ?? '—'}</td>
                    <td className="px-4 py-3">
                      {canDelete && (
                        <ConfirmDestructiveDialog
                          title="Delete category?"
                          description={`This will permanently delete "${cat.name}". Items using this category must be reassigned first.`}
                          confirmWord="DELETE"
                          confirmLabel="Delete"
                          onConfirm={() => handleDelete(cat.id)}
                        >
                          <button
                            type="button"
                            className="p-1.5 hover:bg-red-50 rounded text-neutral-400 hover:text-red-500 transition-colors"
                            title="Delete category"
                          >
                            <Trash2 className="w-4 h-4" />
                          </button>
                        </ConfirmDestructiveDialog>
                      )}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </CardBody>
        </Card>
      )}

      {showForm && <CategoryFormModal onClose={() => setShowForm(false)} />}
    </div>
  )
}
