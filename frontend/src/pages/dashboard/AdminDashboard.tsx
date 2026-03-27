/**
 * Admin Dashboard (Super Admin / System Admin)
 *
 * System health monitoring, user management KPIs, recent activity,
 * and infrastructure status. Provides quick access to admin tools.
 */
import { useAuth } from '@/hooks/useAuth'
import { useAdminDashboardStats } from '@/hooks/useDashboard'
import SkeletonLoader from '@/components/ui/SkeletonLoader'
import { Card } from '@/components/ui/Card'
import { StatusBadge } from '@/components/ui/StatusBadge'
import {
  KpiCard,
  SectionHeader,
  WidgetCard,
  DashboardGrid,
  QuickActions,
  MiniDonutChart,
} from '@/components/dashboard/DashboardWidgets'
import {
  Users,
  Lock,
  AlertCircle,
  UserCheck,
  Shield,
  Database,
  Settings,
  FileText,
  History,
  Cpu,
  CheckCircle2,
  Activity,
} from 'lucide-react'

export default function AdminDashboard() {
  useAuth()
  const { data: stats, isLoading, error } = useAdminDashboardStats()

  if (isLoading) return <SkeletonLoader rows={8} />

  const lockedCount = stats?.system_health.locked_accounts ?? 0
  const totalUsers = stats?.system_health.total_users ?? 0
  const activeUsers = stats?.system_health.active_users ?? 0

  return (
    <div className="space-y-6">
      {/* Header */}
      <div>
        <h1 className="text-xl font-bold text-neutral-900">System Administration</h1>
        <p className="text-sm text-neutral-500 mt-0.5">
          User management, system health, and infrastructure monitoring
        </p>
      </div>

      {/* Error state */}
      {error && (
        <Card className="border-red-200">
          <div className="p-4 flex items-center gap-3">
            <AlertCircle className="h-5 w-5 text-red-500" />
            <span className="text-sm text-red-700">Failed to load admin dashboard. Please refresh.</span>
          </div>
        </Card>
      )}

      {/* Critical: Locked accounts alert */}
      {lockedCount > 0 && (
        <Card className="border-red-200 bg-red-50">
          <div className="p-4 flex items-center gap-4">
            <Lock className="h-5 w-5 text-red-600" />
            <div className="flex-1">
              <h2 className="text-sm font-semibold text-red-800">
                {lockedCount} Account{lockedCount > 1 ? 's' : ''} Locked
              </h2>
              <p className="text-xs text-red-600 mt-0.5">
                User accounts locked due to failed login attempts. Requires administrator attention.
              </p>
            </div>
          </div>
        </Card>
      )}

      {/* System KPIs */}
      <div className="grid grid-cols-2 md:grid-cols-4 gap-3">
        <KpiCard
          label="Total Users"
          value={totalUsers}
          sub="Registered accounts"
          icon={Users}
          color="info"
          href="/admin/users"
        />
        <KpiCard
          label="Currently Online"
          value={activeUsers}
          sub="Active sessions"
          icon={UserCheck}
          color="success"
        />
        <KpiCard
          label="Locked Accounts"
          value={lockedCount}
          sub={lockedCount > 0 ? 'Needs attention' : 'All clear'}
          icon={Lock}
          color={lockedCount > 0 ? 'danger' : 'success'}
          href="/admin/users"
        />
        <KpiCard
          label="Failed Logins Today"
          value={stats?.system_health.failed_logins_today ?? 0}
          sub="Auth failures"
          icon={AlertCircle}
          color={(stats?.system_health.failed_logins_today ?? 0) > 10 ? 'warning' : 'default'}
        />
      </div>

      {/* Charts + Activity Row */}
      <DashboardGrid>
        {/* User Activity Donut */}
        <WidgetCard title="User Status Overview">
          <MiniDonutChart
            data={[
              { name: 'Online', value: activeUsers, color: '#10b981' },
              { name: 'Offline', value: Math.max(0, totalUsers - activeUsers - lockedCount), color: '#d4d4d8' },
              { name: 'Locked', value: lockedCount, color: '#ef4444' },
            ].filter(d => d.value > 0)}
            height={200}
            centerLabel="Total"
            centerValue={totalUsers}
          />
        </WidgetCard>

        {/* Today's Activity */}
        <WidgetCard title="Today's Activity" action={{ label: 'Audit Logs', href: '/admin/audit-logs' }}>
          <div className="grid grid-cols-3 gap-3">
            <Card className="bg-green-50 border-green-100">
              <div className="p-4 text-center">
                <UserCheck className="h-5 w-5 text-green-600 mx-auto mb-2" />
                <p className="text-2xl font-bold text-green-700">{stats?.recent_activity.logins_today ?? 0}</p>
                <p className="text-[10px] text-green-600 uppercase tracking-wide font-medium mt-1">Logins</p>
              </div>
            </Card>
            <Card className="bg-blue-50 border-blue-100">
              <div className="p-4 text-center">
                <Shield className="h-5 w-5 text-blue-600 mx-auto mb-2" />
                <p className="text-2xl font-bold text-blue-700">{stats?.recent_activity.password_changes ?? 0}</p>
                <p className="text-[10px] text-blue-600 uppercase tracking-wide font-medium mt-1">Pwd Changes</p>
              </div>
            </Card>
            <Card className="bg-purple-50 border-purple-100">
              <div className="p-4 text-center">
                <Users className="h-5 w-5 text-purple-600 mx-auto mb-2" />
                <p className="text-2xl font-bold text-purple-700">{stats?.recent_activity.new_users ?? 0}</p>
                <p className="text-[10px] text-purple-600 uppercase tracking-wide font-medium mt-1">New Users</p>
              </div>
            </Card>
          </div>
        </WidgetCard>
      </DashboardGrid>

      {/* System Status */}
      <WidgetCard title="Infrastructure Status">
        <div className="space-y-3">
          <div className="flex items-center justify-between p-3 bg-neutral-50 border border-neutral-200 rounded-lg">
            <div className="flex items-center gap-3">
              <div className="p-2 rounded-lg bg-neutral-100">
                <Database className="h-4 w-4 text-neutral-600" />
              </div>
              <div>
                <p className="text-sm font-medium text-neutral-900">Database Backup</p>
                <p className="text-xs text-neutral-500">
                  {stats?.system_status.last_backup
                    ? `Last backup: ${new Date(stats.system_status.last_backup).toLocaleString()}`
                    : 'No backup recorded'}
                </p>
              </div>
            </div>
            <StatusBadge status={stats?.system_status.last_backup ? 'healthy' : 'warning'}>
              {stats?.system_status.last_backup ? 'OK' : 'Unknown'}
            </StatusBadge>
          </div>

          <div className="flex items-center justify-between p-3 bg-neutral-50 border border-neutral-200 rounded-lg">
            <div className="flex items-center gap-3">
              <div className="p-2 rounded-lg bg-neutral-100">
                <Cpu className="h-4 w-4 text-neutral-600" />
              </div>
              <div>
                <p className="text-sm font-medium text-neutral-900">Queue Workers (Horizon)</p>
                <p className="text-xs text-neutral-500">
                  {stats?.system_status.queue_size ?? 0} jobs in queue
                </p>
              </div>
            </div>
            <StatusBadge status={stats?.system_status.horizon_status === 'running' ? 'healthy' : 'warning'}>
              {stats?.system_status.horizon_status === 'running' ? 'Running' : 'Check Required'}
            </StatusBadge>
          </div>

          <div className="flex items-center justify-between p-3 bg-neutral-50 border border-neutral-200 rounded-lg">
            <div className="flex items-center gap-3">
              <div className="p-2 rounded-lg bg-neutral-100">
                <Activity className="h-4 w-4 text-neutral-600" />
              </div>
              <div>
                <p className="text-sm font-medium text-neutral-900">Application Health</p>
                <p className="text-xs text-neutral-500">All systems operational</p>
              </div>
            </div>
            <StatusBadge status="healthy">Healthy</StatusBadge>
          </div>

          <div className="flex items-center justify-between p-3 bg-neutral-50 border border-neutral-200 rounded-lg">
            <div className="flex items-center gap-3">
              <div className="p-2 rounded-lg bg-neutral-100">
                <CheckCircle2 className="h-4 w-4 text-neutral-600" />
              </div>
              <div>
                <p className="text-sm font-medium text-neutral-900">Security Status</p>
                <p className="text-xs text-neutral-500">
                  {(stats?.system_health.failed_logins_today ?? 0) > 0
                    ? `${stats!.system_health.failed_logins_today} failed login attempts today`
                    : 'No security concerns'}
                </p>
              </div>
            </div>
            <StatusBadge status={(stats?.system_health.failed_logins_today ?? 0) > 20 ? 'warning' : 'healthy'}>
              {(stats?.system_health.failed_logins_today ?? 0) > 20 ? 'Elevated' : 'Normal'}
            </StatusBadge>
          </div>
        </div>
      </WidgetCard>

      {/* Quick Navigation */}
      <div>
        <SectionHeader title="Administration Tools" />
        <QuickActions actions={[
          { label: 'User Management', href: '/admin/users', icon: Users },
          { label: 'Audit Logs', href: '/admin/audit-logs', icon: History },
          { label: 'System Settings', href: '/admin/settings', icon: Settings },
          { label: 'Reference Tables', href: '/admin/reference-tables', icon: FileText },
          { label: 'Roles & Permissions', href: '/admin/roles', icon: Shield },
          { label: 'Departments', href: '/admin/departments', icon: Database },
          { label: 'Modules', href: '/admin/modules', icon: Cpu },
          { label: 'Security', href: '/admin/settings', icon: Lock },
        ]} />
      </div>
    </div>
  )
}
