import { useState } from 'react'
import {
  useDepartments,
  useCreateDepartment,
  useUpdateDepartment,
  useDeleteDepartment,
} from '@/hooks/useEmployees'
import SkeletonLoader from '@/components/ui/SkeletonLoader'
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
  const { data, isLoading, isError } = useDepartments()
  const create = useCreateDepartment()
  const update = useUpdateDepartment()
  const remove = useDeleteDepartment()

  const [form, setForm]       = useState<DeptFormState | null>(null)
  const [formError, setFormError] = useState<string | null>(null)

  const rows = data?.data ?? []

  const openCreate = () => { setForm(emptyForm()); setFormError(null) }
  const openEdit   = (dept: Department) => { setForm({ id: dept.id, code: dept.code, name: dept.name, cost_center_code: dept.cost_center_code ?? '', is_active: dept.is_active }); setFormError(null) }
  const closeForm  = () => setForm(null)

  const handleSave = () => {
    if (!form) return
    setFormError(null)
    if (!form.code.trim() || !form.name.trim()) { setFormError('Code and Name are required.'); return }

    if (form.id) {
      update.mutate({ ...form, id: form.id as number }, { onSuccess: closeForm, onError: () => setFormError('Update failed.') })
    } else {
      create.mutate(form, { onSuccess: closeForm, onError: () => setFormError('Create failed.') })
    }
  }

  const handleDelete = (id: number) => {
    if (confirm('Delete this department?')) {
      remove.mutate(id)
    }
  }

  const set = (field: keyof DeptFormState, value: unknown) =>
    setForm((f) => f ? { ...f, [field]: value } : f)

  if (isLoading) return <SkeletonLoader rows={8} />
  if (isError)   return <div className="text-red-600 text-sm mt-4">Failed to load departments.</div>

  return (
    <div>
      {/* Header */}
      <div className="flex items-center justify-between mb-6">
        <div>
          <h1 className="text-2xl font-bold text-gray-900">Departments</h1>
          <p className="text-sm text-gray-500 mt-0.5">{rows.length} departments</p>
        </div>
        <button onClick={openCreate}
          className="bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium px-4 py-2 rounded-lg transition-colors">
          + Add Department
        </button>
      </div>

      {/* Table */}
      <div className="bg-white border border-gray-200 rounded-xl overflow-hidden">
        <table className="min-w-full text-sm">
          <thead className="bg-gray-50 border-b border-gray-200">
            <tr>
              {['Code', 'Name', 'Cost Center', 'Status', 'Actions'].map((h) => (
                <th key={h} className="px-3 py-2.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">{h}</th>
              ))}
            </tr>
          </thead>
          <tbody className="divide-y divide-gray-100">
            {rows.length === 0 && (
              <tr><td colSpan={5} className="px-3 py-8 text-center text-gray-400">No departments yet. Click + Add Department to start.</td></tr>
            )}
            {rows.map((dept) => (
              <tr key={dept.id} className="even:bg-slate-50 hover:bg-blue-50/60 transition-colors">
                <td className="px-3 py-2 font-mono text-gray-700">{dept.code}</td>
                <td className="px-3 py-2 font-medium text-gray-900">{dept.name}</td>
                <td className="px-3 py-2 text-gray-600">{dept.cost_center_code ?? '—'}</td>
                <td className="px-3 py-2">
                  <span className={`inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ${dept.is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'}`}>
                    {dept.is_active ? 'Active' : 'Inactive'}
                  </span>
                </td>
                <td className="px-3 py-2 flex gap-2">
                  <button onClick={() => openEdit(dept)} className="text-xs text-blue-600 hover:underline">Edit</button>
                  <button onClick={() => handleDelete(dept.id)} className="text-xs text-red-500 hover:underline">Delete</button>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>

      {/* Modal */}
      {form !== null && (
        <div className="fixed inset-0 bg-black/40 flex items-center justify-center z-50">
          <div className="bg-white rounded-xl shadow-xl p-6 w-full max-w-md">
            <h2 className="text-lg font-semibold text-gray-900 mb-4">{form.id ? 'Edit Department' : 'New Department'}</h2>
            {formError && <div className="text-red-600 text-sm mb-3 bg-red-50 rounded px-3 py-2">{formError}</div>}
            <div className="space-y-4">
              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-1">Code</label>
                  <input value={form.code} onChange={(e) => set('code', e.target.value)}
                    className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-blue-500" />
                </div>
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-1">Cost Center</label>
                  <input value={form.cost_center_code} onChange={(e) => set('cost_center_code', e.target.value)}
                    className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-blue-500" />
                </div>
              </div>
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">Name</label>
                <input value={form.name} onChange={(e) => set('name', e.target.value)}
                  className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-blue-500" />
              </div>
              <label className="flex items-center gap-2 text-sm text-gray-700 cursor-pointer">
                <input type="checkbox" checked={form.is_active} onChange={(e) => set('is_active', e.target.checked)} className="rounded" />
                Active
              </label>
            </div>
            <div className="flex justify-end gap-3 mt-5">
              <button onClick={closeForm} className="px-4 py-2 text-sm text-gray-600 hover:bg-gray-100 rounded-lg">Cancel</button>
              <button onClick={handleSave} disabled={create.isPending || update.isPending}
                className="px-4 py-2 text-sm bg-blue-600 hover:bg-blue-700 text-white rounded-lg disabled:opacity-50">
                {form.id ? 'Save Changes' : 'Create'}
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  )
}
