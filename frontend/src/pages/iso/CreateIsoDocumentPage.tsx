import { useState, useMemo } from 'react'
import { useNavigate } from 'react-router-dom'
import { FileText } from 'lucide-react'
import { toast } from 'sonner'
import { useCreateDocument } from '@/hooks/useISO'
import { useEmployees } from '@/hooks/useEmployees'
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

  const set = (k: keyof typeof form, v: unknown) =>
    setForm(prev => ({ ...prev, [k]: v }))

  const [touched, setTouched] = useState<Set<string>>(new Set())
  const touch = (k: string) => setTouched(prev => new Set([...prev, k]))
  const ve = useMemo(() => {
    const e: Record<string, string | undefined> = {}
    if (!form.title.trim()) e.title = 'Title is required.'
    return e
  }, [form])
  const fe = (k: string) => (touched.has(k) ? ve[k] : undefined)

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault()
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
      navigate('/iso/documents')
    } catch {
      toast.error('Failed to create document.')
    }
  }

  return (
    <div className="max-w-2xl">
      <div className="flex items-center gap-3 mb-6">
        <div className="w-10 h-10 bg-blue-100 rounded-xl flex items-center justify-center">
          <FileText className="w-5 h-5 text-blue-600" />
        </div>
        <div>
          <h1 className="text-2xl font-bold text-gray-900">New Controlled Document</h1>
          <p className="text-sm text-gray-500 mt-0.5">Register a document in the ISO document control system</p>
        </div>
      </div>

      <form onSubmit={handleSubmit} className="bg-white border border-gray-200 rounded-xl p-6 space-y-5">
        {/* Title */}
        <div>
          <label className="block text-sm font-medium text-gray-700 mb-1">Title *</label>
          <input
            type="text"
            className={`w-full border rounded-lg px-3 py-2 text-sm ${fe('title') ? 'border-red-400' : 'border-gray-300'}`}
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
            <label className="block text-sm font-medium text-gray-700 mb-1">Document Type *</label>
            <select
              className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm bg-white"
              value={form.document_type}
              onChange={e => set('document_type', e.target.value)}
            >
              {DOC_TYPES.map(t => (
                <option key={t.value} value={t.value}>{t.label}</option>
              ))}
            </select>
          </div>
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">Category</label>
            <input
              type="text"
              className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm"
              value={form.category}
              onChange={e => set('category', e.target.value)}
              placeholder="e.g. Quality, Safety"
            />
          </div>
        </div>

        {/* Version & Owner */}
        <div className="grid grid-cols-2 gap-4">
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">Version</label>
            <input
              type="text"
              className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm"
              value={form.current_version}
              onChange={e => set('current_version', e.target.value)}
              placeholder="e.g. 1.0"
            />
          </div>
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">Owner</label>
            <select
              className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm bg-white"
              value={form.owner_id}
              onChange={e => set('owner_id', e.target.value ? Number(e.target.value) : '')}
            >
              <option value="">— Select Owner —</option>
              {employees.map(emp => (
                <option key={emp.id} value={emp.id}>{emp.full_name} ({emp.employee_code})</option>
              ))}
            </select>
          </div>
        </div>

        {/* Dates */}
        <div className="grid grid-cols-2 gap-4">
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">Effective Date</label>
            <input
              type="date"
              className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm"
              value={form.effective_date}
              onChange={e => set('effective_date', e.target.value)}
            />
          </div>
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">Review Date</label>
            <input
              type="date"
              className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm"
              value={form.review_date}
              onChange={e => set('review_date', e.target.value)}
            />
          </div>
        </div>

        <div className="flex justify-end gap-3 pt-2">
          <button
            type="button"
            onClick={() => navigate('/iso/documents')}
            className="px-4 py-2 text-sm rounded-lg border border-gray-300 hover:bg-gray-50"
          >
            Cancel
          </button>
          <button
            type="submit"
            disabled={createMut.isPending}
            className="px-6 py-2 text-sm rounded-lg bg-blue-600 text-white hover:bg-blue-700 disabled:opacity-50"
          >
            {createMut.isPending ? 'Saving…' : 'Register Document'}
          </button>
        </div>
      </form>
    </div>
  )
}
