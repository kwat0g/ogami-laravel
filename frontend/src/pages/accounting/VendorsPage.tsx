import React, { useState, useCallback } from 'react'
import { Plus, RefreshCw, Archive, CheckCircle, BadgeCheck, ShieldOff, BarChart2, RotateCcw, Trash2 } from 'lucide-react'
import SearchInput from '@/components/ui/SearchInput'
import { toast } from 'sonner'
import { useQuery } from '@tanstack/react-query'
import { useAuthStore } from '@/stores/authStore'
import { PageHeader } from '@/components/ui/PageHeader'
import { ExportButton } from '@/components/ui/ExportButton'
import {
  useVendors,
  useCreateVendor,
  useUpdateVendor,
  useArchiveVendor,
  useAccreditVendor,
  useSuspendVendor,
  useVendorScorecard,
} from '@/hooks/useAP'
import { useRestoreRecord, useForceDeleteRecord } from '@/hooks/useArchiveActions'
import { firstErrorMessage } from '@/lib/errorHandler'
import { formatTIN, formatPhoneNumber, validators, validationMessages } from '@/lib/inputFormatters'
import SkeletonLoader from '@/components/ui/SkeletonLoader'
import ConfirmDestructiveDialog from '@/components/ui/ConfirmDestructiveDialog'
import ArchiveToggleButton from '@/components/ui/ArchiveToggleButton'
import ArchiveViewBanner from '@/components/ui/ArchiveViewBanner'
import ArchiveEmptyState from '@/components/ui/ArchiveEmptyState'
import { DepartmentGuard, ActionButton } from '@/components/ui/guards'
import api from '@/lib/api'
import type { Vendor, CreateVendorPayload, VendorAccreditationStatus } from '@/types/ap'

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function ArchiveVendorButton({ vendor }: { vendor: Vendor }) {
  const archiveMut = useArchiveVendor(vendor.id)
  return (
    <ConfirmDestructiveDialog
      title="Archive Vendor"
      description={`Archive "${vendor.name}"? This will prevent new invoices from being created for this vendor. Existing paid invoices will not be affected.`}
      confirmWord="ARCHIVE"
      confirmLabel="Archive"
      onConfirm={async () => {
        try {
          await archiveMut.mutateAsync()
          toast.success('Vendor archived.')
        } catch (err) {
          toast.error(firstErrorMessage(err))
        }
      }}
    >
      <button className="text-xs text-neutral-500 hover:underline flex items-center gap-1">
        <Trash2 className="w-3 h-3" /> Delete
      </button>
    </ConfirmDestructiveDialog>
  )
}



const accreditationBadgeClass: Record<VendorAccreditationStatus, string> = {
  pending:     'bg-neutral-100 text-neutral-600',
  accredited:  'bg-neutral-100 text-neutral-700',
  suspended:   'bg-neutral-100 text-neutral-700',
  blacklisted: 'bg-neutral-100 text-neutral-600',
}

function AccreditationBadge({ status }: { status: VendorAccreditationStatus }) {
  return (
    <span className={`px-2 py-0.5 rounded text-xs font-medium capitalize ${accreditationBadgeClass[status]}`}>
      {status}
    </span>
  )
}

function AccreditVendorButton({ vendor }: { vendor: Vendor }) {
  const accreditMut = useAccreditVendor(vendor.id)
  if (vendor.accreditation_status === 'accredited') return null
  return (
    <button
      disabled={accreditMut.isPending}
      onClick={() =>
        accreditMut.mutate(undefined, {
          onSuccess: () => toast.success(`${vendor.name} accredited.`),
          onError: (err) => toast.error(firstErrorMessage(err)),
        })
      }
      className="text-xs text-green-600 hover:underline flex items-center gap-1 disabled:opacity-50 disabled:cursor-not-allowed"
    >
      <BadgeCheck className="w-3 h-3" /> Accredit
    </button>
  )
}

