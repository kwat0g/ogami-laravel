import { Link } from 'react-router-dom'
import { useLeaveBalances } from '@/hooks/useLeave'
import { useAttendanceLogs } from '@/hooks/useAttendance'
import StatusBadge from '@/components/ui/StatusBadge'
import CurrencyAmount from '@/components/ui/CurrencyAmount'
import {
  User,
  Mail,
  MapPin,
  Building2,
  Briefcase,
  Wallet,
  ShieldCheck,
  Calendar,
  Clock,
  UserCircle,
  BadgeCheck,
  Users,
  Plus,
  FileText,
  ArrowLeft,
} from 'lucide-react'
import type { Employee } from '@/types/hr'

// ============================================================================
// Types
// ============================================================================

interface EmployeeProfileViewProps {
  employee: Employee
  viewContext: 'hr' | 'team'
  onBack?: () => void
  backLabel?: string
  backTo?: string
  actions?: React.ReactNode
  showStats?: boolean
}

// ============================================================================
// Helper Functions
// ============================================================================

function statusLabel(s: string | undefined | null) {
  if (!s) return '—'
  return s.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase())
}

function formatGender(gender: string | undefined | null) {
  if (!gender) return '—'
  const map: Record<string, string> = {
    male: 'Male',
    female: 'Female',
    other: 'Other',
  }
  return map[gender] || gender
}

function formatCivilStatus(status: string | null) {
  if (!status) return '—'
  return status.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase())
}

function formatDate(dateString: string | null | undefined): string {
  if (!dateString) return '—'
  try {
    return new Date(dateString).toLocaleDateString('en-PH', {
      year: 'numeric',
      month: 'long',
      day: 'numeric',
    })
  } catch {
    return dateString
  }
}

function calculateAge(birthDate: string | null | undefined): number | null {
  if (!birthDate) return null
  try {
    const birth = new Date(birthDate)
    const today = new Date()
    let age = today.getFullYear() - birth.getFullYear()
    const monthDiff = today.getMonth() - birth.getMonth()
    if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birth.getDate())) {
      age--
    }
    return age
  } catch {
    return null
  }
}

function formatDuration(startDate: string | null | undefined): string {
  if (!startDate) return '—'
  try {
    const start = new Date(startDate)
    const now = new Date()
    const years = now.getFullYear() - start.getFullYear()
    const months = now.getMonth() - start.getMonth()
    const totalMonths = years * 12 + months

    if (totalMonths < 1) return 'Less than 1 month'
    if (totalMonths < 12) return `${totalMonths} month${totalMonths > 1 ? 's' : ''}`

    const y = Math.floor(totalMonths / 12)
    const m = totalMonths % 12
    if (m === 0) return `${y} year${y > 1 ? 's' : ''}`
    return `${y} year${y > 1 ? 's' : ''}, ${m} month${m > 1 ? 's' : ''}`
  } catch {
    return '—'
  }
}

// ============================================================================
// Sub-Components
// ============================================================================

function InfoCard({
  title,
  icon: Icon,
  children,
  className = '',
  action,
  emptyState,
}: {
  title: string
  icon: React.ElementType
  children: React.ReactNode
  className?: string
  action?: React.ReactNode
  emptyState?: { message: string; action?: React.ReactNode }
}) {
  const hasContent = children && (Array.isArray(children) ? children.length > 0 : true)

  return (
    <section
      className={`bg-white border border-neutral-200 rounded-xl overflow-hidden ${className}`}
    >
      <div className="px-5 py-3 bg-neutral-50 border-b border-neutral-100 flex items-center justify-between">
        <div className="flex items-center gap-2">
          <Icon className="h-4 w-4 text-neutral-500" />
          <h2 className="text-sm font-medium text-neutral-700">{title}</h2>
        </div>
        {action && <div>{action}</div>}
      </div>
      <div className="p-5">
        {hasContent ? (
          children
        ) : emptyState ? (
          <div className="text-center py-8">
            <p className="text-sm text-neutral-400 mb-3">{emptyState.message}</p>
            {emptyState.action}
          </div>
        ) : (
          <div className="flex flex-col items-center justify-center py-8 text-neutral-300">
            <Icon className="h-10 w-10 mb-2" />
            <p className="text-sm">No data available</p>
          </div>
        )}
      </div>
    </section>
  )
}

