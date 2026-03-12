import { useState } from 'react'
import { toast } from 'sonner'
import { Plus } from 'lucide-react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import api from '@/lib/api'
import { useAuthStore } from '@/stores/authStore'

interface CustomerCreditNote {
  id: number
  ulid: string
  customer_id: number
  credit_note_number: string
  amount_centavos: number
  reason: string
  status: 'draft' | 'posted'
  customer?: { id: number; name: string }
  created_at: string
}

function useCustomerCreditNotes() {
  return useQuery({
    queryKey: ['customer-credit-notes'],
    queryFn: async () => {
      const res = await api.get<{ data: CustomerCreditNote[] }>('/ar/credit-notes')
      return res.data.data
    },
    staleTime: 30_000,
  })
}

function useCreateCustomerCreditNote() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async (payload: { customer_id: number; amount: number; reason: string }) => {
      const res = await api.post<{ data: CustomerCreditNote }>('/ar/credit-notes', payload)
      return res.data.data
    },
    onSuccess: () => { qc.invalidateQueries({ queryKey: ['customer-credit-notes'] }) },
  })
}

function usePostCustomerCreditNote() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async (ulid: string) => {
      const res = await api.patch<{ data: CustomerCreditNote }>(`/ar/credit-notes/${ulid}/post`)
      return res.data.data
    },
    onSuccess: () => { qc.invalidateQueries({ queryKey: ['customer-credit-notes'] }) },
  })
}

export default function CustomerCreditNotesPage(): React.ReactElement {
  const { data: notes, isLoading } = useCustomerCreditNotes()
  const create = useCreateCustomerCreditNote()
  const post = usePostCustomerCreditNote()
  const [showForm, setShowForm] = useState(false)
  const [form, setForm] = useState({ customer_id: 0, amount: 0, reason: '' })

  const list = notes ?? []
  const canCreate = useAuthStore((s) => s.hasPermission('customer_invoices.create'))
  const canApprove = useAuthStore((s) => s.hasPermission('customer_invoices.approve'))

  async function handleCreate(e: React.FormEvent) {
    e.preventDefault()
    try {
      await create.mutateAsync(form)
      toast.success('Credit note created.')
      setShowForm(false)
      setForm({ customer_id: 0, amount: 0, reason: '' })
    } catch {
      toast.error('Failed to create credit note.')
    }
  }

  async function handlePost(ulid: string) {
    try {
      await post.mutateAsync(ulid)
      toast.success('Credit note posted.')
    } catch {
      toast.error('Failed to post credit note.')
    }
  }

  return (
    <div className="max-w-5xl mx-auto">
      <div className="flex items-center justify-between mb-4">
        <div>
          <h1 className="text-2xl font-bold text-neutral-900">Customer Credit Notes</h1>
          <p className="text-sm text-neutral-500">AR credit notes issued to customers.</p>
        </div>
        {canCreate && (
          <button onClick={() => setShowForm(true)}
            className="flex items-center gap-1.5 text-sm bg-neutral-900 text-white rounded-md px-3 py-1.5 hover:bg-neutral-800">
            <Plus className="w-3.5 h-3.5" /> New Credit Note
          </button>
        )}
      </div>

      {showForm && (
        <form onSubmit={handleCreate} className="bg-white border border-neutral-200 rounded-lg p-5 mb-6 grid grid-cols-3 gap-4">
          <div>
            <label className="block text-xs font-medium text-neutral-600 mb-1">Customer ID</label>
            <input type="number" min={1} value={form.customer_id || ''} onChange={(e) => setForm(f => ({ ...f, customer_id: Number(e.target.value) }))}
              required className="w-full border border-neutral-300 rounded px-3 py-2 text-sm" />
          </div>
          <div>
            <label className="block text-xs font-medium text-neutral-600 mb-1">Amount (₱)</label>
            <input type="number" step="0.01" min={0.01} value={form.amount || ''} onChange={(e) => setForm(f => ({ ...f, amount: Number(e.target.value) }))}
              required className="w-full border border-neutral-300 rounded px-3 py-2 text-sm" />
          </div>
          <div>
            <label className="block text-xs font-medium text-neutral-600 mb-1">Reason</label>
            <input value={form.reason} onChange={(e) => setForm(f => ({ ...f, reason: e.target.value }))} required
              className="w-full border border-neutral-300 rounded px-3 py-2 text-sm" />
          </div>
          <div className="col-span-3 flex gap-2">
            <button type="submit" disabled={create.isPending}
              className="text-sm bg-neutral-900 text-white rounded px-4 py-2 hover:bg-neutral-800 disabled:opacity-50">
              {create.isPending ? 'Creating…' : 'Create'}
            </button>
            <button type="button" onClick={() => setShowForm(false)} className="text-sm text-neutral-500">Cancel</button>
          </div>
        </form>
      )}

      {isLoading ? (
        <p className="text-sm text-neutral-500">Loading…</p>
      ) : list.length === 0 ? (
        <div className="bg-white border border-neutral-200 rounded-lg px-6 py-12 text-center">
          <p className="text-neutral-500 text-sm">No customer credit notes.</p>
        </div>
      ) : (
        <div className="bg-white border border-neutral-200 rounded-lg overflow-hidden">
          <table className="w-full text-sm">
            <thead className="bg-neutral-50 border-b border-neutral-200">
              <tr>
                {['CN #', 'Customer', 'Amount', 'Reason', 'Status', 'Actions'].map(h => (
                  <th key={h} className="text-left px-4 py-2 text-xs font-medium text-neutral-600 uppercase">{h}</th>
                ))}
              </tr>
            </thead>
            <tbody>
              {list.map(cn => (
                <tr key={cn.id} className="border-b border-neutral-100 last:border-0 hover:bg-neutral-50">
                  <td className="px-4 py-3 font-mono text-xs">{cn.credit_note_number}</td>
                  <td className="px-4 py-3 text-neutral-800">{cn.customer?.name ?? cn.customer_id}</td>
                  <td className="px-4 py-3 text-neutral-700">₱{(cn.amount_centavos / 100).toLocaleString(undefined, { minimumFractionDigits: 2 })}</td>
                  <td className="px-4 py-3 text-neutral-600 max-w-xs truncate">{cn.reason}</td>
                  <td className="px-4 py-3">
                    <span className={`px-2 py-0.5 rounded text-xs font-medium ${cn.status === 'posted' ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700'}`}>
                      {cn.status}
                    </span>
                  </td>
                  <td className="px-4 py-3">
                    {cn.status === 'draft' && canApprove && (
                      <button onClick={() => handlePost(cn.ulid)} disabled={post.isPending}
                        className="text-xs text-neutral-600 hover:text-neutral-900 underline">Post</button>
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
