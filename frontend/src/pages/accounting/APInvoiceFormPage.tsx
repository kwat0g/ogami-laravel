import { useState, useEffect, useMemo } from 'react'
import { useNavigate } from 'react-router-dom'
import { ArrowLeft } from 'lucide-react'
import { useVendors, useCreateAPInvoice } from '@/hooks/useAP'
import { useFiscalPeriods, useChartOfAccounts } from '@/hooks/useAccounting'
import type { CreateVendorInvoicePayload } from '@/types/ap'

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function formatCurrency(n: number) {
  return '₱' + n.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

// ---------------------------------------------------------------------------
// AP Invoice Form Page
// ---------------------------------------------------------------------------

export default function APInvoiceFormPage() {
  const navigate = useNavigate()

  const { data: vendorsData } = useVendors({ is_active: true })
  const { data: periodsData }  = useFiscalPeriods('open')
  const { data: accountsData } = useChartOfAccounts({})

  const vendors  = vendorsData?.data ?? []
  const periods  = periodsData?.data ?? []
  const accounts = accountsData ?? []

  const [selectedVendorId, setSelectedVendorId] = useState<number | null>(null)
  const selectedVendor = vendors.find(v => v.id === selectedVendorId) ?? null

  // ── Form state ────────────────────────────────────────────────────────────
  const [form, setForm] = useState<CreateVendorInvoicePayload>({
    vendor_id: 0,
    fiscal_period_id: 0,
    ap_account_id: 0,
    expense_account_id: 0,
    invoice_date: '',
    due_date: '',
    net_amount: 0,
    vat_amount: 0,
    or_number: '',
    description: '',
  })

  // Auto-populate vendor_id
  useEffect(() => {
    if (selectedVendorId) {
      setForm(f => ({ ...f, vendor_id: selectedVendorId }))
    }
  }, [selectedVendorId])

  // Computed EWT (client-side preview; server recalculates authoritatively)
  const ewtPreview = selectedVendor?.is_ewt_subject && selectedVendor.ewt_rate
    ? Math.round(form.net_amount * selectedVendor.ewt_rate.rate * 100) / 100
    : 0

  const vatAmount = form.vat_amount ?? 0
  const netPayablePreview = form.net_amount + vatAmount - ewtPreview

  // ── Mutation ──────────────────────────────────────────────────────────────
  const createMut = useCreateAPInvoice()
  const [error, setError] = useState<string | null>(null)
  const [touched, setTouched] = useState<Set<string>>(new Set())
  const touch = (k: string) => setTouched(prev => new Set([...prev, k]))
  const ve = useMemo(() => {
    const e: Record<string, string | undefined> = {}
    if (!selectedVendorId) e.vendor_id = 'Vendor is required.'
    if (!form.fiscal_period_id) e.fiscal_period_id = 'Fiscal period is required.'
    if (!form.ap_account_id) e.ap_account_id = 'AP account is required.'
    if (!form.expense_account_id) e.expense_account_id = 'Expense account is required.'
    if (!form.invoice_date) e.invoice_date = 'Invoice date is required.'
    if (!form.due_date) e.due_date = 'Due date is required.'
    if (!form.net_amount || form.net_amount <= 0) e.net_amount = 'Must be greater than 0.'
    return e
  }, [selectedVendorId, form])
  const fe = (k: string) => (touched.has(k) ? ve[k] : undefined)

  async function handleSubmit(e: React.FormEvent) {
    e.preventDefault()
    setError(null)
    try {
      const invoice = await createMut.mutateAsync({
        ...form,
        vat_amount: vatAmount,
        or_number: form.or_number || null,
        description: form.description || null,
      })
      navigate(`/accounting/ap/invoices/${invoice.ulid}`)
    } catch (err: unknown) {
      const msg = (err as { response?: { data?: { message?: string } } })?.response?.data?.message
      setError(msg ?? 'An unexpected error occurred.')
    }
  }

  return (
    <div className="max-w-2xl mx-auto space-y-6">
      {/* Header */}
      <div className="flex items-center gap-3">
        <button
          onClick={() => navigate(-1)}
          className="p-2 rounded-lg border border-gray-300 hover:bg-gray-50"
        >
          <ArrowLeft className="w-4 h-4" />
        </button>
        <div>
          <h1 className="text-xl font-bold text-gray-900">New AP Invoice</h1>
          <p className="text-sm text-gray-500">Create a vendor invoice (AP-001 to AP-004)</p>
        </div>
      </div>

      {error && (
        <div className="rounded-lg bg-red-50 border border-red-200 text-red-700 text-sm px-4 py-3">{error}</div>
      )}

      <form onSubmit={handleSubmit} className="bg-white rounded-xl border border-gray-200 p-6 space-y-5">
        {/* Vendor */}
        <div>
          <label className="block text-sm font-medium text-gray-700 mb-1">Vendor *</label>
          <select
            className={`w-full border rounded-lg px-3 py-2 text-sm ${fe('vendor_id') ? 'border-red-400' : 'border-gray-300'}`}
            value={selectedVendorId ?? ''}
            onChange={e => setSelectedVendorId(Number(e.target.value) || null)}
            onBlur={() => touch('vendor_id')}
            required
          >
            <option value="">Select vendor…</option>
            {vendors.map(v => (
              <option key={v.id} value={v.id}>{v.name}{v.tin ? ` (${v.tin})` : ''}</option>
            ))}
          </select>
          {fe('vendor_id') && <p className="mt-1 text-xs text-red-600">{fe('vendor_id')}</p>}
          {selectedVendor?.is_ewt_subject && (
            <p className="mt-1 text-xs text-purple-600">
              EWT Subject — ATC: {selectedVendor.atc_code ?? 'N/A'} | Rate: {selectedVendor.ewt_rate ? `${(selectedVendor.ewt_rate.rate * 100).toFixed(2)}%` : 'N/A'}
            </p>
          )}
        </div>

        {/* Fiscal Period */}
        <div>
          <label className="block text-sm font-medium text-gray-700 mb-1">Fiscal Period *</label>
          <select
            className={`w-full border rounded-lg px-3 py-2 text-sm ${fe('fiscal_period_id') ? 'border-red-400' : 'border-gray-300'}`}
            value={form.fiscal_period_id || ''}
            onChange={e => setForm(f => ({ ...f, fiscal_period_id: Number(e.target.value) }))}
            onBlur={() => touch('fiscal_period_id')}
            required
          >
            <option value="">Select period…</option>
            {periods.map(p => (
              <option key={p.id} value={p.id}>{p.name}</option>
            ))}
          </select>
          {fe('fiscal_period_id') && <p className="mt-1 text-xs text-red-600">{fe('fiscal_period_id')}</p>}
        </div>

        {/* GL Accounts */}
        <div className="grid grid-cols-2 gap-4">
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">AP Account *</label>
            <select
              className={`w-full border rounded-lg px-3 py-2 text-sm ${fe('ap_account_id') ? 'border-red-400' : 'border-gray-300'}`}
              value={form.ap_account_id || ''}
              onChange={e => setForm(f => ({ ...f, ap_account_id: Number(e.target.value) }))}
              onBlur={() => touch('ap_account_id')}
              required
            >
              <option value="">Select account…</option>
              {accounts
                .filter(a => a.is_active && a.account_type === 'LIABILITY')
                .map(a => (
                  <option key={a.id} value={a.id}>{a.code} — {a.name}</option>
                ))}
            </select>
            {fe('ap_account_id') && <p className="mt-1 text-xs text-red-600">{fe('ap_account_id')}</p>}
          </div>
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">Expense Account *</label>
            <select
              className={`w-full border rounded-lg px-3 py-2 text-sm ${fe('expense_account_id') ? 'border-red-400' : 'border-gray-300'}`}
              value={form.expense_account_id || ''}
              onChange={e => setForm(f => ({ ...f, expense_account_id: Number(e.target.value) }))}
              onBlur={() => touch('expense_account_id')}
              required
            >
              <option value="">Select account…</option>
              {accounts
                .filter(a => a.is_active && ['OPEX', 'COGS'].includes(a.account_type))
                .map(a => (
                  <option key={a.id} value={a.id}>{a.code} — {a.name}</option>
                ))}
            </select>
            {fe('expense_account_id') && <p className="mt-1 text-xs text-red-600">{fe('expense_account_id')}</p>}
          </div>
        </div>

        {/* Dates */}
        <div className="grid grid-cols-2 gap-4">
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">Invoice Date *</label>
            <input
              type="date"
              className={`w-full border rounded-lg px-3 py-2 text-sm ${fe('invoice_date') ? 'border-red-400' : 'border-gray-300'}`}
              value={form.invoice_date}
              onChange={e => setForm(f => ({ ...f, invoice_date: e.target.value }))}
              onBlur={() => touch('invoice_date')}
              required
            />
            {fe('invoice_date') && <p className="mt-1 text-xs text-red-600">{fe('invoice_date')}</p>}
          </div>
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">Due Date * (AP-001)</label>
            <input
              type="date"
              className={`w-full border rounded-lg px-3 py-2 text-sm ${fe('due_date') ? 'border-red-400' : 'border-gray-300'}`}
              value={form.due_date}
              min={form.invoice_date || undefined}
              onChange={e => setForm(f => ({ ...f, due_date: e.target.value }))}
              onBlur={() => touch('due_date')}
              required
            />
            {fe('due_date') && <p className="mt-1 text-xs text-red-600">{fe('due_date')}</p>}
          </div>
        </div>

        {/* Amounts */}
        <div className="grid grid-cols-2 gap-4">
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">Net Amount *</label>
            <input
              type="number"
              step="0.01"
              min="0.01"
              className={`w-full border rounded-lg px-3 py-2 text-sm ${fe('net_amount') ? 'border-red-400' : 'border-gray-300'}`}
              value={form.net_amount || ''}
              onChange={e => setForm(f => ({ ...f, net_amount: parseFloat(e.target.value) || 0 }))}
              onBlur={() => touch('net_amount')}
              required
            />
            {fe('net_amount') && <p className="mt-1 text-xs text-red-600">{fe('net_amount')}</p>}
          </div>
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">VAT Amount (12%)</label>
            <input
              type="number"
              step="0.01"
              min="0"
              className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm"
              value={vatAmount || ''}
              onChange={e => setForm(f => ({ ...f, vat_amount: parseFloat(e.target.value) || 0 }))}
            />
          </div>
        </div>

        {/* OR Number (required when VAT > 0) */}
        <div>
          <label className="block text-sm font-medium text-gray-700 mb-1">
            OR Number {vatAmount > 0 && <span className="text-red-500">* (required when VAT &gt; 0)</span>}
          </label>
          <input
            className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm"
            value={form.or_number ?? ''}
            onChange={e => setForm(f => ({ ...f, or_number: e.target.value || null }))}
            required={vatAmount > 0}
          />
        </div>

        {/* Description */}
        <div>
          <label className="block text-sm font-medium text-gray-700 mb-1">Description</label>
          <input
            className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm"
            value={form.description ?? ''}
            onChange={e => setForm(f => ({ ...f, description: e.target.value || null }))}
          />
        </div>

        {/* AP-005 Summary */}
        <div className="bg-gray-50 rounded-lg p-4 space-y-2 text-sm">
          <div className="flex justify-between text-gray-600">
            <span>Net Amount</span>
            <span className="font-mono">{formatCurrency(form.net_amount)}</span>
          </div>
          <div className="flex justify-between text-gray-600">
            <span>VAT</span>
            <span className="font-mono">{formatCurrency(vatAmount)}</span>
          </div>
          <div className="flex justify-between text-gray-600">
            <span>EWT (preview)</span>
            <span className="font-mono text-red-600">− {formatCurrency(ewtPreview)}</span>
          </div>
          <div className="flex justify-between font-semibold text-gray-900 border-t border-gray-200 pt-2">
            <span>Net Payable (AP-005)</span>
            <span className="font-mono">{formatCurrency(netPayablePreview)}</span>
          </div>
        </div>

        {/* Actions */}
        <div className="flex justify-end gap-3 pt-2">
          <button
            type="button"
            onClick={() => navigate(-1)}
            className="px-4 py-2 text-sm rounded-lg border border-gray-300 hover:bg-gray-50"
          >
            Cancel
          </button>
          <button
            type="submit"
            disabled={createMut.isPending}
            className="px-4 py-2 text-sm rounded-lg bg-indigo-600 text-white hover:bg-indigo-700 disabled:opacity-50"
          >
            {createMut.isPending ? 'Creating…' : 'Create Invoice (Draft)'}
          </button>
        </div>
      </form>
    </div>
  )
}
