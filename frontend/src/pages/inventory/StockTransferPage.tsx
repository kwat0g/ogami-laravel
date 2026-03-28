import { useState } from 'react'
import { ArrowRightLeft } from 'lucide-react'
import { useMutation, useQueryClient } from '@tanstack/react-query'
import api from '@/lib/api'
import { PageHeader } from '@/components/ui/PageHeader'
import { Card } from '@/components/ui/Card'

export default function StockTransferPage() {
  const qc = useQueryClient()
  const [form, setForm] = useState({
    item_id: '',
    from_location_id: '',
    to_location_id: '',
    quantity: '',
    remarks: '',
  })
  const [result, setResult] = useState<{ message: string } | null>(null)

  const mutation = useMutation({
    mutationFn: async () => {
      const { data } = await api.post('/inventory/transfers', {
        item_id: Number(form.item_id),
        from_location_id: Number(form.from_location_id),
        to_location_id: Number(form.to_location_id),
        quantity: Number(form.quantity),
        remarks: form.remarks || undefined,
      })
      return data
    },
    onSuccess: (data) => {
      setResult({ message: data.message || 'Transfer completed successfully' })
      setForm({ item_id: '', from_location_id: '', to_location_id: '', quantity: '', remarks: '' })
      qc.invalidateQueries({ queryKey: ['stock-balances'] })
      qc.invalidateQueries({ queryKey: ['stock-ledger'] })
    },
  })

  return (
    <div className="space-y-6">
      <PageHeader
        title="Warehouse-to-Warehouse Transfer"
        icon={<ArrowRightLeft className="w-5 h-5 text-neutral-600" />}
      />

      <Card className="p-6 max-w-xl">
        <form className="space-y-4" onSubmit={e => { e.preventDefault(); mutation.mutate() }}>
          <div>
            <label className="block text-sm font-medium mb-1">Item ID</label>
            <input className="input w-full" type="number" value={form.item_id} onChange={e => setForm(p => ({ ...p, item_id: e.target.value }))} required />
          </div>
          <div className="grid grid-cols-2 gap-4">
            <div>
              <label className="block text-sm font-medium mb-1">From Location ID</label>
              <input className="input w-full" type="number" value={form.from_location_id} onChange={e => setForm(p => ({ ...p, from_location_id: e.target.value }))} required />
            </div>
            <div>
              <label className="block text-sm font-medium mb-1">To Location ID</label>
              <input className="input w-full" type="number" value={form.to_location_id} onChange={e => setForm(p => ({ ...p, to_location_id: e.target.value }))} required />
            </div>
          </div>
          <div>
            <label className="block text-sm font-medium mb-1">Quantity</label>
            <input className="input w-full" type="number" step="0.0001" min="0.0001" value={form.quantity} onChange={e => setForm(p => ({ ...p, quantity: e.target.value }))} required />
          </div>
          <div>
            <label className="block text-sm font-medium mb-1">Remarks (optional)</label>
            <textarea className="input w-full" rows={2} value={form.remarks} onChange={e => setForm(p => ({ ...p, remarks: e.target.value }))} />
          </div>
          <button type="submit" className="btn-primary w-full" disabled={mutation.isPending}>
            {mutation.isPending ? 'Transferring...' : 'Transfer Stock'}
          </button>
          {mutation.isError && (
            <p className="text-red-600 text-sm">{(mutation.error as Error).message}</p>
          )}
          {result && (
            <p className="text-green-600 text-sm">{result.message}</p>
          )}
        </form>
      </Card>
    </div>
  )
}
