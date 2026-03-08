import { useState } from 'react'
import { Plus, RefreshCw, Archive, CheckCircle, BadgeCheck, ShieldOff } from 'lucide-react'
import { toast } from 'sonner'
import { PageHeader } from '@/components/ui/PageHeader'
import {
  useVendors,
  useCreateVendor,
  useUpdateVendor,
  useArchiveVendor,
  useAccreditVendor,
  useSuspendVendor,
} from '@/hooks/useAP'
import SkeletonLoader from '@/components/ui/SkeletonLoader'
import ConfirmDestructiveDialog from '@/components/ui/ConfirmDestructiveDialog'
import type { Vendor, CreateVendorPayload, VendorAccreditationStatus } from '@/types/ap'

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function StatusBadge({ active }: { active: boolean }) {
  return active ? (
    <span className="inline-flex items-center gap-1 px-2 py-0.5 rounded text-xs font-medium bg-neutral-100 text-neutral-700">
      <CheckCircle className="w-3 h-3" /> Active
    </span>
  ) : (
    <span className="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-neutral-100 text-neutral-500">
      Inactive
    </span>
  )
}

function EwtBadge({ subject }: { subject: boolean }) {
  return subject ? (
    <span className="px-2 py-0.5 rounded text-xs font-medium bg-neutral-100 text-neutral-700">
      EWT Subject
    </span>
  ) : (
    <span className="px-2 py-0.5 rounded text-xs font-medium bg-neutral-100 text-neutral-400">
      No EWT
    </span>
  )
}

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
        } catch {
          toast.error('Failed to archive vendor.')
        }
      }}
    >
      <button className="text-xs text-neutral-500 hover:underline flex items-center gap-1">
        <Archive className="w-3 h-3" /> Archive
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
          onError: () => toast.error('Failed to accredit vendor.'),
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
                  onError: () => toast.error('Failed to suspend vendor.'),
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

  async function handleSubmit(e: React.FormEvent) {
    e.preventDefault()
    setError(null)
    setFieldErrors({})
    try {
      if (isEdit) {
        await updateMut.mutateAsync(form)
        toast.success('Vendor updated.')
      } else {
        await createMut.mutateAsync(form)
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
              <label className="block text-sm font-medium text-neutral-700 mb-1">Vendor Name *</label>
              <input
                className="w-full border border-neutral-300 rounded px-3 py-2 text-sm focus:ring-1 focus:ring-neutral-400"
                value={form.name}
                onChange={e => setForm(f => ({ ...f, name: e.target.value }))}
                required
              />
            </div>
            <div>
              <label className="block text-sm font-medium text-neutral-700 mb-1">TIN</label>
              <input
                className="w-full border border-neutral-300 rounded px-3 py-2 text-sm focus:ring-1 focus:ring-neutral-400"
                value={form.tin ?? ''}
                onChange={e => setForm(f => ({ ...f, tin: e.target.value || null }))}
                placeholder="000-000-000-000"
              />
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
              <label className="block text-sm font-medium text-neutral-700 mb-1">Email</label>
              <input
                type="email"
                className="w-full border border-neutral-300 rounded px-3 py-2 text-sm focus:ring-1 focus:ring-neutral-400"
                value={form.email ?? ''}
                onChange={e => setForm(f => ({ ...f, email: e.target.value || null }))}
              />
            </div>
            <div>
              <label className="block text-sm font-medium text-neutral-700 mb-1">Phone</label>
              <input
                className="w-full border border-neutral-300 rounded px-3 py-2 text-sm focus:ring-1 focus:ring-neutral-400"
                value={form.phone ?? ''}
                onChange={e => setForm(f => ({ ...f, phone: e.target.value || null }))}
              />
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
              disabled={isPending}
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

export default function VendorsPage() {
  const [search, setSearch] = useState('')
  const [showInactive, setShowInactive] = useState(false)
  const [showForm, setShowForm] = useState(false)
  const [editing, setEditing] = useState<Vendor | null>(null)

  const { data, isLoading, refetch } = useVendors({
    search: search || undefined,
    is_active: showInactive ? undefined : true,
  })

  const vendors = data?.data ?? []

  return (
    <div className="space-y-6">
      <PageHeader title="Vendors" />

      {/* Header */}
      <div className="flex items-center justify-between">
        <div>
          <p className="text-sm text-neutral-500">Manage supplier records and EWT configuration</p>
        </div>
        <div className="flex items-center gap-2">
          <button onClick={() => refetch()} className="p-2 rounded border border-neutral-300 hover:bg-neutral-50">
            <RefreshCw className="w-4 h-4 text-neutral-500" />
          </button>
          <button
            onClick={() => { setEditing(null); setShowForm(true) }}
            className="flex items-center gap-2 px-4 py-2 bg-neutral-900 text-white text-sm rounded hover:bg-neutral-800"
          >
            <Plus className="w-4 h-4" /> Add Vendor
          </button>
        </div>
      </div>

      {/* Filters */}
      <div className="flex items-center gap-4">
        <input
          className="border border-neutral-300 rounded px-3 py-2 text-sm w-72 focus:ring-1 focus:ring-neutral-400"
          placeholder="Search by name or TIN…"
          value={search}
          onChange={e => setSearch(e.target.value)}
        />
        <label className="flex items-center gap-2 text-sm text-neutral-600 cursor-pointer">
          <input
            type="checkbox"
            checked={showInactive}
            onChange={e => setShowInactive(e.target.checked)}
          />
          Show inactive
        </label>
      </div>

      {/* Table */}
      {isLoading ? (
        <SkeletonLoader rows={8} />
      ) : vendors.length === 0 ? (
        <div className="text-center py-16 text-neutral-400">No vendors found.</div>
      ) : (
        <div className="bg-white rounded border border-neutral-200 overflow-hidden">
          <table className="w-full text-sm">
            <thead className="bg-neutral-50 border-b border-neutral-200">
              <tr>
                <th className="text-left px-3 py-2.5 font-medium text-neutral-600">Name</th>
                <th className="text-left px-3 py-2.5 font-medium text-neutral-600">TIN</th>
                <th className="text-left px-3 py-2.5 font-medium text-neutral-600">ATC</th>
                <th className="text-left px-3 py-2.5 font-medium text-neutral-600">EWT</th>
                <th className="text-left px-3 py-2.5 font-medium text-neutral-600">Status</th>
                <th className="text-left px-3 py-2.5 font-medium text-neutral-600">Accreditation</th>
                <th className="text-right px-3 py-2.5 font-medium text-neutral-600">Actions</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-neutral-100">
              {vendors.map(vendor => (
                <tr key={vendor.id} className="even:bg-neutral-100 hover:bg-neutral-50 transition-colors">
                  <td className="px-3 py-2 font-medium text-neutral-900">
                    {vendor.name}
                    {vendor.contact_person && (
                      <div className="text-xs text-neutral-400 font-normal">{vendor.contact_person}</div>
                    )}
                  </td>
                  <td className="px-3 py-2 text-neutral-600">{vendor.tin ?? '—'}</td>
                  <td className="px-3 py-2 text-neutral-500 font-mono text-xs">{vendor.atc_code ?? '—'}</td>
                  <td className="px-3 py-2"><EwtBadge subject={vendor.is_ewt_subject} /></td>
                  <td className="px-3 py-2"><StatusBadge active={vendor.is_active} /></td>
                  <td className="px-3 py-2"><AccreditationBadge status={vendor.accreditation_status ?? 'pending'} /></td>
                  <td className="px-3 py-2">
                    <div className="flex items-center justify-end gap-2">
                      <button
                        onClick={() => { setEditing(vendor); setShowForm(true) }}
                        className="text-xs text-neutral-600 hover:underline"
                      >
                        Edit
                      </button>
                      <AccreditVendorButton vendor={vendor} />
                      <SuspendVendorButton vendor={vendor} />
                      {vendor.is_active && <ArchiveVendorButton vendor={vendor} />}
                    </div>
                  </td>
                </tr>
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