function SuspendVendorButton({ vendor }: { vendor: Vendor }) {
  const suspendMut = useSuspendVendor(vendor.id)
  const [reason, setReason] = useState('')
  const [open, setOpen] = useState(false)
  if (vendor.accreditation_status === 'suspended' || vendor.accreditation_status === 'blacklisted') return null
  return (
    <>
      <button
        onClick={() => setOpen(true)}
        className="text-xs text-neutral-600 hover:underline flex items-center gap-1"
      >
        <ShieldOff className="w-3 h-3" /> Suspend
      </button>
      {open && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50">
          <div className="bg-white rounded border border-neutral-200 w-full max-w-sm max-h-[90vh] overflow-y-auto p-4 sm:p-6 space-y-4">
            <h2 className="text-base font-semibold text-neutral-800">Suspend Vendor</h2>
            <p className="text-sm text-neutral-500">Provide a reason for suspending <strong>{vendor.name}</strong>.</p>
            <textarea
              rows={3}
              className="w-full border rounded-lg px-3 py-2 text-sm"
              placeholder="Reason (min 5 characters)"
              value={reason}
              onChange={e => setReason(e.target.value)}
            />
            <div className="flex justify-end gap-2">
              <button type="button" onClick={() => setOpen(false)} className="px-4 py-2 text-sm rounded border border-neutral-300 hover:bg-neutral-50">Cancel</button>
              <button
                type="button"
                disabled={reason.trim().length < 5 || suspendMut.isPending}
                onClick={() => suspendMut.mutate(reason.trim(), {
                  onSuccess: () => { toast.success('Vendor suspended.'); setOpen(false) },
                  onError: (err) => toast.error(firstErrorMessage(err)),
                })}
                className="px-4 py-2 text-sm rounded bg-neutral-900 text-white hover:bg-neutral-800 disabled:opacity-50 disabled:cursor-not-allowed"
              >
                {suspendMut.isPending ? 'Suspending…' : 'Suspend'}
              </button>
            </div>
          </div>
        </div>
      )}
    </>
  )
}

// ---------------------------------------------------------------------------
// Vendor Form Modal
// ---------------------------------------------------------------------------

const EMPTY_FORM: CreateVendorPayload = {
  name: '',
  tin: '',
  atc_code: '',
  ewt_rate_id: null,
  is_ewt_subject: false,
  is_active: true,
  accreditation_status: 'pending',
  accreditation_notes: '',
  bank_name: '',
  bank_account_no: '',
  bank_account_name: '',
  payment_terms: '',
  address: '',
  contact_person: '',
  email: '',
  phone: '',
  notes: '',
}

interface VendorFormModalProps {
  initial?: Vendor
  onClose: () => void
}

