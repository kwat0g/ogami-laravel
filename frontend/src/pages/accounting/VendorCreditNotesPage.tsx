import { useState } from 'react'
import { toast } from 'sonner'
import { Plus } from 'lucide-react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import api from '@/lib/api'
import { useAuthStore } from '@/stores/authStore'
import { PageHeader } from '@/components/ui/PageHeader'
import { firstErrorMessage } from '@/lib/errorHandler'
import ConfirmDialog from '@/components/ui/ConfirmDialog'

interface CreditNote {
  id: number
  ulid: string
  vendor_id: number
  credit_note_number: string
  amount_centavos: number
  reason: string
  status: 'draft' | 'posted'
  vendor?: { id: number; name: string }
  created_at: string
}

function useVendorCreditNotes() {
  return useQuery({
    queryKey: ['vendor-credit-notes'],
    queryFn: async () => {
      const res = await api.get<{ data: CreditNote[] }>('/accounting/ap/credit-notes')
      return res.data.data
    },
    staleTime: 30_000,
  })
}

function useCreateVendorCreditNote() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async (payload: { vendor_id: number; amount: number; reason: string }) => {
      const res = await api.post<{ data: CreditNote }>('/accounting/ap/credit-notes', payload)
      return res.data.data
    },
    onSuccess: () => { qc.invalidateQueries({ queryKey: ['vendor-credit-notes'] }) },
  })
}

function usePostVendorCreditNote() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async (ulid: string) => {
      const res = await api.patch<{ data: CreditNote }>(`/accounting/ap/credit-notes/${ulid}/post`)
      return res.data.data
    },
    onSuccess: () => { qc.invalidateQueries({ queryKey: ['vendor-credit-notes'] }) },
  })
}

