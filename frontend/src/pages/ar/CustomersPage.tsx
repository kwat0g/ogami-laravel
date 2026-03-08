import { useState } from 'react'
import { toast } from 'sonner'
import { Plus, Archive, CheckCircle, CreditCard, RefreshCw } from 'lucide-react'
import {
  useCustomers,
  useCreateCustomer,
  useUpdateCustomer,
  useArchiveCustomer,
} from '@/hooks/useAR'
import SkeletonLoader from '@/components/ui/SkeletonLoader'
import ConfirmDestructiveDialog from '@/components/ui/ConfirmDestructiveDialog'
import type { Customer, CreateCustomerPayload } from '@/types/ar'

// ---------------------------------------------------------------------------
// Credit Meter — AR-001/AR-004
// ---------------------------------------------------------------------------

function CreditMeter({ customer }: { customer: Customer }) {
  if (customer.credit_limit <= 0) {
    return <span className="text-xs text-neutral-400">Unlimited</span>
  }
  const pct = Math.min(100, (customer.current_outstanding / customer.credit_limit) * 100)
  const colour = pct >= 90 ? 'bg-red-500' : pct >= 70 ? 'bg-yellow-400' : 'bg-green-500'
  return (
    <div className="w-32">
      <div className="flex justify-between text-xs text-neutral-500 mb-0.5">
        <span>₱{customer.current_outstanding.toLocaleString()}</span>
        <span>₱{customer.credit_limit.toLocaleString()}</span>
      </div>
      <div className="h-1.5 bg-neutral-200 rounded-full overflow-hidden">
        <div className={`h-full ${colour} rounded-full`} style={{ width: `${pct}%` }} />
      </div>
    </div>
  )
}

function StatusBadge({ active }: { active: boolean }) {
  return active ? (
    <span className="inline-flex items-center gap-1 px-2 py-0.5 rounded text-xs font-medium bg-neutral-100 text-neutral-700">
      <CheckCircle className="w-3 h-3" /> Active
    </span>
  ) : (
    <span className="px-2 py-0.5 rounded text-xs font-medium bg-neutral-100 text-neutral-500">Inactive</span>
  )
}

function ArchiveCustomerButton({ customer }: { customer: Customer }) {
  const archiveMut = useArchiveCustomer()
  return (
    <ConfirmDestructiveDialog
      title="Archive Customer"
      description={`Archive "${customer.name}"? This will prevent new invoices from being created for this customer.`}
      confirmWord="ARCHIVE"
      confirmLabel="Archive"
      onConfirm={async () => {
        try {
          await archiveMut.mutateAsync(customer.id)
          toast.success('Customer archived.')
        } catch {
          toast.error('Failed to archive customer.')
        }
      }}
    >
      <button className="text-xs text-neutral-500 hover:underline flex items-center gap-1">
        <Archive className="w-3 h-3" /> Archive
      </button>
    </ConfirmDestructiveDialog>
  )
}

// ---------------------------------------------------------------------------
// Customer Form Modal
// ---------------------------------------------------------------------------

const EMPTY_FORM: CreateCustomerPayload = {
  name: '',
  tin: '',
  email: '',
  phone: '',
  contact_person: '',
  address: '',
  billing_address: '',
  credit_limit: 0,
  ar_account_id: null,
  notes: '',
}

interface CustomerFormModalProps {
  initial?: Customer
  onClose: () => void
}

