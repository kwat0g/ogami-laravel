import { useState, useCallback } from 'react'
import { Link } from 'react-router-dom'
import { Plus, FileText } from 'lucide-react'
import { useQuotations } from '@/hooks/useSales'
import { useAuthStore } from '@/stores/authStore'
import { PageHeader } from '@/components/ui/PageHeader'
import SearchInput from '@/components/ui/SearchInput'
import { Card } from '@/components/ui/Card'
import StatusBadge from '@/components/ui/StatusBadge'
import SkeletonLoader from '@/components/ui/SkeletonLoader'
import EmptyState from '@/components/ui/EmptyState'

const STATUS_OPTIONS = ['', 'draft', 'sent', 'accepted', 'converted_to_order', 'rejected', 'expired']

function formatCentavos(c: number) {
  return new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' }).format(c / 100)
}

export default function QuotationListPage() {
  const [filters, setFilters] = useState<Record<string, unknown>>({ per_page: 20, page: 1 })
  const [search, setSearch] = useState('')
  const [isArchiveView, setIsArchiveView] = useState(false)
  const { data, isLoading, isError } = useQuotations({ ...filters, ...(search ? {} : {}) })
  const quotations = data?.data ?? []
  const canCreate = useAuthStore(s => s.hasPermission('sales.quotations.create'))

  const handleSearch = useCallback((val: string) => {
    setFilters(p => ({ ...p, search: val || undefined, page: 1 }))
  }, [])

  return (
    <div className="space-y-6">
      <PageHeader
        title="Price Quotations"
        icon={<FileText className="w-5 h-5 text-neutral-600" />}
        actions={canCreate ? (
          <Link to="/sales/quotations/new" className="btn-primary">
            <Plus className="w-3.5 h-3.5" /> New Quotation
          </Link>
        ) : undefined}
      />

      <Card className="p-4">
        <div className="flex flex-wrap items-center gap-3">
          <SearchInput
            value={search}
            onChange={setSearch}
            onSearch={handleSearch}
            placeholder="Search quotations..."
            className="w-64"
          />
          <select className="input-sm" value={(filters.status as string) ?? ''} onChange={e => setFilters(p => ({ ...p, status: e.target.value || undefined, page: 1 }))}>
            <option value="">All Statuses</option>
            {STATUS_OPTIONS.filter(Boolean).map(s => <option key={s} value={s}>{s.replace(/_/g, ' ')}</option>)}
          </select>
        </div>
      </Card>

      {isLoading ? <SkeletonLoader rows={8} /> : isError ? (
        <Card className="p-6 text-center text-red-600">Failed to load quotations. Please try again.</Card>
      ) : quotations.length === 0 ? (
        <EmptyState title="No quotations" description="Create your first quotation." />
      ) : (
        <Card className="overflow-hidden">
          <table className="w-full text-sm">
            <thead className="bg-neutral-50 dark:bg-neutral-800">
              <tr>
                <th className="text-left p-3 font-medium">Quotation #</th>
                <th className="text-left p-3 font-medium">Customer</th>
                <th className="text-left p-3 font-medium">Status</th>
                <th className="text-right p-3 font-medium">Total</th>
                <th className="text-left p-3 font-medium">Valid Until</th>
                <th className="text-left p-3 font-medium">Created</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-neutral-100 dark:divide-neutral-800">
              {quotations.map(q => (
                <tr key={q.id} className="hover:bg-neutral-50 dark:hover:bg-neutral-800/50">
                  <td className="p-3">
                    <Link to={`/sales/quotations/${q.ulid}`} className="font-medium text-blue-600 hover:underline">
                      {q.quotation_number}
                    </Link>
                  </td>
                  <td className="p-3">{q.customer?.name ?? '-'}</td>
                  <td className="p-3"><StatusBadge status={q.status} /></td>
                  <td className="p-3 text-right font-mono">{formatCentavos(q.total_centavos)}</td>
                  <td className="p-3 text-neutral-500">{new Date(q.validity_date).toLocaleDateString()}</td>
                  <td className="p-3 text-neutral-500">{new Date(q.created_at).toLocaleDateString()}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </Card>
      )}
    </div>
  )
}
