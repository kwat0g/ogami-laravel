/**
 * Shared Dashboard Widget Library
 *
 * Professional ERP-grade dashboard components used across all role-based dashboards.
 * Provides consistent styling and behavior for KPI cards, charts, tables, and alerts.
 */
import { Link } from 'react-router-dom'
import { Card } from '@/components/ui/Card'
import {
  BarChart, Bar, XAxis, YAxis, CartesianGrid, Tooltip, ResponsiveContainer,
  PieChart, Pie, Cell, LineChart, Line, AreaChart, Area,
} from 'recharts'
import { ChevronRight, AlertTriangle, TrendingUp, TrendingDown } from 'lucide-react'

// ============================================================================
// KPI Stat Card
// ============================================================================

interface KpiCardProps {
  label: string
  value: string | number
  sub?: string
  icon: React.ComponentType<{ className?: string }>
  href?: string
  trend?: { value: number; label: string } // positive = up, negative = down
  color?: 'default' | 'success' | 'warning' | 'danger' | 'info'
}

const COLOR_MAP = {
  default: 'bg-neutral-50 text-neutral-600',
  success: 'bg-green-50 text-green-600',
  warning: 'bg-amber-50 text-amber-600',
  danger: 'bg-red-50 text-red-600',
  info: 'bg-blue-50 text-blue-600',
}

export function KpiCard({ label, value, sub, icon: Icon, href, trend, color = 'default' }: KpiCardProps): JSX.Element {
  const content = (
    <Card className="h-full hover:shadow-md transition-shadow">
      <div className="p-4">
        <div className="flex items-start justify-between">
          <div className={`rounded-lg p-2 ${COLOR_MAP[color]}`}>
            <Icon className="h-4 w-4" />
          </div>
          {href && <ChevronRight className="h-4 w-4 text-neutral-300" />}
        </div>
        <div className="mt-3">
          <p className="text-2xl font-bold text-neutral-900 tracking-tight">{value}</p>
          <p className="text-xs font-medium text-neutral-500 mt-1 uppercase tracking-wide">{label}</p>
          {sub && <p className="text-xs text-neutral-400 mt-0.5">{sub}</p>}
          {trend && (
            <div className={`flex items-center gap-1 mt-1.5 text-xs font-medium ${trend.value >= 0 ? 'text-green-600' : 'text-red-600'}`}>
              {trend.value >= 0 ? <TrendingUp className="h-3 w-3" /> : <TrendingDown className="h-3 w-3" />}
              {Math.abs(trend.value)}% {trend.label}
            </div>
          )}
        </div>
      </div>
    </Card>
  )

  if (href) return <Link to={href} className="block">{content}</Link>
  return content
}

// ============================================================================
// Pending Approval Alert
// ============================================================================

interface ApprovalAlertProps {
  count: number
  label: string
  href: string
  icon?: React.ComponentType<{ className?: string }>
  urgency?: 'normal' | 'high' | 'critical'
}

export function ApprovalAlert({ count, label, href, icon: Icon = AlertTriangle, urgency = 'normal' }: ApprovalAlertProps): JSX.Element | null {
  if (count === 0) return null

  const urgencyColors = {
    normal: 'border-amber-200 bg-amber-50',
    high: 'border-orange-200 bg-orange-50',
    critical: 'border-red-200 bg-red-50 animate-pulse',
  }

  return (
    <Link to={href} className="block">
      <div className={`flex items-center justify-between px-4 py-3 rounded-lg border ${urgencyColors[urgency]} hover:shadow-sm transition-shadow`}>
        <div className="flex items-center gap-3">
          <Icon className="h-4 w-4 text-amber-600" />
          <span className="text-sm font-medium text-neutral-700">{label}</span>
        </div>
        <div className="flex items-center gap-2">
          <span className="bg-amber-600 text-white text-xs font-bold rounded-full w-6 h-6 flex items-center justify-center">
            {count}
          </span>
          <ChevronRight className="h-4 w-4 text-neutral-400" />
        </div>
      </div>
    </Link>
  )
}

// ============================================================================
// Section Header
// ============================================================================

export function SectionHeader({ title, action }: { title: string; action?: { label: string; href: string } }): JSX.Element {
  return (
    <div className="flex items-center justify-between mb-3">
      <h3 className="text-sm font-semibold text-neutral-800 uppercase tracking-wide">{title}</h3>
      {action && (
        <Link to={action.href} className="text-xs text-blue-600 hover:text-blue-800 font-medium flex items-center gap-1">
          {action.label} <ChevronRight className="h-3 w-3" />
        </Link>
      )}
    </div>
  )
}