function CustomerFormModal({ initial, onClose }: CustomerFormModalProps) {
  const [form, setForm] = useState<CreateCustomerPayload>(
    initial
      ? {
          name: initial.name,
          tin: initial.tin ?? '',
          email: initial.email ?? '',
          phone: initial.phone ?? '',
          contact_person: initial.contact_person ?? '',
          address: initial.address ?? '',
          billing_address: initial.billing_address ?? '',
          credit_limit: initial.credit_limit,
          ar_account_id: initial.ar_account_id,
          notes: initial.notes ?? '',
        }
      : { ...EMPTY_FORM }
  )

  const createMut = useCreateCustomer()
  const updateMut = useUpdateCustomer(initial?.id ?? 0)

  const set = (k: keyof CreateCustomerPayload, v: unknown) =>
    setForm((prev) => ({ ...prev, [k]: v }))

  const submit = async (e: React.FormEvent) => {
    e.preventDefault()
    if (initial) {
      await updateMut.mutateAsync(form)
      toast.success('Customer updated.')
    } else {
      await createMut.mutateAsync(form)
      toast.success('Customer created.')
    }
    onClose()
  }

  const isPending = createMut.isPending || updateMut.isPending
  const error = createMut.error || updateMut.error

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50">
      <div className="bg-white rounded border border-neutral-200 w-full max-w-lg max-h-[90vh] overflow-y-auto p-4 sm:p-6">
        <h2 className="text-lg font-semibold text-neutral-900 mb-4">{initial ? 'Edit Customer' : 'New Customer'}</h2>
        {error && (
          <p className="text-sm text-red-600 mb-3">{(error as Error).message}</p>
        )}
        <form onSubmit={submit} className="space-y-3">
          <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <label className="col-span-2 block">
              <span className="text-sm font-medium text-neutral-700">Name *</span>
              <input
                className="mt-1 block w-full border border-neutral-300 rounded px-3 py-1.5 text-sm focus:ring-1 focus:ring-neutral-400"
                value={form.name}
                onChange={(e) => set('name', e.target.value)}
                required
              />
            </label>
            <label className="block">
              <span className="text-sm font-medium text-neutral-700">TIN</span>
              <input
                className="mt-1 block w-full border border-neutral-300 rounded px-3 py-1.5 text-sm focus:ring-1 focus:ring-neutral-400"
                value={form.tin ?? ''}
                onChange={(e) => set('tin', e.target.value || null)}
              />
            </label>
            <label className="block">
              <span className="text-sm font-medium text-neutral-700">Credit Limit (₱)</span>
              <input
                type="number"
                min={0}
                step="0.01"
                className="mt-1 block w-full border border-neutral-300 rounded px-3 py-1.5 text-sm focus:ring-1 focus:ring-neutral-400"
                value={form.credit_limit ?? 0}
                onChange={(e) => set('credit_limit', parseFloat(e.target.value) || 0)}
              />
            </label>
            <label className="block">
              <span className="text-sm font-medium text-neutral-700">Email</span>
              <input
                type="email"
                className="mt-1 block w-full border border-neutral-300 rounded px-3 py-1.5 text-sm focus:ring-1 focus:ring-neutral-400"
                value={form.email ?? ''}
                onChange={(e) => set('email', e.target.value || null)}
              />
            </label>
            <label className="block">
              <span className="text-sm font-medium text-neutral-700">Phone</span>
              <input
                className="mt-1 block w-full border border-neutral-300 rounded px-3 py-1.5 text-sm focus:ring-1 focus:ring-neutral-400"
                value={form.phone ?? ''}
                onChange={(e) => set('phone', e.target.value || null)}
              />
            </label>
            <label className="col-span-2 block">
              <span className="text-sm font-medium text-neutral-700">Contact Person</span>
              <input
                className="mt-1 block w-full border border-neutral-300 rounded px-3 py-1.5 text-sm focus:ring-1 focus:ring-neutral-400"
                value={form.contact_person ?? ''}
                onChange={(e) => set('contact_person', e.target.value || null)}
              />
            </label>
            <label className="col-span-2 block">
              <span className="text-sm font-medium text-neutral-700">Address</span>
              <textarea
                rows={2}
                className="mt-1 block w-full border border-neutral-300 rounded px-3 py-1.5 text-sm focus:ring-1 focus:ring-neutral-400"
                value={form.address ?? ''}
                onChange={(e) => set('address', e.target.value || null)}
              />
            </label>
          </div>
          <div className="flex flex-col-reverse sm:flex-row justify-end gap-2 sm:gap-2 pt-2">
            <button type="button" onClick={onClose} className="px-4 py-1.5 rounded border border-neutral-300 text-sm hover:bg-neutral-50">
              Cancel
            </button>
            <button
              type="submit"
              disabled={isPending}
              className="px-4 py-1.5 rounded bg-neutral-900 text-white text-sm font-medium hover:bg-neutral-800 disabled:opacity-60"
            >
              {isPending ? 'Saving…' : initial ? 'Update' : 'Create Customer'}
            </button>
          </div>
        </form>
      </div>
    </div>
  )
}

