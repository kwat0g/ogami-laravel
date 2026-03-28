import { Link } from 'react-router-dom'
import { useAuth } from '@/hooks/useAuth'
import { usePurchaseRequests } from '@/hooks/usePurchaseRequests'
import DashboardHeader from '@/components/dashboard/DashboardHeader'
import { usePurchaseOrders } from '@/hooks/usePurchaseOrders'
import { useGoodsReceipts } from '@/hooks/useGoodsReceipts'
import SkeletonLoader from '@/components/ui/SkeletonLoader'
import { Card, CardHeader, CardBody } from '@/components/ui/Card'
import {
  ShoppingCart,
  ClipboardList,
  Package,
  Truck,
  Building2,
  ChevronRight,
  ArrowUpRight,
  AlertCircle,
  CheckCircle2,
  Archive,
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
            <p className={`text-2xl font-bold tracking-tight ${alert ? 'text-amber-700' : 'text-neutral-900'}`}>{value}</p>
            <p className="text-sm text-neutral-500 mt-0.5">{label}</p>
            {sub && <p className="text-xs text-neutral-400 mt-1">{sub}</p>}
          </div>
        </div>
      </Card>
    </Link>
  )
}

function PipelineStep({ label, count, active }: { label: string; count: number; active?: boolean }) {
  return (
    <div className={`flex-1 text-center p-3 rounded-lg ${active ? 'bg-amber-50 border border-amber-200' : 'bg-neutral-50 border border-neutral-200'}`}>
      <p className={`text-xl font-bold ${active && count > 0 ? 'text-amber-700' : 'text-neutral-900'}`}>{count}</p>
      <p className="text-xs text-neutral-500 mt-0.5">{label}</p>
    </div>
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

export default function PurchasingOfficerDashboard(): React.ReactElement {
  useAuth()
  const { data: prData,  isLoading: loadingPR } = usePurchaseRequests({ status: 'submitted', per_page: 1 })
  const { data: poData,  isLoading: loadingPO } = usePurchaseOrders({ status: 'draft',     per_page: 1 })
  const { data: sentPO,  isLoading: loadingSent } = usePurchaseOrders({ status: 'sent',   per_page: 1 })
  const { data: grData, isLoading: loadingGR } = useGoodsReceipts({ status: 'draft', per_page: 1 })
  const { data: reviewPR, isLoading: loadingReview } = usePurchaseRequests({ status: 'pending_review', per_page: 1 })

  const isLoading = loadingPR || loadingPO || loadingSent || loadingGR || loadingReview

  if (isLoading) return <SkeletonLoader rows={8} />

  const pendingPRs = (prData as { meta?: { total?: number } } | undefined)?.meta?.total ?? 0
  const reviewPRs  = (reviewPR as { meta?: { total?: number } } | undefined)?.meta?.total ?? 0
  const draftPOs   = (poData as { meta?: { total?: number } } | undefined)?.meta?.total ?? 0
  const sentPOs    = (sentPO as { meta?: { total?: number } } | undefined)?.meta?.total ?? 0
  const pendingGRs = (grData as { meta?: { total?: number } } | undefined)?.meta?.total ?? 0
  const totalActionable = pendingPRs + reviewPRs + draftPOs + pendingGRs

  return (
    <div className="space-y-6">
      {/* Header */}
      <div>
        <DashboardHeader roleLabel="Purchasing Officer" subtitle="Purchase requests, orders, and vendor management" />
        <p className="text-sm text-neutral-500 mt-0.5">Manage purchase requests, orders, vendor relationships, and goods receipts</p>
      </div>

      {/* Action Alert */}
      {pendingPRs > 0 && (
        <Link to="/procurement/purchase-requests">
          <Card className="border-amber-200 bg-amber-50/50 hover:shadow-sm transition-all">
            <div className="p-4 flex items-center gap-4">
              <AlertCircle className="h-5 w-5 text-amber-600 shrink-0" />
              <div className="flex-1">
                <p className="text-sm font-semibold text-amber-800">{pendingPRs} Purchase Request{pendingPRs > 1 ? 's' : ''} Awaiting Review</p>
                <p className="text-xs text-amber-600">Submitted by department heads and managers</p>
              </div>
              <ChevronRight className="h-4 w-4 text-amber-400" />
            </div>
          </Card>
        </Link>
      )}

      {/* KPI Cards */}
      <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
        <KpiCard
          label="Pending PRs"
          value={pendingPRs}
          sub="Awaiting your review"
          icon={ClipboardList}
          href="/procurement/purchase-requests"
          alert={pendingPRs > 0}
        />
        <KpiCard
          label="Draft POs"
          value={draftPOs}
          sub="Not yet sent to vendor"
          icon={ShoppingCart}
          href="/procurement/purchase-orders"
          alert={draftPOs > 0}
        />
        <KpiCard
          label="Sent POs"
          value={sentPOs}
          sub="Awaiting vendor fulfillment"
          icon={Truck}
          href="/procurement/purchase-orders"
        />
        <KpiCard
          label="Pending Review"
          value={reviewPRs}
          sub="PRs to review"
          icon={ClipboardList}
          href="/procurement/purchase-requests?status=pending_review"
          alert={reviewPRs > 0}
        />
        <KpiCard
          label="Pending GRs"
          value={pendingGRs}
          sub="Draft goods receipts"
          icon={Package}
          href="/procurement/goods-receipts"
          alert={pendingGRs > 0}
        />
        <KpiCard
          label="Action Items"
          value={totalActionable}
          sub={totalActionable > 0 ? 'Needs your attention' : 'All clear'}
          icon={AlertCircle}
          href="/procurement/purchase-requests"
          alert={totalActionable > 0}
        />
      </div>

      {/* Procurement Pipeline Visual */}
      <Card>
        <CardHeader>
          <div className="flex items-center gap-2">
            <ShoppingCart className="h-4 w-4 text-neutral-500" />
            Procurement Pipeline
          </div>
        </CardHeader>
        <CardBody>
          <div className="flex items-center gap-2">
            <PipelineStep label="PR Submitted" count={pendingPRs} active={pendingPRs > 0} />
            <ChevronRight className="h-4 w-4 text-neutral-300 shrink-0" />
            <PipelineStep label="PO Draft" count={draftPOs} active={draftPOs > 0} />
            <ChevronRight className="h-4 w-4 text-neutral-300 shrink-0" />
            <PipelineStep label="PO Sent" count={sentPOs} active={sentPOs > 0} />
            <ChevronRight className="h-4 w-4 text-neutral-300 shrink-0" />
            <PipelineStep label="GR Pending" count={pendingGRs} active={pendingGRs > 0} />
            <CheckCircle2 className="h-4 w-4 text-green-500 shrink-0" />
          </div>
        </CardBody>
      </Card>

      {/* Modules */}
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <Card>
          <CardHeader>
            <div className="flex items-center gap-2">
              <ShoppingCart className="h-4 w-4 text-neutral-500" />
              Procurement
            </div>
          </CardHeader>
          <CardBody>
            <div className="space-y-2">
              <ModuleLink href="/procurement/purchase-requests" label="Purchase Requests" icon={ClipboardList} desc="Review and approve PRs" />
              <ModuleLink href="/procurement/purchase-orders" label="Purchase Orders" icon={ShoppingCart} desc="Create and manage POs" />
              <ModuleLink href="/procurement/goods-receipts" label="Goods Receipts" icon={Archive} desc="Receive and inspect deliveries" />
              <ModuleLink href="/procurement/rfqs" label="Requests for Quotation" icon={ClipboardList} desc="Send RFQs to vendors" />
            </div>
          </CardBody>
        </Card>

        <Card>
          <CardHeader>
            <div className="flex items-center gap-2">
              <Building2 className="h-4 w-4 text-neutral-500" />
              Vendor & Inventory
            </div>
          </CardHeader>
          <CardBody>
            <div className="space-y-2">
              <ModuleLink href="/accounting/vendors" label="Vendors" icon={Building2} desc="Manage vendor profiles and accreditation" />
              <ModuleLink href="/inventory/items" label="Inventory Items" icon={Package} desc="View item master data" />
              <ModuleLink href="/inventory/stock" label="Stock Levels" icon={Package} desc="Current stock balances" />
              <ModuleLink href="/delivery" label="Delivery Tracking" icon={Truck} desc="Inbound delivery status" />
            </div>
          </CardBody>
        </Card>
      </div>
    </div>
  )
}
