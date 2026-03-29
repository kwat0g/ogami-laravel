import { useExecutiveDashboard } from '@/hooks/useAnalytics'
import { PageHeader } from '@/components/ui/PageHeader'
import { Card } from '@/components/ui/Card'
import SkeletonLoader from '@/components/ui/SkeletonLoader'
import {
  BarChart, Bar, XAxis, YAxis, CartesianGrid, Tooltip, ResponsiveContainer,
  PieChart, Pie, Cell, LineChart, Line, Legend,
} from 'recharts'
import { TrendingUp, AlertTriangle, Package, Users, CheckCircle2, Ticket } from 'lucide-react'

const COLORS = ['#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#06b6d4']
const AGING_COLORS = ['#10b981', '#3b82f6', '#f59e0b', '#f97316', '#ef4444']

function formatPeso(centavos: number): string {
  return '₱' + (centavos / 100).toLocaleString(undefined, { minimumFractionDigits: 0, maximumFractionDigits: 0 })
}

function KpiCard({ icon: Icon, label, value, sub, color = 'text-blue-600' }: {
  icon: React.ElementType; label: string; value: string | number; sub?: string; color?: string
}): JSX.Element {
  return (
    <Card className="p-4">
      <div className="flex items-center gap-3">
        <div className={`rounded-lg p-2 bg-opacity-10 ${color} bg-current`}>
          <Icon className={`h-5 w-5 ${color}`} />
        </div>
        <div>
          <p className="text-xs text-neutral-500 uppercase tracking-wide">{label}</p>
          <p className="text-lg font-semibold text-neutral-900">{value}</p>
          {sub && <p className="text-xs text-neutral-400">{sub}</p>}
        </div>
      </div>
    </Card>
  )
}

