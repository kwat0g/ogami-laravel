import { Link } from 'react-router-dom'
import { useAuth } from '@/hooks/useAuth'
import { useStaffDashboardStats } from '@/hooks/useDashboard'
import SkeletonLoader from '@/components/ui/SkeletonLoader'
import { Card, CardHeader, CardBody } from '@/components/ui/Card'
import { StatusBadge } from '@/components/ui/StatusBadge'
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

// Simple stat card using Card component
function StatCard({
  label,
  value,
  sub,
  icon: Icon,
  href,
}: {
  label: string
  value: React.ReactNode
  sub?: string
  icon: React.ComponentType<{ className?: string }>
  href?: string
}) {
  const content = (
    <Card className="h-full">
      <div className="p-5">
        <div className="flex items-start justify-between">
          <Icon className="h-5 w-5 text-neutral-500" />
          {href && (
            <ChevronRight className="h-4 w-4 text-neutral-400" />
          )}
        </div>
        <div className="mt-4">
          <p className="text-2xl font-semibold text-neutral-900">{value}</p>
          <p className="text-sm text-neutral-600 mt-1">{label}</p>
          {sub && <p className="text-xs text-neutral-500 mt-1">{sub}</p>}
        </div>
      </div>
    </Card>
  )

  if (href) {
    return <Link to={href} className="block">{content}</Link>
  }
  return content
}

// Section card using Card component
function SectionCard({ 
  title, 
  children,
  action
}: { 
  title: string
  children: React.ReactNode
  action?: { label: string; href: string }
}) {
  return (
    <Card>
      <CardHeader action={action && (
        <Link to={action.href} className="px-2 py-1 text-xs border border-neutral-200 rounded bg-white text-neutral-600 hover:bg-neutral-50 hover:border-neutral-300 hover:text-neutral-900 flex items-center gap-1">
          {action.label}
          <ChevronRight className="h-3 w-3" />
        </Link>
      )}>
        {title}
      </CardHeader>
      <CardBody>{children}</CardBody>
    </Card>
  )
}

