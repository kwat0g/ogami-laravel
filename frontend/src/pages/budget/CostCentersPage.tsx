import { useState } from 'react'
import { toast } from 'sonner'
import { firstErrorMessage } from '@/lib/errorHandler'
import { Plus, Building2 } from 'lucide-react'
import { useCostCenters, useCreateCostCenter, type CostCenter } from '@/hooks/useBudget'
import { useAuthStore } from '@/stores/authStore'
import { PageHeader } from '@/components/ui/PageHeader'
import { Card } from '@/components/ui/Card'
import EmptyState from '@/components/ui/EmptyState'
import SkeletonLoader from '@/components/ui/SkeletonLoader'
import StatusBadge from '@/components/ui/StatusBadge'

const emptyForm = (): { id?: number; code: string; name: string; is_active: boolean } => ({
  code: '', name: '', is_active: true,
})

export default function CostCentersPage(): React.ReactElement {
  const [form, setForm] = useState(emptyForm())
  const [isArchiveView, setIsArchiveView] = useState(false)
  const [editing, setEditing] = useState(false)
  const { data, isLoading } = useCostCenters()
  const create = useCreateCostCenter()
  const canManage = useAuthStore((s) => s.hasPermission('budget.manage'))

  const set = <K extends keyof typeof form>(k: K, v: (typeof form)[K]) =>
    setForm((f) => ({ ...f, [k]: v }))

  const openCreate = () => { setForm(emptyForm()); setEditing(true) }
  const openEdit = (cc: CostCenter) => { setForm({ id: cc.id, code: cc.code, name: cc.name, is_active: cc.is_active }); setEditing(true) }

  async function handleSave(e: React.FormEvent) {
    e.preventDefault()
    try {
      if (form.id) {
        // update is a hook that needs to be called per-id, so we call it inline
        // We'll use the API directly here for simplicity
        const { default: api } = await import('@/lib/api')
        await api.patch(`/budget/cost-centers/${form.id}`, { code: form.code, name: form.name, is_active: form.is_active })
        toast.success('Cost center updated.')
      } else {
        await create.mutateAsync({ code: form.code, name: form.name })
        toast.success('Cost center created.')
      }
      setEditing(false)
      setForm(emptyForm())
    } catch (_err) {
      toast.error(firstErrorMessage(err, 'Failed to save cost center.'))
    }
  }

  const centers: CostCenter[] = data?.data ?? []

  return (
    <div className="max-w-5xl mx-auto space-y-6">
      <PageHeader
        title="Cost Centers"
        subtitle="Manage organizational cost centers for budget tracking."
        icon={<Building2 className="w-5 h-5 text-neutral-600" />}
        actions={
          canManage && (
            <button onClick={openCreate}
              className="btn-primary">
              <Plus className="w-3.5 h-3.5" /> Add Cost Center
            </button>
          )
        }
      />

      {editing && (
        <Card>
          <form onSubmit={handleSave} className="grid grid-cols-3 gap-4">
            <div>
              <label className="block text-xs font-medium text-neutral-600 mb-1">Code</label>
              <input value={form.code} onChange={(e) => set('code', e.target.value)} required
                className="w-full border border-neutral-300 rounded px-3 py-2 text-sm focus:ring-1 focus:ring-neutral-400 outline-none" placeholder="CC-001" />
            </div>
            <div>
              <label className="block text-xs font-medium text-neutral-600 mb-1">Name</label>
              <input value={form.name} onChange={(e) => set('name', e.target.value)} required
                className="w-full border border-neutral-300 rounded px-3 py-2 text-sm focus:ring-1 focus:ring-neutral-400 outline-none" placeholder="Administration" />
            </div>
            <div className="flex items-end gap-3">
              {form.id && (
                <label className="flex items-center gap-1.5 text-sm cursor-pointer">
                  <input type="checkbox" checked={form.is_active} onChange={(e) => set('is_active', e.target.checked)} className="rounded border-neutral-300" />
                  <span className="text-neutral-700">Active</span>
                </label>
              )}
              <button type="submit" className="btn-primary">
                {form.id ? 'Update' : 'Create'}
              </button>
              <button type="button" onClick={() => setEditing(false)}
                className="btn-ghost">Cancel</button>
            </div>
          </form>
        </Card>
      )}

      {isLoading ? (
        <SkeletonLoader rows={6} />
      ) : centers.length === 0 ? (
        <EmptyState
          title="No cost centers defined"
          description="Create cost centers to organize budget tracking by department or division."
          action={canManage && (
            <button onClick={openCreate} className="btn-primary">
              <Plus className="w-3.5 h-3.5" /> Add Cost Center
            </button>
          )}
        />
      ) : (
        <Card>
          <table className="w-full text-sm">
            <thead className="bg-neutral-50 border-b border-neutral-200">
              <tr>
                {['Code', 'Name', 'Status', 'Actions'].map((h) => (
                  <th key={h} className="text-left px-4 py-3 text-xs font-semibold text-neutral-500 uppercase tracking-wider">{h}</th>
                ))}
              </tr>
            </thead>
            <tbody className="divide-y divide-neutral-100">
              {centers.map((cc) => (
                <tr key={cc.id} className="even:bg-neutral-50 hover:bg-neutral-50 transition-colors">
                  <td className="px-4 py-3 font-mono text-xs text-neutral-600">{cc.code}</td>
                  <td className="px-4 py-3 font-medium text-neutral-900">{cc.name}</td>
                  <td className="px-4 py-3">
                    <StatusBadge status={cc.is_active ? 'active' : 'inactive'}>
                      {cc.is_active ? 'Active' : 'Inactive'}
                    </StatusBadge>
                  </td>
                  <td className="px-4 py-3">
                    {canManage && (
                      <button onClick={() => openEdit(cc)} className="text-xs text-neutral-600 hover:text-neutral-900 underline">Edit</button>
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
