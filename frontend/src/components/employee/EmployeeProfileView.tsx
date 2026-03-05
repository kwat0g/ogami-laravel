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
  Phone,
  UserCircle,
  BadgeCheck,
  AlertCircle,
  Users,
  Plus,
  FileText,
  ArrowLeft
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

function statusLabel(s: string) {
  return s.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase())
}

function formatGender(gender: string) {
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
      day: 'numeric'
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
  emptyState
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
    <section className={`bg-white border border-gray-200 rounded-xl overflow-hidden shadow-sm ${className}`}>
      <div className="px-5 py-3 bg-gradient-to-r from-gray-50 to-white border-b border-gray-100 flex items-center justify-between">
        <div className="flex items-center gap-2">
          <Icon className="h-4 w-4 text-blue-600" />
          <h2 className="text-sm font-semibold text-gray-700">{title}</h2>
        </div>
        {action && <div>{action}</div>}
      </div>
      <div className="p-5">
        {hasContent ? children : (
          emptyState ? (
            <div className="text-center py-8">
              <p className="text-sm text-gray-400 mb-3">{emptyState.message}</p>
              {emptyState.action}
            </div>
          ) : (
            <div className="flex flex-col items-center justify-center py-8 text-gray-300">
              <Icon className="h-10 w-10 mb-2" />
              <p className="text-sm">No data available</p>
            </div>
          )
        )}
      </div>
    </section>
  )
}

function InfoRow({ 
  label, 
  value, 
  highlight = false,
  icon
}: { 
  label: string
  value: React.ReactNode
  highlight?: boolean
  icon?: React.ElementType
}) {
  const Icon = icon
  const hasValue = value && value !== '—' && value !== null && value !== undefined
  
  return (
    <div className="flex justify-between items-start py-2.5 border-b border-gray-50 last:border-0">
      <div className="flex items-center gap-1.5">
        {Icon && <Icon className="h-3.5 w-3.5 text-gray-400" />}
        <span className="text-xs text-gray-500 uppercase tracking-wide">{label}</span>
      </div>
      <span className={`text-sm text-right ${highlight ? 'font-semibold text-gray-900' : 'text-gray-700'} ${!hasValue ? 'text-gray-400 italic' : ''}`}>
        {value || '—'}
      </span>
    </div>
  )
}

