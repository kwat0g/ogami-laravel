import { useState, useEffect } from 'react'
import { useNavigate } from 'react-router-dom'
import { AlertTriangle } from 'lucide-react'
import { useCreateCustomerInvoice } from '@/hooks/useAR'
import { useCustomers } from '@/hooks/useAR'
import type { CreateCustomerInvoicePayload } from '@/types/ar'

// VAT rate will eventually come from system_settings via API.
// For now it is read from the form so the user can see the derived amount.
const VAT_RATE = 0.12

// ---------------------------------------------------------------------------
// Credit Check Banner (AR-001 client-side preview)
// ---------------------------------------------------------------------------

function CreditCheckBanner({
  outstanding,
  limit,
  invoiceAmount,
}: {
  outstanding: number
  limit: number
  invoiceAmount: number
}) {
  if (limit <= 0) return null
  const projected = outstanding + invoiceAmount
  const exceeded = projected > limit

  return (
    <div
      className={`flex items-start gap-2 rounded-lg px-3 py-2 text-sm ${
        exceeded ? 'bg-red-50 border border-red-200 text-red-700' : 'bg-green-50 border border-green-200 text-green-700'
      }`}
    >
      {exceeded && <AlertTriangle className="w-4 h-4 shrink-0 mt-0.5" />}
      <div>
        <span className="font-medium">{exceeded ? 'Credit limit will be exceeded' : 'Credit available'}</span>
        <span className="ml-2 text-xs opacity-80">
          Outstanding: ₱{outstanding.toLocaleString()} · Limit: ₱{limit.toLocaleString()} ·
          Projected: ₱{projected.toLocaleString()}
        </span>
      </div>
    </div>
  )
}

// ---------------------------------------------------------------------------
// Customer Invoice Form Page
// ---------------------------------------------------------------------------

