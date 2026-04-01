import { useState } from 'react'
import { PageHeader } from '@/components/ui/PageHeader'
import { useWorkCenters, useCreateWorkCenter, useDeleteWorkCenter, type WorkCenter } from '@/hooks/useProduction'
import PermissionGuard from '@/components/ui/PermissionGuard'
import { useAuthStore } from '@/stores/authStore'
import { toast } from 'sonner'

export default function WorkCenterListPage() {
  const { data: workCenters, isLoading } = useWorkCenters()
  const createMut = useCreateWorkCenter()
  const deleteMut = useDeleteWorkCenter()
  const { hasPermission } = useAuthStore()
  const canCreateWorkCenter = hasPermission('production.orders.create')
  const canUpdateWorkCenter = hasPermission('production.orders.update')
  const [showForm, setShowForm] = useState(false)
  const [form, setForm] = useState({ name: '', code: '', description: '', hourly_labor_rate: '', hourly_overhead_rate: '', capacity_hours_per_day: '' })

  const handleCreate = async (e: React.FormEvent) => {
    e.preventDefault()
    try {
      await createMut.mutateAsync({
        name: form.name,
        code: form.code,
        description: form.description || undefined,
        hourly_labor_rate: form.hourly_labor_rate ? Number(form.hourly_labor_rate) : undefined,
        hourly_overhead_rate: form.hourly_overhead_rate ? Number(form.hourly_overhead_rate) : undefined,
        capacity_hours_per_day: form.capacity_hours_per_day ? Number(form.capacity_hours_per_day) : undefined,
        is_active: true,
      })
      toast.success('Work center created.')
      setShowForm(false)
      setForm({ name: '', code: '', description: '', hourly_labor_rate: '', hourly_overhead_rate: '', capacity_hours_per_day: '' })
    } catch { toast.error('Failed to create work center.') }
  }

  return (
    <div className="p-6 space-y-6">
      <div className="flex items-center justify-between">
        <PageHeader title="Work Centers" />
        <PermissionGuard permission="production.orders.create">
          <button onClick={() => setShowForm(!showForm)} className="px-4 py-2 bg-blue-600 text-white text-sm rounded hover:bg-blue-700">
            {showForm ? 'Cancel' : '+ Add Work Center'}
          </button>
        </PermissionGuard>
      </div>

      {showForm && canCreateWorkCenter && (
        <form onSubmit={handleCreate} className="bg-white dark:bg-neutral-800 rounded-lg border p-5 grid grid-cols-2 gap-4">
          <div>
            <label className="block text-sm font-medium mb-1">Code *</label>
            <input value={form.code} onChange={e => setForm({...form, code: e.target.value})} required className="w-full border rounded px-3 py-2 text-sm" placeholder="WC-001" />
          </div>
          <div>
            <label className="block text-sm font-medium mb-1">Name *</label>
            <input value={form.name} onChange={e => setForm({...form, name: e.target.value})} required className="w-full border rounded px-3 py-2 text-sm" placeholder="Injection Molding" />
          </div>
          <div className="col-span-2">
            <label className="block text-sm font-medium mb-1">Description</label>
            <textarea value={form.description} onChange={e => setForm({...form, description: e.target.value})} className="w-full border rounded px-3 py-2 text-sm" rows={2} />
          </div>
          <div>
            <label className="block text-sm font-medium mb-1">Hourly Labor Rate</label>
            <input type="number" step="0.01" value={form.hourly_labor_rate} onChange={e => setForm({...form, hourly_labor_rate: e.target.value})} className="w-full border rounded px-3 py-2 text-sm" />
          </div>
          <div>
            <label className="block text-sm font-medium mb-1">Hourly Overhead Rate</label>
            <input type="number" step="0.01" value={form.hourly_overhead_rate} onChange={e => setForm({...form, hourly_overhead_rate: e.target.value})} className="w-full border rounded px-3 py-2 text-sm" />
          </div>
          <div>
            <label className="block text-sm font-medium mb-1">Capacity Hours/Day</label>
            <input type="number" step="0.5" value={form.capacity_hours_per_day} onChange={e => setForm({...form, capacity_hours_per_day: e.target.value})} className="w-full border rounded px-3 py-2 text-sm" />
          </div>
          <div className="flex items-end">
            <button type="submit" disabled={createMut.isPending} className="px-4 py-2 bg-green-600 text-white text-sm rounded hover:bg-green-700 disabled:opacity-50">
              {createMut.isPending ? 'Creating...' : 'Create'}
            </button>
          </div>
        </form>
      )}

      {isLoading ? (
        <div className="animate-pulse space-y-3">{[1,2,3].map(i => <div key={i} className="h-16 bg-neutral-200 rounded" />)}</div>
      ) : (
        <div className="bg-white dark:bg-neutral-800 rounded-lg border overflow-hidden">
          <table className="w-full text-sm">
            <thead className="bg-neutral-50 dark:bg-neutral-700">
              <tr>
                <th className="text-left px-4 py-3 font-medium">Code</th>
                <th className="text-left px-4 py-3 font-medium">Name</th>
                <th className="text-right px-4 py-3 font-medium">Labor Rate/hr</th>
                <th className="text-right px-4 py-3 font-medium">Overhead Rate/hr</th>
                <th className="text-right px-4 py-3 font-medium">Capacity hrs/day</th>
                <th className="text-center px-4 py-3 font-medium">Status</th>
                {canUpdateWorkCenter && <th className="text-center px-4 py-3 font-medium">Actions</th>}
              </tr>
            </thead>
            <tbody className="divide-y">
              {(workCenters ?? []).map((wc: WorkCenter) => (
                <tr key={wc.id} className="hover:bg-neutral-50 dark:hover:bg-neutral-700/50">
                  <td className="px-4 py-3 font-mono text-xs">{wc.code}</td>
                  <td className="px-4 py-3 font-medium">{wc.name}</td>
                  <td className="px-4 py-3 text-right">{wc.hourly_labor_rate != null ? `₱${Number(wc.hourly_labor_rate).toFixed(2)}` : '-'}</td>
                  <td className="px-4 py-3 text-right">{wc.hourly_overhead_rate != null ? `₱${Number(wc.hourly_overhead_rate).toFixed(2)}` : '-'}</td>
                  <td className="px-4 py-3 text-right">{wc.capacity_hours_per_day ?? '-'}</td>
                  <td className="px-4 py-3 text-center">
                    <span className={`px-2 py-0.5 rounded-full text-xs ${wc.is_active ? 'bg-green-100 text-green-700' : 'bg-neutral-100 text-neutral-500'}`}>
                      {wc.is_active ? 'Active' : 'Inactive'}
                    </span>
                  </td>
                  {canUpdateWorkCenter && (
                    <td className="px-4 py-3 text-center">
                      <PermissionGuard permission="production.orders.update">
                        <button onClick={() => { if (confirm('Delete this work center?')) deleteMut.mutate(wc.id) }} className="text-xs text-red-600 hover:underline">Delete</button>
                      </PermissionGuard>
                    </td>
                  )}
                </tr>
              ))}
              {(!workCenters || workCenters.length === 0) && (
                <tr><td colSpan={canUpdateWorkCenter ? 7 : 6} className="px-4 py-8 text-center text-neutral-500">No work centers configured. Add one to get started.</td></tr>
              )}
            </tbody>
          </table>
        </div>
      )}
    </div>
  )
}
