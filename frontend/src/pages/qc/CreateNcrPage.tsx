import { useState, useMemo } from 'react'
import { useNavigate } from 'react-router-dom'
import { AlertTriangle } from 'lucide-react'
import { toast } from 'sonner'
import { PageHeader } from '@/components/ui/PageHeader'
import { useCreateNcr, useInspections } from '@/hooks/useQC'
import { firstErrorMessage } from '@/lib/errorHandler'
import type { NcrSeverity } from '@/types/qc'

export default function CreateNcrPage(): React.ReactElement {
  const navigate = useNavigate()
  const createMut = useCreateNcr()

  const { data: inspectionsData } = useInspections({ per_page: 200 })
  const inspections = inspectionsData?.data ?? []

  const [form, setForm] = useState({
    inspection_id: 0,
    title: '',
    description: '',
    severity: 'major' as NcrSeverity,
  })

  const set = (k: keyof typeof form, v: unknown) =>
    setForm(prev => ({ ...prev, [k]: v }))

  const [touched, setTouched] = useState<Set<string>>(new Set())
  const touch = (k: string) => setTouched(prev => new Set([...prev, k]))
  const ve = useMemo(() => {
    const e: Record<string, string | undefined> = {}
    if (!form.inspection_id) e.inspection_id = 'Linked inspection is required.'
    if (!form.title.trim()) e.title = 'Title is required.'
    if (!form.description.trim()) e.description = 'Description is required.'
    if (form.description.trim().length < 10) e.description = 'Description must be at least 10 characters.'
    return e
  }, [form])
  const fe = (k: string) => (touched.has(k) ? ve[k] : undefined)

  const isFormValid = useMemo(() => {
    return form.inspection_id && 
           form.title.trim() && 
           form.description.trim() && 
           form.description.trim().length >= 10
  }, [form])

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault()

    // Touch all fields for validation display
    setTouched(new Set(['inspection_id', 'title', 'description']))

    if (!form.inspection_id) {
      return
    }
    if (!form.title.trim()) {
      return
    }
    if (!form.description.trim() || form.description.trim().length < 10) {
      return
    }

    try {
      const ncr = await createMut.mutateAsync({
        inspection_id: form.inspection_id,
        title: form.title.trim(),
        description: form.description.trim(),
        severity: form.severity,
      })
      toast.success('NCR created successfully.')
      navigate(`/qc/ncrs/${(ncr as { ulid?: string })?.ulid ?? ''}`)
    } catch (err: unknown) {
      toast.error(firstErrorMessage(err))
    }
  }

  return (
    <div className="max-w-4xl mx-auto">
      <div className="flex items-center gap-3 mb-6">
        <div className="w-10 h-10 bg-neutral-100 rounded-lg flex items-center justify-center">
          <AlertTriangle className="w-5 h-5 text-neutral-600" />
        </div>
        <div>
          <PageHeader title="Raise Non-Conformance Report" backTo="/qc/ncrs" />
          <p className="text-sm text-neutral-500 mt-0.5">Document a quality non-conformance for CAPA</p>
        </div>
      </div>

      <form onSubmit={handleSubmit} className="bg-white border border-neutral-200 rounded-lg p-6 space-y-5">
        {/* Linked Inspection */}
        <div>
          <label className="block text-sm font-medium text-neutral-700 mb-1">Linked Inspection *</label>
          <select
            className={`w-full border rounded px-3 py-2 text-sm bg-white focus:ring-1 focus:ring-neutral-400 ${fe('inspection_id') ? 'border-red-400' : 'border-neutral-300'}`}
            value={form.inspection_id || ''}
            onChange={e => set('inspection_id', Number(e.target.value))}
            onBlur={() => touch('inspection_id')}
            required
          >
            <option value="">— Select Inspection —</option>
            {inspections.map(insp => (
              <option key={insp.id} value={insp.id}>
                {insp.inspection_reference} — {insp.stage?.toUpperCase() || 'N/A'} — {insp.inspection_date}
                {insp.item_master ? ` — ${insp.item_master.item_code}` : ''}
              </option>
            ))}
          </select>
          {fe('inspection_id') && <p className="mt-1 text-xs text-red-600">{fe('inspection_id')}</p>}
        </div>

        {/* Severity */}
        <div>
          <label className="block text-sm font-medium text-neutral-700 mb-1">Severity *</label>
          <select
            className="w-full border border-neutral-300 rounded px-3 py-2 text-sm bg-white focus:ring-1 focus:ring-neutral-400"
            value={form.severity}
            onChange={e => set('severity', e.target.value)}
          >
            <option value="minor">Minor</option>
            <option value="major">Major</option>
            <option value="critical">Critical</option>
          </select>
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
            placeholder="Short description of the non-conformance"
            required
          />
          {fe('title') && <p className="mt-1 text-xs text-red-600">{fe('title')}</p>}
        </div>

        {/* Description */}
        <div>
          <label className="block text-sm font-medium text-neutral-700 mb-1">Description *</label>
          <textarea
            rows={4}
            className={`w-full border rounded px-3 py-2 text-sm resize-none focus:ring-1 focus:ring-neutral-400 ${fe('description') ? 'border-red-400' : 'border-neutral-300'}`}
            value={form.description}
            onChange={e => set('description', e.target.value)}
            onBlur={() => touch('description')}
            placeholder="Detailed description of the non-conformance (minimum 10 characters)"
            required
          />
          {fe('description') && <p className="mt-1 text-xs text-red-600">{fe('description')}</p>}
          <p className="mt-1 text-xs text-neutral-400">
            {form.description.trim().length} / 10 characters minimum
          </p>
        </div>

        <div className="flex justify-end gap-3 pt-2">
          <button
            type="button"
            onClick={() => navigate('/qc/ncrs')}
            className="px-4 py-2 text-sm rounded border border-neutral-300 hover:bg-neutral-50"
          >
            Cancel
          </button>
          <button
            type="submit"
            disabled={createMut.isPending || !isFormValid}
            className="px-6 py-2 text-sm rounded bg-neutral-900 text-white hover:bg-neutral-800 disabled:opacity-50 disabled:cursor-not-allowed"
          >
            {createMut.isPending ? 'Saving…' : 'Raise NCR'}
          </button>
        </div>
      </form>
    </div>
  )
}