export default function CustomerInvoiceFormPage() {
  const navigate = useNavigate()
  const createMut = useCreateCustomerInvoice()
  const { data: customersData } = useCustomers({ is_active: true, per_page: 500 })
  const customers = customersData?.data ?? []

  const [form, setForm] = useState<CreateCustomerInvoicePayload>({
    customer_id: 0,
    fiscal_period_id: 0,
    ar_account_id: 0,
    revenue_account_id: 0,
    invoice_date: new Date().toISOString().slice(0, 10),
    due_date: new Date().toISOString().slice(0, 10),
    subtotal: 0,
    vat_amount: 0,
    vat_exemption_reason: null,
    description: null,
  })

  // Derived: vat_amount from subtotal × VAT_RATE
  const derivedVat = Math.round(form.subtotal * VAT_RATE * 100) / 100
  const total = form.subtotal + (form.vat_amount ?? 0)

  const selectedCustomer = customers.find((c) => c.id === form.customer_id)

  const set = (k: keyof CreateCustomerInvoicePayload, v: unknown) =>
    setForm((prev) => ({ ...prev, [k]: v }))

  // Auto-fill vat_amount when subtotal changes
  useEffect(() => {
    set('vat_amount', derivedVat)
  // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [form.subtotal])

  const submit = async (e: React.FormEvent) => {
    e.preventDefault()
    await createMut.mutateAsync(form)
    navigate('/ar/invoices')
  }

  return (
    <div className="p-6 max-w-2xl">
      <div className="mb-6">
        <h1 className="text-2xl font-bold text-gray-900">New Customer Invoice</h1>
        <p className="text-sm text-gray-500 mt-0.5">Invoice number will be generated on approval (AR-003)</p>
      </div>

      {createMut.error && (
        <div className="mb-4 p-3 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700">
          {(createMut.error as Error).message}
        </div>
      )}

      <form onSubmit={submit} className="space-y-4">
        {/* Customer */}
        <label className="block">
          <span className="text-sm font-medium text-gray-700">Customer *</span>
          <select
            className="mt-1 block w-full border rounded-lg px-3 py-1.5 text-sm"
            value={form.customer_id || ''}
            onChange={(e) => set('customer_id', parseInt(e.target.value))}
            required
          >
            <option value="">Select customer…</option>
            {customers.map((c) => (
              <option key={c.id} value={c.id}>
                {c.name}
              </option>
            ))}
          </select>
        </label>

        {/* AR-001: Real-time credit check preview */}
        {selectedCustomer && (
          <CreditCheckBanner
            outstanding={selectedCustomer.current_outstanding}
            limit={selectedCustomer.credit_limit}
            invoiceAmount={total}
          />
        )}

        {/* Dates */}
        <div className="grid grid-cols-2 gap-3">
          <label className="block">
            <span className="text-sm font-medium text-gray-700">Invoice Date *</span>
            <input
              type="date"
              className="mt-1 block w-full border rounded-lg px-3 py-1.5 text-sm"
              value={form.invoice_date}
              onChange={(e) => set('invoice_date', e.target.value)}
              required
            />
          </label>
          <label className="block">
            <span className="text-sm font-medium text-gray-700">Due Date *</span>
            <input
              type="date"
              min={form.invoice_date}
              className="mt-1 block w-full border rounded-lg px-3 py-1.5 text-sm"
              value={form.due_date}
              onChange={(e) => set('due_date', e.target.value)}
              required
            />
          </label>
        </div>

        {/* Amounts */}
        <div className="grid grid-cols-2 gap-3">
          <label className="block">
            <span className="text-sm font-medium text-gray-700">Subtotal (₱) *</span>
            <input
              type="number"
              min={0.01}
              step="0.01"
              className="mt-1 block w-full border rounded-lg px-3 py-1.5 text-sm"
              value={form.subtotal || ''}
              onChange={(e) => set('subtotal', parseFloat(e.target.value) || 0)}
              required
            />
          </label>
          <label className="block">
            <span className="text-sm font-medium text-gray-700">
              VAT Amount (₱)
              <span className="ml-1 text-xs text-gray-400">(auto: {VAT_RATE * 100}%)</span>
            </span>
            <input
              type="number"
              min={0}
              step="0.01"
              className="mt-1 block w-full border rounded-lg px-3 py-1.5 text-sm"
              value={form.vat_amount ?? ''}
              onChange={(e) => set('vat_amount', parseFloat(e.target.value) || 0)}
            />
          </label>
        </div>

        {/* Total preview */}
        <div className="rounded-lg bg-gray-50 border px-4 py-3 text-sm">
          <div className="flex justify-between">
            <span className="text-gray-600">Subtotal</span>
            <span className="font-medium">₱{form.subtotal.toLocaleString()}</span>
          </div>
          <div className="flex justify-between">
            <span className="text-gray-600">VAT</span>
            <span className="font-medium">₱{(form.vat_amount ?? 0).toLocaleString()}</span>
          </div>
          <div className="flex justify-between border-t pt-1 mt-1 font-semibold">
            <span>Total</span>
            <span>₱{total.toLocaleString()}</span>
          </div>
        </div>

        {/* Description */}
        <label className="block">
          <span className="text-sm font-medium text-gray-700">Description</span>
          <textarea
            rows={2}
            className="mt-1 block w-full border rounded-lg px-3 py-1.5 text-sm"
            value={form.description ?? ''}
            onChange={(e) => set('description', e.target.value || null)}
          />
        </label>

        <div className="flex justify-end gap-2 pt-2">
          <button
            type="button"
            onClick={() => navigate('/ar/invoices')}
            className="px-4 py-2 rounded-lg border text-sm hover:bg-gray-50"
          >
            Cancel
          </button>
          <button
            type="submit"
            disabled={createMut.isPending}
            className="px-4 py-2 rounded-lg bg-blue-600 text-white text-sm font-medium hover:bg-blue-700 disabled:opacity-60"
          >
            {createMut.isPending ? 'Saving…' : 'Create Invoice (Draft)'}
          </button>
        </div>
      </form>
    </div>
  )
}