function VendorFormModal({ initial, onClose }: VendorFormModalProps) {
  const isEdit = !!initial
  const [form, setForm] = useState<CreateVendorPayload>(
    initial ? {
      name: initial.name,
      tin: initial.tin ?? '',
      atc_code: initial.atc_code ?? '',
      ewt_rate_id: initial.ewt_rate_id,
      is_ewt_subject: initial.is_ewt_subject,
      is_active: initial.is_active,
      accreditation_status: initial.accreditation_status ?? 'pending',
      accreditation_notes: initial.accreditation_notes ?? '',
      bank_name: initial.bank_name ?? '',
      bank_account_no: initial.bank_account_no ?? '',
      bank_account_name: initial.bank_account_name ?? '',
      payment_terms: initial.payment_terms ?? '',
      address: initial.address ?? '',
      contact_person: initial.contact_person ?? '',
      email: initial.email ?? '',
      phone: initial.phone ?? '',
      notes: initial.notes ?? '',
    } : EMPTY_FORM,
  )

  const createMut = useCreateVendor()
  const updateMut = useUpdateVendor(initial?.id ?? 0)

  const [error, setError] = useState<string | null>(null)
  const [fieldErrors, setFieldErrors] = useState<Record<string, string[]>>({})
  const [touched, setTouched] = useState(false)

  // Client-side validation
  const nameError = touched && !form.name.trim() ? 'Vendor name is required.' : undefined
  const emailError = touched && form.email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(form.email)
    ? 'Please enter a valid email address.'
    : undefined
  const tinError = touched && form.tin && !validators.tin(form.tin)
    ? validationMessages.tin
    : undefined
  const phoneError = touched && form.phone && !validators.phone(form.phone)
    ? validationMessages.phone
    : undefined
  const isValid = form.name.trim() 
    && (!form.email || /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(form.email))
    && (!form.tin || validators.tin(form.tin))
    && (!form.phone || validators.phone(form.phone))

  // Normalize form data: convert empty strings to null
  // Note: TIN is kept with dashes (backend expects XXX-XXX-XXX-XXX format)
  const normalizeForm = (formData: CreateVendorPayload): CreateVendorPayload => ({
    ...formData,
    tin: formData.tin?.trim() || null,
    atc_code: formData.atc_code?.trim() || null,
    address: formData.address?.trim() || null,
    contact_person: formData.contact_person?.trim() || null,
    email: formData.email?.trim() || null,
    phone: formData.phone?.trim() || null,
    notes: formData.notes?.trim() || null,
    payment_terms: formData.payment_terms?.trim() || null,
    bank_name: formData.bank_name?.trim() || null,
    bank_account_no: formData.bank_account_no?.trim() || null,
    bank_account_name: formData.bank_account_name?.trim() || null,
    accreditation_notes: formData.accreditation_notes?.trim() || null,
  })

  async function handleSubmit(e: React.FormEvent) {
    e.preventDefault()
    setTouched(true)
    if (!isValid) return
    setError(null)
    setFieldErrors({})
    const payload = normalizeForm(form)
    try {
      if (isEdit) {
        await updateMut.mutateAsync(payload)
        toast.success('Vendor updated.')
      } else {
        await createMut.mutateAsync(payload)
        toast.success('Vendor created.')
      }
      onClose()
    } catch (err: unknown) {
      const data = (err as { response?: { data?: { message?: string; errors?: Record<string, string[]> } } })?.response?.data
      const msg = data?.message ?? 'An unexpected error occurred.'
      const errs = data?.errors ?? {}
      setError(msg)
      setFieldErrors(errs)
      toast.error(msg)
    }
  }

  const isPending = createMut.isPending || updateMut.isPending

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50">
      <div className="bg-white rounded border border-neutral-200 w-full max-w-lg max-h-[90vh] overflow-y-auto p-4 sm:p-6 space-y-4">
        <h2 className="text-lg font-semibold text-neutral-900">{isEdit ? 'Edit Vendor' : 'Add Vendor'}</h2>

        {error && (
          <div className="rounded bg-red-50 border border-red-200 text-red-700 text-sm px-3 py-2">
            {error}
            {Object.keys(fieldErrors).length > 0 && (
              <ul className="mt-1 list-disc list-inside space-y-0.5">
                {Object.entries(fieldErrors).map(([field, msgs]) => (
                  <li key={field}><span className="font-medium">{field}:</span> {msgs[0]}</li>
                ))}
              </ul>
            )}
          </div>
        )}

        <form onSubmit={handleSubmit} className="space-y-4">
          <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div className="col-span-2">
              <label className="block text-sm font-medium text-neutral-700 mb-1">
                Vendor Name <span className="text-red-500">*</span>
              </label>
              <input
                className={`w-full border rounded px-3 py-2 text-sm focus:ring-1 focus:ring-neutral-400 ${
                  nameError ? 'border-red-400' : 'border-neutral-300'
                }`}
                value={form.name}
                onChange={e => setForm(f => ({ ...f, name: e.target.value }))}
                onBlur={() => setTouched(true)}
                required
              />
              {nameError && <p className="mt-1 text-xs text-red-600">{nameError}</p>}
            </div>
            <div>
              <label className="block text-sm font-medium text-neutral-700 mb-1">TIN</label>
              <input
                className={`w-full border rounded px-3 py-2 text-sm focus:ring-1 focus:ring-neutral-400 ${
                  tinError ? 'border-red-400' : 'border-neutral-300'
                }`}
                value={form.tin ?? ''}
                onChange={e => setForm(f => ({ ...f, tin: formatTIN(e.target.value) || null }))}
                onBlur={() => setTouched(true)}
                placeholder="000-000-000-000"
                maxLength={15}
              />
              {tinError && <p className="mt-1 text-xs text-red-600">{tinError}</p>}
              <p className="mt-1 text-xs text-neutral-400">{validationMessages.tin}</p>
            </div>
            <div>
              <label className="block text-sm font-medium text-neutral-700 mb-1">ATC Code</label>
              <input
                className="w-full border border-neutral-300 rounded px-3 py-2 text-sm focus:ring-1 focus:ring-neutral-400"
                value={form.atc_code ?? ''}
                onChange={e => setForm(f => ({ ...f, atc_code: e.target.value || null }))}
                placeholder="e.g. WC010"
              />
            </div>
            <div>
              <label className="block text-sm font-medium text-neutral-700 mb-1">Contact Person</label>
              <input
                className="w-full border border-neutral-300 rounded px-3 py-2 text-sm focus:ring-1 focus:ring-neutral-400"
                value={form.contact_person ?? ''}
                onChange={e => setForm(f => ({ ...f, contact_person: e.target.value || null }))}
              />
            </div>
            <div>
              <label className="block text-sm font-medium text-neutral-700 mb-1">Phone</label>
              <input
                className="w-full border border-neutral-300 rounded px-3 py-2 text-sm focus:ring-1 focus:ring-neutral-400"
                value={form.phone ?? ''}
                onChange={e => setForm(f => ({ ...f, phone: formatPhoneNumber(e.target.value) || null }))}
                placeholder="09XX XXX XXXX"
                maxLength={13}
              />
              <p className="mt-1 text-xs text-neutral-400">{validationMessages.phone}</p>
            </div>
            <div className="col-span-2">
              <label className="block text-sm font-medium text-neutral-700 mb-1">Email</label>
              <input
                type="email"
                className={`w-full border rounded px-3 py-2 text-sm focus:ring-1 focus:ring-neutral-400 ${
                  emailError ? 'border-red-400' : 'border-neutral-300'
                }`}
                value={form.email ?? ''}
                onChange={e => setForm(f => ({ ...f, email: e.target.value || null }))}
                onBlur={() => setTouched(true)}
              />
              {emailError && <p className="mt-1 text-xs text-red-600">{emailError}</p>}
            </div>
            <div className="col-span-2">
              <label className="block text-sm font-medium text-neutral-700 mb-1">Address</label>
              <textarea
                rows={2}
                className="w-full border border-neutral-300 rounded px-3 py-2 text-sm focus:ring-1 focus:ring-neutral-400"
                value={form.address ?? ''}
                onChange={e => setForm(f => ({ ...f, address: e.target.value || null }))}
              />
            </div>
            <div className="col-span-1 sm:col-span-2 flex flex-col sm:flex-row sm:items-center gap-2 sm:gap-4">
              <label className="flex items-center gap-2 text-sm font-medium text-neutral-700 cursor-pointer">
                <input
                  type="checkbox"
                  checked={form.is_ewt_subject}
                  onChange={e => setForm(f => ({ ...f, is_ewt_subject: e.target.checked }))}
                />
                Subject to EWT (AP-004)
              </label>
              {isEdit && (
                <label className="flex items-center gap-2 text-sm font-medium text-neutral-700 cursor-pointer">
                  <input
                    type="checkbox"
                    checked={form.is_active ?? true}
                    onChange={e => setForm(f => ({ ...f, is_active: e.target.checked }))}
                  />
                  Active
                </label>
              )}
            </div>

            {/* ── Banking ──────────────────────────────── */}
            <div className="col-span-1 sm:col-span-2">
              <p className="text-xs font-medium text-neutral-500 mb-2 mt-1">Banking Details</p>
            </div>
            <div>
              <label className="block text-sm font-medium text-neutral-700 mb-1">Bank Name</label>
              <input
                className="w-full border border-neutral-300 rounded px-3 py-2 text-sm"
                value={form.bank_name ?? ''}
                onChange={e => setForm(f => ({ ...f, bank_name: e.target.value || null }))}
              />
            </div>
            <div>
              <label className="block text-sm font-medium text-neutral-700 mb-1">Account No.</label>
              <input
                className="w-full border border-neutral-300 rounded px-3 py-2 text-sm font-mono"
                value={form.bank_account_no ?? ''}
                onChange={e => setForm(f => ({ ...f, bank_account_no: e.target.value || null }))}
              />
            </div>
            <div>
              <label className="block text-sm font-medium text-neutral-700 mb-1">Account Name</label>
              <input
                className="w-full border border-neutral-300 rounded px-3 py-2 text-sm"
                value={form.bank_account_name ?? ''}
                onChange={e => setForm(f => ({ ...f, bank_account_name: e.target.value || null }))}
              />
            </div>
            <div>
              <label className="block text-sm font-medium text-neutral-700 mb-1">Payment Terms</label>
              <input
                className="w-full border border-neutral-300 rounded px-3 py-2 text-sm"
                value={form.payment_terms ?? ''}
                placeholder="e.g. Net 30"
                onChange={e => setForm(f => ({ ...f, payment_terms: e.target.value || null }))}
              />
            </div>
          </div>

          <div className="flex flex-col-reverse sm:flex-row justify-end gap-2 sm:gap-3 pt-2">
            <button
              type="button"
              onClick={onClose}
              className="px-4 py-2 text-sm rounded border border-neutral-300 hover:bg-neutral-50"
            >
              Cancel
            </button>
            <button
              type="submit"
              disabled={isPending || !isValid}
              className="px-4 py-2 text-sm rounded bg-neutral-900 text-white hover:bg-neutral-800 disabled:opacity-50 disabled:cursor-not-allowed"
            >
              {isPending ? 'Saving…' : isEdit ? 'Save Changes' : 'Add Vendor'}
            </button>
          </div>
        </form>
      </div>
    </div>
  )
}

