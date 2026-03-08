import { useState, useMemo } from 'react'
import { useNavigate } from 'react-router-dom'
import { toast } from 'sonner'
import { PageHeader } from '@/components/ui/PageHeader'
import { useCreateAudit } from '@/hooks/useISO'
import { useEmployees } from '@/hooks/useEmployees'

const STANDARDS = [
  'ISO 9001:2015',
  'IATF 16949:2016',
  'ISO 14001:2015',
  'ISO 45001:2018',
  'Other',
]

export default function CreateIsoAuditPage(): React.ReactElement {
  const navigate = useNavigate()
  const createMut = useCreateAudit()

  const { data: employeesData } = useEmployees({ per_page: 200, is_active: true })
  const employees = employeesData?.data ?? []

  const [form, setForm] = useState({
    audit_scope: '',
    standard: 'ISO 9001:2015',
    lead_auditor_id: '' as number | '',
    audit_date: '',
  })

  const set = (k: keyof typeof form, v: unknown) =>
    setForm(prev => ({ ...prev, [k]: v }))

  const [touched, setTouched] = useState<Set<string>>(new Set())
  const touch = (k: string) => setTouched(prev => new Set([...prev, k]))
  const ve = useMemo(() => {
    const e: Record<string, string | undefined> = {}
    if (!form.audit_scope.trim()) e.audit_scope = 'Audit scope is required.'
    if (!form.audit_date) e.audit_date = 'Audit date is required.'
    return e
  }, [form])
  const fe = (k: string) => (touched.has(k) ? ve[k] : undefined)

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault()
    try {
      await createMut.mutateAsync({
        audit_scope: form.audit_scope,
        standard: form.standard || undefined,
        lead_auditor_id: form.lead_auditor_id !== '' ? Number(form.lead_auditor_id) : null,
        audit_date: form.audit_date,
      })
      toast.success('Internal audit scheduled.')
      navigate('/iso/audits')
    } catch {
      toast.error('Failed to create audit.')
    }
  }

  return (
    <div className="max-w-4xl mx-auto">
      <PageHeader title="New Internal Audit" backTo="/iso/audits" />

      <form onSubmit={handleSubmit} className="bg-white border border-neutral-200 rounded p-6 space-y-5">
        {/* Standard */}
        <div>
          <label className="block text-sm font-medium text-neutral-700 mb-1">Standard *</label>
          <select
            className="w-full border border-neutral-300 rounded px-3 py-2 text-sm bg-white"
            value={form.standard}
            onChange={e => set('standard', e.target.value)}
          >
            {STANDARDS.map(s => <option key={s} value={s}>{s}</option>)}
          </select>
        </div>

        {/* Audit Scope */}
        <div>
          <label className="block text-sm font-medium text-neutral-700 mb-1">Audit Scope *</label>
          <textarea
            rows={4}
            className={`w-full border rounded px-3 py-2 text-sm resize-none ${fe('audit_scope') ? 'border-red-400' : 'border-neutral-300'}`}
            value={form.audit_scope}
            onChange={e => set('audit_scope', e.target.value)}
            onBlur={() => touch('audit_scope')}
            placeholder="Describe the scope, processes, and departments to be audited"
            required
          />
          {fe('audit_scope') && <p className="mt-1 text-xs text-red-600">{fe('audit_scope')}</p>}
        </div>

        {/* Lead Auditor & Date */}
        <div className="grid grid-cols-2 gap-4">
          <div>
            <label className="block text-sm font-medium text-neutral-700 mb-1">Lead Auditor</label>
            <select
              className="w-full border border-neutral-300 rounded px-3 py-2 text-sm bg-white"
              value={form.lead_auditor_id}
              onChange={e => set('lead_auditor_id', e.target.value ? Number(e.target.value) : '')}
            >
              <option value="">— Select Auditor —</option>
              {employees.map(emp => (
                <option key={emp.id} value={emp.id}>{emp.full_name}</option>
              ))}
            </select>
          </div>
          <div>
            <label className="block text-sm font-medium text-neutral-700 mb-1">Audit Date *</label>
            <input
              type="date"
              className={`w-full border rounded px-3 py-2 text-sm ${fe('audit_date') ? 'border-red-400' : 'border-neutral-300'}`}
              value={form.audit_date}
              onChange={e => set('audit_date', e.target.value)}
              onBlur={() => touch('audit_date')}
              required
            />
            {fe('audit_date') && <p className="mt-1 text-xs text-red-600">{fe('audit_date')}</p>}
          </div>
        </div>

        <div className="flex justify-end gap-3 pt-2">
          <button
            type="button"
            onClick={() => navigate('/iso/audits')}
            className="px-4 py-2 text-sm rounded border border-neutral-300 hover:bg-neutral-50"
          >
            Cancel
          </button>
          <button
            type="submit"
            disabled={createMut.isPending}
            className="px-6 py-2 text-sm rounded bg-neutral-900 text-white hover:bg-neutral-800 disabled:opacity-50 disabled:cursor-not-allowed"
          >
            {createMut.isPending ? 'Saving…' : 'Schedule Audit'}
          </button>
        </div>
      </form>
    </div>
  )
}
