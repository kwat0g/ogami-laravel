import { Link } from 'react-router-dom'
import { useAuth } from '@/hooks/useAuth'
import { useStaffDashboardStats } from '@/hooks/useDashboard'
import SkeletonLoader from '@/components/ui/SkeletonLoader'
import {
  FileText,
  Calendar,
  Wallet,
  UserCircle,
  Clock,
  CheckCircle2,
  AlertCircle,
  PiggyBank,
  Timer,
  ChevronRight,
} from 'lucide-react'

// Modern stat card with subtle shadow and hover effect
function StatCard({
  label,
  value,
  sub,
  icon: Icon,
  color = 'blue',
  href,
}: {
  label: string
  value: React.ReactNode
  sub?: string
  icon: React.ComponentType<{ className?: string }>
  color?: 'blue' | 'green' | 'amber' | 'purple' | 'red' | 'gray' | 'indigo'
  href?: string
}) {
  const colorMap = {
    blue: {
      bg: 'bg-blue-50',
      border: 'border-blue-100',
      iconBg: 'bg-blue-500',
      text: 'text-blue-700',
      subText: 'text-blue-600',
    },
    green: {
      bg: 'bg-green-50',
      border: 'border-green-100',
      iconBg: 'bg-green-500',
      text: 'text-green-700',
      subText: 'text-green-600',
    },
    amber: {
      bg: 'bg-amber-50',
      border: 'border-amber-100',
      iconBg: 'bg-amber-500',
      text: 'text-amber-700',
      subText: 'text-amber-600',
    },
    purple: {
      bg: 'bg-purple-50',
      border: 'border-purple-100',
      iconBg: 'bg-purple-500',
      text: 'text-purple-700',
      subText: 'text-purple-600',
    },
    red: {
      bg: 'bg-red-50',
      border: 'border-red-100',
      iconBg: 'bg-red-500',
      text: 'text-red-700',
      subText: 'text-red-600',
    },
    gray: {
      bg: 'bg-gray-50',
      border: 'border-gray-200',
      iconBg: 'bg-gray-500',
      text: 'text-gray-700',
      subText: 'text-gray-600',
    },
    indigo: {
      bg: 'bg-indigo-50',
      border: 'border-indigo-100',
      iconBg: 'bg-indigo-500',
      text: 'text-indigo-700',
      subText: 'text-indigo-600',
    },
  }

  const colors = colorMap[color]

  const content = (
    <div className={`${colors.bg} border ${colors.border} rounded-xl p-5 hover:shadow-md transition-all duration-200`}>
      <div className="flex items-start justify-between">
        <div className={`h-12 w-12 rounded-xl ${colors.iconBg} flex items-center justify-center shadow-sm`}>
          <Icon className="h-6 w-6 text-white" />
        </div>
        {href && (
          <div className="h-8 w-8 rounded-lg bg-white/60 flex items-center justify-center">
            <ChevronRight className="h-4 w-4 text-gray-400" />
          </div>
        )}
      </div>
      <div className="mt-4">
        <p className="text-3xl font-bold text-gray-900">{value}</p>
        <p className={`text-sm font-medium ${colors.text} mt-1`}>{label}</p>
        {sub && <p className={`text-xs mt-1 ${colors.subText}`}>{sub}</p>}
      </div>
    </div>
  )

  if (href) {
    return <Link to={href} className="block">{content}</Link>
  }
  return content
}

