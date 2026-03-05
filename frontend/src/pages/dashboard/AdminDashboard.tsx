import { Link } from 'react-router-dom'
import { useAuth } from '@/hooks/useAuth'
import { useAdminDashboardStats } from '@/hooks/useDashboard'
import SkeletonLoader from '@/components/ui/SkeletonLoader'
import { 
  Users, 
  AlertCircle, 
  Shield,
  Activity,
  Lock,
  Database,
  Server,
  Settings,
  FileText,
  UserCheck,
  History,
  ChevronRight,
  CheckCircle2,
  Cpu
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
  value: string | number
  sub?: string
  icon: React.ComponentType<{ className?: string }>
  color?: 'blue' | 'amber' | 'green' | 'red' | 'gray' | 'purple' | 'indigo'
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
    amber: {
      bg: 'bg-amber-50',
      border: 'border-amber-100',
      iconBg: 'bg-amber-500',
      text: 'text-amber-700',
      subText: 'text-amber-600',
    },
    green: {
      bg: 'bg-green-50',
      border: 'border-green-100',
      iconBg: 'bg-green-500',
      text: 'text-green-700',
      subText: 'text-green-600',
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
    purple: {
      bg: 'bg-purple-50',
      border: 'border-purple-100',
      iconBg: 'bg-purple-500',
      text: 'text-purple-700',
      subText: 'text-purple-600',
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

// Status indicator
function StatusBadge({ 
  status, 
  label 
}: { 
  status: 'healthy' | 'warning' | 'critical' | 'unknown'
  label: string
}) {
  const statusMap = {
    healthy: {
      dot: 'bg-green-500',
      bg: 'bg-green-50',
      border: 'border-green-200',
      text: 'text-green-700',
    },
    warning: {
      dot: 'bg-amber-500',
      bg: 'bg-amber-50',
      border: 'border-amber-200',
      text: 'text-amber-700',
    },
    critical: {
      dot: 'bg-red-500',
      bg: 'bg-red-50',
      border: 'border-red-200',
      text: 'text-red-700',
    },
    unknown: {
      dot: 'bg-gray-400',
      bg: 'bg-gray-50',
      border: 'border-gray-200',
      text: 'text-gray-600',
    },
  }

  const colors = statusMap[status]

  return (
    <span className={`inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium ${colors.bg} ${colors.border} ${colors.text} border`}>
      <span className={`h-1.5 w-1.5 rounded-full ${colors.dot}`} />
      {label}
    </span>
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

export default function AdminDashboard() {
  const { user } = useAuth()
  const { data: stats, isLoading } = useAdminDashboardStats()

  if (isLoading) {
    return <SkeletonLoader rows={8} />
  }

  const lockedCount = stats?.system_health.locked_accounts ?? 0

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex items-start justify-between">
        <div>
          <h1 className="text-2xl font-bold text-gray-900">
            System Administration
          </h1>
          <p className="text-sm text-gray-500 mt-1">
            Welcome back, {user?.name?.split(' ')[0] ?? 'there'}. System health and user management overview.
          </p>
        </div>
        <div className="text-right">
          <p className="text-xs text-gray-500">{new Date().toLocaleDateString('en-PH', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' })}</p>
        </div>
      </div>

      {/* Alert for locked accounts */}
      {lockedCount > 0 && (
        <div className="bg-red-50 border border-red-200 rounded-xl p-5">
          <div className="flex items-center gap-4">
            <div className="h-14 w-14 rounded-xl bg-red-500 flex items-center justify-center shadow-sm">
              <Lock className="h-7 w-7 text-white" />
            </div>
            <div className="flex-1">
              <h2 className="text-lg font-bold text-red-800">
                {lockedCount} Account{lockedCount > 1 ? 's' : ''} Locked
              </h2>
              <p className="text-sm text-red-600 mt-1">
                User accounts have been locked due to failed login attempts and require administrator attention.
              </p>
            </div>
            <Link 
              to="/admin/users"
              className="px-4 py-2 bg-red-600 text-white text-sm font-semibold rounded-lg hover:bg-red-700 transition-colors shadow-sm"
            >
              Review Users
            </Link>
          </div>
        </div>
      )}

      {/* Stats Grid */}
      <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
        <StatCard
          label="Total Users"
          value={stats?.system_health.total_users ?? 0}
          sub="Registered accounts"
          icon={Users}
          color="blue"
          href="/admin/users"
        />
        <StatCard
          label="Currently Online"
          value={stats?.system_health.active_users ?? 0}
          sub="Active sessions"
          icon={UserCheck}
          color="green"
        />
        <StatCard
          label="Locked Accounts"
          value={lockedCount}
          sub={lockedCount > 0 ? 'Needs attention' : 'All clear'}
          icon={Lock}
          color={lockedCount > 0 ? 'red' : 'green'}
          href="/admin/users"
        />
        <StatCard
          label="Failed Logins Today"
          value={stats?.system_health.failed_logins_today ?? 0}
          sub="Authentication failures"
          icon={AlertCircle}
          color={stats?.system_health.failed_logins_today ? 'amber' : 'green'}
        />
      </div>

      {/* Recent Activity */}
      <SectionCard title="Recent Activity (Today)" icon={Activity} action={{ label: 'View audit logs', href: '/admin/audit-logs' }}>
        <div className="grid grid-cols-3 gap-4">
          <div className="p-5 rounded-xl bg-blue-50 border border-blue-100">
            <div className="flex items-center gap-2 mb-3">
              <div className="h-8 w-8 rounded-lg bg-blue-500 flex items-center justify-center">
                <UserCheck className="h-4 w-4 text-white" />
              </div>
              <span className="text-sm font-semibold text-blue-700">Logins</span>
            </div>
            <p className="text-2xl font-bold text-gray-900">{stats?.recent_activity.logins_today ?? 0}</p>
            <p className="text-xs text-gray-600 mt-1">Successful logins</p>
          </div>
          <div className="p-5 rounded-xl bg-purple-50 border border-purple-100">
            <div className="flex items-center gap-2 mb-3">
              <div className="h-8 w-8 rounded-lg bg-purple-500 flex items-center justify-center">
                <Shield className="h-4 w-4 text-white" />
              </div>
              <span className="text-sm font-semibold text-purple-700">Password Changes</span>
            </div>
            <p className="text-2xl font-bold text-gray-900">{stats?.recent_activity.password_changes ?? 0}</p>
            <p className="text-xs text-gray-600 mt-1">Updated passwords</p>
          </div>
          <div className="p-5 rounded-xl bg-green-50 border border-green-100">
            <div className="flex items-center gap-2 mb-3">
              <div className="h-8 w-8 rounded-lg bg-green-500 flex items-center justify-center">
                <Users className="h-4 w-4 text-white" />
              </div>
              <span className="text-sm font-semibold text-green-700">New Users</span>
            </div>
            <p className="text-2xl font-bold text-gray-900">{stats?.recent_activity.new_users ?? 0}</p>
            <p className="text-xs text-gray-600 mt-1">Accounts created</p>
          </div>
        </div>
      </SectionCard>

      {/* System Status */}
      <SectionCard title="System Status" icon={Server}>
        <div className="space-y-4">
          <div className="flex items-center justify-between p-4 rounded-xl bg-gray-50 border border-gray-200">
            <div className="flex items-center gap-3">
              <div className="h-10 w-10 rounded-xl bg-blue-100 text-blue-600 flex items-center justify-center">
                <Database className="h-5 w-5" />
              </div>
              <div>
                <p className="text-sm font-semibold text-gray-900">Last Backup</p>
                <p className="text-xs text-gray-500">
                  {stats?.system_status.last_backup 
                    ? new Date(stats.system_status.last_backup).toLocaleString()
                    : 'No backup recorded'
                  }
                </p>
              </div>
            </div>
            <StatusBadge 
              status={stats?.system_status.last_backup ? 'healthy' : 'warning'}
              label={stats?.system_status.last_backup ? 'OK' : 'Unknown'}
            />
          </div>

          <div className="flex items-center justify-between p-4 rounded-xl bg-gray-50 border border-gray-200">
            <div className="flex items-center gap-3">
              <div className="h-10 w-10 rounded-xl bg-purple-100 text-purple-600 flex items-center justify-center">
                <Cpu className="h-5 w-5" />
              </div>
              <div>
                <p className="text-sm font-semibold text-gray-900">Queue Workers</p>
                <p className="text-xs text-gray-500">
                  {stats?.system_status.queue_size ?? 0} jobs in queue
                </p>
              </div>
            </div>
            <StatusBadge 
              status={stats?.system_status.horizon_status === 'running' ? 'healthy' : 'warning'}
              label={stats?.system_status.horizon_status === 'running' ? 'Running' : 'Check Required'}
            />
          </div>

          <div className="flex items-center justify-between p-4 rounded-xl bg-gray-50 border border-gray-200">
            <div className="flex items-center gap-3">
              <div className="h-10 w-10 rounded-xl bg-green-100 text-green-600 flex items-center justify-center">
                <CheckCircle2 className="h-5 w-5" />
              </div>
              <div>
                <p className="text-sm font-semibold text-gray-900">System Health</p>
                <p className="text-xs text-gray-500">All systems operational</p>
              </div>
            </div>
            <StatusBadge status="healthy" label="Healthy" />
          </div>
        </div>
      </SectionCard>

      {/* Quick Links */}
      <div>
        <h2 className="text-sm font-semibold text-gray-700 mb-3">Quick Access</h2>
        <div className="grid grid-cols-2 md:grid-cols-4 gap-3">
          <Link 
            to="/admin/users"
            className="flex items-center gap-3 p-4 rounded-xl border border-gray-200 bg-white hover:border-blue-300 hover:shadow-md transition-all duration-200 group"
          >
            <div className="h-10 w-10 rounded-lg bg-blue-100 text-blue-600 flex items-center justify-center group-hover:bg-blue-500 group-hover:text-white transition-colors">
              <Users className="h-5 w-5" />
            </div>
            <span className="text-sm font-semibold text-gray-700 group-hover:text-gray-900">User Management</span>
          </Link>
          <Link 
            to="/admin/audit-logs"
            className="flex items-center gap-3 p-4 rounded-xl border border-gray-200 bg-white hover:border-blue-300 hover:shadow-md transition-all duration-200 group"
          >
            <div className="h-10 w-10 rounded-lg bg-purple-100 text-purple-600 flex items-center justify-center group-hover:bg-purple-500 group-hover:text-white transition-colors">
              <History className="h-5 w-5" />
            </div>
            <span className="text-sm font-semibold text-gray-700 group-hover:text-gray-900">Audit Logs</span>
          </Link>
          <Link 
            to="/admin/settings"
            className="flex items-center gap-3 p-4 rounded-xl border border-gray-200 bg-white hover:border-blue-300 hover:shadow-md transition-all duration-200 group"
          >
            <div className="h-10 w-10 rounded-lg bg-amber-100 text-amber-600 flex items-center justify-center group-hover:bg-amber-500 group-hover:text-white transition-colors">
              <Settings className="h-5 w-5" />
            </div>
            <span className="text-sm font-semibold text-gray-700 group-hover:text-gray-900">System Settings</span>
          </Link>
          <Link 
            to="/admin/reference-tables"
            className="flex items-center gap-3 p-4 rounded-xl border border-gray-200 bg-white hover:border-blue-300 hover:shadow-md transition-all duration-200 group"
          >
            <div className="h-10 w-10 rounded-lg bg-green-100 text-green-600 flex items-center justify-center group-hover:bg-green-500 group-hover:text-white transition-colors">
              <FileText className="h-5 w-5" />
            </div>
            <span className="text-sm font-semibold text-gray-700 group-hover:text-gray-900">Reference Tables</span>
          </Link>
        </div>
      </div>
    </div>
  )
}