// ---------------------------------------------------------------------------
// Main Page
// ---------------------------------------------------------------------------

function ScorecardMetric({ label, value, unit = '%' }: { label: string; value: number; unit?: string }) {
  const color = value >= 90 ? 'text-emerald-600' : value >= 70 ? 'text-amber-600' : 'text-red-600'
  return (
    <div className="text-center">
      <p className={`text-lg font-bold ${color}`}>{value.toFixed(1)}{unit}</p>
      <p className="text-xs text-neutral-500">{label}</p>
    </div>
  )
}

function VendorScorecardPanel({ vendorId }: { vendorId: number }) {
  const { data, isLoading } = useVendorScorecard(vendorId)
  if (isLoading) return <p className="text-xs text-neutral-400 py-2">Loading scorecard…</p>
  if (!data) return <p className="text-xs text-neutral-400 py-2">No data available.</p>
  return (
    <div className="grid grid-cols-5 gap-4 py-2">
      <ScorecardMetric label="On-Time Delivery" value={data.on_time_pct} />
      <ScorecardMetric label="Fill Rate" value={data.fill_rate_pct} />
      <ScorecardMetric label="Quality Rate" value={data.quality_rate_pct} />
      <div className="text-center">
        <p className="text-lg font-bold text-neutral-700">{data.total_orders}</p>
        <p className="text-xs text-neutral-500">Total Orders</p>
      </div>
      <div className="text-center">
        <p className="text-lg font-bold text-neutral-700">
          {data.avg_lead_time_days !== null ? `${data.avg_lead_time_days}d` : '—'}
        </p>
        <p className="text-xs text-neutral-500">Avg Lead Time</p>
      </div>
    </div>
  )
}

