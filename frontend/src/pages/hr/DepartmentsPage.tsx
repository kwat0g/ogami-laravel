import { useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { toast } from 'sonner'
import { useAuthStore } from '@/stores/authStore'
import {
  useDepartments,
  useCreateDepartment,
  useUpdateDepartment,
  useDeleteDepartment,
} from '@/hooks/useEmployees'
import SkeletonLoader from '@/components/ui/SkeletonLoader'
import { PageHeader } from '@/components/ui/PageHeader'
import ConfirmDestructiveDialog from '@/components/ui/ConfirmDestructiveDialog'
import { firstErrorMessage } from '@/lib/errorHandler'
import type { Department } from '@/types/hr'

interface DeptFormState {
  id?: number
  code: string
  name: string
  cost_center_code: string
  is_active: boolean
}

const emptyForm = (): DeptFormState => ({ code: '', name: '', cost_center_code: '', is_active: true })

export default function DepartmentsPage() {
  const { hasPermission } = useAuthStore()
  const navigate = useNavigate()
  const canManage = hasPermission('employees.manage_structure')
  const { data, isLoading, isError, _refetch } = useDepartments()
  const create = useCreateDepartment()
  const update = useUpdateDepartment()
  const remove = useDeleteDepartment()

  const [form, setForm] = useState<DeptFormState | null>(null)
  const [formError, setFormError] = useState<string | null>(null)

  const rows = data?.data ?? []

  const openCreate = () => { setForm(emptyForm()); setFormError(null) }
  const openEdit = (dept: Department) => { setForm({ id: dept.id, code: dept.code, name: dept.name, cost_center_code: dept.cost_center_code ?? '', is_active: dept.is_active }); setFormError(null) }
  const closeForm = () => setForm(null)

  const handleSave = async () => {
    if (!form) return
    setFormError(null)
    if (!form.code.trim() || !form.name.trim()) { setFormError('Code and Name are required.'); return }

    try {
      if (form.id) {
        await update.mutateAsync({ ...form, id: form.id as number })
        toast.success('Department updated successfully')
      } else {
        await create.mutateAsync(form)
        toast.success('Department created successfully')
      }
      closeForm()
      refetch()
    } catch (err: unknown) {
      const message = firstErrorMessage(err)
      toast.error(`Failed to ${form.id ? 'update' : 'create'} department: ${message}`)
      setFormError(`${form.id ? 'Update' : 'Create'} failed: ${message}`)
    }
  }

  const handleDelete = async (id: number) => {
    try {
      await remove.mutateAsync(id)
      toast.success('Department deleted successfully')
      refetch()
    } catch (err: unknown) {
      const message = firstErrorMessage(err)
      toast.error(`Failed to delete department: ${message}`)
      throw err
    }
  }

  const set = (field: keyof DeptFormState, value: unknown) =>
    setForm((f) => f ? { ...f, [field]: value } : f)

  if (isLoading) return <SkeletonLoader rows={8} />
  if (isError) return <div className="text-red-600 text-sm mt-4">Failed to load departments.</div>

  return (
    <div>
      <PageHeader
        title="Departments"
        actions={
          <>
            <button
              onClick={() => navigate('/hr/positions')}
              className="px-4 py-2 text-sm border border-neutral-300 text-neutral-700 rounded hover:bg-neutral-50 bg-white"
            >
              Positions
            </button>
            <button
              onClick={() => navigate('/hr/shifts')}
              className="px-4 py-2 text-sm border border-neutral-300 text-neutral-700 rounded hover:bg-neutral-50 bg-white"
            >
              Shift Schedules
            </button>
            {canManage && (
              <button
                onClick={openCreate}
                className="px-4 py-2 text-sm bg-neutral-900 text-white rounded hover:bg-neutral-800"
              >
                + Add Department
              </button>
            )}
          </>
        }
      />

      {/* Table */}
      <div className="bg-white border border-neutral-200 rounded-lg overflow-hidden">
        <table className="min-w-full text-sm">
          <thead className="bg-neutral-50 border-b border-neutral-200">
            <tr>
              {['Code', 'Name', 'Cost Center', 'Status', canManage ? 'Actions' : ''].filter(Boolean).map((h) => (
                <th key={h} className="px-3 py-2.5 text-left text-xs font-semibold text-neutral-500 uppercase tracking-wider">{h}</th>
              ))}
            </tr>
          </thead>
          <tbody className="divide-y divide-neutral-100">
            {rows.length === 0 && (
              <tr><td colSpan={canManage ? 5 : 4} className="px-3 py-8 text-center text-neutral-400">
                No departments yet. {canManage && 'Click + Add Department to start.'}
              </td></tr>
            )}
            {rows.map((dept) => (
              <tr key={dept.id} className="even:bg-neutral-100 hover:bg-neutral-50 transition-colors">
                <td className="px-3 py-2 font-mono text-neutral-700">{dept.code}</td>
                <td className="px-3 py-2 font-medium text-neutral-900">{dept.name}</td>
                <td className="px-3 py-2 text-neutral-600">{dept.cost_center_code ?? '—'}</td>
                <td className="px-3 py-2">
                  <span className={`inline-flex items-center rounded px-2 py-0.5 text-xs font-medium ${dept.is_active ? 'bg-green-100 text-green-700' : 'bg-neutral-100 text-neutral-500'}`}>
                    {dept.is_active ? 'Active' : 'Inactive'}
                  </span>
                </td>
                {canManage && (
                  <td className="px-3 py-2 flex gap-2">
                    <button onClick={() => openEdit(dept)} className="text-xs text-neutral-600 hover:underline">Edit</button>
                    <ConfirmDestructiveDialog
                      title="Delete Department?"
                      description={`This will permanently delete "${dept.name}". Any employees assigned to this department will need to be reassigned. This action cannot be undone.`}
                      confirmWord="DELETE"
                      confirmLabel="Delete Department"
                      onConfirm={() => handleDelete(dept.id)}
                    >
                      <button className="text-xs text-red-500 hover:underline">Delete</button>
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
            <h2 className="text-lg font-semibold text-neutral-900 mb-4">{form.id ? 'Edit Department' : 'New Department'}</h2>
            {formError && <div className="text-red-600 text-sm mb-3 bg-red-50 rounded px-3 py-2">{formError}</div>}
            <div className="space-y-4">
              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="block text-sm font-medium text-neutral-700 mb-1">
                    Code <span className="text-red-500">*</span>
                  </label>
                  <input
                    value={form.code}
                    onChange={(e) => set('code', e.target.value)}
                    className={`w-full border rounded px-3 py-2 text-sm outline-none focus:ring-1 focus:ring-neutral-400 ${!form.code.trim() && formError ? 'border-red-500' : 'border-neutral-300'
                      }`}
                  />
                </div>
                <div>
                  <label className="block text-sm font-medium text-neutral-700 mb-1">Cost Center</label>
                  <input
                    value={form.cost_center_code}
                    onChange={(e) => set('cost_center_code', e.target.value)}
                    className="w-full border border-neutral-300 rounded px-3 py-2 text-sm outline-none focus:ring-1 focus:ring-neutral-400"
                  />
                </div>
              </div>
              <div>
                <label className="block text-sm font-medium text-neutral-700 mb-1">
                  Name <span className="text-red-500">*</span>
                </label>
                <input
                  value={form.name}
                  onChange={(e) => set('name', e.target.value)}
                  className={`w-full border rounded px-3 py-2 text-sm outline-none focus:ring-1 focus:ring-neutral-400 ${!form.name.trim() && formError ? 'border-red-500' : 'border-neutral-300'
                    }`}
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
                className="px-4 py-2 text-sm bg-neutral-900 hover:bg-neutral-800 text-white rounded disabled:opacity-50 disabled:cursor-not-allowed">
                {create.isPending || update.isPending ? 'Saving…' : form.id ? 'Save Changes' : 'Create'}
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  )
}