function InfoRow({
  label,
  value,
  highlight = false,
  icon,
}: {
  label: string
  value: React.ReactNode
  highlight?: boolean
  icon?: React.ElementType
}) {
  const Icon = icon
  const hasValue = value && value !== '—' && value !== null && value !== undefined

  return (
    <div className="flex justify-between items-start py-2.5 border-b border-neutral-100 last:border-0">
      <div className="flex items-center gap-1.5">
        {Icon && <Icon className="h-3.5 w-3.5 text-neutral-400" />}
        <span className="text-xs text-neutral-500">{label}</span>
      </div>
      <span
        className={`text-sm text-right ${highlight ? 'font-medium text-neutral-900' : 'text-neutral-700'} ${!hasValue ? 'text-neutral-400 italic' : ''}`}
      >
        {value || '—'}
      </span>
    </div>
  )
}

function StatCard({
  label,
  value,
  subtext,
}: {
  label: string
  value: string | number
  subtext?: string
}) {
  return (
    <div className="p-4 border border-neutral-200 rounded">
      <div>
        <p className="text-xs font-medium text-neutral-500">{label}</p>
        <p className="text-lg font-semibold mt-1 text-neutral-900">{value}</p>
        {subtext && <p className="text-xs mt-1 text-neutral-400">{subtext}</p>}
      </div>
    </div>
  )
}

// ============================================================================
// Main Component
// ============================================================================

