import { useState, useMemo } from 'react'
import { useNavigate } from 'react-router-dom'
import { ClipboardCheck } from 'lucide-react'
import { toast } from 'sonner'
import { useCreateInspection, useInspectionTemplates } from '@/hooks/useQC'
import { useItems } from '@/hooks/useInventory'
import { useEmployees, useDepartments } from '@/hooks/useEmployees'
import type { InspectionStage } from '@/types/qc'

const STAGE_LABELS: Record<InspectionStage, string> = {
  iqc: 'IQC — Incoming Quality Control',
  ipqc: 'IPQC — In-Process Quality Control',
  oqc: 'OQC — Outgoing Quality Control',
}

export default function CreateInspectionPage(): React.ReactElement {
  const navigate = useNavigate()
  const createMut = useCreateInspection()

  const { data: itemsData } = useItems({ per_page: 500 })
  const items = itemsData?.data ?? []

  const { data: templatesData } = useInspectionTemplates({ is_active: true, per_page: 100 })
  const templates = templatesData?.data ?? []

  const { data: deptsData } = useDepartments()
  const qcDeptId = deptsData?.data?.find(d => d.code === 'QC')?.id

  const { data: employeesData } = useEmployees({
    department_id: qcDeptId,
    per_page: 200,
    is_active: true,
  })
  const employees = employeesData?.data ?? []

  const [form, setForm] = useState({
    stage: 'iqc' as InspectionStage,
    inspection_date: (() => {
      const d = new Date()
      return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`
    })(),
    item_master_id: '' as number | '',
    qty_inspected: '',
    inspection_template_id: '' as number | '',
    inspector_id: '' as number | '',
    remarks: '',
  })

  const set = (k: keyof typeof form, v: unknown) =>
    setForm(prev => ({ ...prev, [k]: v }))

  const [touched, setTouched] = useState<Set<string>>(new Set())
  const touch = (k: string) => setTouched(prev => new Set([...prev, k]))
  const ve = useMemo(() => {
    const e: Record<string, string | undefined> = {}
    if (!form.inspection_date) e.inspection_date = 'Inspection date is required.'
    const qty = Number(form.qty_inspected)
    if (!form.qty_inspected || isNaN(qty) || qty <= 0) e.qty_inspected = 'Must be greater than 0.'
    return e
  }, [form])
  const fe = (k: string) => (touched.has(k) ? ve[k] : undefined)

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault()
    const parsedQty = Number(form.qty_inspected)
    if (!form.qty_inspected || parsedQty <= 0) {
      toast.error('Qty Inspected must be greater than 0.')
      return
    }
    try {
      const insp = await createMut.mutateAsync({
        stage: form.stage,
        inspection_date: form.inspection_date,
        item_master_id: form.item_master_id !== '' ? Number(form.item_master_id) : undefined,
        qty_inspected: parsedQty,
        inspection_template_id: form.inspection_template_id !== '' ? Number(form.inspection_template_id) : undefined,
        inspector_id: form.inspector_id !== '' ? Number(form.inspector_id) : undefined,
        remarks: form.remarks || undefined,
      })
      toast.success('Inspection created.')
      navigate(`/qc/inspections/${(insp as { ulid?: string })?.ulid ?? ''}`)
    } catch (err: unknown) {
      const data = (err as { response?: { data?: { data?: Record<string, string[]> } } })?.response?.data
      const fieldErrors = data?.data
      if (fieldErrors) {
        const first = Object.values(fieldErrors).flat()[0]
        toast.error(first ?? 'Failed to create inspection.')
      } else {
        toast.error('Failed to create inspection.')
      }
    }
  }

  return (
    <div className="max-w-4xl mx-auto">
      <div className="flex items-center gap-3 mb-6">
        <div className="w-10 h-10 bg-neutral-100 rounded-lg flex items-center justify-center">
          <ClipboardCheck className="w-5 h-5 text-neutral-600" />
        </div>
        <div>
          <h1 className="text-lg font-semibold text-neutral-900 mb-6">New Inspection</h1>
          <p className="text-sm text-neutral-500 mt-0.5">Record an IQC, IPQC, or OQC inspection</p>
        </div>
      </div>

      <form onSubmit={handleSubmit} className="bg-white border border-neutral-200 rounded-lg p-6 space-y-5">
        {/* Stage */}
        <div>
          <label className="block text-sm font-medium text-neutral-700 mb-1">Stage *</label>
          <select
            className="w-full border border-neutral-300 rounded px-3 py-2 text-sm bg-white focus:ring-1 focus:ring-neutral-400"
            value={form.stage}
            onChange={e => set('stage', e.target.value)}
            required
          >
            {(Object.entries(STAGE_LABELS) as [InspectionStage, string][]).map(([k, v]) => (
              <option key={k} value={k}>{v}</option>
            ))}
          </select>
        </div>

        {/* Date */}
        <div>
          <label className="block text-sm font-medium text-neutral-700 mb-1">Inspection Date *</label>
          <input
            type="date"
            className={`w-full border rounded px-3 py-2 text-sm focus:ring-1 focus:ring-neutral-400 ${fe('inspection_date') ? 'border-red-400' : 'border-neutral-300'}`}
            value={form.inspection_date}
            onChange={e => set('inspection_date', e.target.value)}
            onBlur={() => touch('inspection_date')}
            required
          />
          {fe('inspection_date') && <p className="mt-1 text-xs text-red-600">{fe('inspection_date')}</p>}
        </div>

        {/* Item */}
        <div>
          <label className="block text-sm font-medium text-neutral-700 mb-1">Item</label>
          <select
            className="w-full border border-neutral-300 rounded px-3 py-2 text-sm bg-white focus:ring-1 focus:ring-neutral-400"
            value={form.item_master_id}
            onChange={e => set('item_master_id', e.target.value ? Number(e.target.value) : '')}
          >
            <option value="">— Select Item —</option>
            {items.map(i => (
              <option key={i.id} value={i.id}>{i.item_code} — {i.name}</option>
            ))}
          </select>
        </div>

        {/* Qty & Template */}
        <div className="grid grid-cols-2 gap-4">
          <div>
            <label className="block text-sm font-medium text-neutral-700 mb-1">Qty Inspected *</label>
            <input
              type="number"
              min="0.001"
              step="0.001"
              className={`w-full border rounded px-3 py-2 text-sm focus:ring-1 focus:ring-neutral-400 ${fe('qty_inspected') ? 'border-red-400' : 'border-neutral-300'} [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none`}
              value={form.qty_inspected}
              onChange={e => set('qty_inspected', e.target.value)}
              onBlur={() => touch('qty_inspected')}
              required
            />
            {fe('qty_inspected') && <p className="mt-1 text-xs text-red-600">{fe('qty_inspected')}</p>}
          </div>
          <div>
            <label className="block text-sm font-medium text-neutral-700 mb-1">Template</label>
            <select
              className="w-full border border-neutral-300 rounded px-3 py-2 text-sm bg-white focus:ring-1 focus:ring-neutral-400"
              value={form.inspection_template_id}
              onChange={e => set('inspection_template_id', e.target.value ? Number(e.target.value) : '')}
            >
              <option value="">— None —</option>
              {templates.map(t => (
                <option key={t.id} value={t.id}>{t.name}</option>
              ))}
            </select>
          </div>
        </div>

        {/* Inspector */}
        <div>
          <label className="block text-sm font-medium text-neutral-700 mb-1">Inspector</label>
          <select
            className="w-full border border-neutral-300 rounded px-3 py-2 text-sm bg-white focus:ring-1 focus:ring-neutral-400"
            value={form.inspector_id}
            onChange={e => set('inspector_id', e.target.value ? Number(e.target.value) : '')}
          >
            <option value="">— Select Inspector —</option>
            {employees.map(emp => (
              <option key={emp.id} value={emp.id}>
                {emp.full_name}{emp.position ? ` — ${emp.position.title}` : ''}
              </option>
            ))}
          </select>
        </div>

        {/* Remarks */}
        <div>
          <label className="block text-sm font-medium text-neutral-700 mb-1">Remarks</label>
          <textarea
            rows={2}
            className="w-full border border-neutral-300 rounded px-3 py-2 text-sm resize-none focus:ring-1 focus:ring-neutral-400"
            value={form.remarks}
            onChange={e => set('remarks', e.target.value)}
          />
        </div>

        <div className="flex justify-end gap-3 pt-2">
          <button
            type="button"
            onClick={() => navigate('/qc/inspections')}
            className="px-4 py-2 text-sm rounded border border-neutral-300 hover:bg-neutral-50"
          >
            Cancel
          </button>
          <button
            type="submit"
            disabled={createMut.isPending}
            className="px-6 py-2 text-sm rounded bg-neutral-900 text-white hover:bg-neutral-800 disabled:opacity-50 disabled:cursor-not-allowed"
          >
            {createMut.isPending ? 'Saving…' : 'Create Inspection'}
          </button>
        </div>
      </form>
    </div>
  )
}
