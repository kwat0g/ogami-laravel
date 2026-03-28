import { useState, useCallback } from 'react'
import { Link } from 'react-router-dom'
import { ShoppingBag } from 'lucide-react'
import { useSalesOrders } from '@/hooks/useSales'
import { PageHeader } from '@/components/ui/PageHeader'
import SearchInput from '@/components/ui/SearchInput'
import Pagination from '@/components/ui/Pagination'
import { Card } from '@/components/ui/Card'
import StatusBadge from '@/components/ui/StatusBadge'
import SkeletonLoader from '@/components/ui/SkeletonLoader'
import EmptyState from '@/components/ui/EmptyState'

const STATUS_OPTIONS = ['', 'draft', 'confirmed', 'in_production', 'partially_delivered', 'delivered', 'invoiced', 'cancelled']

function formatCentavos(c: number) {
  return new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' }).format(c / 100)
}

export default function SalesOrderListPage() {
  const [filters, setFilters] = useState<Record<string, unknown>>({ per_page: 20, page: 1 })
  const [search, setSearch] = useState('')
  const { data, isLoading } = useSalesOrders(filters)
  const orders = data?.data ?? []

  const handleSearch = useCallback((val: string) => {
    setFilters(p => ({ ...p, search: val || undefined, page: 1 }))
  }, [])

  return (
    <div className="space-y-6">
      <PageHeader
        title="Sales Order Processing"
        icon={<ShoppingBag className="w-5 h-5 text-neutral-600" />}
        subtitle="Sales orders are created from accepted quotations"
      />

      <Card className="p-4">
        <div className="flex flex-wrap items-center gap-3">
          <SearchInput
            value={search}
            onChange={setSearch}
            onSearch={handleSearch}
            placeholder="Search sales orders..."
            className="w-64"
          />
          <select className="input-sm" value={(filters.status as string) ?? ''} onChange={e => setFilters(p => ({ ...p, status: e.target.value || undefined, page: 1 }))}>
            <option value="">All Statuses</option>
            {STATUS_OPTIONS.filter(Boolean).map(s => <option key={s} value={s}>{s.replace(/_/g, ' ')}</option>)}
          </select>
        </div>
      </Card>

      {isLoading ? <SkeletonLoader rows={8} /> : orders.length === 0 ? (
        <EmptyState title="No sales orders" description="Create a sales order or convert a quotation." />
      ) : (
        <Card className="overflow-hidden">
          <table className="w-full text-sm">
            <thead className="bg-neutral-50 dark:bg-neutral-800">
              <tr>
                <th className="text-left p-3 font-medium">Order #</th>
                <th className="text-left p-3 font-medium">Customer</th>
                <th className="text-left p-3 font-medium">Status</th>
                <th className="text-right p-3 font-medium">Total</th>
                <th className="text-left p-3 font-medium">Delivery Date</th>
                <th className="text-left p-3 font-medium">Created</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-neutral-100 dark:divide-neutral-800">
              {orders.map(o => (
                <tr key={o.id} className="hover:bg-neutral-50 dark:hover:bg-neutral-800/50">
                  <td className="p-3">
                    <Link to={`/sales/orders/${o.ulid}`} className="font-medium text-blue-600 hover:underline">
                      {o.order_number}
                    </Link>
                  </td>
                  <td className="p-3">{o.customer?.name ?? '-'}</td>
                  <td className="p-3"><StatusBadge status={o.status} /></td>
                  <td className="p-3 text-right font-mono">{formatCentavos(o.total_centavos)}</td>
                  <td className="p-3 text-neutral-500">{o.promised_delivery_date ? new Date(o.promised_delivery_date).toLocaleDateString() : '-'}</td>
                  <td className="p-3 text-neutral-500">{new Date(o.created_at).toLocaleDateString()}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </Card>
      )}
    </div>
  )
}