// ---------------------------------------------------------------------------
// Customers Page
// ---------------------------------------------------------------------------

export default function CustomersPage() {
  const [search, setSearch] = useState('')
  const [modalCustomer, setModalCustomer] = useState<Customer | null | undefined>(undefined)
  const { data, isLoading, refetch } = useCustomers({ search, per_page: 50 })

  const customers = data?.data ?? []

  return (
    <div className="p-6 space-y-4">
      {/* Header */}
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-lg font-semibold text-neutral-900 mb-1">Customers</h1>
          <p className="text-sm text-neutral-500">Manage customer accounts and credit limits</p>
        </div>
        <div className="flex items-center gap-2">
          <button
            onClick={() => refetch()}
            className="p-2 rounded border border-neutral-300 hover:bg-neutral-50 text-neutral-500"
          >
            <RefreshCw className="w-4 h-4" />
          </button>
          <button
            onClick={() => setModalCustomer(null)}
            className="inline-flex items-center gap-1.5 px-4 py-2 rounded bg-neutral-900 text-white text-sm font-medium hover:bg-neutral-800"
          >
            <Plus className="w-4 h-4" /> New Customer
          </button>
        </div>
      </div>

      {/* Search */}
      <input
        type="search"
        placeholder="Search by name or TIN…"
        value={search}
        onChange={(e) => setSearch(e.target.value)}
        className="w-full max-w-sm border border-neutral-300 rounded px-3 py-1.5 text-sm focus:ring-1 focus:ring-neutral-400"
      />

      {/* Table */}
      {isLoading ? (
        <SkeletonLoader rows={6} />
      ) : (
        <div className="overflow-x-auto rounded border border-neutral-200">
          <table className="min-w-full divide-y divide-neutral-100 text-sm">
            <thead className="bg-neutral-50">
              <tr>
                {['Name', 'TIN', 'Contact', 'Credit Usage', 'Status', ''].map((h) => (
                  <th key={h} className="px-3 py-2.5 text-left text-xs font-semibold text-neutral-500">
                    {h}
                  </th>
                ))}
              </tr>
            </thead>
            <tbody className="bg-white divide-y divide-neutral-100">
              {customers.length === 0 ? (
                <tr>
                  <td colSpan={6} className="px-3 py-8 text-center text-neutral-400">
                    No customers found.
                  </td>
                </tr>
              ) : (
                customers.map((c) => (
                  <tr key={c.id} className="even:bg-neutral-100 hover:bg-neutral-50 transition-colors">
                    <td className="px-3 py-2 font-medium text-neutral-900">{c.name}</td>
                    <td className="px-3 py-2 text-neutral-500">{c.tin ?? '—'}</td>
                    <td className="px-3 py-2 text-neutral-500">
                      {c.contact_person ?? c.email ?? '—'}
                    </td>
                    <td className="px-3 py-2">
                      <CreditMeter customer={c} />
                    </td>
                    <td className="px-3 py-2">
                      <StatusBadge active={c.is_active} />
                    </td>
                    <td className="px-3 py-2">
                      <div className="flex items-center gap-3">
                        <button
                          onClick={() => setModalCustomer(c)}
                          className="text-xs text-neutral-600 hover:underline flex items-center gap-1"
                        >
                          <CreditCard className="w-3 h-3" /> Edit
                        </button>
                        {c.is_active && <ArchiveCustomerButton customer={c} />}
                      </div>
                    </td>
                  </tr>
                ))
              )}
            </tbody>
          </table>
        </div>
      )}

      {/* Modal */}
      {modalCustomer !== undefined && (
        <CustomerFormModal
          initial={modalCustomer ?? undefined}
          onClose={() => setModalCustomer(undefined)}
        />
      )}
    </div>
  )
}
