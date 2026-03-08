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

// Simple stat card - no decorative styling
function StatCard({
  label,
  value,
  sub,
  icon: Icon,
  href,
}: {
  label: string
  value: string | number
  sub?: string
  icon: React.ComponentType<{ className?: string }>
  href?: string
}) {
  const content = (
    <div className="bg-white border border-neutral-200 rounded p-5">
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
  )

  if (href) {
    return <Link to={href} className="block">{content}</Link>
  }
  return content
}

// Status indicator - minimal
function StatusBadge({ 
  status, 
  label 
}: { 
  status: 'healthy' | 'warning' | 'critical' | 'unknown'
  label: string
}) {
  const dotColor = {
    healthy: 'bg-green-500',
    warning: 'bg-amber-500',
    critical: 'bg-red-500',
    unknown: 'bg-neutral-400',
  }[status]

  return (
    <span className="inline-flex items-center gap-1.5 text-sm text-neutral-600">
      <span className={`h-1.5 w-1.5 rounded-full ${dotColor}`} />
      {label}
    </span>
  )
}

// Section card with header - minimal styling
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
    <div className="bg-white border border-neutral-200 rounded">
      <div className="px-4 py-3 border-b border-neutral-200 flex items-center justify-between">
        <h2 className="text-sm font-medium text-neutral-900">{title}</h2>
        {action && (
          <Link to={action.href} className="text-xs text-neutral-600 hover:text-neutral-900 flex items-center gap-1">
            {action.label}
            <ChevronRight className="h-3 w-3" />
          </Link>
        )}
      </div>
      <div className="p-4">
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
      <h1 className="text-lg font-semibold text-neutral-900 mb-6">
        System Administration
      </h1>

      {/* Alert for locked accounts */}
      {lockedCount > 0 && (
        <div className="bg-white border border-red-200 rounded p-4">
          <div className="flex items-center gap-4">
            <Lock className="h-5 w-5 text-red-600" />
            <div className="flex-1">
              <h2 className="text-sm font-semibold text-neutral-900">
                {lockedCount} Account{lockedCount > 1 ? 's' : ''} Locked
              </h2>
              <p className="text-xs text-neutral-600 mt-1">
                User accounts have been locked due to failed login attempts and require administrator attention.
              </p>
            </div>
            <Link 
              to="/admin/users"
              className="px-3 py-1.5 bg-neutral-900 text-white text-xs font-medium rounded hover:bg-neutral-800"
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
          href="/admin/users"
        />
        <StatCard
          label="Currently Online"
          value={stats?.system_health.active_users ?? 0}
          sub="Active sessions"
          icon={UserCheck}
        />
        <StatCard
          label="Locked Accounts"
          value={lockedCount}
          sub={lockedCount > 0 ? 'Needs attention' : 'All clear'}
          icon={Lock}
          href="/admin/users"
        />
        <StatCard
          label="Failed Logins Today"
          value={stats?.system_health.failed_logins_today ?? 0}
          sub="Authentication failures"
          icon={AlertCircle}
        />
      </div>

      {/* Recent Activity */}
      <SectionCard title="Recent Activity (Today)" action={{ label: 'View audit logs', href: '/admin/audit-logs' }}>
        <div className="grid grid-cols-3 gap-4">
          <div className="p-4 bg-neutral-50 border border-neutral-200 rounded">
            <div className="flex items-center gap-2 mb-2">
              <UserCheck className="h-4 w-4 text-neutral-500" />
              <span className="text-sm font-medium text-neutral-700">Logins</span>
            </div>
            <p className="text-xl font-semibold text-neutral-900">{stats?.recent_activity.logins_today ?? 0}</p>
            <p className="text-xs text-neutral-500 mt-1">Successful logins</p>
          </div>
          <div className="p-4 bg-neutral-50 border border-neutral-200 rounded">
            <div className="flex items-center gap-2 mb-2">
              <Shield className="h-4 w-4 text-neutral-500" />
              <span className="text-sm font-medium text-neutral-700">Password Changes</span>
            </div>
            <p className="text-xl font-semibold text-neutral-900">{stats?.recent_activity.password_changes ?? 0}</p>
            <p className="text-xs text-neutral-500 mt-1">Updated passwords</p>
          </div>
          <div className="p-4 bg-neutral-50 border border-neutral-200 rounded">
            <div className="flex items-center gap-2 mb-2">
              <Users className="h-4 w-4 text-neutral-500" />
              <span className="text-sm font-medium text-neutral-700">New Users</span>
            </div>
            <p className="text-xl font-semibold text-neutral-900">{stats?.recent_activity.new_users ?? 0}</p>
            <p className="text-xs text-neutral-500 mt-1">Accounts created</p>
          </div>
        </div>
      </SectionCard>

      {/* System Status */}
      <SectionCard title="System Status">
        <div className="space-y-3">
          <div className="flex items-center justify-between p-3 bg-neutral-50 border border-neutral-200 rounded">
            <div className="flex items-center gap-3">
              <Database className="h-4 w-4 text-neutral-500" />
              <div>
                <p className="text-sm font-medium text-neutral-900">Last Backup</p>
                <p className="text-xs text-neutral-500">
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

          <div className="flex items-center justify-between p-3 bg-neutral-50 border border-neutral-200 rounded">
            <div className="flex items-center gap-3">
              <Cpu className="h-4 w-4 text-neutral-500" />
              <div>
                <p className="text-sm font-medium text-neutral-900">Queue Workers</p>
                <p className="text-xs text-neutral-500">
                  {stats?.system_status.queue_size ?? 0} jobs in queue
                </p>
              </div>
            </div>
            <StatusBadge 
              status={stats?.system_status.horizon_status === 'running' ? 'healthy' : 'warning'}
              label={stats?.system_status.horizon_status === 'running' ? 'Running' : 'Check Required'}
            />
          </div>

          <div className="flex items-center justify-between p-3 bg-neutral-50 border border-neutral-200 rounded">
            <div className="flex items-center gap-3">
              <CheckCircle2 className="h-4 w-4 text-neutral-500" />
              <div>
                <p className="text-sm font-medium text-neutral-900">System Health</p>
                <p className="text-xs text-neutral-500">All systems operational</p>
              </div>
            </div>
            <StatusBadge status="healthy" label="Healthy" />
          </div>
        </div>
      </SectionCard>

      {/* Quick Links */}
      <div>
        <h2 className="text-sm font-medium text-neutral-700 mb-3">Quick Access</h2>
        <div className="grid grid-cols-2 md:grid-cols-4 gap-3">
          <Link 
            to="/admin/users"
            className="flex items-center gap-3 p-3 border border-neutral-200 bg-white rounded hover:border-neutral-300"
          >
            <Users className="h-4 w-4 text-neutral-500" />
            <span className="text-sm font-medium text-neutral-700">User Management</span>
          </Link>
          <Link 
            to="/admin/audit-logs"
            className="flex items-center gap-3 p-3 border border-neutral-200 bg-white rounded hover:border-neutral-300"
          >
            <History className="h-4 w-4 text-neutral-500" />
            <span className="text-sm font-medium text-neutral-700">Audit Logs</span>
          </Link>
          <Link 
            to="/admin/settings"
            className="flex items-center gap-3 p-3 border border-neutral-200 bg-white rounded hover:border-neutral-300"
          >
            <Settings className="h-4 w-4 text-neutral-500" />
            <span className="text-sm font-medium text-neutral-700">System Settings</span>
          </Link>
          <Link 
            to="/admin/reference-tables"
            className="flex items-center gap-3 p-3 border border-neutral-200 bg-white rounded hover:border-neutral-300"
          >
            <FileText className="h-4 w-4 text-neutral-500" />
            <span className="text-sm font-medium text-neutral-700">Reference Tables</span>
          </Link>
        </div>
      </div>
    </div>
  )
}