export default function VendorsPage() {
  const [search, setSearch] = useState('')
  const [debouncedSearch, setDebouncedSearch] = useState('')
  const [showForm, setShowForm] = useState(false)
  const [editing, setEditing] = useState<Vendor | null>(null)
  const [scorecardVendorId, setScorecardVendorId] = useState<number | null>(null)
  const [isArchiveView, setIsArchiveView] = useState(false)

  const handleSearch = useCallback((val: string) => {
    setDebouncedSearch(val)
  }, [])

  // Active list
  const { data, isLoading, refetch } = useVendors({
    search: debouncedSearch || undefined,
    is_active: undefined,
  })

  // Archived list
  const { data: archivedData, isLoading: archivedLoading, refetch: refetchArchived } = useQuery({
    queryKey: ['vendors', 'archived', debouncedSearch],
    queryFn: () => api.get('/vendors-archived', { params: { search: debouncedSearch || undefined, per_page: 50 } }),
    enabled: isArchiveView,
  })

  const vendors = isArchiveView ? (archivedData?.data?.data ?? []) : (data?.data ?? [])
  const currentLoading = isArchiveView ? archivedLoading : isLoading
  const currentRefetch = isArchiveView ? refetchArchived : refetch
  
  // Calculate summary stats (only for active view)
  const activeVendors = isArchiveView ? 0 : vendors.filter((v: Vendor) => v.is_active).length
  const accreditedVendors = isArchiveView ? 0 : vendors.filter((v: Vendor) => v.accreditation_status === 'accredited').length
  const ewtVendors = isArchiveView ? 0 : vendors.filter((v: Vendor) => v.is_ewt_subject).length

  const canManage = useAuthStore((s) => s.hasPermission('vendors.manage'))
  const canAccredit = useAuthStore((s) => s.hasPermission('vendors.accredit'))
  const canSuspend = useAuthStore((s) => s.hasPermission('vendors.suspend'))
  const canArchive = useAuthStore((s) => s.hasPermission('vendors.archive'))
  const isSuperAdmin = useAuthStore((s) => s.user?.roles?.some((r: { name: string }) => r.name === 'super_admin'))


  return (
    <div className="space-y-6">
      <PageHeader
        title="Vendors"
        actions={
          <div className="flex items-center gap-2">
            <ExportButton
              data={data?.data ?? []}
              columns={[
                { key: 'name', label: 'Vendor Name' },
                { key: 'tin', label: 'TIN' },
                { key: 'contact_person', label: 'Contact Person' },
                { key: 'email', label: 'Email' },
                { key: 'phone', label: 'Phone' },
                { key: 'status', label: 'Status' },
              ]}
              filename="vendors"
            />
            <ArchiveToggleButton isArchiveView={isArchiveView} onToggle={() => setIsArchiveView(prev => !prev)} />
          </div>
        }
      />

      {isArchiveView && <ArchiveViewBanner />}

      {/* Summary Stats */}
      {!isArchiveView && !isLoading && vendors.length > 0 && (
        <div className="grid grid-cols-4 gap-4">
          <div className="bg-blue-50 border border-blue-200 rounded-xl p-4">
            <p className="text-xs font-medium text-blue-600 uppercase tracking-wide">Total Vendors</p>
            <p className="text-2xl font-bold text-blue-700 mt-1">{vendors.length}</p>
          </div>
          <div className="bg-emerald-50 border border-emerald-200 rounded-xl p-4">
            <p className="text-xs font-medium text-emerald-600 uppercase tracking-wide">Active</p>
            <p className="text-2xl font-bold text-emerald-700 mt-1">{activeVendors}</p>
          </div>
          <div className="bg-purple-50 border border-purple-200 rounded-xl p-4">
            <p className="text-xs font-medium text-purple-600 uppercase tracking-wide">Accredited</p>
            <p className="text-2xl font-bold text-purple-700 mt-1">{accreditedVendors}</p>
          </div>
          <div className="bg-amber-50 border border-amber-200 rounded-xl p-4">
            <p className="text-xs font-medium text-amber-600 uppercase tracking-wide">EWT Subject</p>
            <p className="text-2xl font-bold text-amber-700 mt-1">{ewtVendors}</p>
          </div>
        </div>
      )}

      {/* Header */}
      <div className="flex items-center justify-between">
        <div>
          <p className="text-sm text-neutral-500">Manage supplier records and EWT configuration</p>
        </div>
        <div className="flex items-center gap-2">
          <button onClick={() => currentRefetch()} className="p-2 rounded border border-neutral-300 hover:bg-neutral-50">
            <RefreshCw className="w-4 h-4 text-neutral-500" />
          </button>
          {!isArchiveView && (
            <DepartmentGuard module="vendors">
              {canManage && (
                <button
                  onClick={() => { setEditing(null); setShowForm(true) }}
                  className="flex items-center gap-2 px-4 py-2 bg-neutral-900 text-white text-sm rounded hover:bg-neutral-800"
                >
                  <Plus className="w-4 h-4" /> Add Vendor
                </button>
              )}
            </DepartmentGuard>
          )}
        </div>
      </div>

      {/* Filters */}
      <div className="flex items-center gap-4">
        <SearchInput
          value={search}
          onChange={setSearch}
          onSearch={handleSearch}
          placeholder="Search by name or TIN..."
          className="w-72"
        />
      </div>

      {/* Table */}
      {currentLoading ? (
        <SkeletonLoader rows={8} />
      ) : vendors.length === 0 ? (
        <ArchiveEmptyState isArchiveView={isArchiveView} recordLabel="vendors" />
      ) : (
        <div className="bg-white rounded border border-neutral-200 overflow-hidden">
          <table className="w-full text-sm">
            <thead className="bg-neutral-50 border-b border-neutral-200">
              <tr>
                <th className="text-left px-3 py-2.5 font-medium text-neutral-600">Name</th>
                <th className="text-left px-3 py-2.5 font-medium text-neutral-600">TIN</th>
                {!isArchiveView && <th className="text-left px-3 py-2.5 font-medium text-neutral-600">ATC</th>}
                {!isArchiveView && <th className="text-left px-3 py-2.5 font-medium text-neutral-600">EWT</th>}
                {!isArchiveView && <th className="text-left px-3 py-2.5 font-medium text-neutral-600">Status</th>}
                {!isArchiveView && <th className="text-left px-3 py-2.5 font-medium text-neutral-600">Accreditation</th>}
                {isArchiveView && <th className="text-left px-3 py-2.5 font-medium text-neutral-600">Archived On</th>}
                <th className="text-right px-3 py-2.5 font-medium text-neutral-600">Actions</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-neutral-100">
              {vendors.map(vendor => (
                <React.Fragment key={vendor.id}>
                <tr className={`even:bg-neutral-100 hover:bg-neutral-50 transition-colors ${
                  !vendor.is_active ? 'opacity-60' : ''
                }`}>
                  <td className="px-3 py-2">
                    <span className={`font-semibold ${vendor.is_active ? 'text-neutral-900' : 'text-neutral-500'}`}>
                      {vendor.name}
                    </span>
                    {vendor.contact_person && (
                      <div className="text-xs text-neutral-400 font-normal">{vendor.contact_person}</div>
                    )}
                  </td>
                  <td className="px-3 py-2 font-mono text-xs text-neutral-600">{vendor.tin ?? '—'}</td>
                  {!isArchiveView && (
                    <>
                      <td className="px-3 py-2">
                        {vendor.atc_code ? (
                          <span className="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-700 font-mono">
                            {vendor.atc_code}
                          </span>
                        ) : (
                          <span className="text-neutral-300">—</span>
                        )}
                      </td>
                      <td className="px-3 py-2">
                        {vendor.is_ewt_subject ? (
                          <span className="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-amber-100 text-amber-700">
                            EWT Subject
                          </span>
                        ) : (
                          <span className="text-neutral-400 text-xs">No EWT</span>
                        )}
                      </td>
                      <td className="px-3 py-2">
                        {vendor.is_active ? (
                          <span className="inline-flex items-center gap-1 px-2 py-0.5 rounded text-xs font-medium bg-emerald-100 text-emerald-700">
                            <CheckCircle className="w-3 h-3" /> Active
                          </span>
                        ) : (
                          <span className="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-neutral-100 text-neutral-500">
                            Inactive
                          </span>
                        )}
                      </td>
                      <td className="px-3 py-2">
                        <AccreditationBadge status={vendor.accreditation_status ?? 'pending'} />
                      </td>
                    </>
                  )}
                  {isArchiveView && (
                    <td className="px-3 py-2 text-xs text-neutral-500">
                      {vendor.deleted_at ? new Date(vendor.deleted_at).toLocaleDateString() : '—'}
                    </td>
                  )}
                  <td className="px-3 py-2">
                    {isArchiveView ? (
                      <div className="flex items-center justify-end gap-2">
                        <ConfirmDestructiveDialog
                          title="Restore Vendor?"
                          description={`Restore "${vendor.name}" to the active vendor list?`}
                          confirmWord="RESTORE"
                          confirmLabel="Restore"
                          variant="warning"
                          onConfirm={async () => {
                            try {
                              await api.post(`/vendors/${vendor.id}/restore`)
                              toast.success(`Vendor "${vendor.name}" restored.`)
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
                            title="Permanently Delete Vendor?"
                            description='This action cannot be undone. Type "DELETE" to confirm.'
                            confirmWord="DELETE"
                            confirmLabel="Permanently Delete"
                            onConfirm={async () => {
                              try {
                                await api.delete(`/vendors/${vendor.id}/force`)
                                toast.success('Vendor permanently deleted.')
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
                    ) : (
                      <DepartmentGuard module="vendors">
                        <div className="flex items-center justify-end gap-2">
                          <button
                            onClick={() => setScorecardVendorId(scorecardVendorId === vendor.id ? null : vendor.id)}
                            className="flex items-center gap-1 text-xs text-neutral-500 hover:text-neutral-800"
                            title="View Performance Scorecard"
                          >
                            <BarChart2 className="w-3.5 h-3.5" />
                            Perf
                          </button>
                          {canManage && (
                            <button
                              onClick={() => { setEditing(vendor); setShowForm(true) }}
                              className="text-xs text-neutral-600 hover:underline"
                            >
                              Edit
                            </button>
                          )}
                          {canAccredit && <AccreditVendorButton vendor={vendor} />}
                          {canSuspend && <SuspendVendorButton vendor={vendor} />}
                          {canArchive && vendor.is_active && <ArchiveVendorButton vendor={vendor} />}
                        </div>
                      </DepartmentGuard>
                    )}
                  </td>
                </tr>
                {scorecardVendorId === vendor.id && (
                  <tr className="bg-neutral-50">
                    <td colSpan={7} className="px-6 pb-3">
                      <p className="text-xs font-semibold text-neutral-500 uppercase tracking-wide mb-2 pt-2">Performance Scorecard</p>
                      <VendorScorecardPanel vendorId={vendor.id} />
                    </td>
                  </tr>
                )}
                </React.Fragment>
              ))}
            </tbody>
          </table>
        </div>
      )}

      {/* Modals */}
      {showForm && (
        <VendorFormModal
          initial={editing ?? undefined}
          onClose={() => { setShowForm(false); setEditing(null) }}
        />
      )}

    </div>
  )
}