function StatCard({ label, value, subtext, icon: Icon, color = 'blue' }: {
  label: string
  value: string | number
  subtext?: string
  icon: React.ElementType
  color?: 'blue' | 'green' | 'amber' | 'red' | 'purple' | 'gray'
}) {
  const colorClasses = {
    blue: 'bg-blue-50 text-blue-600 border-blue-100',
    green: 'bg-green-50 text-green-600 border-green-100',
    amber: 'bg-amber-50 text-amber-600 border-amber-100',
    red: 'bg-red-50 text-red-600 border-red-100',
    purple: 'bg-purple-50 text-purple-600 border-purple-100',
    gray: 'bg-gray-50 text-gray-500 border-gray-100',
  }
  
  return (
    <div className={`p-4 rounded-xl border ${colorClasses[color]}`}>
      <div className="flex items-start justify-between">
        <div>
          <p className="text-xs font-medium opacity-80">{label}</p>
          <p className="text-2xl font-bold mt-1">{value}</p>
          {subtext && <p className="text-xs mt-1 opacity-70">{subtext}</p>}
        </div>
        <Icon className="h-5 w-5 opacity-60" />
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
  showStats = true
}: EmployeeProfileViewProps) {
  const isHR = viewContext === 'hr'
  
  // Fetch leave balances and attendance for both views
  // Date range: 1st of month to last day of month
  const now = new Date()
  const firstDayOfMonth = new Date(now.getFullYear(), now.getMonth(), 1).toISOString().slice(0, 10)
  const lastDayOfMonth = new Date(now.getFullYear(), now.getMonth() + 1, 0).toISOString().slice(0, 10)
  
  const { data: leaveBalancesResponse } = useLeaveBalances({ employee_id: employee.id, year: now.getFullYear(), per_page: 50 })
  // The balances endpoint returns paginated EmployeeLeaveBalance rows; since we
  // filter by employee_id there will be at most one entry.
  const employeeLeaveData = leaveBalancesResponse?.data?.[0]
  const leaveBalanceItems = employeeLeaveData?.balances ?? []
  const { data: attendanceData } = useAttendanceLogs({ 
    employee_id: employee.id,
    date_from: firstDayOfMonth,
    date_to: lastDayOfMonth,
    per_page: 31
  })

  // Get initials for avatar
  const initials = employee.full_name
    .split(' ')
    .map(n => n[0])
    .join('')
    .toUpperCase()
    .slice(0, 2)

  const age = calculateAge(employee.date_of_birth)
  const tenure = formatDuration(employee.date_hired)

  // Calculate attendance stats
  const attendanceLogs = attendanceData?.data || []
  const presentDays = attendanceLogs.filter(log => log.is_present).length
  const absentDays = attendanceLogs.filter(log => log.is_absent).length
  const lateDays = attendanceLogs.filter(log => (log.late_minutes || 0) > 0).length

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
            className="inline-flex items-center gap-1.5 text-sm text-gray-500 hover:text-blue-600 transition-colors"
          >
            <ArrowLeft className="h-4 w-4" />
            {backLabel}
          </Link>
        ) : onBack ? (
          <button
            onClick={onBack}
            className="inline-flex items-center gap-1.5 text-sm text-gray-500 hover:text-blue-600 transition-colors"
          >
            <ArrowLeft className="h-4 w-4" />
            {backLabel}
          </button>
        ) : null}
      </div>

      {/* Profile Header */}
      <div className="bg-white border border-gray-200 rounded-2xl p-6 shadow-sm">
        <div className="flex flex-col md:flex-row md:items-center gap-6">
          {/* Avatar */}
          <div className="flex-shrink-0">
            <div className="w-24 h-24 rounded-full bg-gradient-to-br from-blue-500 to-blue-600 flex items-center justify-center text-white text-3xl font-bold shadow-lg ring-4 ring-blue-50">
              {initials}
            </div>
          </div>
          
          {/* Info */}
          <div className="flex-1 min-w-0">
            <div className="flex flex-wrap items-center gap-3">
              <h1 className="text-2xl font-bold text-gray-900">{employee.full_name}</h1>
              <StatusBadge 
                label={employee.employment_status.replace('_', ' ')} 
                autoVariant 
              />
            </div>
            
            <div className="flex flex-wrap items-center gap-x-4 gap-y-2 mt-3 text-sm">
              <span className="font-mono text-gray-500 bg-gray-100 px-2 py-0.5 rounded">
                {employee.employee_code}
              </span>
              {employee.department && (
                <span className="text-gray-600 flex items-center gap-1">
                  <Building2 className="h-4 w-4 text-gray-400" />
                  {employee.department.name}
                </span>
              )}
              {employee.position && (
                <span className="text-gray-600 flex items-center gap-1">
                  <Briefcase className="h-4 w-4 text-gray-400" />
                  {employee.position.title}
                </span>
              )}
              <span className="text-gray-500 flex items-center gap-1">
                <Calendar className="h-4 w-4 text-gray-400" />
                {tenure} tenure
              </span>
            </div>
          </div>

          {/* Quick Stats */}
          {showStats && (
            <div className="flex gap-3">
              <div className="text-center px-4 py-2 bg-blue-50 rounded-xl">
                <p className="text-2xl font-bold text-blue-600">{presentDays}</p>
                <p className="text-xs text-blue-600/70">Present</p>
              </div>
              <div className="text-center px-4 py-2 bg-green-50 rounded-xl">
                <p className="text-2xl font-bold text-green-600">{totalLeaveBalance}</p>
                <p className="text-xs text-green-600/70">Leave Days</p>
              </div>
            </div>
          )}

          {/* Custom Actions */}
          {actions && (
            <div className="flex flex-wrap gap-2">
              {actions}
            </div>
          )}
        </div>
      </div>

      {/* Stats Row */}
      {showStats && (
        <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
          <StatCard 
            label="Present This Month" 
            value={presentDays} 
            subtext="Working days"
            icon={BadgeCheck}
            color="green"
          />
          <StatCard 
            label="Late Arrivals" 
            value={lateDays} 
            subtext="This month"
            icon={Clock}
            color={lateDays > 0 ? 'amber' : 'blue'}
          />
          <StatCard 
            label="Absences" 
            value={absentDays} 
            subtext="This month"
            icon={AlertCircle}
            color={absentDays > 0 ? 'red' : 'blue'}
          />
          <StatCard 
            label="Leave Balance" 
            value={totalLeaveBalance} 
            subtext="Days available"
            icon={Calendar}
            color="purple"
          />
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
              value={age ? `${formatDate(employee.date_of_birth)} (${age} years old)` : formatDate(employee.date_of_birth)} 
            />
            <InfoRow label="Gender" value={formatGender(employee.gender)} />
            <InfoRow label="Civil Status" value={formatCivilStatus(employee.civil_status)} />
            <InfoRow label="Citizenship" value={employee.citizenship} />
          </div>
        </InfoCard>

        {/* Contact Details */}
        <InfoCard title="Contact Details" icon={Mail}>
          <div className="space-y-1">
            <div className="flex justify-between items-start py-2.5 border-b border-gray-50">
              <span className="text-xs text-gray-500 uppercase tracking-wide">Personal Email</span>
              {employee.personal_email ? (
                <a 
                  href={`mailto:${employee.personal_email}`}
                  className="text-sm text-blue-600 hover:underline"
                >
                  {employee.personal_email}
                </a>
              ) : (
                <span className="text-sm text-gray-400 italic">Not provided</span>
              )}
            </div>
            <div className="flex justify-between items-start py-2.5 border-b border-gray-50">
              <span className="text-xs text-gray-500 uppercase tracking-wide">Mobile</span>
              {employee.personal_phone ? (
                <a 
                  href={`tel:${employee.personal_phone}`}
                  className="text-sm text-blue-600 hover:underline"
                >
                  {employee.personal_phone}
                </a>
              ) : (
                <span className="text-sm text-gray-400 italic">Not provided</span>
              )}
            </div>
            <InfoRow 
              label="Present Address" 
              value={
                employee.present_address ? (
                  <span className="flex items-start gap-1">
                    <MapPin className="h-3.5 w-3.5 text-gray-400 mt-0.5 flex-shrink-0" />
                    <span className="line-clamp-2">{employee.present_address}</span>
                  </span>
                ) : 'Not provided'
              } 
            />
            <InfoRow 
              label="Permanent Address" 
              value={
                employee.permanent_address ? (
                  <span className="flex items-start gap-1">
                    <MapPin className="h-3.5 w-3.5 text-gray-400 mt-0.5 flex-shrink-0" />
                    <span className="line-clamp-2">{employee.permanent_address}</span>
                  </span>
                ) : 'Not provided'
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
            message: employee.salary_grade ? 'Compensation details loading...' : 'Salary grade not assigned',
            action: !employee.salary_grade && isHR && (
              <Link 
                to={`/hr/employees/${employee.ulid}/edit`} 
                className="text-sm text-blue-600 hover:underline inline-flex items-center gap-1"
              >
                <Plus className="h-4 w-4" />
                Assign Salary Grade
              </Link>
            )
          }}
        >
          {employee.salary_grade && (
            <div className="space-y-1">
              <InfoRow 
                label="Salary Grade" 
                value={employee.salary_grade.code} 
              />
              <InfoRow 
                label="Step" 
                value={employee.salary_grade.name} 
              />
              <div className="flex justify-between items-start py-2.5 border-b border-gray-50">
                <span className="text-xs text-gray-500 uppercase tracking-wide">Basic Monthly</span>
                <span className="text-sm font-semibold text-gray-900">
                  {employee.basic_monthly_rate ? (
                    <CurrencyAmount centavos={employee.basic_monthly_rate} />
                  ) : '—'}
                </span>
              </div>
              <div className="flex justify-between items-start py-2.5 border-b border-gray-50">
                <span className="text-xs text-gray-500 uppercase tracking-wide">Basic Daily</span>
                <span className="text-sm text-gray-700">
                  {employee.daily_rate ? (
                    <CurrencyAmount centavos={employee.daily_rate} />
                  ) : '—'}
                </span>
              </div>
              <div className="flex justify-between items-start py-2.5 border-b border-gray-50">
                <span className="text-xs text-gray-500 uppercase tracking-wide">Basic Hourly</span>
                <span className="text-sm text-gray-700">
                  {employee.hourly_rate ? (
                    <CurrencyAmount centavos={employee.hourly_rate} />
                  ) : '—'}
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
                className="text-sm text-blue-600 hover:underline inline-flex items-center gap-1"
              >
                <Plus className="h-4 w-4" />
                Add Information
              </Link>
            )
          }}
        >
          <div className="space-y-4">
            {/* Government IDs Section */}
            <div>
              <p className="text-xs font-medium text-gray-500 uppercase tracking-wide mb-2">Government IDs</p>
              <div className="space-y-1">
                {isHR ? (
                  // HR View - Show On file / Missing badges
                  <>
                    <InfoRow 
                      label="SSS No." 
                      value={
                        employee.has_sss_no ? (
                          <StatusBadge label="On file" variant="success" />
                        ) : (
                          <span className="text-gray-400 italic text-xs">Not recorded</span>
                        )
                      } 
                    />
                    <InfoRow 
                      label="PhilHealth" 
                      value={
                        employee.has_philhealth_no ? (
                          <StatusBadge label="On file" variant="success" />
                        ) : (
                          <span className="text-gray-400 italic text-xs">Not recorded</span>
                        )
                      } 
                    />
                    <InfoRow 
                      label="Pag-IBIG" 
                      value={
                        employee.has_pagibig_no ? (
                          <StatusBadge label="On file" variant="success" />
                        ) : (
                          <span className="text-gray-400 italic text-xs">Not recorded</span>
                        )
                      } 
                    />
                    <InfoRow 
                      label="TIN" 
                      value={
                        employee.has_tin ? (
                          <StatusBadge label="On file" variant="success" />
                        ) : (
                          <span className="text-gray-400 italic text-xs">Not recorded</span>
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
                      value={employee.has_sss_no ? <span className="text-green-600 flex items-center gap-1"><BadgeCheck className="h-3.5 w-3.5" /> Registered</span> : <span className="text-gray-400 italic text-xs">Not recorded</span>} 
                    />
                    <InfoRow 
                      label="PhilHealth" 
                      value={employee.has_philhealth_no ? <span className="text-green-600 flex items-center gap-1"><BadgeCheck className="h-3.5 w-3.5" /> Registered</span> : <span className="text-gray-400 italic text-xs">Not recorded</span>} 
                    />
                    <InfoRow 
                      label="Pag-IBIG" 
                      value={employee.has_pagibig_no ? <span className="text-green-600 flex items-center gap-1"><BadgeCheck className="h-3.5 w-3.5" /> Registered</span> : <span className="text-gray-400 italic text-xs">Not recorded</span>} 
                    />
                    <InfoRow 
                      label="TIN" 
                      value={employee.has_tin ? <span className="text-green-600 flex items-center gap-1"><BadgeCheck className="h-3.5 w-3.5" /> Registered</span> : <span className="text-gray-400 italic text-xs">Not recorded</span>} 
                    />
                  </>
                )}
              </div>
            </div>

            {/* Divider */}
            <div className="border-t border-gray-100" />

            {/* Bank Information Section */}
            <div>
              <p className="text-xs font-medium text-gray-500 uppercase tracking-wide mb-2">Bank Information</p>
              <div className="space-y-1">
                <InfoRow label="Bank Name" value={employee.bank_name ?? <span className="text-gray-400 italic text-xs">Not recorded</span>} />
                <InfoRow 
                  label="Account Number" 
                  value={
                    employee.bank_account_no ? (
                      <span className="font-mono text-sm">
                        {'•••• •••• ' + employee.bank_account_no.slice(-4)}
                      </span>
                    ) : (
                      <span className="text-gray-400 italic text-xs">Not recorded</span>
                    )
                  } 
                />
              </div>
            </div>

            {/* Notes Section */}
            {employee.notes && (
              <>
                <div className="border-t border-gray-100" />
                <div>
                  <p className="text-xs font-medium text-gray-500 uppercase tracking-wide mb-2">Notes</p>
                  <div className="flex items-start gap-2">
                    <FileText className="h-4 w-4 text-gray-400 mt-0.5 flex-shrink-0" />
                    <p className="text-sm text-gray-700 whitespace-pre-wrap">{employee.notes}</p>
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
          action={<span className="text-xs text-gray-500">{new Date().getFullYear()}</span>}
          emptyState={{ message: 'No leave balances found' }}
        >
          {leaveBalanceItems.some(b => b.has_balance) && (
            <div className="space-y-2">
              {leaveBalanceItems.filter(b => b.has_balance).map((balance) => (
                <div key={balance.leave_type_id} className="flex justify-between items-center py-2 border-b border-gray-50 last:border-0">
                  <span className="text-sm text-gray-600">{balance.leave_type_name}</span>
                  <div className="flex items-center gap-2">
                    <div className="w-24 h-2 bg-gray-100 rounded-full overflow-hidden">
                      <div 
                        className={`h-full rounded-full ${balance.balance > 5 ? 'bg-green-500' : balance.balance > 0 ? 'bg-amber-500' : 'bg-red-500'}`}
                        style={{ width: `${Math.min((balance.balance / (balance.opening_balance || 1)) * 100, 100)}%` }}
                      />
                    </div>
                    <span className={`text-sm font-medium ${balance.balance > 5 ? 'text-green-600' : balance.balance > 0 ? 'text-amber-600' : 'text-red-600'}`}>
                      {balance.balance} days
                    </span>
                  </div>
                </div>
              ))}
            </div>
          )}
        </InfoCard>

        {/* Recent Attendance */}
        <InfoCard 
          title="Recent Attendance" 
          icon={Clock}
          action={
            <Link 
              to={isHR 
                ? `/hr/attendance?employee_id=${employee.id}&employee_name=${encodeURIComponent(employee.full_name)}` 
                : `/team/attendance?employee_id=${employee.id}&employee_name=${encodeURIComponent(employee.full_name)}`
              } 
              className="text-xs text-blue-600 hover:underline"
            >
              View All
            </Link>
          }
          emptyState={{ message: 'No attendance records this month' }}
        >
          {attendanceLogs.length > 0 && (
            <div className="space-y-2">
              {attendanceLogs.slice(0, 5).map((log) => (
                <div key={log.id} className="flex justify-between items-center py-2 border-b border-gray-50 last:border-0">
                  <span className="text-sm text-gray-600">
                    {new Date(log.work_date).toLocaleDateString('en-PH', { month: 'short', day: 'numeric' })}
                  </span>
                  <div className="flex items-center gap-2">
                    {log.is_absent ? (
                      <span className="text-xs px-2 py-0.5 bg-red-100 text-red-700 rounded-full">Absent</span>
                    ) : log.late_minutes > 0 ? (
                      <span className="text-xs px-2 py-0.5 bg-amber-100 text-amber-700 rounded-full">Late ({log.late_minutes}m)</span>
                    ) : (
                      <span className="text-xs px-2 py-0.5 bg-green-100 text-green-700 rounded-full">Present</span>
                    )}
                    {log.time_in && (
                      <span className="text-xs text-gray-500">
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
            message: 'Reporting structure not configured',
            action: !hasSupervisor && isHR && (
              <Link 
                to={`/hr/employees/${employee.ulid}/edit`} 
                className="text-sm text-blue-600 hover:underline inline-flex items-center gap-1"
              >
                <Plus className="h-4 w-4" />
                Assign Supervisor
              </Link>
            )
          }}
        >
          <div className="space-y-1">
            <InfoRow 
              label="Immediate Supervisor" 
              value={
                employee.supervisor?.full_name ? (
                  <Link 
                    to={isHR ? `/hr/employees/${employee.supervisor.ulid}` : `/team/employees/${employee.supervisor.ulid}`}
                    className="text-blue-600 hover:underline flex items-center gap-1"
                  >
                    <UserCircle className="h-3.5 w-3.5" />
                    {employee.supervisor.full_name}
                  </Link>
                ) : 'Not Assigned'
              } 
              highlight 
            />
            <InfoRow 
              label="Department Head" 
              value="See HR for department head" 
            />
            <InfoRow 
              label="HR Contact" 
              value="HR Department" 
            />
          </div>
        </InfoCard>

        {/* Emergency Contact - Optional */}
        <InfoCard 
          title="Emergency Contact" 
          icon={Phone}
          emptyState={{ 
            message: 'Emergency contact not set',
            action: isHR && (
              <Link 
                to={`/hr/employees/${employee.ulid}/edit`} 
                className="text-sm text-blue-600 hover:underline inline-flex items-center gap-1"
              >
                <Plus className="h-4 w-4" />
                Add Emergency Contact
              </Link>
            )
          }}
        >
          <div className="space-y-1">
            <InfoRow label="Contact Person" value={null} highlight />
            <InfoRow label="Relationship" value={null} />
            <InfoRow label="Phone" value={null} />
            <InfoRow label="Address" value={null} />
          </div>
        </InfoCard>
      </div>
    </div>
  )
}

// Export helper components for reuse
// eslint-disable-next-line react-refresh/only-export-components
export { InfoCard, InfoRow, StatCard, formatDate, formatDuration, calculateAge }
