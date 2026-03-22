import { useState } from 'react'
import { toast } from 'sonner'
import { firstErrorMessage } from '@/lib/errorHandler'
import { Plus, Tags } from 'lucide-react'
import { useFixedAssetCategories, useCreateFixedAssetCategory } from '@/hooks/useFixedAssets'
import type { FixedAssetCategory } from '@/types/fixed_assets'
import { useAuthStore } from '@/stores/authStore'
import { PageHeader } from '@/components/ui/PageHeader'
import { Card } from '@/components/ui/Card'
import EmptyState from '@/components/ui/EmptyState'
import SkeletonLoader from '@/components/ui/SkeletonLoader'

export default function FixedAssetCategoriesPage(): React.ReactElement {
  const { data: categories, isLoading } = useFixedAssetCategories()
  const create = useCreateFixedAssetCategory()
  const [showForm, setShowForm] = useState(false)
  const [form, setForm] = useState({ name: '', default_useful_life_years: 5, default_depreciation_method: 'straight_line' as const })
  const canManage = useAuthStore((s) => s.hasPermission('fixed_assets.manage'))

  async function handleCreate(e: React.FormEvent) {
    e.preventDefault()
    try {
      await create.mutateAsync(form as unknown as Omit<FixedAssetCategory, 'id'>)
      toast.success('Category created.')
      setShowForm(false)
      setForm({ name: '', default_useful_life_years: 5, default_depreciation_method: 'straight_line' })
    } catch (err) {
      toast.error(firstErrorMessage(err, 'Failed to create category.'))
    }
  }

  const list: FixedAssetCategory[] = categories ?? []

  return (
    <div className="max-w-4xl mx-auto space-y-6">
      <PageHeader
        title="Fixed Asset Categories"
        subtitle="Define categories with default depreciation settings."
        icon={<Tags className="w-5 h-5 text-neutral-600" />}
        actions={
          canManage && (
            <button onClick={() => setShowForm(true)}
              className="btn-primary">
              <Plus className="w-3.5 h-3.5" /> Add Category
            </button>
          )
        }
      />

      {showForm && (
        <Card>
          <form onSubmit={handleCreate} className="grid grid-cols-3 gap-4">
            <div>
              <label className="block text-xs font-medium text-neutral-600 mb-1">Name</label>
              <input value={form.name} onChange={(e) => setForm((f) => ({ ...f, name: e.target.value }))} required
                className="w-full border border-neutral-300 rounded px-3 py-2 text-sm focus:ring-1 focus:ring-neutral-400 outline-none" placeholder="Machinery" />
            </div>
            <div>
              <label className="block text-xs font-medium text-neutral-600 mb-1">Useful Life (years)</label>
              <input type="number" min={1} value={form.default_useful_life_years}
                onChange={(e) => setForm((f) => ({ ...f, default_useful_life_years: Number(e.target.value) }))}
                className="w-full border border-neutral-300 rounded px-3 py-2 text-sm focus:ring-1 focus:ring-neutral-400 outline-none" />
            </div>
            <div className="flex items-end gap-3">
              <button type="submit" disabled={create.isPending}
                className="btn-primary disabled:opacity-50">Create</button>
              <button type="button" onClick={() => setShowForm(false)} className="btn-ghost">Cancel</button>
            </div>
          </form>
        </Card>
      )}

      {isLoading ? (
        <SkeletonLoader rows={6} />
      ) : list.length === 0 ? (
        <EmptyState
          title="No categories defined"
          description="Create categories to organize fixed assets and set default depreciation settings."
          action={canManage && (
            <button onClick={() => setShowForm(true)} className="btn-primary">
              <Plus className="w-3.5 h-3.5" /> Add Category
            </button>
          )}
        />
      ) : (
        <Card>
          <table className="w-full text-sm">
            <thead className="bg-neutral-50 border-b border-neutral-200">
              <tr>
                {['Name', 'Useful Life', 'Depreciation Method'].map((h) => (
                  <th key={h} className="text-left px-4 py-3 text-xs font-semibold text-neutral-500 uppercase tracking-wider">{h}</th>
                ))}
              </tr>
            </thead>
            <tbody className="divide-y divide-neutral-100">
              {list.map((c) => (
                <tr key={c.id} className="even:bg-neutral-50 hover:bg-neutral-50 transition-colors">
                  <td className="px-4 py-3 font-medium text-neutral-900">{c.name}</td>
                  <td className="px-4 py-3 text-neutral-600">{c.default_useful_life_years} years</td>
                  <td className="px-4 py-3 text-neutral-600 capitalize">{c.default_depreciation_method.replace(/_/g, ' ')}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </Card>
      )}
    </div>
  )
}