// Simple table for recent requests using Card component
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
    <Card>
      <CardHeader action={(
        <Link to={href} className="px-2 py-1 text-xs border border-neutral-200 rounded bg-white text-neutral-600 hover:bg-neutral-50 hover:border-neutral-300 hover:text-neutral-900 flex items-center gap-1">
          View all
          <ChevronRight className="h-3 w-3" />
        </Link>
      )}>
        {title}
      </CardHeader>
      <div className="divide-y divide-neutral-100">
        {children || (
          <p className="px-4 py-6 text-sm text-neutral-400 text-center">{emptyMessage}</p>
        )}
      </div>
    </Card>
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
  const normalizedStatus = status?.toLowerCase() || 'draft'
  
  return (
    <div className="px-4 py-3 flex items-center justify-between hover:bg-neutral-50">
      <div className="flex-1 min-w-0">
        <p className="text-sm font-medium text-neutral-900">{type}</p>
        <p className="text-xs text-neutral-500">{date} {detail && `• ${detail}`}</p>
      </div>
      <StatusBadge status={normalizedStatus}>
        {status?.replace(/_/g, ' ') || 'Unknown'}
      </StatusBadge>
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
    <div className="space-y-5">
      {/* Header */}
      <h1 className="text-lg font-semibold text-neutral-900">
        Welcome, {user?.name?.split(' ')[0] ?? 'there'}
      </h1>

      {/* Stats Grid */}
      <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
        <StatCard
          label="Leave Balance"
          value={`${stats?.leave.balance_days ?? 0} days`}
          sub={`${stats?.leave.pending_requests ?? 0} pending`}
          icon={Calendar}
          href="/me/leaves"
        />
        <StatCard
          label="Active Loans"
          value={stats?.loans.active_loans ?? 0}
          sub={`${stats?.loans.pending_approvals ?? 0} pending`}
          icon={PiggyBank}
          href="/me/loans"
        />
        <StatCard
          label="OT Hours"
          value={stats?.attendance.this_month.ot_hours ?? 0}
          sub="This month"
          icon={Timer}
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
          href="/self-service/payslips"
        />
      </div>

      {/* Attendance Summary */}
      <SectionCard title="Attendance This Month" action={{ label: 'View details', href: '/me/attendance' }}>
        <div className="grid grid-cols-4 gap-4">
          <Card className="bg-neutral-50">
            <div className="text-center p-3">
              <CheckCircle2 className="h-4 w-4 text-neutral-500 mx-auto mb-2" />
              <p className="text-xl font-semibold text-neutral-900">{stats?.attendance.this_month.present ?? 0}</p>
              <p className="text-xs text-neutral-600">Present</p>
            </div>
          </Card>
          <Card className="bg-neutral-50">
            <div className="text-center p-3">
              <AlertCircle className="h-4 w-4 text-neutral-500 mx-auto mb-2" />
              <p className="text-xl font-semibold text-neutral-900">{stats?.attendance.this_month.absent ?? 0}</p>
              <p className="text-xs text-neutral-600">Absent</p>
            </div>
          </Card>
          <Card className="bg-neutral-50">
            <div className="text-center p-3">
              <Clock className="h-4 w-4 text-neutral-500 mx-auto mb-2" />
              <p className="text-xl font-semibold text-neutral-900">{stats?.attendance.this_month.late ?? 0}</p>
              <p className="text-xs text-neutral-600">Late</p>
            </div>
          </Card>
          <Card className="bg-neutral-50">
            <div className="text-center p-3">
              <Timer className="h-4 w-4 text-neutral-500 mx-auto mb-2" />
              <p className="text-xl font-semibold text-neutral-900">{stats?.attendance.this_month.ot_hours ?? 0}</p>
              <p className="text-xs text-neutral-600">OT Hours</p>
            </div>
          </Card>
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
        <SectionCard title="Active Loans" action={{ label: 'View all', href: '/me/loans' }}>
          <div className="flex items-center gap-4 p-4 bg-neutral-50 border border-neutral-200 rounded-lg">
            <PiggyBank className="h-5 w-5 text-neutral-500" />
            <div className="flex-1">
              <p className="text-xs text-neutral-600">Total Outstanding Balance</p>
              <p className="text-xl font-semibold text-neutral-900">
                ₱{((stats?.loans.total_outstanding ?? 0) / 100).toLocaleString('en-PH', { 
                  minimumFractionDigits: 2,
                  maximumFractionDigits: 2
                })}
              </p>
            </div>
            <div className="text-right">
              <p className="text-xs text-neutral-600">Active Loans</p>
              <p className="text-xl font-semibold text-neutral-900">{stats?.loans.active_loans}</p>
            </div>
          </div>
        </SectionCard>
      )}

      {/* Quick Links */}
      <div>
        <h2 className="text-sm font-medium text-neutral-700 mb-3">Quick Access</h2>
        <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-3">
          <Link 
            to="/self-service/payslips" 
            className="flex items-center gap-3 p-3 border border-neutral-200 bg-white rounded-xl hover:border-neutral-300 shadow-subtle"
          >
            <FileText className="h-4 w-4 text-neutral-500" />
            <div className="flex-1 min-w-0">
              <p className="text-sm font-medium text-neutral-700">My Payslips</p>
              <p className="text-xs text-neutral-500 truncate">View and download payslips</p>
            </div>
          </Link>
          <Link 
            to="/me/leaves" 
            className="flex items-center gap-3 p-3 border border-neutral-200 bg-white rounded-xl hover:border-neutral-300 shadow-subtle"
          >
            <Calendar className="h-4 w-4 text-neutral-500" />
            <div className="flex-1 min-w-0">
              <p className="text-sm font-medium text-neutral-700">My Leaves</p>
              <p className="text-xs text-neutral-500 truncate">Apply for leave or check balance</p>
            </div>
          </Link>
          <Link 
            to="/me/loans" 
            className="flex items-center gap-3 p-3 border border-neutral-200 bg-white rounded-xl hover:border-neutral-300 shadow-subtle"
          >
            <Wallet className="h-4 w-4 text-neutral-500" />
            <div className="flex-1 min-w-0">
              <p className="text-sm font-medium text-neutral-700">My Loans</p>
              <p className="text-xs text-neutral-500 truncate">Track repayments and apply</p>
            </div>
          </Link>
          <Link 
            to="/me/overtime" 
            className="flex items-center gap-3 p-3 border border-neutral-200 bg-white rounded-xl hover:border-neutral-300 shadow-subtle"
          >
            <Timer className="h-4 w-4 text-neutral-500" />
            <div className="flex-1 min-w-0">
              <p className="text-sm font-medium text-neutral-700">My Overtime</p>
              <p className="text-xs text-neutral-500 truncate">View OT history and requests</p>
            </div>
          </Link>
          <Link 
            to="/me/attendance" 
            className="flex items-center gap-3 p-3 border border-neutral-200 bg-white rounded-xl hover:border-neutral-300 shadow-subtle"
          >
            <Clock className="h-4 w-4 text-neutral-500" />
            <div className="flex-1 min-w-0">
              <p className="text-sm font-medium text-neutral-700">My Attendance</p>
              <p className="text-xs text-neutral-500 truncate">View attendance records</p>
            </div>
          </Link>
          <Link 
            to="/me/profile" 
            className="flex items-center gap-3 p-3 border border-neutral-200 bg-white rounded-xl hover:border-neutral-300 shadow-subtle"
          >
            <UserCircle className="h-4 w-4 text-neutral-500" />
            <div className="flex-1 min-w-0">
              <p className="text-sm font-medium text-neutral-700">My Profile</p>
              <p className="text-xs text-neutral-500 truncate">Update personal information</p>
            </div>
          </Link>
        </div>
      </div>
    </div>
  )
}