export default function ExecutiveAnalyticsDashboard(): JSX.Element {
  const { data, isLoading, isError } = useExecutiveDashboard()

  if (isLoading) return <SkeletonLoader lines={12} />
  if (isError || !data) return <p className="text-red-600 p-4">Failed to load executive dashboard.</p>

  const agingData = [
    { name: 'Current', value: data.ar_aging.current },
    { name: '31-60', value: data.ar_aging.bucket_31_60 },
    { name: '61-90', value: data.ar_aging.bucket_61_90 },
    { name: '91-120', value: data.ar_aging.bucket_91_120 },
    { name: '120+', value: data.ar_aging.over_120 },
  ]

  const revenueData = data.sales.monthly_trend.map((m) => ({
    month: m.month,
    revenue: m.total_revenue_centavos / 100,
    orders: m.order_count,
  }))

  return (
    <div className="space-y-6">
      <PageHeader title="Executive Analytics" subtitle="Real-time business intelligence across all modules" />

      {/* KPI Cards */}
      <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4">
        <KpiCard
          icon={TrendingUp}
          label="Win Rate"
          value={`${data.sales.win_rate.win_rate_pct}%`}
          sub={`${data.sales.win_rate.won} won / ${data.sales.win_rate.lost} lost`}
          color="text-green-600"
        />
        <KpiCard
          icon={AlertTriangle}
          label="AR Outstanding"
          value={formatPeso(data.ar_aging.grand_total * 100)}
          color="text-amber-600"
        />
        <KpiCard
          icon={Package}
          label="MRP Shortages"
          value={data.mrp_summary.components_with_shortage}
          sub={`of ${data.mrp_summary.total_components_needed} components`}
          color="text-red-600"
        />
        <KpiCard
          icon={CheckCircle2}
          label="QC Pass Rate"
          value={`${data.quality_kpi.overall_pass_rate_pct}%`}
          sub={`${data.quality_kpi.open_ncrs} open NCRs`}
          color="text-blue-600"
        />
        <KpiCard
          icon={Users}
          label="Headcount"
          value={data.headcount.reduce((s, d) => s + d.headcount, 0)}
          sub={`${data.headcount.length} departments`}
          color="text-indigo-600"
        />
        <KpiCard
          icon={Ticket}
          label="Open Tickets"
          value={data.tickets.total_open}
          sub={`${data.tickets.overdue_count} overdue`}
          color="text-orange-600"
        />
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {/* Revenue Trend */}
        <Card className="p-4">
          <h3 className="font-semibold text-neutral-800 mb-3">Monthly Revenue Trend</h3>
          <ResponsiveContainer width="100%" height={250}>
            <LineChart data={revenueData}>
              <CartesianGrid strokeDasharray="3 3" />
              <XAxis dataKey="month" fontSize={11} />
              <YAxis fontSize={11} tickFormatter={(v: number) => `₱${(v / 1000).toFixed(0)}k`} />
              <Tooltip formatter={(v: number) => `₱${v.toLocaleString()}`} />
              <Line type="monotone" dataKey="revenue" stroke="#3b82f6" strokeWidth={2} dot={{ r: 3 }} />
            </LineChart>
          </ResponsiveContainer>
        </Card>

        {/* AR Aging */}
        <Card className="p-4">
          <h3 className="font-semibold text-neutral-800 mb-3">AR Aging Distribution</h3>
          <ResponsiveContainer width="100%" height={250}>
            <BarChart data={agingData}>
              <CartesianGrid strokeDasharray="3 3" />
              <XAxis dataKey="name" fontSize={11} />
              <YAxis fontSize={11} tickFormatter={(v: number) => `₱${(v / 1000).toFixed(0)}k`} />
              <Tooltip formatter={(v: number) => `₱${v.toLocaleString()}`} />
              <Bar dataKey="value" radius={[4, 4, 0, 0]}>
                {agingData.map((_entry, i) => (
                  <Cell key={i} fill={AGING_COLORS[i]} />
                ))}
              </Bar>
            </BarChart>
          </ResponsiveContainer>
        </Card>

        {/* Pipeline Funnel */}
        <Card className="p-4">
          <h3 className="font-semibold text-neutral-800 mb-3">Sales Pipeline</h3>
          <ResponsiveContainer width="100%" height={250}>
            <PieChart>
              <Pie
                data={data.sales.pipeline.map((s) => ({ name: s.status, value: s.count }))}
                cx="50%"
                cy="50%"
                outerRadius={90}
                label={({ name, value }: { name: string; value: number }) => `${name} (${value})`}
                fontSize={10}
              >
                {data.sales.pipeline.map((_s, i) => (
                  <Cell key={i} fill={COLORS[i % COLORS.length]} />
                ))}
              </Pie>
              <Tooltip />
            </PieChart>
          </ResponsiveContainer>
        </Card>

        {/* Budget Utilization */}
        <Card className="p-4">
          <h3 className="font-semibold text-neutral-800 mb-3">Budget Utilization by Cost Center</h3>
          <div className="space-y-3 max-h-[250px] overflow-y-auto">
            {data.budget.map((row) => (
              <div key={row.cost_center_id}>
                <div className="flex justify-between text-sm mb-1">
                  <span className="font-medium text-neutral-700">{row.cost_center_name}</span>
                  <span className={`text-xs font-semibold ${
                    row.utilization_pct > 100 ? 'text-red-600' :
                    row.utilization_pct > 80 ? 'text-amber-600' : 'text-green-600'
                  }`}>
                    {row.utilization_pct}%
                  </span>
                </div>
                <div className="h-2 bg-neutral-100 rounded-full overflow-hidden">
                  <div
                    className={`h-full rounded-full transition-all ${
                      row.utilization_pct > 100 ? 'bg-red-500' :
                      row.utilization_pct > 80 ? 'bg-amber-500' : 'bg-green-500'
                    }`}
                    style={{ width: `${Math.min(row.utilization_pct, 100)}%` }}
                  />
                </div>
              </div>
            ))}
            {data.budget.length === 0 && (
              <p className="text-sm text-neutral-400 text-center py-4">No budget data for current year.</p>
            )}
          </div>
        </Card>

        {/* Headcount by Department */}
        <Card className="p-4">
          <h3 className="font-semibold text-neutral-800 mb-3">Headcount by Department</h3>
          <ResponsiveContainer width="100%" height={250}>
            <BarChart data={data.headcount} layout="vertical">
              <CartesianGrid strokeDasharray="3 3" />
              <XAxis type="number" fontSize={11} />
              <YAxis type="category" dataKey="department_name" fontSize={10} width={100} />
              <Tooltip />
              <Legend />
              <Bar dataKey="active" stackId="a" fill="#3b82f6" name="Active" />
              <Bar dataKey="on_leave" stackId="a" fill="#f59e0b" name="On Leave" />
            </BarChart>
          </ResponsiveContainer>
        </Card>

        {/* Top Customers */}
        <Card className="p-4">
          <h3 className="font-semibold text-neutral-800 mb-3">Top Customers by Revenue</h3>
          <div className="space-y-2">
            {data.sales.top_customers.map((c, i) => (
              <div key={c.customer_id} className="flex items-center justify-between py-1.5 border-b border-neutral-100 last:border-0">
                <div className="flex items-center gap-2">
                  <span className="text-xs font-bold text-neutral-400 w-5">#{i + 1}</span>
                  <span className="text-sm font-medium text-neutral-700">{c.customer_name}</span>
                </div>
                <div className="text-right">
                  <span className="text-sm font-semibold text-neutral-900">{formatPeso(c.total_revenue_centavos)}</span>
                  <span className="text-xs text-neutral-400 ml-2">{c.order_count} orders</span>
                </div>
              </div>
            ))}
            {data.sales.top_customers.length === 0 && (
              <p className="text-sm text-neutral-400 text-center py-4">No revenue data yet.</p>
            )}
          </div>
        </Card>
      </div>
    </div>
  )
}