export default function VendorCreditNotesPage(): React.ReactElement {
  const { data: notes, isLoading } = useVendorCreditNotes()
  const create = useCreateVendorCreditNote()
  const post = usePostVendorCreditNote()
  const [showForm, setShowForm] = useState(false)
  const [form, setForm] = useState({ vendor_id: 0, amount: 0, reason: '' })
  const [errors, setErrors] = useState<Record<string, string>>({})

  const list = notes ?? []
  const canCreate = useAuthStore((s) => s.hasPermission('vendor_invoices.create'))
  const canApprove = useAuthStore((s) => s.hasPermission('vendor_invoices.approve'))

  function validateForm(): boolean {
    const newErrors: Record<string, string> = {}
    if (!form.vendor_id || form.vendor_id <= 0) {
      newErrors.vendor_id = 'Vendor ID is required.'
    }
    if (!form.amount || form.amount <= 0) {
      newErrors.amount = 'Amount must be greater than 0.'
    }
    if (!form.reason.trim()) {
      newErrors.reason = 'Reason is required.'
    }
    setErrors(newErrors)
    return Object.keys(newErrors).length === 0
  }

  async function handleCreate() {
    if (!validateForm()) {
      toast.error('Please fix the form errors.')
      return
    }
    try {
      await create.mutateAsync(form)
      toast.success('Credit note created.')
      setShowForm(false)
      setForm({ vendor_id: 0, amount: 0, reason: '' })
      setErrors({})
    } catch (err) {
      toast.error(firstErrorMessage(err))
    }
  }

  async function handlePost(ulid: string) {
    try {
      await post.mutateAsync(ulid)
      toast.success('Credit note posted.')
    } catch (err) {
      toast.error(firstErrorMessage(err))
    }
  }

  return (
    <div className="max-w-5xl mx-auto space-y-6">
      <PageHeader
        title="Vendor Credit Notes"
        subtitle="AP credit notes issued to vendors"
        actions={
          canCreate ? (
            <button onClick={() => setShowForm(true)}
              className="inline-flex items-center gap-1.5 text-sm bg-neutral-900 text-white rounded px-4 py-2 font-medium hover:bg-neutral-800">
              <Plus className="w-4 h-4" /> New Credit Note
            </button>
          ) : undefined
        }
      />

      {showForm && (
        <div className="bg-white border border-neutral-200 rounded-lg p-5 mb-6">
          <h2 className="text-sm font-semibold text-neutral-800 mb-4">New Credit Note</h2>
          <div className="grid grid-cols-3 gap-4">
            <div>
              <label className="block text-xs font-medium text-neutral-600 mb-1">Vendor ID *</label>
              <input 
                type="number" 
                min={1} 
                value={form.vendor_id || ''} 
                onChange={(e) => setForm(f => ({ ...f, vendor_id: Number(e.target.value) }))}
                className={`w-full border rounded px-3 py-2 text-sm ${errors.vendor_id ? 'border-red-400' : 'border-neutral-300'}`} 
              />
              {errors.vendor_id && <p className="mt-1 text-xs text-red-600">{errors.vendor_id}</p>}
            </div>
            <div>
              <label className="block text-xs font-medium text-neutral-600 mb-1">Amount (₱) *</label>
              <input 
                type="number" 
                step="0.01" 
                min={0.01} 
                value={form.amount || ''} 
                onChange={(e) => setForm(f => ({ ...f, amount: Number(e.target.value) }))}
                className={`w-full border rounded px-3 py-2 text-sm ${errors.amount ? 'border-red-400' : 'border-neutral-300'}`} 
              />
              {errors.amount && <p className="mt-1 text-xs text-red-600">{errors.amount}</p>}
            </div>
            <div>
              <label className="block text-xs font-medium text-neutral-600 mb-1">Reason *</label>
              <input 
                value={form.reason} 
                onChange={(e) => setForm(f => ({ ...f, reason: e.target.value }))}
                className={`w-full border rounded px-3 py-2 text-sm ${errors.reason ? 'border-red-400' : 'border-neutral-300'}`} 
              />
              {errors.reason && <p className="mt-1 text-xs text-red-600">{errors.reason}</p>}
            </div>
          </div>
          <div className="col-span-3 flex gap-2 mt-4">
            <ConfirmDialog
              title="Create Credit Note?"
              description="This will create a new vendor credit note. Please verify the details are correct."
              confirmLabel="Create"
              onConfirm={handleCreate}
            >
              <button 
                type="button" 
                disabled={create.isPending}
                className="text-sm bg-neutral-900 text-white rounded px-4 py-2 hover:bg-neutral-800 disabled:opacity-50"
              >
                {create.isPending ? 'Creating…' : 'Create'}
              </button>
            </ConfirmDialog>
            <button 
              type="button" 
              onClick={() => { setShowForm(false); setErrors({}); setForm({ vendor_id: 0, amount: 0, reason: '' }) }} 
              className="text-sm text-neutral-500 hover:text-neutral-700 px-4 py-2"
            >
              Cancel
            </button>
          </div>
        </div>
      )}

      {isLoading ? (
        <p className="text-sm text-neutral-500">Loading…</p>
      ) : list.length === 0 ? (
        <div className="bg-white border border-neutral-200 rounded-lg px-6 py-12 text-center">
          <p className="text-neutral-500 text-sm">No vendor credit notes.</p>
        </div>
      ) : (
        <div className="bg-white border border-neutral-200 rounded-xl overflow-hidden">
          <table className="w-full text-sm">
            <thead className="bg-neutral-50 border-b border-neutral-200">
              <tr>
                {['CN #', 'Vendor', 'Amount', 'Reason', 'Status', 'Actions'].map(h => (
                  <th key={h} className="text-left px-4 py-2 text-xs font-medium text-neutral-600 uppercase">{h}</th>
                ))}
              </tr>
            </thead>
            <tbody>
              {list.map(cn => (
                <tr key={cn.id} className="border-b border-neutral-100 last:border-0 hover:bg-neutral-50">
                  <td className="px-4 py-3 font-mono text-xs">{cn.credit_note_number}</td>
                  <td className="px-4 py-3 text-neutral-800">{cn.vendor?.name ?? cn.vendor_id}</td>
                  <td className="px-4 py-3 text-neutral-700">₱{(cn.amount_centavos / 100).toLocaleString(undefined, { minimumFractionDigits: 2 })}</td>
                  <td className="px-4 py-3 text-neutral-600 max-w-xs truncate">{cn.reason}</td>
                  <td className="px-4 py-3">
                    <span className={`px-2 py-0.5 rounded text-xs font-medium ${cn.status === 'posted' ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700'}`}>
                      {cn.status}
                    </span>
                  </td>
                  <td className="px-4 py-3">
                    {cn.status === 'draft' && canApprove && (
                      <ConfirmDialog
                        title="Post Credit Note?"
                        description="This will post the credit note and make it active. Posted credit notes cannot be edited."
                        confirmLabel="Post"
                        onConfirm={() => handlePost(cn.ulid)}
                      >
                        <button 
                          disabled={post.isPending}
                          className="text-xs text-neutral-600 hover:text-neutral-900 underline disabled:opacity-50"
                        >
                          {post.isPending ? 'Posting…' : 'Post'}
                        </button>
                      </ConfirmDialog>
                    )}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </div>
  )
}