// ============================================================================
// Mini Bar Chart
// ============================================================================

interface MiniBarChartProps {
  data: { name: string; value: number }[]
  color?: string
  height?: number
  formatValue?: (v: number) => string
}

export function MiniBarChart({ data, color = '#3b82f6', height = 180, formatValue }: MiniBarChartProps): JSX.Element {
  return (
    <ResponsiveContainer width="100%" height={height}>
      <BarChart data={data}>
        <CartesianGrid strokeDasharray="3 3" stroke="#f0f0f0" />
        <XAxis dataKey="name" fontSize={10} tick={{ fill: '#999' }} />
        <YAxis fontSize={10} tick={{ fill: '#999' }} tickFormatter={formatValue} />
        <Tooltip formatter={formatValue ? (v: number) => formatValue(v) : undefined} />
        <Bar dataKey="value" fill={color} radius={[3, 3, 0, 0]} />
      </BarChart>
    </ResponsiveContainer>
  )
}

// ============================================================================
// Mini Line/Area Chart
// ============================================================================

interface MiniAreaChartProps {
  data: { name: string; value: number }[]
  color?: string
  height?: number
  formatValue?: (v: number) => string
}

export function MiniAreaChart({ data, color = '#3b82f6', height = 180, formatValue }: MiniAreaChartProps): JSX.Element {
  return (
    <ResponsiveContainer width="100%" height={height}>
      <AreaChart data={data}>
        <CartesianGrid strokeDasharray="3 3" stroke="#f0f0f0" />
        <XAxis dataKey="name" fontSize={10} tick={{ fill: '#999' }} />
        <YAxis fontSize={10} tick={{ fill: '#999' }} tickFormatter={formatValue} />
        <Tooltip formatter={formatValue ? (v: number) => formatValue(v) : undefined} />
        <defs>
          <linearGradient id={`gradient-${color.replace('#', '')}`} x1="0" y1="0" x2="0" y2="1">
            <stop offset="5%" stopColor={color} stopOpacity={0.3} />
            <stop offset="95%" stopColor={color} stopOpacity={0} />
          </linearGradient>
        </defs>
        <Area type="monotone" dataKey="value" stroke={color} strokeWidth={2} fill={`url(#gradient-${color.replace('#', '')})`} />
      </AreaChart>
    </ResponsiveContainer>
  )
}

// ============================================================================
// Mini Donut Chart
// ============================================================================

interface DonutChartProps {
  data: { name: string; value: number; color: string }[]
  height?: number
  centerLabel?: string
  centerValue?: string | number
}

const RADIAN = Math.PI / 180

export function MiniDonutChart({ data, height = 200, centerLabel, centerValue }: DonutChartProps): JSX.Element {
  return (
    <div className="relative">
      <ResponsiveContainer width="100%" height={height}>
        <PieChart>
          <Pie
            data={data}
            cx="50%"
            cy="50%"
            innerRadius={50}
            outerRadius={75}
            paddingAngle={2}
            dataKey="value"
          >
            {data.map((entry, i) => (
              <Cell key={i} fill={entry.color} />
            ))}
          </Pie>
          <Tooltip formatter={(v: number, _name: string, props: { payload: { name: string } }) => [`${v}`, props.payload.name]} />
        </PieChart>
      </ResponsiveContainer>
      {centerLabel && (
        <div className="absolute inset-0 flex flex-col items-center justify-center pointer-events-none">
          <span className="text-xl font-bold text-neutral-900">{centerValue}</span>
          <span className="text-[10px] text-neutral-500 uppercase tracking-wide">{centerLabel}</span>
        </div>
      )}
      <div className="flex flex-wrap justify-center gap-3 mt-2">
        {data.map((d, i) => (
          <div key={i} className="flex items-center gap-1.5">
            <div className="w-2.5 h-2.5 rounded-full" style={{ backgroundColor: d.color }} />
            <span className="text-[10px] text-neutral-600">{d.name} ({d.value})</span>
          </div>
        ))}
      </div>
    </div>
  )
}

// ============================================================================
// Progress Bar with Label
// ============================================================================

interface ProgressBarProps {
  label: string
  value: number
  max: number
  formatValue?: (v: number) => string
  showPct?: boolean
}