// Quick link card
function QuickLink({
  to,
  icon: Icon,
  label,
  description,
  color = 'blue'
}: {
  to: string
  icon: React.ComponentType<{ className?: string }>
  label: string
  description: string
  color?: 'blue' | 'green' | 'amber' | 'purple' | 'indigo'
}) {
  const colorMap = {
    blue: { bg: 'bg-blue-100', text: 'text-blue-600', hoverBg: 'group-hover:bg-blue-500' },
    green: { bg: 'bg-green-100', text: 'text-green-600', hoverBg: 'group-hover:bg-green-500' },
    amber: { bg: 'bg-amber-100', text: 'text-amber-600', hoverBg: 'group-hover:bg-amber-500' },
    purple: { bg: 'bg-purple-100', text: 'text-purple-600', hoverBg: 'group-hover:bg-purple-500' },
    indigo: { bg: 'bg-indigo-100', text: 'text-indigo-600', hoverBg: 'group-hover:bg-indigo-500' },
  }
  
  const colors = colorMap[color]

  return (
    <Link
      to={to}
      className="flex items-center gap-3 p-4 bg-white border border-gray-200 rounded-xl hover:border-blue-300 hover:shadow-md transition-all duration-200 group"
    >
      <div className={`h-10 w-10 rounded-lg ${colors.bg} ${colors.text} ${colors.hoverBg} flex items-center justify-center group-hover:text-white transition-colors`}>
        <Icon className="h-5 w-5" />
      </div>
      <div className="flex-1 min-w-0">
        <p className="text-sm font-semibold text-gray-900">{label}</p>
        <p className="text-xs text-gray-500 truncate">{description}</p>
      </div>
      <ChevronRight className="h-4 w-4 text-gray-400 group-hover:text-blue-500" />
    </Link>
  )
}

// Section card with header
function SectionCard({ 
  title, 
  icon: Icon, 
  children,
  action
}: { 
  title: string
  icon?: React.ComponentType<{ className?: string }>
  children: React.ReactNode
  action?: { label: string; href: string }
}) {
  return (
    <div className="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
      <div className="px-6 py-4 border-b border-gray-100 flex items-center justify-between bg-gray-50/50">
        <div className="flex items-center gap-2">
          {Icon && <Icon className="h-5 w-5 text-gray-500" />}
          <h2 className="text-sm font-semibold text-gray-800">{title}</h2>
        </div>
        {action && (
          <Link to={action.href} className="text-xs text-blue-600 hover:text-blue-700 font-medium flex items-center gap-1">
            {action.label}
            <ChevronRight className="h-3 w-3" />
          </Link>
        )}
      </div>
      <div className="p-6">
        {children}
      </div>
    </div>
  )
}

// Simple table for recent requests
function RecentRequestsTable({ 
  title, 
  emptyMessage,
  href,
  children 
}: { 
  title: string
  emptyMessage: string
  href: string
  children: React.ReactNode
}) {
  return (
    <div className="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
      <div className="px-6 py-4 border-b border-gray-100 flex items-center justify-between bg-gray-50/50">
        <h3 className="text-sm font-semibold text-gray-800">{title}</h3>
        <Link to={href} className="text-xs text-blue-600 hover:text-blue-700 font-medium flex items-center gap-1">
          View all
          <ChevronRight className="h-3 w-3" />
        </Link>
      </div>
      <div className="divide-y divide-gray-100">
        {children || (
          <p className="px-6 py-8 text-sm text-gray-400 text-center">{emptyMessage}</p>
        )}
      </div>
    </div>
  )
}

function RequestRow({ 
  type, 
  date, 
  status,
  detail
}: { 
  type: string
  date: string
  status: string
  detail?: string
}) {
  const statusColors: Record<string, { bg: string; text: string; border: string }> = {
    pending: { bg: 'bg-amber-50', text: 'text-amber-700', border: 'border-amber-200' },
    approved: { bg: 'bg-green-50', text: 'text-green-700', border: 'border-green-200' },
    rejected: { bg: 'bg-red-50', text: 'text-red-700', border: 'border-red-200' },
    cancelled: { bg: 'bg-gray-50', text: 'text-gray-700', border: 'border-gray-200' },
    supervisor_approved: { bg: 'bg-blue-50', text: 'text-blue-700', border: 'border-blue-200' },
  }
  
  const colors = statusColors[status] || statusColors.pending
  
  return (
    <div className="px-6 py-4 flex items-center justify-between hover:bg-gray-50 transition-colors">
      <div className="flex-1 min-w-0">
        <p className="text-sm font-semibold text-gray-900">{type}</p>
        <p className="text-xs text-gray-500">{date} {detail && `• ${detail}`}</p>
      </div>
      <span className={`text-xs font-medium px-2.5 py-1 rounded-full border ${colors.bg} ${colors.text} ${colors.border} capitalize`}>
        {status.replace('_', ' ')}
      </span>
    </div>
  )
}

