import { useState, useCallback } from 'react'
import { toast } from 'sonner'
import { Plus, CheckCircle, CreditCard, RefreshCw, RotateCcw, Trash2 } from 'lucide-react'
import { useQuery } from '@tanstack/react-query'
import SearchInput from '@/components/ui/SearchInput'
import {
  useCustomers,
  useCreateCustomer,
  useUpdateCustomer,
  useArchiveCustomer,
} from '@/hooks/useAR'
import { useAuthStore } from '@/stores/authStore'
import SkeletonLoader from '@/components/ui/SkeletonLoader'
import { DepartmentGuard } from '@/components/ui/guards'
import ConfirmDestructiveDialog from '@/components/ui/ConfirmDestructiveDialog'
import ArchiveToggleButton from '@/components/ui/ArchiveToggleButton'
import ArchiveViewBanner from '@/components/ui/ArchiveViewBanner'
import ArchiveEmptyState from '@/components/ui/ArchiveEmptyState'
import { PageHeader } from '@/components/ui/PageHeader'
import { firstErrorMessage } from '@/lib/errorHandler'
import { formatTIN, formatPhoneNumber, validators, validationMessages } from '@/lib/inputFormatters'
import { ExportButton } from '@/components/ui/ExportButton'
import api from '@/lib/api'
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

// ---------------------------------------------------------------------------
// Archive Customer Button with Confirmation
// ---------------------------------------------------------------------------

function ArchiveCustomerButton({ customer, onSuccess }: { customer: Customer; onSuccess?: () => void }) {
  const archiveMut = useArchiveCustomer()
  
  const handleArchive = async () => {
    try {
      await archiveMut.mutateAsync(customer.id)
      toast.success(`Customer "${customer.name}" has been archived successfully`)
      onSuccess?.()
    } catch (err) {
      const message = firstErrorMessage(err)
      toast.error(`Failed to archive customer: ${message}`)
      throw err
    }
  }

  return (
    <ConfirmDestructiveDialog
      title="Archive Customer"
      description={`Archive "${customer.name}"? This will prevent new invoices from being created for this customer.`}
      confirmWord="ARCHIVE"
      confirmLabel="Archive"
      onConfirm={handleArchive}
    >
      <button className="text-xs text-neutral-500 hover:underline flex items-center gap-1">
        <Trash2 className="w-3 h-3" /> Delete
      </button>
    </ConfirmDestructiveDialog>
  )
}

// ---------------------------------------------------------------------------
// Customer Form Modal with Validation
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
  onSuccess?: () => void
}

