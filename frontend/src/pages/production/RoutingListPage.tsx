import { useState } from 'react'
import { PageHeader } from '@/components/ui/PageHeader'
import { useRoutings, useWorkCenters, useCreateRouting, useDeleteRouting, type Routing, type WorkCenter } from '@/hooks/useProduction'
import { useBoms } from '@/hooks/useProduction'
import PermissionGuard from '@/components/ui/PermissionGuard'
import { useAuthStore } from '@/stores/authStore'
import { toast } from 'sonner'

export default function RoutingListPage() {
  const [selectedBomId, setSelectedBomId] = useState<number | undefined>(undefined)
  const { data: routings, isLoading } = useRoutings(selectedBomId)
  const { data: bomsData } = useBoms()
  const { data: workCenters } = useWorkCenters()
  const createMut = useCreateRouting()
  const deleteMut = useDeleteRouting()
  const { hasPermission } = useAuthStore()
  const canCreateRouting = hasPermission('production.orders.create')
  const canUpdateRouting = hasPermission('production.orders.update')
  const [showForm, setShowForm] = useState(false)
  const [form, setForm] = useState({
    bom_id: '',
    work_center_id: '',
    step_number: '10',
    operation_name: '',
    setup_time_minutes: '',
    run_time_minutes: '',
    description: '',
  })

  const boms = (bomsData as { data?: { id: number; product_name?: string; product_item_code?: string }[] })?.data ?? []

  const handleCreate = async (e: React.FormEvent) => {
    e.preventDefault()
    try {
      await createMut.mutateAsync({
        bom_id: Number(form.bom_id),
        work_center_id: Number(form.work_center_id),
        step_number: Number(form.step_number),
        operation_name: form.operation_name,
        setup_time_minutes: form.setup_time_minutes ? Number(form.setup_time_minutes) : undefined,
        run_time_minutes: form.run_time_minutes ? Number(form.run_time_minutes) : undefined,
        description: form.description || undefined,
      })
      toast.success('Routing step created.')
      setShowForm(false)
  }

  return (
    <div className="p-6 space-y-6">
      <div className="flex items-center justify-between">
        <PageHeader title="Production Routings" />
        <PermissionGuard permission="production.orders.create">
          <button onClick={() => setShowForm(!showForm)} className="px-4 py-2 bg-blue-600 text-white text-sm rounded hover:bg-blue-700">
            {showForm ? 'Cancel' : '+ Add Routing Step'}
          </button>
        </PermissionGuard>
      </div>

      {/* BOM Filter */}
      <div className="flex items-center gap-3">
        <label className="text-sm font-medium">Filter by BOM:</label>
        <select
          value={selectedBomId ?? ''}
          onChange={e => setSelectedBomId(e.target.value ? Number(e.target.value) : undefined)}
          className="border rounded px-3 py-2 text-sm min-w-[250px]"
        >
          <option value="">All Routings</option>
          {boms.map((b: { id: number; product_name?: string; product_item_code?: string }) => (
            <option key={b.id} value={b.id}>{b.product_item_code} - {b.product_name}</option>
          ))}
        </select>
      </div>

      {showForm && canCreateRouting && (
        <form onSubmit={handleCreate} className="bg-white dark:bg-neutral-800 rounded-lg border p-5 grid grid-cols-2 gap-4">
          <div>
            <label className="block text-sm font-medium mb-1">BOM *</label>
            <select value={form.bom_id} onChange={e => setForm({...form, bom_id: e.target.value})} required className="w-full border rounded px-3 py-2 text-sm">
              <option value="">Select BOM</option>
              {boms.map((b: { id: number; product_name?: string }) => <option key={b.id} value={b.id}>{b.product_name}</option>)}
            </select>
          </div>
          <div>
            <label className="block text-sm font-medium mb-1">Work Center *</label>
            <select value={form.work_center_id} onChange={e => setForm({...form, work_center_id: e.target.value})} required className="w-full border rounded px-3 py-2 text-sm">
              <option value="">Select Work Center</option>
              {(workCenters ?? []).map((wc: WorkCenter) => <option key={wc.id} value={wc.id}>{wc.code} - {wc.name}</option>)}
            </select>
          </div>
          <div>
            <label className="block text-sm font-medium mb-1">Step # *</label>
            <input type="number" value={form.step_number} onChange={e => setForm({...form, step_number: e.target.value})} required className="w-full border rounded px-3 py-2 text-sm" />
          </div>
          <div>
            <label className="block text-sm font-medium mb-1">Operation Name *</label>
            <input value={form.operation_name} onChange={e => setForm({...form, operation_name: e.target.value})} required className="w-full border rounded px-3 py-2 text-sm" placeholder="Injection Molding" />
          </div>
          <div>
            <label className="block text-sm font-medium mb-1">Setup Time (min)</label>
            <input type="number" value={form.setup_time_minutes} onChange={e => setForm({...form, setup_time_minutes: e.target.value})} className="w-full border rounded px-3 py-2 text-sm" />
          </div>
          <div>
            <label className="block text-sm font-medium mb-1">Run Time (min/unit)</label>
            <input type="number" value={form.run_time_minutes} onChange={e => setForm({...form, run_time_minutes: e.target.value})} className="w-full border rounded px-3 py-2 text-sm" />
          </div>
          <div className="col-span-2 flex justify-end">
            <button type="submit" disabled={createMut.isPending} className="px-4 py-2 bg-green-600 text-white text-sm rounded hover:bg-green-700 disabled:opacity-50">
              {createMut.isPending ? 'Creating...' : 'Create Step'}
            </button>
          </div>
        </form>
      )}

      {isLoading ? (
        <div className="animate-pulse space-y-3">{[1,2,3].map(i => <div key={i} className="h-12 bg-neutral-200 rounded" />)}</div>
      ) : (
        <div className="bg-white dark:bg-neutral-800 rounded-lg border overflow-hidden">
          <table className="w-full text-sm">
            <thead className="bg-neutral-50 dark:bg-neutral-700">
              <tr>
                <th className="text-left px-4 py-3 font-medium">Step</th>
                <th className="text-left px-4 py-3 font-medium">Operation</th>
                <th className="text-left px-4 py-3 font-medium">Work Center</th>
                <th className="text-right px-4 py-3 font-medium">Setup (min)</th>
                <th className="text-right px-4 py-3 font-medium">Run (min/unit)</th>
                {canUpdateRouting && <th className="text-center px-4 py-3 font-medium">Actions</th>}
              </tr>
            </thead>
            <tbody className="divide-y">
              {(routings ?? []).map((r: Routing) => (
                <tr key={r.id} className="hover:bg-neutral-50 dark:hover:bg-neutral-700/50">
                  <td className="px-4 py-3 font-mono">{r.step_number}</td>
                  <td className="px-4 py-3 font-medium">{r.operation_name}</td>
                  <td className="px-4 py-3">{r.work_center?.name ?? `WC #${r.work_center_id}`}</td>
                  <td className="px-4 py-3 text-right">{r.setup_time_minutes ?? '-'}</td>
                  <td className="px-4 py-3 text-right">{r.run_time_minutes ?? '-'}</td>
                  {canUpdateRouting && (
                    <td className="px-4 py-3 text-center">
                      <PermissionGuard permission="production.orders.update">
                        <button onClick={() => { if (confirm('Delete this routing step?')) deleteMut.mutate(r.id) }} className="text-xs text-red-600 hover:underline">Delete</button>
                      </PermissionGuard>
                    </td>
                  )}
                </tr>
              ))}
              {(!routings || routings.length === 0) && (
                <tr><td colSpan={canUpdateRouting ? 6 : 5} className="px-4 py-8 text-center text-neutral-500">No routing steps found. Add routing steps to define the production process.</td></tr>
              )}
            </tbody>
          </table>
        </div>
      )}
    </div>
  )
}
