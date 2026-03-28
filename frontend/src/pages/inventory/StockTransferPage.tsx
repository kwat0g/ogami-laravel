import { useState } from 'react'
import { ArrowRightLeft } from 'lucide-react'
import { useMutation, useQueryClient } from '@tanstack/react-query'
import api from '@/lib/api'
import { PageHeader } from '@/components/ui/PageHeader'
import { Card } from '@/components/ui/Card'
import { useItems, useWarehouseLocations } from '@/hooks/useInventory'
import { toast } from 'sonner'
import { firstErrorMessage } from '@/lib/errorHandler'
import ConfirmDialog from '@/components/ui/ConfirmDialog'

export default function StockTransferPage() {
  const qc = useQueryClient()
  const [form, setForm] = useState({
    item_id: '' as number | '',
    from_location_id: '' as number | '',
    to_location_id: '' as number | '',
    quantity: '',
    remarks: '',
  })
  const [showConfirm, setShowConfirm] = useState(false)

  const { data: itemsData } = useItems({ is_active: true, per_page: 500 })
  const { data: locations } = useWarehouseLocations({ is_active: true })
  const items = itemsData?.data ?? []

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
      toast.success(data.message || 'Transfer completed successfully')
      setForm({ item_id: '', from_location_id: '', to_location_id: '', quantity: '', remarks: '' })
      qc.invalidateQueries({ queryKey: ['stock-balances'] })
      qc.invalidateQueries({ queryKey: ['stock-ledger'] })
    },
    onError: (err) => {
      toast.error(firstErrorMessage(err))
    },
  })

  const validateForm = (): boolean => {
    if (!form.item_id) {
      toast.error('Please select an item.')
      return false
    }
    if (!form.from_location_id) {
      toast.error('Please select a source warehouse.')
      return false
    }
    if (!form.to_location_id) {
      toast.error('Please select a destination warehouse.')
      return false
    }
    if (form.from_location_id === form.to_location_id) {
      toast.error('Source and destination warehouses must be different.')
      return false
    }
    const qty = parseFloat(form.quantity)
    if (isNaN(qty) || qty <= 0) {
      toast.error('Please enter a quantity greater than zero.')
      return false
    }
    return true
  }

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault()
    if (!validateForm()) return
    setShowConfirm(true)
  }

  const selectedItem = items.find(i => i.id === form.item_id)
  const fromLocation = (locations ?? []).find(l => l.id === form.from_location_id)
  const toLocation = (locations ?? []).find(l => l.id === form.to_location_id)

  return (
    <div className="space-y-6">
      <PageHeader
        title="Warehouse-to-Warehouse Transfer"
        icon={<ArrowRightLeft className="w-5 h-5 text-neutral-600" />}
      />

      <Card className="p-6 max-w-xl">
        <form className="space-y-4" onSubmit={handleSubmit}>
          <div>
            <label className="block text-xs font-medium text-neutral-500 mb-1">Item *</label>
            <select
              value={form.item_id}
              onChange={(e) => setForm(p => ({ ...p, item_id: e.target.value ? Number(e.target.value) : '' }))}
              className="w-full border border-neutral-300 rounded px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-neutral-400 bg-white"
              required
            >
              <option value="">Select item...</option>
              {items.map((item) => (
                <option key={item.id} value={item.id}>{item.item_code} - {item.name}</option>
              ))}
            </select>
          </div>
          <div className="grid grid-cols-2 gap-4">
            <div>
              <label className="block text-xs font-medium text-neutral-500 mb-1">From Warehouse *</label>
              <select
                value={form.from_location_id}
                onChange={(e) => setForm(p => ({ ...p, from_location_id: e.target.value ? Number(e.target.value) : '' }))}
                className="w-full border border-neutral-300 rounded px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-neutral-400 bg-white"
                required
              >
                <option value="">Select source...</option>
                {(locations ?? []).map((l) => (
                  <option key={l.id} value={l.id}>{l.code} - {l.name}</option>
                ))}
              </select>
            </div>
            <div>
              <label className="block text-xs font-medium text-neutral-500 mb-1">To Warehouse *</label>
              <select
                value={form.to_location_id}
                onChange={(e) => setForm(p => ({ ...p, to_location_id: e.target.value ? Number(e.target.value) : '' }))}
                className="w-full border border-neutral-300 rounded px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-neutral-400 bg-white"
                required
              >
                <option value="">Select destination...</option>
                {(locations ?? []).filter(l => l.id !== form.from_location_id).map((l) => (
                  <option key={l.id} value={l.id}>{l.code} - {l.name}</option>
                ))}
              </select>
            </div>
          </div>
          <div>
            <label className="block text-xs font-medium text-neutral-500 mb-1">Quantity *</label>
            <input
              type="number"
              step="0.0001"
              min="0.0001"
              value={form.quantity}
              onChange={(e) => setForm(p => ({ ...p, quantity: e.target.value }))}
              className="w-full border border-neutral-300 rounded px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-neutral-400 bg-white"
              required
              placeholder="Enter quantity to transfer"
            />
          </div>
          <div>
            <label className="block text-xs font-medium text-neutral-500 mb-1">Remarks (optional)</label>
            <textarea
              rows={2}
              value={form.remarks}
              onChange={(e) => setForm(p => ({ ...p, remarks: e.target.value }))}
              className="w-full border border-neutral-300 rounded px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-neutral-400 bg-white"
              placeholder="Reason for transfer..."
            />
          </div>
          <button
            type="submit"
            className="w-full bg-teal-600 hover:bg-teal-700 text-white font-medium py-2.5 rounded transition-colors text-sm disabled:opacity-50"
            disabled={mutation.isPending}
          >
            {mutation.isPending ? 'Transferring...' : 'Transfer Stock'}
          </button>
        </form>
      </Card>

      <ConfirmDialog
        title="Confirm Stock Transfer"
        description={
          <div className="space-y-2">
            <p>You are about to transfer stock:</p>
            <ul className="text-sm text-neutral-700 list-disc pl-4 space-y-1">
              <li><strong>Item:</strong> {selectedItem ? `${selectedItem.item_code} - ${selectedItem.name}` : '—'}</li>
              <li><strong>Quantity:</strong> {form.quantity}</li>
              <li><strong>From:</strong> {fromLocation ? `${fromLocation.code} - ${fromLocation.name}` : '—'}</li>
              <li><strong>To:</strong> {toLocation ? `${toLocation.code} - ${toLocation.name}` : '—'}</li>
            </ul>
            <p className="text-amber-600 text-xs">This action will move inventory between warehouses immediately.</p>
          </div>
        }
        confirmLabel="Confirm Transfer"
        onConfirm={async () => {
          await mutation.mutateAsync()
          setShowConfirm(false)
        }}
      >
        {showConfirm ? <span /> : <span className="hidden" />}
      </ConfirmDialog>
    </div>
  )
}
