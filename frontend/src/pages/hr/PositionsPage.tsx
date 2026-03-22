import { useState } from 'react'
import { toast } from 'sonner'
import { useAuthStore } from '@/stores/authStore'
import {
  usePositions,
  useDepartments,
  useCreatePosition,
  useUpdatePosition,
  useDeletePosition,
} from '@/hooks/useEmployees'
import SkeletonLoader from '@/components/ui/SkeletonLoader'
import { PageHeader } from '@/components/ui/PageHeader'
import ConfirmDialog from '@/components/ui/ConfirmDialog'
import ConfirmDestructiveDialog from '@/components/ui/ConfirmDestructiveDialog'
import { firstErrorMessage } from '@/lib/errorHandler'
import type { Position } from '@/types/hr'

interface PosFormState {
  id?: number
  code: string
  title: string
  department_id: number | undefined
  pay_grade: string
  description: string
  is_active: boolean
}

const emptyForm = (): PosFormState => ({ code: '', title: '', department_id: undefined, pay_grade: '', description: '', is_active: true })

export default function PositionsPage() {
  const { hasPermission } = useAuthStore()
  const canManage = hasPermission('employees.manage_structure')
  const [deptFilter, setDeptFilter] = useState<number | undefined>()

  const { data: depts, isLoading: deptsLoading } = useDepartments()
  const { data, isLoading, isError, refetch } = usePositions(deptFilter)
  const create = useCreatePosition()
  const update = useUpdatePosition()
  const remove = useDeletePosition()

  const [form, setForm]         = useState<PosFormState | null>(null)
  const [formError, setFormError] = useState<string | null>(null)

  const deptList = depts?.data ?? []
  const rows     = data?.data ?? []

  const openCreate = () => { setForm(emptyForm()); setFormError(null) }
  const openEdit   = (pos: Position) => {
    setForm({ id: pos.id, code: pos.code ?? '', title: pos.title, department_id: pos.department_id ?? undefined, pay_grade: pos.pay_grade ?? '', description: pos.description ?? '', is_active: pos.is_active })
    setFormError(null)
  }
  const closeForm = () => setForm(null)

  const set = (field: keyof PosFormState, value: unknown) =>
    setForm((f) => f ? { ...f, [field]: value } : f)

  const handleDelete = async (id: number) => {
    try {
      await remove.mutateAsync(id)
      toast.success('Position deleted successfully')
      refetch()
    } catch (err: unknown) {
      const message = firstErrorMessage(err)
      toast.error(`Failed to delete position: ${message}`)
      throw err
    }
  }

  const handleSave = async () => {
    if (!form) return
    setFormError(null)
    if (!form.title.trim()) { setFormError('Title is required.'); return }
    if (!form.id && !form.code.trim()) { setFormError('Code is required.'); return }
    
    try {
      if (form.id) {
        await update.mutateAsync({ ...form, id: form.id as number })
        toast.success('Position updated successfully')
      } else {
        await create.mutateAsync(form)
        toast.success('Position created successfully')
      }
      closeForm()
      refetch()
    } catch (err: unknown) {
      const message = firstErrorMessage(err)
      toast.error(`Failed to ${form.id ? 'update' : 'create'} position: ${message}`)
      setFormError(`${form.id ? 'Update' : 'Create'} failed: ${message}`)
    }
  }

  if (isLoading || deptsLoading) return <SkeletonLoader rows={8} />
  if (isError) return <div className="text-red-600 text-sm mt-4">Failed to load positions.</div>

  return (
    <div>
      <PageHeader title="Positions" />

      {canManage && (
        <div className="flex justify-end mb-4">
          <button
            onClick={openCreate}
            className="px-4 py-2 text-sm bg-neutral-900 text-white rounded hover:bg-neutral-800"
          >
            + Add Position
          </button>
        </div>
      )}

      {/* Filter by dept */}
      <div className="bg-white border border-neutral-200 rounded-lg p-4 mb-4">
        <select
          value={deptFilter ?? ''}
          onChange={(e) => setDeptFilter(Number(e.target.value) || undefined)}
          className="border border-neutral-300 rounded px-3 py-2 text-sm bg-white focus:ring-1 focus:ring-neutral-400 outline-none min-w-[200px]"
        >
          <option value="">All Departments</option>
          {deptList.map((d) => <option key={d.id} value={d.id}>{d.name}</option>)}
        </select>
      </div>

      {/* Table */}
      <div className="bg-white border border-neutral-200 rounded-lg overflow-hidden">
        <table className="min-w-full text-sm">
          <thead className="bg-neutral-50 border-b border-neutral-200">
            <tr>
              {['Title', 'Department', 'Status', canManage ? 'Actions' : ''].filter(Boolean).map((h) => (
                <th key={h} className="px-3 py-2.5 text-left text-xs font-semibold text-neutral-500 uppercase tracking-wider">{h}</th>
              ))}
            </tr>
          </thead>
          <tbody className="divide-y divide-neutral-100">
            {rows.length === 0 && (
              <tr><td colSpan={canManage ? 4 : 3} className="px-3 py-8 text-center text-neutral-400">No positions found.</td></tr>
            )}
            {rows.map((pos) => (
              <tr key={pos.id} className="hover:bg-neutral-50 transition-colors">
                <td className="px-3 py-2 font-medium text-neutral-900">{pos.title}</td>
                <td className="px-3 py-2 text-neutral-600">{pos.department?.name ?? '—'}</td>
                <td className="px-3 py-2">
                  <span className={`inline-flex items-center rounded px-2 py-0.5 text-xs font-medium ${pos.is_active ? 'bg-green-100 text-green-700' : 'bg-neutral-100 text-neutral-500'}`}>
                    {pos.is_active ? 'Active' : 'Inactive'}
                  </span>
                </td>
                {canManage && (
                  <td className="px-3 py-2 flex gap-2">
                    <button onClick={() => openEdit(pos)} className="text-xs text-neutral-600 hover:underline">Edit</button>
                    <ConfirmDestructiveDialog
                      title="Delete Position?"
                      description={`This will permanently delete "${pos.title}". Any employees assigned to this position will need to be reassigned. This action cannot be undone.`}
                      confirmWord="DELETE"
                      confirmLabel="Delete Position"
                      onConfirm={() => handleDelete(pos.id)}
                    >
                      <button disabled={remove.isPending} className="text-xs text-red-500 hover:underline disabled:opacity-50 disabled:cursor-not-allowed">Delete</button>
                    </ConfirmDestructiveDialog>
                  </td>
                )}
              </tr>
            ))}
          </tbody>
        </table>
      </div>

      {/* Modal */}
      {form !== null && (
        <div className="fixed inset-0 bg-black/40 flex items-center justify-center z-50">
          <div className="bg-white rounded-lg border border-neutral-200 p-6 w-full max-w-md">
            <h2 className="text-lg font-semibold text-neutral-900 mb-4">{form.id ? 'Edit Position' : 'New Position'}</h2>
            {formError && <div className="text-red-600 text-sm mb-3 bg-red-50 rounded px-3 py-2">{formError}</div>}
            <div className="space-y-4">
              <div>
                <label className="block text-sm font-medium text-neutral-700 mb-1">
                  Code <span className="text-red-500">*</span>
                </label>
                <input 
                  value={form.code} 
                  onChange={(e) => set('code', e.target.value.toUpperCase())}
                  placeholder="e.g. HR-MGR"
                  className={`w-full border rounded px-3 py-2 text-sm outline-none focus:ring-1 focus:ring-neutral-400 font-mono ${
                    !form.code.trim() && formError ? 'border-red-500' : 'border-neutral-300'
                  }`} 
                />
              </div>
              <div>
                <label className="block text-sm font-medium text-neutral-700 mb-1">
                  Title <span className="text-red-500">*</span>
                </label>
                <input 
                  value={form.title} 
                  onChange={(e) => set('title', e.target.value)}
                  className={`w-full border rounded px-3 py-2 text-sm outline-none focus:ring-1 focus:ring-neutral-400 ${
                    !form.title.trim() && formError ? 'border-red-500' : 'border-neutral-300'
                  }`} 
                />
              </div>
              <div>
                <label className="block text-sm font-medium text-neutral-700 mb-1">Department</label>
                <select 
                  value={form.department_id ?? ''} 
                  onChange={(e) => set('department_id', Number(e.target.value) || undefined)}
                  className="w-full border border-neutral-300 rounded px-3 py-2 text-sm outline-none focus:ring-1 focus:ring-neutral-400"
                >
                  <option value="">— None —</option>
                  {deptList.map((d) => <option key={d.id} value={d.id}>{d.name}</option>)}
                </select>
              </div>
              <div>
                <label className="block text-sm font-medium text-neutral-700 mb-1">Description</label>
                <textarea 
                  value={form.description} 
                  onChange={(e) => set('description', e.target.value)} 
                  rows={2}
                  className="w-full border border-neutral-300 rounded px-3 py-2 text-sm outline-none focus:ring-1 focus:ring-neutral-400" 
                />
              </div>
              <label className="flex items-center gap-2 text-sm text-neutral-700 cursor-pointer">
                <input type="checkbox" checked={form.is_active} onChange={(e) => set('is_active', e.target.checked)} className="rounded" />
                Active
              </label>
            </div>
            <div className="flex justify-end gap-3 mt-5">
              <button onClick={closeForm} className="px-4 py-2 text-sm text-neutral-600 hover:bg-neutral-100 rounded">Cancel</button>
              <button 
                onClick={handleSave} 
                disabled={create.isPending || update.isPending}
                className="px-4 py-2 text-sm bg-neutral-900 hover:bg-neutral-800 text-white rounded disabled:opacity-50 disabled:cursor-not-allowed"
              >
                {create.isPending || update.isPending ? 'Saving…' : form.id ? 'Save Changes' : 'Create'}
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  )
}