export default function EmployeeDashboard() {
  const { user } = useAuth()
  const { data: stats, isLoading } = useStaffDashboardStats()

  if (isLoading) {
    return <SkeletonLoader rows={8} />
  }

  const currentYear = new Date().getFullYear()

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex items-start justify-between">
        <div>
          <h1 className="text-2xl font-bold text-gray-900">
            Welcome, {user?.name?.split(' ')[0] ?? 'there'}
          </h1>
          <p className="text-sm text-gray-500 mt-1">
            Your personal dashboard for {currentYear}
          </p>
        </div>
        <div className="text-right">
          <p className="text-xs text-gray-500">{new Date().toLocaleDateString('en-PH', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' })}</p>
        </div>
      </div>

      {/* Stats Grid */}
      <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
        <StatCard
          label="Leave Balance"
          value={`${stats?.leave.balance_days ?? 0} days`}
          sub={`${stats?.leave.pending_requests ?? 0} pending`}
          icon={Calendar}
          color="blue"
          href="/me/leaves"
        />
        <StatCard
          label="Active Loans"
          value={stats?.loans.active_loans ?? 0}
          sub={`${stats?.loans.pending_approvals ?? 0} pending`}
          icon={PiggyBank}
          color="amber"
          href="/me/loans"
        />
        <StatCard
          label="OT Hours"
          value={stats?.attendance.this_month.ot_hours ?? 0}
          sub="This month"
          icon={Timer}
          color="purple"
          href="/me/overtime"
        />
        <StatCard
          label="YTD Net Pay"
          value={`₱${((stats?.payroll.ytd_net ?? 0) / 100).toLocaleString('en-PH', { 
            minimumFractionDigits: 0,
            maximumFractionDigits: 0
          })}`}
          sub={`${currentYear} to date`}
          icon={Wallet}
          color="green"
          href="/self-service/payslips"
        />
      </div>

      {/* Attendance Summary */}
      <SectionCard title="Attendance This Month" icon={Clock} action={{ label: 'View details', href: '/me/attendance' }}>
        <div className="grid grid-cols-4 gap-4">
          <div className="text-center p-4 rounded-xl bg-green-50 border border-green-100">
            <div className="h-10 w-10 rounded-lg bg-green-500 flex items-center justify-center mx-auto mb-2">
              <CheckCircle2 className="h-5 w-5 text-white" />
            </div>
            <p className="text-2xl font-bold text-gray-900">{stats?.attendance.this_month.present ?? 0}</p>
            <p className="text-xs text-gray-600 font-medium">Present</p>
          </div>
          <div className="text-center p-4 rounded-xl bg-red-50 border border-red-100">
            <div className="h-10 w-10 rounded-lg bg-red-500 flex items-center justify-center mx-auto mb-2">
              <AlertCircle className="h-5 w-5 text-white" />
            </div>
            <p className="text-2xl font-bold text-gray-900">{stats?.attendance.this_month.absent ?? 0}</p>
            <p className="text-xs text-gray-600 font-medium">Absent</p>
          </div>
          <div className="text-center p-4 rounded-xl bg-amber-50 border border-amber-100">
            <div className="h-10 w-10 rounded-lg bg-amber-500 flex items-center justify-center mx-auto mb-2">
              <Clock className="h-5 w-5 text-white" />
            </div>
            <p className="text-2xl font-bold text-gray-900">{stats?.attendance.this_month.late ?? 0}</p>
            <p className="text-xs text-gray-600 font-medium">Late</p>
          </div>
          <div className="text-center p-4 rounded-xl bg-blue-50 border border-blue-100">
            <div className="h-10 w-10 rounded-lg bg-blue-500 flex items-center justify-center mx-auto mb-2">
              <Timer className="h-5 w-5 text-white" />
            </div>
            <p className="text-2xl font-bold text-gray-900">{stats?.attendance.this_month.ot_hours ?? 0}</p>
            <p className="text-xs text-gray-600 font-medium">OT Hours</p>
          </div>
        </div>
      </SectionCard>

      {/* Recent Requests */}
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <RecentRequestsTable 
          title="Recent Leave Requests" 
          emptyMessage="No recent leave requests"
          href="/me/leaves"
        >
          {stats?.recent_requests.leaves?.slice(0, 5).map((leave) => (
            <RequestRow
              key={leave.id}
              type={leave.leave_type?.name ?? 'Leave'}
              date={`${leave.date_from} to ${leave.date_to}`}
              detail={`${leave.total_days} day${leave.total_days > 1 ? 's' : ''}`}
              status={leave.status}
            />
          ))}
        </RecentRequestsTable>

        <RecentRequestsTable 
          title="Recent Overtime Requests" 
          emptyMessage="No recent overtime requests"
          href="/me/overtime"
        >
          {stats?.recent_requests.overtime?.slice(0, 5).map((ot) => (
            <RequestRow
              key={ot.id}
              type="Overtime Request"
              date={ot.work_date}
              detail={`${ot.requested_hours} hours`}
              status={ot.status}
            />
          ))}
        </RecentRequestsTable>
      </div>

      {/* Loan Summary */}
      {(stats?.loans.active_loans ?? 0) > 0 && (
        <SectionCard title="Active Loans" icon={PiggyBank} action={{ label: 'View all', href: '/me/loans' }}>
          <div className="flex items-center gap-4 p-5 rounded-xl bg-gradient-to-r from-amber-50 to-orange-50 border border-amber-100">
            <div className="h-14 w-14 rounded-xl bg-amber-500 flex items-center justify-center shadow-sm">
              <PiggyBank className="h-7 w-7 text-white" />
            </div>
            <div className="flex-1">
              <p className="text-sm text-gray-600">Total Outstanding Balance</p>
              <p className="text-2xl font-bold text-gray-900">
                ₱{((stats?.loans.total_outstanding ?? 0) / 100).toLocaleString('en-PH', { 
                  minimumFractionDigits: 2,
                  maximumFractionDigits: 2
                })}
              </p>
            </div>
            <div className="text-right">
              <p className="text-sm text-gray-600">Active Loans</p>
              <p className="text-2xl font-bold text-gray-900">{stats?.loans.active_loans}</p>
            </div>
          </div>
        </SectionCard>
      )}

      {/* Quick Links */}
      <div>
        <h2 className="text-sm font-semibold text-gray-700 mb-3">Quick Access</h2>
        <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-3">
          <QuickLink 
            to="/self-service/payslips" 
            icon={FileText} 
            label="My Payslips" 
            description="View and download payslips"
            color="blue"
          />
          <QuickLink 
            to="/me/leaves" 
            icon={Calendar} 
            label="My Leaves" 
            description="Apply for leave or check balance"
            color="green"
          />
          <QuickLink 
            to="/me/loans" 
            icon={Wallet} 
            label="My Loans" 
            description="Track repayments and apply"
            color="amber"
          />
          <QuickLink 
            to="/me/overtime" 
            icon={Timer} 
            label="My Overtime" 
            description="View OT history and requests"
            color="purple"
          />
          <QuickLink 
            to="/me/attendance" 
            icon={Clock} 
            label="My Attendance" 
            description="View attendance records"
            color="indigo"
          />
          <QuickLink 
            to="/me/profile" 
            icon={UserCircle} 
            label="My Profile" 
            description="Update personal information"
            color="blue"
          />
        </div>
      </div>
    </div>
  )
}
