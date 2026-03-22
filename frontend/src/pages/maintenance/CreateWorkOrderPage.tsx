import { useState, useMemo } from 'react'
import { useNavigate } from 'react-router-dom'
import { toast } from 'sonner'
import { PageHeader } from '@/components/ui/PageHeader'
import { useEquipment, useCreateWorkOrder } from '@/hooks/useMaintenance'
import { useEmployees } from '@/hooks/useEmployees'
import { firstErrorMessage } from '@/lib/errorHandler'
import ConfirmDialog from '@/components/ui/ConfirmDialog'
import type { WorkOrderType, WorkOrderPriority } from '@/types/maintenance'

export default function CreateWorkOrderPage(): React.ReactElement {
  const navigate = useNavigate()
  const createMut = useCreateWorkOrder()

  const { data: equipmentData } = useEquipment({ per_page: 500 } as Record<string, number>)
  const equipment = equipmentData?.data ?? []

  const { data: employeesData } = useEmployees({ per_page: 200, is_active: true })
  const employees = (employeesData?.data ?? []).filter(
    e => e.department?.name === 'Maintenance'
  )

  const [form, setForm] = useState({
    equipment_id: 0,
    type: 'corrective' as WorkOrderType,
    priority: 'normal' as WorkOrderPriority,
    title: '',
    description: '',
    assigned_to_id: '' as number | '',
    scheduled_date: '',
  })

  const set = (k: keyof typeof form, v: unknown) =>
    setForm(prev => ({ ...prev, [k]: v }))

  const [touched, setTouched] = useState<Set<string>>(new Set())
  const touch = (k: string) => setTouched(prev => new Set([...prev, k]))
  const ve = useMemo(() => {
    const e: Record<string, string | undefined> = {}
    if (!form.equipment_id) e.equipment_id = 'Equipment is required.'
    if (!form.title.trim()) e.title = 'Title is required.'
    if (form.title.trim().length < 3) e.title = 'Title must be at least 3 characters.'
    if (form.description && form.description.length > 2000) e.description = 'Description must be less than 2000 characters.'
    if (form.scheduled_date) {
      const scheduled = new Date(form.scheduled_date)
      const today = new Date()
      today.setHours(0, 0, 0, 0)
      if (scheduled < today) e.scheduled_date = 'Scheduled date cannot be in the past.'
    }
    return e
  }, [form])
  const fe = (k: string) => (touched.has(k) ? ve[k] : undefined)
  const hasErrors = Object.keys(ve).length > 0

  const [showConfirm, setShowConfirm] = useState(false)

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault()
    setTouched(new Set(Object.keys(ve)))
    if (hasErrors) {
      toast.error('Please fix the errors before submitting.')
      return
    }
    setShowConfirm(true)
  }

  const doSubmit = async () => {
    try {
      await createMut.mutateAsync({
        equipment_id: form.equipment_id,
        type: form.type,
        priority: form.priority,
        title: form.title,
        description: form.description || undefined,
        assigned_to_id: form.assigned_to_id !== '' ? Number(form.assigned_to_id) : null,
        scheduled_date: form.scheduled_date || undefined,
      })
      navigate('/maintenance/work-orders')
    } catch (err) {
      toast.error(firstErrorMessage(err))
    }
  }

  return (
    <div className="max-w-4xl mx-auto">
      <PageHeader title="New Work Order" backTo="/maintenance/work-orders" />

      <form onSubmit={handleSubmit} className="bg-white border border-neutral-200 rounded-lg p-6 space-y-5">
        {/* Equipment */}
        <div>
          <label className="block text-sm font-medium text-neutral-700 mb-1">Equipment *</label>
          <select
            className={`w-full border rounded px-3 py-2 text-sm bg-white focus:ring-1 focus:ring-neutral-400 ${fe('equipment_id') ? 'border-red-400' : 'border-neutral-300'}`}
            value={form.equipment_id || ''}
            onChange={e => set('equipment_id', Number(e.target.value))}
            onBlur={() => touch('equipment_id')}
            required
          >
            <option value="">— Select Equipment —</option>
            {equipment.map(eq => (
              <option key={eq.id} value={eq.id}>{eq.equipment_code} — {eq.name}</option>
            ))}
          </select>
          {fe('equipment_id') && <p className="mt-1 text-xs text-red-600">{fe('equipment_id')}</p>}
        </div>

        {/* Type & Priority */}
        <div className="grid grid-cols-2 gap-4">
          <div>
            <label className="block text-sm font-medium text-neutral-700 mb-1">Type *</label>
            <select
              className="w-full border border-neutral-300 rounded px-3 py-2 text-sm bg-white focus:ring-1 focus:ring-neutral-400"
              value={form.type}
              onChange={e => set('type', e.target.value)}
            >
              <option value="corrective">Corrective</option>
              <option value="preventive">Preventive</option>
            </select>
          </div>
          <div>
            <label className="block text-sm font-medium text-neutral-700 mb-1">Priority *</label>
            <select
              className="w-full border border-neutral-300 rounded px-3 py-2 text-sm bg-white focus:ring-1 focus:ring-neutral-400"
              value={form.priority}
              onChange={e => set('priority', e.target.value)}
            >
              <option value="low">Low</option>
              <option value="normal">Normal</option>
              <option value="high">High</option>
              <option value="critical">Critical</option>
            </select>
          </div>
        </div>

        {/* Title */}
        <div>
          <label className="block text-sm font-medium text-neutral-700 mb-1">Title *</label>
          <input
            type="text"
            className={`w-full border rounded px-3 py-2 text-sm focus:ring-1 focus:ring-neutral-400 ${fe('title') ? 'border-red-400' : 'border-neutral-300'}`}
            value={form.title}
            onChange={e => set('title', e.target.value)}
            onBlur={() => touch('title')}
            placeholder="Brief description of the work"
            required
          />
          {fe('title') && <p className="mt-1 text-xs text-red-600">{fe('title')}</p>}
        </div>

        {/* Description */}
        <div>
          <label className="block text-sm font-medium text-neutral-700 mb-1">Description</label>
          <textarea
            rows={3}
            className={`w-full border rounded px-3 py-2 text-sm resize-none focus:ring-1 focus:ring-neutral-400 ${fe('description') ? 'border-red-400' : 'border-neutral-300'}`}
            value={form.description}
            onChange={e => set('description', e.target.value)}
            onBlur={() => touch('description')}
            placeholder="Detailed description of the maintenance task"
          />
          {fe('description') && <p className="mt-1 text-xs text-red-600">{fe('description')}</p>}
        </div>

        {/* Assigned To & Scheduled Date */}
        <div className="grid grid-cols-2 gap-4">
          <div>
            <label className="block text-sm font-medium text-neutral-700 mb-1">Assigned To</label>
            <select
              className="w-full border border-neutral-300 rounded px-3 py-2 text-sm bg-white focus:ring-1 focus:ring-neutral-400"
              value={form.assigned_to_id}
              onChange={e => set('assigned_to_id', e.target.value ? Number(e.target.value) : '')}
            >
              <option value="">— Unassigned —</option>
              {employees.map(emp => (
                <option key={emp.id} value={emp.id}>{emp.full_name}{emp.position?.title ? ` — ${emp.position.title}` : ''}</option>
              ))}
            </select>
          </div>
          <div>
            <label className="block text-sm font-medium text-neutral-700 mb-1">Scheduled Date</label>
            <input
              type="date"
              className={`w-full border rounded px-3 py-2 text-sm focus:ring-1 focus:ring-neutral-400 ${fe('scheduled_date') ? 'border-red-400' : 'border-neutral-300'}`}
              value={form.scheduled_date}
              onChange={e => set('scheduled_date', e.target.value)}
              onBlur={() => touch('scheduled_date')}
            />
            {fe('scheduled_date') && <p className="mt-1 text-xs text-red-600">{fe('scheduled_date')}</p>}
          </div>
        </div>

        <div className="flex justify-end gap-3 pt-2">
          <button
            type="button"
            onClick={() => navigate('/maintenance/work-orders')}
            className="px-4 py-2 text-sm rounded border border-neutral-300 hover:bg-neutral-50"
          >
            Cancel
          </button>
          <button
            type="submit"
            disabled={createMut.isPending || hasErrors}
            className="px-6 py-2 text-sm rounded bg-neutral-900 text-white hover:bg-neutral-800 disabled:opacity-50 disabled:cursor-not-allowed"
          >
            {createMut.isPending ? 'Saving…' : 'Create Work Order'}
          </button>
        </div>
      </form>

      <ConfirmDialog
        title="Create Work Order?"
        description="This will create a new work order and notify the assigned technician."
        confirmLabel="Create"
        onConfirm={doSubmit}
      >
        <span className="hidden" />
      </ConfirmDialog>
    </div>
  )
}