function CustomerFormModal({ initial, onClose, onSuccess }: CustomerFormModalProps) {
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
  
  const [errors, setErrors] = useState<Record<string, string>>({})
  const [touched, setTouched] = useState(false)

  const createMut = useCreateCustomer()
  const updateMut = useUpdateCustomer(initial?.id ?? 0)

  const set = (k: keyof CreateCustomerPayload, v: unknown) => {
    setForm((prev) => ({ ...prev, [k]: v }))
    // Clear error when field is edited
    if (errors[k]) {
      setErrors((prev) => ({ ...prev, [k]: '' }))
    }
  }

  // Client-side validation with real-time error messages
  const tinError = touched && form.tin && !validators.tin(form.tin)
    ? validationMessages.tin
    : undefined
  const phoneError = touched && form.phone && !validators.phone(form.phone)
    ? validationMessages.phone
    : undefined

  const validate = (): boolean => {
    const newErrors: Record<string, string> = {}
    
    if (!form.name?.trim()) {
      newErrors.name = 'Customer name is required'
    } else if (form.name.length < 2) {
      newErrors.name = 'Name must be at least 2 characters'
    } else if (form.name.length > 100) {
      newErrors.name = 'Name must not exceed 100 characters'
    }
    
    if (form.email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(form.email)) {
      newErrors.email = 'Please enter a valid email address'
    }
    
    if (form.credit_limit < 0) {
      newErrors.credit_limit = 'Credit limit cannot be negative'
    }
    
    if (form.tin && !validators.tin(form.tin)) {
      newErrors.tin = validationMessages.tin
    }
    
    if (form.phone && !validators.phone(form.phone)) {
      newErrors.phone = validationMessages.phone
    }
    
    setErrors(newErrors)
    return Object.keys(newErrors).length === 0
  }

  // Normalize form data: convert empty strings to null
  // Note: TIN is kept with dashes (backend expects XXX-XXX-XXX-XXX format)
  const normalizeForm = (formData: CreateCustomerPayload): CreateCustomerPayload => ({
    ...formData,
    tin: formData.tin?.trim() || null,
    phone: formData.phone?.trim() || null,
    email: formData.email?.trim() || null,
    address: formData.address?.trim() || null,
    billing_address: formData.billing_address?.trim() || null,
    contact_person: formData.contact_person?.trim() || null,
    notes: formData.notes?.trim() || null,
  })

  const submit = async (e: React.FormEvent) => {
    e.preventDefault()
    setTouched(true)
    
    // Validate before submission
    if (!validate()) {
      toast.error('Please fix the validation errors before submitting')
      return
    }
    
    try {
      const normalizedForm = normalizeForm(form)
      if (initial) {
        await updateMut.mutateAsync(normalizedForm)
        toast.success(`Customer "${form.name}" has been updated successfully`)
      } else {
        await createMut.mutateAsync(normalizedForm)
        toast.success(`Customer "${form.name}" has been created successfully`)
      }
      onSuccess?.()
      onClose()
    } catch (err) {
      const parsed = firstErrorMessage(err)
      toast.error(`${initial ? 'Update' : 'Create'} failed: ${parsed}`)
      // Don't close modal on error so user can fix
    }
  }

  const isPending = createMut.isPending || updateMut.isPending

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50">
      <div className="bg-white rounded border border-neutral-200 w-full max-w-lg max-h-[90vh] overflow-y-auto p-4 sm:p-6">
        <h2 className="text-lg font-semibold text-neutral-900 mb-4">
          {initial ? 'Edit Customer' : 'New Customer'}
        </h2>
        
        <form onSubmit={submit} className="space-y-3">
          <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
            {/* Name Field */}
            <label className="col-span-2 block">
              <span className="text-sm font-medium text-neutral-700">
                Name <span className="text-red-500">*</span>
              </span>
              <input
                className={`mt-1 block w-full border rounded px-3 py-1.5 text-sm focus:ring-1 focus:ring-neutral-400 ${
                  errors.name ? 'border-red-500' : 'border-neutral-300'
                }`}
                value={form.name}
                onChange={(e) => set('name', e.target.value)}
                placeholder="Enter customer name"
              />
              {errors.name && <p className="text-xs text-red-600 mt-1">{errors.name}</p>}
            </label>
            
            {/* TIN Field */}
            <label className="block">
              <span className="text-sm font-medium text-neutral-700">TIN</span>
              <input
                className={`mt-1 block w-full border rounded px-3 py-1.5 text-sm focus:ring-1 focus:ring-neutral-400 ${
                  tinError ? 'border-red-400' : 'border-neutral-300'
                }`}
                value={form.tin ?? ''}
                onChange={(e) => set('tin', formatTIN(e.target.value) || null)}
                placeholder="000-000-000-000"
              />
              {tinError && <p className="mt-1 text-xs text-red-600">{tinError}</p>}
              <p className="mt-1 text-xs text-neutral-400">{validationMessages.tin}</p>
            </label>
            
            {/* Credit Limit Field */}
            <label className="block">
              <span className="text-sm font-medium text-neutral-700">Credit Limit (₱)</span>
              <input
                type="number"
                min={0}
                step="0.01"
                className={`mt-1 block w-full border rounded px-3 py-1.5 text-sm focus:ring-1 focus:ring-neutral-400 ${
                  errors.credit_limit ? 'border-red-500' : 'border-neutral-300'
                }`}
                value={form.credit_limit ?? 0}
                onChange={(e) => set('credit_limit', parseFloat(e.target.value) || 0)}
              />
              {errors.credit_limit && <p className="text-xs text-red-600 mt-1">{errors.credit_limit}</p>}
            </label>
            
            {/* Contact Person Field */}
            <label className="block">
              <span className="text-sm font-medium text-neutral-700">Contact Person</span>
              <input
                className="mt-1 block w-full border border-neutral-300 rounded px-3 py-1.5 text-sm focus:ring-1 focus:ring-neutral-400"
                value={form.contact_person ?? ''}
                onChange={(e) => set('contact_person', e.target.value || null)}
                placeholder="Primary contact name"
              />
            </label>
            
            {/* Phone Field */}
            <label className="block">
              <span className="text-sm font-medium text-neutral-700">Phone</span>
              <input
                className={`mt-1 block w-full border rounded px-3 py-1.5 text-sm focus:ring-1 focus:ring-neutral-400 ${
                  phoneError ? 'border-red-400' : 'border-neutral-300'
                }`}
                value={form.phone ?? ''}
                onChange={(e) => set('phone', formatPhoneNumber(e.target.value) || null)}
                placeholder="09XX XXX XXXX"
              />
              {phoneError && <p className="mt-1 text-xs text-red-600">{phoneError}</p>}
              <p className="mt-1 text-xs text-neutral-400">{validationMessages.phone}</p>
            </label>
            
            {/* Email Field */}
            <label className="col-span-2 block">
              <span className="text-sm font-medium text-neutral-700">Email</span>
              <input
                type="email"
                className={`mt-1 block w-full border rounded px-3 py-1.5 text-sm focus:ring-1 focus:ring-neutral-400 ${
                  errors.email ? 'border-red-500' : 'border-neutral-300'
                }`}
                value={form.email ?? ''}
                onChange={(e) => set('email', e.target.value || null)}
                placeholder="contact@company.com"
              />
              {errors.email && <p className="text-xs text-red-600 mt-1">{errors.email}</p>}
            </label>
            
            {/* Address Field */}
            <label className="col-span-2 block">
              <span className="text-sm font-medium text-neutral-700">Address</span>
              <textarea
                rows={2}
                className="mt-1 block w-full border border-neutral-300 rounded px-3 py-1.5 text-sm focus:ring-1 focus:ring-neutral-400"
                value={form.address ?? ''}
                onChange={(e) => set('address', e.target.value || null)}
                placeholder="Complete business address"
              />
            </label>
          </div>
          
          <div className="flex flex-col-reverse sm:flex-row justify-end gap-2 sm:gap-2 pt-2">
            <button 
              type="button" 
              onClick={onClose} 
              className="px-4 py-1.5 rounded border border-neutral-300 text-sm hover:bg-neutral-50"
            >
              Cancel
            </button>
            <button
              type="submit"
              disabled={isPending}
              className="px-4 py-1.5 rounded bg-neutral-900 text-white text-sm font-medium hover:bg-neutral-800 disabled:opacity-60 disabled:cursor-not-allowed"
            >
              {isPending ? 'Saving…' : initial ? 'Update Customer' : 'Create Customer'}
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
  const canManage = useAuthStore(s => s.hasPermission('customers.manage'))
  const canArchive = useAuthStore(s => s.hasPermission('customers.archive'))
  const isSuperAdmin = useAuthStore(s => s.user?.roles?.some((r: { name: string }) => r.name === 'super_admin'))
  const [search, setSearch] = useState('')
  const [debouncedSearch, setDebouncedSearch] = useState('')
  const [modalCustomer, setModalCustomer] = useState<Customer | null | undefined>(undefined)
  const [isArchiveView, setIsArchiveView] = useState(false)

  const handleSearch = useCallback((val: string) => {
    setDebouncedSearch(val)
  }, [])

  const { data, isLoading, refetch } = useCustomers({ search: debouncedSearch || undefined, per_page: 50 })

  const { data: archivedData, isLoading: archivedLoading, refetch: refetchArchived } = useQuery({
    queryKey: ['customers', 'archived', debouncedSearch],
    queryFn: () => api.get('/customers-archived', { params: { search: debouncedSearch || undefined, per_page: 50 } }),
    enabled: isArchiveView,
  })

  const customers = isArchiveView ? (archivedData?.data?.data ?? []) : (data?.data ?? [])
  const currentLoading = isArchiveView ? archivedLoading : isLoading

  const handleRefresh = async () => {
    try {
      await refetch()
      toast.success('Customer list refreshed')
    } catch (err) {
      toast.error('Failed to refresh customer list')
    }
  }

  return (
    <div className="space-y-4">
      <PageHeader
        title="Customers"
        actions={
          <div className="flex items-center gap-2">
            <ExportButton
              data={data?.data ?? []}
              columns={[
                { key: 'name', label: 'Customer Name' },
                { key: 'tin', label: 'TIN' },
                { key: 'contact_person', label: 'Contact Person' },
                { key: 'email', label: 'Email' },
                { key: 'phone', label: 'Phone' },
                { key: 'status', label: 'Status' },
              ]}
              filename="customers"
            />
            <ArchiveToggleButton isArchiveView={isArchiveView} onToggle={() => setIsArchiveView(prev => !prev)} />
          </div>
        }
      />

      {isArchiveView && <ArchiveViewBanner />}

      {/* Header */}
      <div className="flex items-center justify-between">
        <div>
          <p className="text-sm text-neutral-500">{isArchiveView ? 'Archived customer records' : 'Manage customer accounts and credit limits'}</p>
        </div>
        <div className="flex items-center gap-2">
          <button
            onClick={handleRefresh}
            className="p-2 rounded border border-neutral-300 hover:bg-neutral-50 text-neutral-500"
            title="Refresh list"
          >
            <RefreshCw className="w-4 h-4" />
          </button>
          {!isArchiveView && (
            <DepartmentGuard module="customers">
              {canManage && (
                <button
                  onClick={() => setModalCustomer(null)}
                  className="inline-flex items-center gap-1.5 px-4 py-2 rounded bg-neutral-900 text-white text-sm font-medium hover:bg-neutral-800"
                >
                  <Plus className="w-4 h-4" /> New Customer
                </button>
              )}
            </DepartmentGuard>
          )}
        </div>
      </div>

      {/* Search */}
      <SearchInput
        value={search}
        onChange={setSearch}
        onSearch={handleSearch}
        placeholder="Search by name or TIN..."
        className="max-w-sm"
      />

      {/* Table */}
      {currentLoading ? (
        <SkeletonLoader rows={6} />
      ) : customers.length === 0 ? (
        <ArchiveEmptyState isArchiveView={isArchiveView} recordLabel="customers" />
      ) : (
        <div className="overflow-x-auto rounded border border-neutral-200">
          <table className="min-w-full divide-y divide-neutral-100 text-sm">
            <thead className="bg-neutral-50">
              <tr>
                {(isArchiveView
                  ? ['Name', 'TIN', 'Contact', 'Archived On', '']
                  : ['Name', 'TIN', 'Contact', 'Credit Usage', 'Status', '']
                ).map((h) => (
                  <th key={h} className="px-3 py-2.5 text-left text-xs font-semibold text-neutral-500">
                    {h}
                  </th>
                ))}
              </tr>
            </thead>
            <tbody className="bg-white divide-y divide-neutral-100">
              {customers.map((c: Customer & { deleted_at?: string }) => (
                <tr key={c.id} className={`even:bg-neutral-100 hover:bg-neutral-50 transition-colors ${isArchiveView ? 'text-neutral-400' : ''}`}>
                  <td className="px-3 py-2 font-medium text-neutral-900">{c.name}</td>
                  <td className="px-3 py-2 text-neutral-500">{c.tin ?? '—'}</td>
                  <td className="px-3 py-2 text-neutral-500">
                    {c.contact_person ?? c.email ?? '—'}
                  </td>
                  {isArchiveView ? (
                    <>
                      <td className="px-3 py-2 text-xs text-neutral-500">
                        {c.deleted_at ? new Date(c.deleted_at).toLocaleDateString() : '—'}
                      </td>
                      <td className="px-3 py-2">
                        <div className="flex items-center gap-3">
                          <ConfirmDestructiveDialog
                            title="Restore Customer?"
                            description={`Restore "${c.name}" to the active customer list?`}
                            confirmWord="RESTORE"
                            confirmLabel="Restore"
                            variant="warning"
                            onConfirm={async () => {
                              try {
                                await api.post(`/customers/${c.id}/restore`)
                                toast.success(`Customer "${c.name}" restored.`)
                                refetch()
                                refetchArchived()
                              } catch (err) {
                                toast.error(firstErrorMessage(err))
                              }
                            }}
                          >
                            <button className="text-xs text-blue-600 hover:underline flex items-center gap-1">
                              <RotateCcw className="w-3 h-3" /> Restore
                            </button>
                          </ConfirmDestructiveDialog>
                          {isSuperAdmin && (
                            <ConfirmDestructiveDialog
                              title="Permanently Delete Customer?"
                              description='This action cannot be undone. Type "DELETE" to confirm.'
                              confirmWord="DELETE"
                              confirmLabel="Permanently Delete"
                              onConfirm={async () => {
                                try {
                                  await api.delete(`/customers/${c.id}/force`)
                                  toast.success('Customer permanently deleted.')
                                  refetchArchived()
                                } catch (err) {
                                  toast.error(firstErrorMessage(err))
                                }
                              }}
                            >
                              <button className="text-xs text-red-600 hover:underline flex items-center gap-1">
                                <Trash2 className="w-3 h-3" /> Delete Forever
                              </button>
                            </ConfirmDestructiveDialog>
                          )}
                        </div>
                      </td>
                    </>
                  ) : (
                    <>
                      <td className="px-3 py-2">
                        <CreditMeter customer={c} />
                      </td>
                      <td className="px-3 py-2">
                        <StatusBadge active={c.is_active} />
                      </td>
                      <td className="px-3 py-2">
                        <div className="flex items-center gap-3">
                          {canManage && (
                            <button
                              onClick={() => setModalCustomer(c)}
                              className="text-xs text-neutral-600 hover:underline flex items-center gap-1"
                            >
                              <CreditCard className="w-3 h-3" /> Edit
                            </button>
                          )}
                          {canArchive && c.is_active && (
                            <ArchiveCustomerButton customer={c} onSuccess={() => { refetch(); refetchArchived() }} />
                          )}
                        </div>
                      </td>
                    </>
                  )}
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}

      {/* Modal */}
      {modalCustomer !== undefined && (
        <CustomerFormModal
          initial={modalCustomer ?? undefined}
          onClose={() => setModalCustomer(undefined)}
          onSuccess={() => refetch()}
        />
      )}
    </div>
  )
}
