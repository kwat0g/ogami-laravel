import { Link } from 'react-router-dom'
import { useAuth } from '@/hooks/useAuth'
import { useDeliveryReceipts, useShipments } from '@/hooks/useDelivery'
import SkeletonLoader from '@/components/ui/SkeletonLoader'
import { Card, CardHeader, CardBody } from '@/components/ui/Card'
import {
  Truck,
  Package,
  ClipboardList,
  Building2,
  Ship,
  ChevronRight,
  Archive,
  BarChart3,
  ArrowUpRight,
  AlertCircle,
} from 'lucide-react'

function KpiCard({
  label,
  value,
  sub,
  icon: Icon,
  href,
  alert,
}: {
  label: string
  value: number | string
  sub?: string
  icon: React.ComponentType<{ className?: string }>
  href: string
  alert?: boolean
}) {
  return (
    <Link to={href}>
      <Card className={`h-full hover:shadow-md transition-all ${alert ? 'border-amber-200 bg-amber-50/30' : ''}`}>
        <div className="p-5">
          <div className="flex items-start justify-between">
            <div className={`p-2 rounded-lg ${alert ? 'bg-amber-100' : 'bg-neutral-100'}`}>
              <Icon className={`h-4 w-4 ${alert ? 'text-amber-600' : 'text-neutral-600'}`} />
            </div>
            <ArrowUpRight className="h-4 w-4 text-neutral-400" />
          </div>
          <div className="mt-3">
            <p className={`text-lg font-semibold tracking-tight ${alert ? 'text-amber-700' : 'text-neutral-900'}`}>{value}</p>
            <p className="text-sm text-neutral-500 mt-0.5">{label}</p>
            {sub && <p className="text-xs text-neutral-400 mt-1">{sub}</p>}
          </div>
        </div>
      </Card>
    </Link>
  )
}

function ModuleLink({
  href,
  label,
  icon: Icon,
  desc,
}: {
  href: string
  label: string
  icon: React.ComponentType<{ className?: string }>
  desc?: string
}) {
  return (
    <Link
      to={href}
      className="flex items-center gap-3 p-3 border border-neutral-200 bg-white rounded-lg hover:bg-neutral-50 hover:border-neutral-300 transition-all"
    >
      <div className="p-1.5 rounded bg-neutral-100">
        <Icon className="h-4 w-4 text-neutral-600" />
      </div>
      <div className="flex-1 min-w-0">
        <p className="text-sm font-medium text-neutral-700">{label}</p>
        {desc && <p className="text-xs text-neutral-400 truncate">{desc}</p>}
      </div>
      <ChevronRight className="h-4 w-4 text-neutral-300 shrink-0" />
    </Link>
  )
}

export default function ImpexOfficerDashboard(): React.ReactElement {
  useAuth()
  const { data: receiptsData,  isLoading: loadingReceipts }  = useDeliveryReceipts({ status: 'pending',   per_page: '1' })
  const { data: shipmentsData, isLoading: loadingShipments } = useShipments({ status: 'in_transit', per_page: '1' })

  const isLoading = loadingReceipts || loadingShipments

  if (isLoading) return <SkeletonLoader rows={8} />

  const pendingReceipts = (receiptsData as { meta?: { total?: number } } | undefined)?.meta?.total ?? 0
  const activeShipments = (shipmentsData as { meta?: { total?: number } } | undefined)?.meta?.total ?? 0

  return (
    <div className="space-y-6">
      {/* Header */}
      <div>
        <h1 className="text-lg font-semibold text-neutral-900">Import / Export Dashboard</h1>
        <p className="text-sm text-neutral-500 mt-0.5">Manage inbound receipts, outbound shipments, and delivery logistics</p>
      </div>

      {/* Pending Alert */}
      {pendingReceipts > 0 && (
        <Link to="/delivery/receipts">
          <Card className="border-amber-200 bg-amber-50/50 hover:shadow-sm transition-all">
            <div className="p-4 flex items-center gap-4">
              <AlertCircle className="h-5 w-5 text-amber-600 shrink-0" />
              <div className="flex-1">
                <p className="text-sm font-semibold text-amber-800">{pendingReceipts} Inbound Receipt{pendingReceipts > 1 ? 's' : ''} Pending Confirmation</p>
                <p className="text-xs text-amber-600">Goods arrived but not yet confirmed in system</p>
              </div>
              <ChevronRight className="h-4 w-4 text-amber-400" />
            </div>
          </Card>
        </Link>
      )}

      {/* KPI Cards */}
      <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <KpiCard
          label="Pending Receipts"
          value={pendingReceipts}
          sub="Awaiting confirmation"
          icon={Archive}
          href="/delivery/receipts"
          alert={pendingReceipts > 0}
        />
        <KpiCard
          label="In-Transit Shipments"
          value={activeShipments}
          sub="Currently in transit"
          icon={Ship}
          href="/delivery/shipments"
        />
        <KpiCard
          label="Delivery Operations"
          value="—"
          sub="View full delivery log"
          icon={Truck}
          href="/delivery"
        />
      </div>

      {/* Module Navigation */}
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <Card>
          <CardHeader>
            <div className="flex items-center gap-2">
              <Truck className="h-4 w-4 text-neutral-500" />
              Delivery Operations
            </div>
          </CardHeader>
          <CardBody>
            <div className="space-y-2">
              <ModuleLink href="/delivery/receipts" label="Inbound Receipts" icon={Archive} desc="Confirm received goods and materials" />
              <ModuleLink href="/delivery/shipments" label="Outbound Shipments" icon={Ship} desc="Track shipments to customers" />
              <ModuleLink href="/procurement/goods-receipts" label="Goods Receipts" icon={Package} desc="Match received goods to POs" />
            </div>
          </CardBody>
        </Card>

        <Card>
          <CardHeader>
            <div className="flex items-center gap-2">
              <Building2 className="h-4 w-4 text-neutral-500" />
              Procurement & Inventory
            </div>
          </CardHeader>
          <CardBody>
            <div className="space-y-2">
              <ModuleLink href="/procurement/purchase-orders" label="Purchase Orders" icon={ClipboardList} desc="Track vendor purchase orders" />
              <ModuleLink href="/accounting/vendors" label="Vendors" icon={Building2} desc="Vendor profiles and contacts" />
              <ModuleLink href="/inventory/stock" label="Stock Levels" icon={BarChart3} desc="Current inventory balances" />
              <ModuleLink href="/inventory/items" label="Inventory Items" icon={Package} desc="Item master catalog" />
            </div>
          </CardBody>
        </Card>
      </div>
    </div>
  )
}
