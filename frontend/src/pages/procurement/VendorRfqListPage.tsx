import { useState, useCallback } from 'react'
import { Link } from 'react-router-dom'
import { toast } from 'sonner'
import { Plus, Eye, Send } from 'lucide-react'
import { useVendorRfqs, useCreateVendorRfq, useSendVendorRfq, type VendorRfq } from '@/hooks/useVendorRfqs'
import { useAuthStore } from '@/stores/authStore'
import ConfirmDialog from '@/components/ui/ConfirmDialog'
import SearchInput from '@/components/ui/SearchInput'
import { PageHeader } from '@/components/ui/PageHeader'
import { firstErrorMessage } from '@/lib/errorHandler'

export default function VendorRfqListPage(): React.ReactElement {
  const [statusFilter, setStatusFilter] = useState<string | undefined>()
  const [showForm, setShowForm] = useState(false)
  const [form, setForm] = useState({ title: '', description: '', deadline: '', vendor_ids: '' })
  const [search, setSearch] = useState('')
  const [debouncedSearch, setDebouncedSearch] = useState('')

  const handleSearch = useCallback((val: string) => {
    setDebouncedSearch(val)
  }, [])

  const { data, isLoading } = useVendorRfqs({ status: statusFilter, ...(debouncedSearch ? { search: debouncedSearch } : {}) })
  const create = useCreateVendorRfq()
  const send = useSendVendorRfq()

  const rfqs: VendorRfq[] = data?.data ?? []
  const canCreate = useAuthStore((s) => s.hasPermission('procurement.purchase-order.create'))
  const canManage = useAuthStore((s) => s.hasPermission('procurement.purchase-order.manage'))

  async function handleCreate(e: React.FormEvent) {
    e.preventDefault()
    if (!form.title.trim()) {
      toast.error('Title is required.')
      return
    }
    if (form.deadline && new Date(form.deadline) < new Date()) {
      toast.error('Deadline cannot be in the past.')
      return
    }
    const vendorIds = form.vendor_ids.split(',').map(Number).filter(Boolean)
    if (vendorIds.length === 0) {
      toast.error('At least one vendor ID is required.')
      return
    }
    try {
      await create.mutateAsync({
        title: form.title,
        description: form.description || undefined,
        deadline: form.deadline || undefined,
        vendor_ids: vendorIds,
      })
      toast.success('RFQ created.')
      setShowForm(false)
      setForm({ title: '', description: '', deadline: '', vendor_ids: '' })
    } catch (_err) {
      const message = firstErrorMessage(err)
      toast.error(message ?? 'Failed to create RFQ.')
    }
  }

  async function handleSend(ulid: string) {
    try {
      await send.mutateAsync(ulid)
      toast.success('RFQ sent to vendors.')
    } catch (_err) {
      const message = firstErrorMessage(err)
      toast.error(message ?? 'Failed to send RFQ.')
    }
  }

  return (
    <div className="space-y-6">
      <PageHeader
        title="Vendor RFQs"
        subtitle="Request for Quotation management"
        actions={
          canCreate ? (
            <button onClick={() => setShowForm(true)}
              className="inline-flex items-center gap-1.5 text-sm bg-neutral-900 text-white rounded px-4 py-2 font-medium hover:bg-neutral-800">
              <Plus className="w-4 h-4" /> New RFQ
            </button>
          ) : undefined
        }
      />

      {/* Filter */}
      <div className="mb-4 flex flex-wrap items-center gap-3">
        <SearchInput
          value={search}
          onChange={setSearch}
          onSearch={handleSearch}
          placeholder="Search RFQs..."
          className="w-64"
        />
        <select value={statusFilter ?? ''} onChange={(e) => setStatusFilter(e.target.value || undefined)}
          className="border border-neutral-300 rounded px-3 py-1.5 text-sm">
          <option value="">All statuses</option>
          <option value="draft">Draft</option>
          <option value="sent">Sent</option>
          <option value="closed">Closed</option>
          <option value="cancelled">Cancelled</option>
        </select>
      </div>

      {showForm && (
        <form onSubmit={handleCreate} className="bg-white border border-neutral-200 rounded-lg p-5 mb-6 grid grid-cols-2 gap-4">
          <div>
            <label className="block text-xs font-medium text-neutral-600 mb-1">Title</label>
            <input value={form.title} onChange={(e) => setForm(f => ({ ...f, title: e.target.value }))} required
              className="w-full border border-neutral-300 rounded px-3 py-2 text-sm" placeholder="RFQ for raw materials" />
          </div>
          <div>
            <label className="block text-xs font-medium text-neutral-600 mb-1">Deadline</label>
            <input type="date" value={form.deadline} onChange={(e) => setForm(f => ({ ...f, deadline: e.target.value }))}
              className="w-full border border-neutral-300 rounded px-3 py-2 text-sm" />
          </div>
          <div>
            <label className="block text-xs font-medium text-neutral-600 mb-1">Description</label>
            <input value={form.description} onChange={(e) => setForm(f => ({ ...f, description: e.target.value }))}
              className="w-full border border-neutral-300 rounded px-3 py-2 text-sm" />
          </div>
          <div>
            <label className="block text-xs font-medium text-neutral-600 mb-1">Vendor IDs (comma-separated)</label>
            <input value={form.vendor_ids} onChange={(e) => setForm(f => ({ ...f, vendor_ids: e.target.value }))}
              className="w-full border border-neutral-300 rounded px-3 py-2 text-sm" placeholder="1,2,3" />
          </div>
          <div className="col-span-2 flex gap-2">
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
      ) : rfqs.length === 0 ? (
        <div className="bg-white border border-neutral-200 rounded-lg px-6 py-12 text-center">
          <p className="text-neutral-500 text-sm">No RFQs found.</p>
        </div>
      ) : (
        <div className="bg-white border border-neutral-200 rounded-lg overflow-hidden">
          <table className="w-full text-sm">
            <thead className="bg-neutral-50 border-b border-neutral-200">
              <tr>
                {['Reference', 'Title', 'Vendors', 'Deadline', 'Status', 'Actions'].map(h => (
                  <th key={h} className="text-left px-4 py-2 text-xs font-medium text-neutral-600 uppercase">{h}</th>
                ))}
              </tr>
            </thead>
            <tbody>
              {rfqs.map(rfq => (
                <tr key={rfq.id} className="border-b border-neutral-100 last:border-0 hover:bg-neutral-50">
                  <td className="px-4 py-3 font-mono text-xs">{rfq.rfq_reference}</td>
                  <td className="px-4 py-3 text-neutral-800">{rfq.title}</td>
                  <td className="px-4 py-3 text-neutral-600">{rfq.vendors?.length ?? 0}</td>
                  <td className="px-4 py-3 text-neutral-600">{rfq.deadline ?? '—'}</td>
                  <td className="px-4 py-3">
                    <span className={`px-2 py-0.5 rounded text-xs font-medium ${
                      rfq.status === 'sent' ? 'bg-blue-100 text-blue-700' :
                      rfq.status === 'closed' ? 'bg-emerald-100 text-emerald-700' :
                      rfq.status === 'cancelled' ? 'bg-red-100 text-red-600' :
                      'bg-neutral-100 text-neutral-500'
                    }`}>{rfq.status}</span>
                  </td>
                  <td className="px-4 py-3 flex items-center gap-2">
                    <Link to={`/procurement/rfqs/${rfq.ulid}`} className="text-neutral-500 hover:text-neutral-800">
                      <Eye className="w-4 h-4" />
                    </Link>
                    {rfq.status === 'draft' && canManage && (
                      <ConfirmDialog
                        title="Send RFQ to Vendors?"
                        description="This will send the RFQ to all selected vendors."
                        onConfirm={() => handleSend(rfq.ulid)}
                      >
                        <button disabled={send.isPending}
                          className="text-neutral-500 hover:text-blue-600 disabled:opacity-50">
                          <Send className="w-4 h-4" />
                        </button>
                      </ConfirmDialog>
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
