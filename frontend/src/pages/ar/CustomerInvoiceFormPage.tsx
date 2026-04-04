import { formatPesoAmount } from '@/lib/formatters'
import { useState, useEffect, useMemo } from 'react'
import { useNavigate } from 'react-router-dom'
import { toast } from 'sonner'
import { AlertTriangle } from 'lucide-react'
import { PageHeader } from '@/components/ui/PageHeader'
import { useCreateCustomerInvoice } from '@/hooks/useAR'
import { useCustomers } from '@/hooks/useAR'
import { useFiscalPeriods, useChartOfAccounts } from '@/hooks/useAccounting'
import { firstErrorMessage } from '@/lib/errorHandler'
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
          Outstanding: {formatPesoAmount(outstanding)} · Limit: {formatPesoAmount(limit)} ·
          Projected: {formatPesoAmount(projected)}
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
  const { data: periodsData }  = useFiscalPeriods('open')
  const { data: accountsData } = useChartOfAccounts({})
  const periods  = periodsData?.data ?? []
  const accounts = accountsData ?? []

  const [form, setForm] = useState<CreateCustomerInvoicePayload>({
    customer_id: 0,
    fiscal_period_id: 0,
    ar_account_id: 0,
    revenue_account_id: 0,
    invoice_date: new Date().toISOString().slice(0, 10),
    due_date: new Date().toISOString().slice(0, 10),
    subtotal: 0,
    vat_amount: 0,
    or_number: null,
    vat_exemption_reason: null,
    description: null,
  })

  // Derived: vat_amount from subtotal × VAT_RATE
  const derivedVat = Math.round(form.subtotal * VAT_RATE * 100) / 100
  const total = form.subtotal + (form.vat_amount ?? 0)

  const selectedCustomer = customers.find((c) => c.id === form.customer_id)

  const set = (k: keyof CreateCustomerInvoicePayload, v: unknown) =>
    setForm((prev) => ({ ...prev, [k]: v }))

  const [touched, setTouched] = useState<Set<string>>(new Set())
  const touch = (k: string) => setTouched(prev => new Set([...prev, k]))
  const ve = useMemo(() => {
    const e: Record<string, string | undefined> = {}
    if (!form.customer_id) e.customer_id = 'Customer is required.'
    if (!form.fiscal_period_id) e.fiscal_period_id = 'Fiscal period is required.'
    if (!form.ar_account_id) e.ar_account_id = 'AR account is required.'
    if (!form.revenue_account_id) e.revenue_account_id = 'Revenue account is required.'
    if (!form.invoice_date) e.invoice_date = 'Invoice date is required.'
    if (!form.due_date) e.due_date = 'Due date is required.'
    if (!form.subtotal || form.subtotal <= 0) e.subtotal = 'Must be greater than 0.'
    if ((form.vat_amount ?? 0) > 0 && !form.or_number) e.or_number = 'Official receipt (OR) number is required when VAT is charged.'
    return e
  }, [form])
  const fe = (k: string) => (touched.has(k) ? ve[k] : undefined)

  const isFormValid = useMemo(() => {
    return Object.keys(ve).length === 0 &&
      form.customer_id > 0 &&
      form.fiscal_period_id > 0 &&
      form.ar_account_id > 0 &&
      form.revenue_account_id > 0 &&
      form.subtotal > 0
  }, [ve, form])

  // Auto-fill vat_amount when subtotal changes
  useEffect(() => {
    set('vat_amount', derivedVat)
  // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [form.subtotal])

  const submit = async (e: React.FormEvent) => {
    e.preventDefault()
    
    // Touch all fields to show validation errors
    setTouched(new Set(['customer_id', 'fiscal_period_id', 'ar_account_id', 'revenue_account_id', 'invoice_date', 'due_date', 'subtotal', 'or_number']))
    
    if (!isFormValid) {
      return
    }

    try {
      await createMut.mutateAsync(form)
      toast.success('Customer invoice created successfully.')
      navigate('/ar/invoices')
    } catch (err) {
      toast.error(firstErrorMessage(err))
    }
  }

  return (
    <div className="max-w-4xl mx-auto">
      <PageHeader title="New Invoice" backTo="/ar/invoices" />

      {createMut.error && (
        <div className="mb-4 p-3 bg-red-50 border border-red-200 rounded text-sm text-red-700">
          {firstErrorMessage(createMut.error)}
        </div>
      )}

      <form onSubmit={submit} className="space-y-4">
        {/* Customer */}
        <label className="block">
          <span className="text-sm font-medium text-neutral-700">Customer *</span>
          <select
            className={`mt-1 block w-full border rounded px-3 py-1.5 text-sm focus:ring-1 focus:ring-neutral-400 ${fe('customer_id') ? 'border-red-400' : 'border-neutral-300'}`}
            value={form.customer_id || ''}
            onChange={(e) => set('customer_id', parseInt(e.target.value))}
            onBlur={() => touch('customer_id')}
            required
          >
            <option value="">Select customer…</option>
            {customers.map((c) => (
              <option key={c.id} value={c.id}>
                {c.name}
              </option>
            ))}
          </select>
          {fe('customer_id') && <p className="mt-1 text-xs text-red-600">{fe('customer_id')}</p>}
        </label>

        {/* AR-001: Real-time credit check preview */}
        {selectedCustomer && (
          <CreditCheckBanner
            outstanding={selectedCustomer.current_outstanding}
            limit={selectedCustomer.credit_limit}
            invoiceAmount={total}
          />
        )}

        {/* Fiscal Period */}
        <label className="block">
          <span className="text-sm font-medium text-neutral-700">Fiscal Period *</span>
          <select
            className={`mt-1 block w-full border rounded px-3 py-1.5 text-sm focus:ring-1 focus:ring-neutral-400 ${fe('fiscal_period_id') ? 'border-red-400' : 'border-neutral-300'}`}
            value={form.fiscal_period_id || ''}
            onChange={e => set('fiscal_period_id', parseInt(e.target.value))}
            onBlur={() => touch('fiscal_period_id')}
            required
          >
            <option value="">— Select Period —</option>
            {periods.map(p => (
              <option key={p.id} value={p.id}>{p.name}</option>
            ))}
          </select>
          {fe('fiscal_period_id') && <p className="mt-1 text-xs text-red-600">{fe('fiscal_period_id')}</p>}
        </label>

        {/* GL Accounts */}
        <div className="grid grid-cols-2 gap-3">
          <label className="block">
            <span className="text-sm font-medium text-neutral-700">AR Account *</span>
            <select
              className={`mt-1 block w-full border rounded px-3 py-1.5 text-sm focus:ring-1 focus:ring-neutral-400 ${fe('ar_account_id') ? 'border-red-400' : 'border-neutral-300'}`}
              value={form.ar_account_id || ''}
              onChange={e => set('ar_account_id', parseInt(e.target.value))}
              onBlur={() => touch('ar_account_id')}
              required
            >
              <option value="">— Select Account —</option>
              {accounts
                .filter(a => a.is_active && a.account_type === 'ASSET')
                .map(a => (
                  <option key={a.id} value={a.id}>{a.code} — {a.name}</option>
                ))}
            </select>
            {fe('ar_account_id') && <p className="mt-1 text-xs text-red-600">{fe('ar_account_id')}</p>}
          </label>
          <label className="block">
            <span className="text-sm font-medium text-neutral-700">Revenue Account *</span>
            <select
              className={`mt-1 block w-full border rounded px-3 py-1.5 text-sm focus:ring-1 focus:ring-neutral-400 ${fe('revenue_account_id') ? 'border-red-400' : 'border-neutral-300'}`}
              value={form.revenue_account_id || ''}
              onChange={e => set('revenue_account_id', parseInt(e.target.value))}
              onBlur={() => touch('revenue_account_id')}
              required
            >
              <option value="">— Select Account —</option>
              {accounts
                .filter(a => a.is_active && a.account_type === 'REVENUE')
                .map(a => (
                  <option key={a.id} value={a.id}>{a.code} — {a.name}</option>
                ))}
            </select>
            {fe('revenue_account_id') && <p className="mt-1 text-xs text-red-600">{fe('revenue_account_id')}</p>}
          </label>
        </div>

        {/* Dates */}
        <div className="grid grid-cols-2 gap-3">
          <label className="block">
            <span className="text-sm font-medium text-neutral-700">Invoice Date *</span>
            <input
              type="date"
              className={`mt-1 block w-full border rounded px-3 py-1.5 text-sm focus:ring-1 focus:ring-neutral-400 ${fe('invoice_date') ? 'border-red-400' : 'border-neutral-300'}`}
              value={form.invoice_date}
              onChange={(e) => set('invoice_date', e.target.value)}
              onBlur={() => touch('invoice_date')}
              required
            />
            {fe('invoice_date') && <p className="mt-1 text-xs text-red-600">{fe('invoice_date')}</p>}
          </label>
          <label className="block">
            <span className="text-sm font-medium text-neutral-700">Due Date *</span>
            <input
              type="date"
              min={form.invoice_date}
              className={`mt-1 block w-full border rounded px-3 py-1.5 text-sm focus:ring-1 focus:ring-neutral-400 ${fe('due_date') ? 'border-red-400' : 'border-neutral-300'}`}
              value={form.due_date}
              onChange={(e) => set('due_date', e.target.value)}
              onBlur={() => touch('due_date')}
              required
            />
            {fe('due_date') && <p className="mt-1 text-xs text-red-600">{fe('due_date')}</p>}
          </label>
        </div>

        {/* Amounts */}
        <div className="grid grid-cols-2 gap-3">
          <label className="block">
            <span className="text-sm font-medium text-neutral-700">Subtotal (₱) *</span>
            <input
              type="number"
              min={0.01}
              step="0.01"
              className={`mt-1 block w-full border rounded px-3 py-1.5 text-sm focus:ring-1 focus:ring-neutral-400 ${fe('subtotal') ? 'border-red-400' : 'border-neutral-300'}`}
              value={form.subtotal || ''}
              onChange={(e) => set('subtotal', parseFloat(e.target.value) || 0)}
              onBlur={() => touch('subtotal')}
              required
            />
            {fe('subtotal') && <p className="mt-1 text-xs text-red-600">{fe('subtotal')}</p>}
          </label>
          <label className="block">
            <span className="text-sm font-medium text-neutral-700">
              VAT Amount (₱)
              <span className="ml-1 text-xs text-neutral-400">(auto: {VAT_RATE * 100}%)</span>
            </span>
            <input
              type="number"
              min={0}
              step="0.01"
              className="mt-1 block w-full border border-neutral-300 rounded px-3 py-1.5 text-sm focus:ring-1 focus:ring-neutral-400"
              value={form.vat_amount ?? ''}
              onChange={(e) => set('vat_amount', parseFloat(e.target.value) || 0)}
            />
          </label>
        </div>

        {/* VAT-001: OR number required when VAT > 0 */}
        {(form.vat_amount ?? 0) > 0 && (
          <label className="block">
            <span className="text-sm font-medium text-neutral-700">
              Official Receipt (OR) Number <span className="text-red-500">*</span>
            </span>
            <input
              type="text"
              maxLength={50}
              placeholder="e.g. OR-2026-00123"
              className={`mt-1 block w-full border rounded px-3 py-1.5 text-sm focus:ring-1 focus:ring-neutral-400 ${fe('or_number') ? 'border-red-400' : 'border-neutral-300'}`}
              value={form.or_number ?? ''}
              onChange={(e) => set('or_number', e.target.value || null)}
              onBlur={() => touch('or_number')}
              required
            />
            {fe('or_number') && <p className="mt-1 text-xs text-red-600">{fe('or_number')}</p>}
          </label>
        )}

        {/* Total preview */}
        <div className="rounded bg-neutral-50 border border-neutral-200 px-4 py-3 text-sm">
          <div className="flex justify-between">
            <span className="text-neutral-600">Subtotal</span>
            <span className="font-medium">{formatPesoAmount(form.subtotal)}</span>
          </div>
          <div className="flex justify-between">
            <span className="text-neutral-600">VAT</span>
            <span className="font-medium">{formatPesoAmount((form.vat_amount ?? 0))}</span>
          </div>
          <div className="flex justify-between border-t border-neutral-200 pt-1 mt-1 font-semibold">
            <span>Total</span>
            <span>{formatPesoAmount(total)}</span>
          </div>
        </div>

        {/* Description */}
        <label className="block">
          <span className="text-sm font-medium text-neutral-700">Description</span>
          <textarea
            rows={2}
            className="mt-1 block w-full border border-neutral-300 rounded px-3 py-1.5 text-sm focus:ring-1 focus:ring-neutral-400"
            value={form.description ?? ''}
            onChange={(e) => set('description', e.target.value || null)}
          />
        </label>

        <div className="flex justify-end gap-2 pt-2">
          <button
            type="button"
            onClick={() => navigate('/ar/invoices')}
            className="px-4 py-2 rounded border border-neutral-300 text-sm hover:bg-neutral-50"
          >
            Cancel
          </button>
          <button
            type="submit"
            disabled={createMut.isPending || !isFormValid}
            className="px-4 py-2 rounded bg-neutral-900 text-white text-sm font-medium hover:bg-neutral-800 disabled:opacity-60 disabled:cursor-not-allowed"
          >
            {createMut.isPending ? 'Saving…' : 'Create Invoice (Draft)'}
          </button>
        </div>
      </form>
    </div>
  )
}