export function ProgressBar({ label, value, max, formatValue, showPct = true }: ProgressBarProps): JSX.Element {
  const pct = max > 0 ? Math.min((value / max) * 100, 100) : 0
  const color = pct > 90 ? 'bg-red-500' : pct > 70 ? 'bg-amber-500' : 'bg-green-500'

  return (
    <div>
      <div className="flex justify-between text-xs mb-1">
        <span className="font-medium text-neutral-600 truncate">{label}</span>
        <span className="text-neutral-500 ml-2 whitespace-nowrap">
          {formatValue ? formatValue(value) : value} / {formatValue ? formatValue(max) : max}
          {showPct && <span className="ml-1 font-semibold">({pct.toFixed(0)}%)</span>}
        </span>
      </div>
      <div className="h-2 bg-neutral-100 rounded-full overflow-hidden">
        <div className={`h-full rounded-full transition-all ${color}`} style={{ width: `${pct}%` }} />
      </div>
    </div>
  )
}

// ============================================================================
// Quick Action Grid
// ============================================================================

interface QuickAction {
  label: string
  href: string
  icon: React.ComponentType<{ className?: string }>
  color?: string
}

export function QuickActions({ actions }: { actions: QuickAction[] }): JSX.Element {
  return (
    <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-2">
      {actions.map((action) => (
        <Link
          key={action.href}
          to={action.href}
          className="flex flex-col items-center gap-2 p-3 rounded-lg border border-neutral-200 hover:bg-blue-50 hover:border-blue-200 transition-all group"
        >
          <action.icon className={`h-5 w-5 ${action.color ?? 'text-neutral-500'} group-hover:text-blue-600`} />
          <span className="text-[11px] font-medium text-neutral-600 text-center group-hover:text-blue-700">{action.label}</span>
        </Link>
      ))}
    </div>
  )
}

// ============================================================================
// Activity Feed / Recent Items Table
// ============================================================================

interface ActivityItem {
  id: string | number
  label: string
  sub?: string
  status?: string
  date?: string
  href?: string
}

export function ActivityFeed({ items, emptyMessage = 'No recent activity' }: { items: ActivityItem[]; emptyMessage?: string }): JSX.Element {
  if (items.length === 0) {
    return <p className="text-sm text-neutral-400 text-center py-6">{emptyMessage}</p>
  }

  return (
    <div className="divide-y divide-neutral-100">
      {items.map((item) => {
        const content = (
          <div className="flex items-center justify-between py-2.5 px-1 hover:bg-neutral-50 rounded transition-colors">
            <div className="min-w-0 flex-1">
              <p className="text-sm font-medium text-neutral-800 truncate">{item.label}</p>
              {item.sub && <p className="text-xs text-neutral-500 truncate">{item.sub}</p>}
            </div>
            <div className="flex items-center gap-2 ml-3 flex-shrink-0">
              {item.status && (
                <span className={`text-[10px] font-semibold px-2 py-0.5 rounded-full uppercase ${
                  item.status === 'approved' || item.status === 'completed' || item.status === 'passed' ? 'bg-green-100 text-green-700' :
                  item.status === 'pending' || item.status === 'draft' ? 'bg-neutral-100 text-neutral-600' :
                  item.status === 'rejected' || item.status === 'failed' || item.status === 'overdue' ? 'bg-red-100 text-red-700' :
                  'bg-blue-100 text-blue-700'
                }`}>
                  {item.status}
                </span>
              )}
              {item.date && <span className="text-[10px] text-neutral-400">{item.date}</span>}
            </div>
          </div>
        )

        if (item.href) return <Link key={item.id} to={item.href} className="block">{content}</Link>
        return <div key={item.id}>{content}</div>
      })}
    </div>
  )
}

// ============================================================================
// Dashboard Grid Layout Helpers
// ============================================================================

export function DashboardGrid({ children }: { children: React.ReactNode }): JSX.Element {
  return <div className="grid grid-cols-1 lg:grid-cols-2 gap-4">{children}</div>
}

export function DashboardFullWidth({ children }: { children: React.ReactNode }): JSX.Element {
  return <div className="lg:col-span-2">{children}</div>
}

export function WidgetCard({ title, children, action, className = '' }: {
  title: string
  children: React.ReactNode
  action?: { label: string; href: string }
  className?: string
}): JSX.Element {
  return (
    <Card className={`p-4 ${className}`}>
      <SectionHeader title={title} action={action} />
      {children}
    </Card>
  )
}

// ============================================================================
// Peso Formatter
// ============================================================================

export function formatPeso(centavos: number): string {
  return '₱' + (centavos / 100).toLocaleString(undefined, { minimumFractionDigits: 0, maximumFractionDigits: 0 })
}

export function formatPesoDecimal(amount: number): string {
  return '₱' + amount.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}
