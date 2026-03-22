import { useState, useMemo } from 'react'
import { useNavigate } from 'react-router-dom'
import { toast } from 'sonner'
import { PageHeader } from '@/components/ui/PageHeader'
import { useCreateDocument } from '@/hooks/useISO'
import { useEmployees } from '@/hooks/useEmployees'
import { firstErrorMessage } from '@/lib/errorHandler'
import ConfirmDialog from '@/components/ui/ConfirmDialog'
import type { DocumentType } from '@/types/iso'

const DOC_TYPES: { value: DocumentType; label: string }[] = [
  { value: 'procedure', label: 'Procedure' },
  { value: 'work_instruction', label: 'Work Instruction' },
  { value: 'form', label: 'Form' },
  { value: 'manual', label: 'Manual' },
  { value: 'policy', label: 'Policy' },
  { value: 'record', label: 'Record' },
]

export default function CreateIsoDocumentPage(): React.ReactElement {
  const navigate = useNavigate()
  const createMut = useCreateDocument()

  const { data: employeesData } = useEmployees({ per_page: 200, is_active: true })
  const employees = employeesData?.data ?? []

  const [form, setForm] = useState({
    title: '',
    document_type: 'procedure' as DocumentType,
    category: '',
    current_version: '1.0',
    owner_id: '' as number | '',
    effective_date: '',
    review_date: '',
  })
  const [showConfirm, setShowConfirm] = useState(false)

  const set = (k: keyof typeof form, v: unknown) =>
    setForm(prev => ({ ...prev, [k]: v }))

  const [touched, setTouched] = useState<Set<string>>(new Set())
  const touch = (k: string) => setTouched(prev => new Set([...prev, k]))
  
  const ve = useMemo(() => {
    const e: Record<string, string | undefined> = {}
    if (!form.title.trim()) e.title = 'Title is required.'
    if (form.review_date && form.effective_date && form.review_date < form.effective_date) {
      e.review_date = 'Review date must be after effective date.'
    }
    return e
  }, [form])
  
  const fe = (k: string) => (touched.has(k) ? ve[k] : undefined)

  // Check if form is valid
  const isFormValid = useMemo(() => {
    return form.title.trim().length > 0 && !ve.review_date
  }, [form, ve])

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault()
    if (!isFormValid) {
      // Touch all fields to show errors
      setTouched(new Set(['title', 'effective_date', 'review_date']))
      if (!form.title.trim()) toast.error('Title is required')
      return
    }
    setShowConfirm(true)
  }

  const handleConfirmCreate = async () => {
    try {
      await createMut.mutateAsync({
        title: form.title,
        document_type: form.document_type,
        category: form.category || undefined,
        current_version: form.current_version || undefined,
        owner_id: form.owner_id !== '' ? Number(form.owner_id) : null,
        effective_date: form.effective_date || undefined,
        review_date: form.review_date || undefined,
      })
      toast.success('Document registered.')
      setShowConfirm(false)
      navigate('/iso/documents')
    } catch (err) {
      toast.error(firstErrorMessage(err))
    }
  }

  return (
    <div className="max-w-4xl mx-auto">
      <PageHeader title="New Document" backTo="/iso/documents" />

      <form onSubmit={handleSubmit} className="bg-white border border-neutral-200 rounded p-6 space-y-5">
        {/* Title */}
        <div>
          <label className="block text-sm font-medium text-neutral-700 mb-1">Title *</label>
          <input
            type="text"
            className={`w-full border rounded px-3 py-2 text-sm ${fe('title') ? 'border-red-400' : 'border-neutral-300'}`}
            value={form.title}
            onChange={e => set('title', e.target.value)}
            onBlur={() => touch('title')}
            placeholder="Document title"
            required
          />
          {fe('title') && <p className="mt-1 text-xs text-red-600">{fe('title')}</p>}
        </div>

        {/* Type & Category */}
        <div className="grid grid-cols-2 gap-4">
          <div>
            <label className="block text-sm font-medium text-neutral-700 mb-1">Document Type *</label>
            <select
              className="w-full border border-neutral-300 rounded px-3 py-2 text-sm bg-white"
              value={form.document_type}
              onChange={e => set('document_type', e.target.value)}
            >
              {DOC_TYPES.map(t => (
                <option key={t.value} value={t.value}>{t.label}</option>
              ))}
            </select>
          </div>
          <div>
            <label className="block text-sm font-medium text-neutral-700 mb-1">Category</label>
            <input
              type="text"
              className="w-full border border-neutral-300 rounded px-3 py-2 text-sm"
              value={form.category}
              onChange={e => set('category', e.target.value)}
              placeholder="e.g. Quality, Safety"
            />
          </div>
        </div>

        {/* Version & Owner */}
        <div className="grid grid-cols-2 gap-4">
          <div>
            <label className="block text-sm font-medium text-neutral-700 mb-1">Version</label>
            <input
              type="text"
              className="w-full border border-neutral-300 rounded px-3 py-2 text-sm"
              value={form.current_version}
              onChange={e => set('current_version', e.target.value)}
              placeholder="e.g. 1.0"
            />
          </div>
          <div>
            <label className="block text-sm font-medium text-neutral-700 mb-1">Owner</label>
            <select
              className="w-full border border-neutral-300 rounded px-3 py-2 text-sm bg-white"
              value={form.owner_id}
              onChange={e => set('owner_id', e.target.value ? Number(e.target.value) : '')}
            >
              <option value="">— Select Owner —</option>
              {employees.map(emp => (
                <option key={emp.id} value={emp.id}>{emp.full_name}</option>
              ))}
            </select>
          </div>
        </div>

        {/* Dates */}
        <div className="grid grid-cols-2 gap-4">
          <div>
            <label className="block text-sm font-medium text-neutral-700 mb-1">Effective Date</label>
            <input
              type="date"
              className="w-full border border-neutral-300 rounded px-3 py-2 text-sm"
              value={form.effective_date}
              onChange={e => set('effective_date', e.target.value)}
            />
          </div>
          <div>
            <label className="block text-sm font-medium text-neutral-700 mb-1">Review Date</label>
            <input
              type="date"
              className={`w-full border rounded px-3 py-2 text-sm ${fe('review_date') ? 'border-red-400' : 'border-neutral-300'}`}
              value={form.review_date}
              onChange={e => set('review_date', e.target.value)}
              onBlur={() => touch('review_date')}
            />
            {fe('review_date') && <p className="mt-1 text-xs text-red-600">{fe('review_date')}</p>}
          </div>
        </div>

        <div className="flex justify-end gap-3 pt-2">
          <button
            type="button"
            onClick={() => navigate('/iso/documents')}
            className="px-4 py-2 text-sm rounded border border-neutral-300 hover:bg-neutral-50"
          >
            Cancel
          </button>
          <button
            type="submit"
            disabled={createMut.isPending || !isFormValid}
            className="px-6 py-2 text-sm rounded bg-neutral-900 text-white hover:bg-neutral-800 disabled:opacity-50 disabled:cursor-not-allowed"
          >
            {createMut.isPending ? 'Saving…' : 'Register Document'}
          </button>
        </div>
      </form>

      <ConfirmDialog
        title="Register new document?"
        description={`This will create a new ${form.document_type.replace('_', ' ')} document titled "${form.title}". The document will be created in draft status.`}
        confirmLabel="Register Document"
        onConfirm={handleConfirmCreate}
      >
        <span />
      </ConfirmDialog>
    </div>
  )
}