export default function EmployeeProfileView({
  employee,
  viewContext,
  onBack,
  backLabel = 'Back',
  backTo,
  actions,
  showStats = true,
}: EmployeeProfileViewProps) {
  const isHR = viewContext === 'hr'

  // Fetch leave balances and attendance for both views
  // Date range: 1st of month to last day of month
  const now = new Date()
  const firstDayOfMonth = new Date(now.getFullYear(), now.getMonth(), 1).toISOString().slice(0, 10)
  const lastDayOfMonth = new Date(now.getFullYear(), now.getMonth() + 1, 0)
    .toISOString()
    .slice(0, 10)

  const { data: leaveBalancesResponse } = useLeaveBalances({
    employee_id: employee.id,
    year: now.getFullYear(),
    per_page: 50,
  })
  // The balances endpoint returns paginated EmployeeLeaveBalance rows; since we
  // filter by employee_id there will be at most one entry.
  const employeeLeaveData = leaveBalancesResponse?.data?.[0]
  // Exclude OTH (Others) — discretionary type with no fixed balance row
  const leaveBalanceItems = (employeeLeaveData?.balances ?? []).filter(
    (b) => b.leave_type_code !== 'OTH',
  )
  const { data: attendanceData } = useAttendanceLogs({
    employee_id: employee.id,
    date_from: firstDayOfMonth,
    date_to: lastDayOfMonth,
    per_page: 31,
  })

  // Get initials for avatar
  const initials = employee.full_name
    .split(' ')
    .map((n) => n[0])
    .join('')
    .toUpperCase()
    .slice(0, 2)

  const age = calculateAge(employee.date_of_birth)
  const tenure = formatDuration(employee.date_hired)

  // Calculate attendance stats
  const attendanceLogs = attendanceData?.data || []
  const presentDays = attendanceLogs.filter((log) => log.is_present).length
  const absentDays = attendanceLogs.filter((log) => log.is_absent).length
  const lateDays = attendanceLogs.filter((log) => (log.late_minutes || 0) > 0).length

  // Calculate total leave balance from the employee's balance entry
  const totalLeaveBalance = employeeLeaveData?.total_balance ?? 0

  // Check if employee has supervisor
  const hasSupervisor = !!employee.supervisor?.full_name

  return (
    <div className="max-w-7xl space-y-6">
      {/* Back Link */}
      <div>
        {backTo ? (
          <Link
            to={backTo}
            className="inline-flex items-center gap-1.5 text-sm text-neutral-500 hover:text-neutral-700 transition-colors"
          >
            <ArrowLeft className="h-4 w-4" />
            {backLabel}
          </Link>
        ) : onBack ? (
          <button
            onClick={onBack}
            className="inline-flex items-center gap-1.5 text-sm text-neutral-500 hover:text-neutral-700 transition-colors"
          >
            <ArrowLeft className="h-4 w-4" />
            {backLabel}
          </button>
        ) : null}
      </div>

      {/* Profile Header */}
      <div className="bg-white border border-neutral-200 rounded-lg p-6">
        <div className="flex flex-col md:flex-row md:items-center gap-6">
          {/* Avatar */}
          <div className="flex-shrink-0">
            <div className="w-24 h-24 bg-neutral-100 flex items-center justify-center text-neutral-600 text-3xl font-medium">
              {initials}
            </div>
          </div>

          {/* Info */}
          <div className="flex-1 min-w-0">
            <div className="flex flex-wrap items-center gap-3">
              <h1 className="text-2xl font-semibold text-neutral-900">{employee.full_name}</h1>
              <StatusBadge status={employee.employment_status}>
                {employee.employment_status?.replace('_', ' ') || 'Unknown'}
              </StatusBadge>
            </div>

            <div className="flex flex-wrap items-center gap-x-4 gap-y-2 mt-3 text-sm">
              <span className="font-mono text-neutral-500 bg-neutral-100 px-2 py-0.5 rounded">
                {employee.employee_code}
              </span>
              {employee.department && (
                <span className="text-neutral-600 flex items-center gap-1">
                  <Building2 className="h-4 w-4 text-neutral-400" />
                  {employee.department.name}
                </span>
              )}
              {employee.position && (
                <span className="text-neutral-600 flex items-center gap-1">
                  <Briefcase className="h-4 w-4 text-neutral-400" />
                  {employee.position.title}
                </span>
              )}
              <span className="text-neutral-500 flex items-center gap-1">
                <Calendar className="h-4 w-4 text-neutral-400" />
                {tenure} tenure
              </span>
            </div>
          </div>

          {/* Quick Stats */}
          {showStats && (
            <div className="flex gap-3">
              <div className="text-center px-4 py-2 border border-neutral-200 rounded">
                <p className="text-lg font-semibold text-neutral-900">{presentDays}</p>
                <p className="text-xs text-neutral-500">Present</p>
              </div>
              <div className="text-center px-4 py-2 border border-neutral-200 rounded">
                <p className="text-lg font-semibold text-neutral-900">{lateDays}</p>
                <p className="text-xs text-neutral-500">Late</p>
              </div>
              <div className="text-center px-4 py-2 border border-neutral-200 rounded">
                <p className="text-lg font-semibold text-neutral-900">{totalLeaveBalance}</p>
                <p className="text-xs text-neutral-500">Leave Days</p>
              </div>
            </div>
          )}

          {/* Custom Actions */}
          {actions && <div className="flex flex-wrap gap-2">{actions}</div>}
        </div>
      </div>

      {/* Stats Row */}
      {showStats && (
        <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
          <StatCard label="Present This Month" value={presentDays} subtext="Working days" />
          <StatCard label="Late Arrivals" value={lateDays} subtext="This month" />
          <StatCard label="Absences" value={absentDays} subtext="This month" />
          <StatCard label="Leave Balance" value={totalLeaveBalance} subtext="Days available" />
        </div>
      )}

      {/* Main Info Grid */}
      <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {/* Personal Information */}
        <InfoCard title="Personal Information" icon={User}>
          <div className="space-y-1">
            <InfoRow label="Full Name" value={employee.full_name} highlight />
            <InfoRow
              label="Date of Birth"
              value={
                age
                  ? `${formatDate(employee.date_of_birth)} (${age} years old)`
                  : formatDate(employee.date_of_birth)
              }
            />
            <InfoRow label="Gender" value={formatGender(employee.gender)} />
            <InfoRow label="Civil Status" value={formatCivilStatus(employee.civil_status)} />
            <InfoRow label="Citizenship" value={employee.citizenship} />
          </div>
        </InfoCard>

        {/* Contact Details */}
        <InfoCard title="Contact Details" icon={Mail}>
          <div className="space-y-1">
            <div className="flex justify-between items-start py-2.5 border-b border-neutral-100">
              <span className="text-xs text-neutral-500">Personal Email</span>
              {employee.personal_email ? (
                <a
                  href={`mailto:${employee.personal_email}`}
                  className="text-sm text-neutral-700 hover:underline"
                >
                  {employee.personal_email}
                </a>
              ) : (
                <span className="text-sm text-neutral-400 italic">Not provided</span>
              )}
            </div>
            <div className="flex justify-between items-start py-2.5 border-b border-neutral-100">
              <span className="text-xs text-neutral-500">Mobile</span>
              {employee.personal_phone ? (
                <a
                  href={`tel:${employee.personal_phone}`}
                  className="text-sm text-neutral-700 hover:underline"
                >
                  {employee.personal_phone}
                </a>
              ) : (
                <span className="text-sm text-neutral-400 italic">Not provided</span>
              )}
            </div>
            <InfoRow
              label="Present Address"
              value={
                employee.present_address ? (
                  <span className="flex items-start gap-1">
                    <MapPin className="h-3.5 w-3.5 text-neutral-400 mt-0.5 flex-shrink-0" />
                    <span className="line-clamp-2">{employee.present_address}</span>
                  </span>
                ) : (
                  'Not provided'
                )
              }
            />
            <InfoRow
              label="Permanent Address"
              value={
                employee.permanent_address ? (
                  <span className="flex items-start gap-1">
                    <MapPin className="h-3.5 w-3.5 text-neutral-400 mt-0.5 flex-shrink-0" />
                    <span className="line-clamp-2">{employee.permanent_address}</span>
                  </span>
                ) : (
                  'Not provided'
                )
              }
            />
          </div>
        </InfoCard>

        {/* Employment Details */}
        <InfoCard title="Employment Details" icon={Briefcase}>
          <div className="space-y-1">
            <InfoRow label="Department" value={employee.department?.name} highlight />
            <InfoRow label="Position" value={employee.position?.title} highlight />
            <InfoRow
              label="Employment Type"
              value={employee.employment_type ? statusLabel(employee.employment_type) : null}
            />
            <InfoRow
              label="Pay Basis"
              value={employee.pay_basis === 'monthly' ? 'Monthly (Fixed)' : 'Daily Rate'}
            />
            <InfoRow label="Date Hired" value={formatDate(employee.date_hired)} />
            <InfoRow label="Years of Service" value={tenure} />
            {employee.current_shift && (
              <InfoRow
                label="Shift Schedule"
                value={employee.current_shift.shift_name ?? 'Unknown'}
              />
            )}
            <InfoRow label="Regularization Date" value={formatDate(employee.regularization_date)} />
            {employee.separation_date && (
              <InfoRow label="Separation Date" value={formatDate(employee.separation_date)} />
            )}
          </div>
        </InfoCard>

        {/* Compensation */}
        <InfoCard
          title="Compensation & Benefits"
          icon={Wallet}
          emptyState={{
            message: employee.salary_grade
              ? 'Compensation details loading...'
              : 'Salary grade not assigned',
            action: !employee.salary_grade && isHR && (
              <Link
                to={`/hr/employees/${employee.ulid}/edit`}
                className="text-sm text-neutral-600 hover:underline inline-flex items-center gap-1"
              >
                <Plus className="h-4 w-4" />
                Assign Salary Grade
              </Link>
            ),
          }}
        >
          {employee.salary_grade && (
            <div className="space-y-1">
              <InfoRow label="Salary Grade" value={employee.salary_grade.code} />
              <InfoRow label="Step" value={employee.salary_grade.name} />
              <div className="flex justify-between items-start py-2.5 border-b border-neutral-100">
                <span className="text-xs text-neutral-500">Basic Monthly</span>
                <span className="text-sm font-medium text-neutral-900">
                  {employee.basic_monthly_rate ? (
                    <CurrencyAmount centavos={employee.basic_monthly_rate} />
                  ) : (
                    '—'
                  )}
                </span>
              </div>
              <div className="flex justify-between items-start py-2.5 border-b border-neutral-100">
                <span className="text-xs text-neutral-500">Basic Daily</span>
                <span className="text-sm text-neutral-700">
                  {employee.daily_rate ? <CurrencyAmount centavos={employee.daily_rate} /> : '—'}
                </span>
              </div>
              <div className="flex justify-between items-start py-2.5 border-b border-neutral-100">
                <span className="text-xs text-neutral-500">Basic Hourly</span>
                <span className="text-sm text-neutral-700">
                  {employee.hourly_rate ? <CurrencyAmount centavos={employee.hourly_rate} /> : '—'}
                </span>
              </div>
            </div>
          )}
        </InfoCard>

        {/* Government IDs & Bank Information - Combined */}
        <InfoCard
          title="Government IDs & Bank Information"
          icon={ShieldCheck}
          emptyState={{
            message: 'No government or bank information recorded',
            action: isHR && (
              <Link
                to={`/hr/employees/${employee.ulid}/edit`}
                className="text-sm text-neutral-600 hover:underline inline-flex items-center gap-1"
              >
                <Plus className="h-4 w-4" />
                Add Information
              </Link>
            ),
          }}
        >
          <div className="space-y-4">
            {/* Government IDs Section */}
            <div>
              <p className="text-xs font-medium text-neutral-500 mb-2">Government IDs</p>
              <div className="space-y-1">
                {isHR ? (
                  // HR View - Show On file / Missing badges
                  <>
                    <InfoRow
                      label="SSS No."
                      value={
                        employee.has_sss_no ? (
                          <StatusBadge status="active">On file</StatusBadge>
                        ) : (
                          <span className="text-neutral-400 italic text-xs">Not recorded</span>
                        )
                      }
                    />
                    <InfoRow
                      label="PhilHealth"
                      value={
                        employee.has_philhealth_no ? (
                          <StatusBadge status="active">On file</StatusBadge>
                        ) : (
                          <span className="text-neutral-400 italic text-xs">Not recorded</span>
                        )
                      }
                    />
                    <InfoRow
                      label="Pag-IBIG"
                      value={
                        employee.has_pagibig_no ? (
                          <StatusBadge status="active">On file</StatusBadge>
                        ) : (
                          <span className="text-neutral-400 italic text-xs">Not recorded</span>
                        )
                      }
                    />
                    <InfoRow
                      label="TIN"
                      value={
                        employee.has_tin ? (
                          <StatusBadge status="active">On file</StatusBadge>
                        ) : (
                          <span className="text-neutral-400 italic text-xs">Not recorded</span>
                        )
                      }
                    />
                    <InfoRow label="BIR Status" value={employee.bir_status ?? '—'} />
                  </>
                ) : (
                  // Team View - Show Registered badges
                  <>
                    <InfoRow
                      label="SSS"
                      value={
                        employee.has_sss_no ? (
                          <span className="text-neutral-700 flex items-center gap-1">
                            <BadgeCheck className="h-3.5 w-3.5" /> Registered
                          </span>
                        ) : (
                          <span className="text-neutral-400 italic text-xs">Not recorded</span>
                        )
                      }
                    />
                    <InfoRow
                      label="PhilHealth"
                      value={
                        employee.has_philhealth_no ? (
                          <span className="text-neutral-700 flex items-center gap-1">
                            <BadgeCheck className="h-3.5 w-3.5" /> Registered
                          </span>
                        ) : (
                          <span className="text-neutral-400 italic text-xs">Not recorded</span>
                        )
                      }
                    />
                    <InfoRow
                      label="Pag-IBIG"
                      value={
                        employee.has_pagibig_no ? (
                          <span className="text-neutral-700 flex items-center gap-1">
                            <BadgeCheck className="h-3.5 w-3.5" /> Registered
                          </span>
                        ) : (
                          <span className="text-neutral-400 italic text-xs">Not recorded</span>
                        )
                      }
                    />
                    <InfoRow
                      label="TIN"
                      value={
                        employee.has_tin ? (
                          <span className="text-neutral-700 flex items-center gap-1">
                            <BadgeCheck className="h-3.5 w-3.5" /> Registered
                          </span>
                        ) : (
                          <span className="text-neutral-400 italic text-xs">Not recorded</span>
                        )
                      }
                    />
                  </>
                )}
              </div>
            </div>

            {/* Divider */}
            <div className="border-t border-neutral-100" />

            {/* Bank Information Section */}
            <div>
              <p className="text-xs font-medium text-neutral-500 mb-2">Bank Information</p>
              <div className="space-y-1">
                <InfoRow
                  label="Bank Name"
                  value={
                    employee.bank_name ?? (
                      <span className="text-neutral-400 italic text-xs">Not recorded</span>
                    )
                  }
                />
                <InfoRow
                  label="Account Number"
                  value={
                    employee.bank_account_no ? (
                      <span className="font-mono text-sm">
                        {'•••• •••• ' + employee.bank_account_no.slice(-4)}
                      </span>
                    ) : (
                      <span className="text-neutral-400 italic text-xs">Not recorded</span>
                    )
                  }
                />
              </div>
            </div>

            {/* Notes Section */}
            {employee.notes && (
              <>
                <div className="border-t border-neutral-100" />
                <div>
                  <p className="text-xs font-medium text-neutral-500 mb-2">Notes</p>
                  <div className="flex items-start gap-2">
                    <FileText className="h-4 w-4 text-neutral-400 mt-0.5 flex-shrink-0" />
                    <p className="text-sm text-neutral-700 whitespace-pre-wrap">{employee.notes}</p>
                  </div>
                </div>
              </>
            )}
          </div>
        </InfoCard>

        {/* Leave Balances */}
        <InfoCard
          title="Leave Balances"
          icon={Calendar}
          action={<span className="text-xs text-neutral-500">{new Date().getFullYear()}</span>}
          emptyState={{ message: 'No leave balances found for this year' }}
        >
          {leaveBalanceItems.length > 0 && (
            <div className="space-y-2.5">
              {leaveBalanceItems.map((balance) => {
                // Total entitlement = opening + accrued + adjusted
                const totalEntitlement =
                  balance.opening_balance + balance.accrued + balance.adjusted
                const pct =
                  totalEntitlement > 0
                    ? Math.min((balance.balance / totalEntitlement) * 100, 100)
                    : balance.balance > 0
                      ? 100
                      : 0
                const _isEmpty = balance.balance <= 0 && totalEntitlement > 0
                const isEventBased = totalEntitlement === 0

                return (
                  <div
                    key={balance.leave_type_id}
                    className="py-2 border-b border-neutral-100 last:border-0"
                  >
                    <div className="flex justify-between items-center mb-1.5">
                      <span className="text-sm text-neutral-700">{balance.leave_type_name}</span>
                      <div className="flex items-baseline gap-0.5">
                        {isEventBased ? (
                          <span className="text-xs text-neutral-400 italic">Event-based</span>
                        ) : (
                          <>
                            <span className="text-sm font-medium text-neutral-900">
                              {balance.balance}
                            </span>
                            <span className="text-xs text-neutral-400">
                              /{totalEntitlement} days
                            </span>
                          </>
                        )}
                      </div>
                    </div>
                    {!isEventBased && (
                      <div className="h-1.5 bg-neutral-100 rounded overflow-hidden">
                        <div
                          className="h-full bg-neutral-400 rounded transition-all"
                          style={{ width: `${pct}%` }}
                        />
                      </div>
                    )}
                    {balance.used > 0 && (
                      <p className="text-[11px] text-neutral-400 mt-0.5">
                        {balance.used} day{balance.used !== 1 ? 's' : ''} used
                      </p>
                    )}
                  </div>
                )
              })}
            </div>
          )}
        </InfoCard>

        {/* Recent Attendance */}
        <InfoCard
          title="Recent Attendance"
          icon={Clock}
          action={
            <Link
              to={
                isHR
                  ? `/hr/attendance?employee_id=${employee.id}&employee_name=${encodeURIComponent(employee.full_name)}`
                  : `/team/attendance?employee_id=${employee.id}&employee_name=${encodeURIComponent(employee.full_name)}`
              }
              className="text-xs text-neutral-600 hover:underline"
            >
              View All
            </Link>
          }
          emptyState={{ message: 'No attendance records this month' }}
        >
          {attendanceLogs.length > 0 && (
            <div className="space-y-2">
              {attendanceLogs.slice(0, 5).map((log) => (
                <div
                  key={log.id}
                  className="flex justify-between items-center py-2 border-b border-neutral-100 last:border-0"
                >
                  <span className="text-sm text-neutral-600">
                    {new Date(log.work_date).toLocaleDateString('en-PH', {
                      month: 'short',
                      day: 'numeric',
                    })}
                  </span>
                  <div className="flex items-center gap-2">
                    {log.is_absent ? (
                      <span className="text-xs px-2 py-0.5 bg-neutral-100 text-neutral-700 rounded">
                        Absent
                      </span>
                    ) : log.late_minutes > 0 ? (
                      <span className="text-xs px-2 py-0.5 bg-neutral-100 text-neutral-700 rounded">
                        Late ({log.late_minutes}m)
                      </span>
                    ) : (
                      <span className="text-xs px-2 py-0.5 bg-neutral-100 text-neutral-700 rounded">
                        Present
                      </span>
                    )}
                    {log.time_in && (
                      <span className="text-xs text-neutral-500">
                        {log.time_in.slice(0, 5)} - {log.time_out?.slice(0, 5) || '—'}
                      </span>
                    )}
                  </div>
                </div>
              ))}
            </div>
          )}
        </InfoCard>

        {/* Reporting Structure */}
        <InfoCard
          title="Reporting Structure"
          icon={Users}
          emptyState={{
            message: 'No supervisor assigned',
            action: !hasSupervisor && isHR && (
              <Link
                to={`/hr/employees/${employee.ulid}/edit`}
                className="text-sm text-neutral-600 hover:underline inline-flex items-center gap-1"
              >
                <Plus className="h-4 w-4" />
                Assign Supervisor
              </Link>
            ),
          }}
        >
          {hasSupervisor && (
            <div className="space-y-1">
              <InfoRow
                label="Immediate Supervisor"
                value={
                  <Link
                    to={
                      isHR
                        ? `/hr/employees/${employee.supervisor!.ulid}`
                        : `/team/employees/${employee.supervisor!.ulid}`
                    }
                    className="text-neutral-700 hover:underline flex items-center gap-1"
                  >
                    <UserCircle className="h-3.5 w-3.5" />
                    {employee.supervisor!.full_name}
                  </Link>
                }
                highlight
              />
              <InfoRow label="Employee Code" value={employee.supervisor!.employee_code} />
            </div>
          )}
        </InfoCard>
      </div>
    </div>
  )
}

// Export helper components for reuse
// eslint-disable-next-line react-refresh/only-export-components
export { InfoCard, InfoRow, StatCard, formatDate, formatDuration, calculateAge }
