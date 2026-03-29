import { useState } from 'react'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { z } from 'zod'
import { toast } from 'sonner'
import {
  User,
  Pencil,
  X,
  Check,
  Mail,
  Phone,
  MapPin,
  Building2,
  Briefcase,
  Calendar,
  Banknote,
  CreditCard,
  FileText,
  Shield,
  Heart,
  Home,
  BadgeCheck,
  Clock,
  TrendingUp,
  Award,
  AlertCircle,
  Lock,
  FileCheck,
  Clock4,
} from 'lucide-react'
import { useMyProfile, useUpdateMyProfile, type UpdateProfilePayload } from '@/hooks/useEmployeeSelf'
import { SkeletonLoader } from '@/components/ui'
import CurrencyAmount from '@/components/ui/CurrencyAmount'
import StatusBadge from '@/components/ui/StatusBadge'
import { PageHeader } from '@/components/ui/PageHeader'
import { parseApiError } from '@/lib/errorHandler'

// ── Edit schema (only the fields we allow self-service) ───────────────────────

const editSchema = z.object({
  personal_email: z.string().email('Enter a valid email').or(z.literal('')).optional(),
  personal_phone: z.string().max(30, 'Too long').optional(),
  present_address: z.string().max(500, 'Too long').optional(),
  bank_name: z.string().max(100, 'Too long').optional(),
  bank_account_no: z.string().max(50, 'Too long').optional(),
})

type EditFormValues = z.infer<typeof editSchema>

// ── Helper Components ─────────────────────────────────────────────────────────

function Avatar({ name, size = 'lg' }: { name: string; size?: 'sm' | 'md' | 'lg' | 'xl' }) {
  const initials = name
    .split(' ')
    .map((n) => n[0])
    .slice(0, 2)
    .join('')
    .toUpperCase()

  const sizeClasses = {
    sm: 'h-10 w-10 text-sm',
    md: 'h-14 w-14 text-base',
    lg: 'h-20 w-20 text-xl',
    xl: 'h-24 w-24 text-2xl',
  }

  // Generate consistent color based on name
  const colors = [
    'bg-neutral-100 text-neutral-700',
    'bg-neutral-100 text-neutral-700',
    'bg-neutral-100 text-neutral-700',
    'bg-neutral-100 text-neutral-700',
    'bg-neutral-100 text-neutral-700',
    'bg-neutral-100 text-neutral-700',
  ]
  const colorIndex = name.charCodeAt(0) % colors.length
  const colorClass = colors[colorIndex]

  return (
    <div
      className={`${sizeClasses[size]} ${colorClass} rounded flex items-center justify-center font-semibold`}
    >
      {initials}
    </div>
  )
}

function Card({
  children,
  className = '',
  padding = 'normal',
}: {
  children: React.ReactNode
  className?: string
  padding?: 'none' | 'normal' | 'large'
}) {
  const paddingClasses = {
    none: '',
    normal: 'p-6',
    large: 'p-8',
  }
  return (
    <div
      className={`bg-white rounded border border-neutral-200 ${paddingClasses[padding]} ${className}`}
    >
      {children}
    </div>
  )
}

function CardHeader({
  icon: Icon,
  title,
  subtitle,
  action,
}: {
  icon?: React.ElementType
  title: string
  subtitle?: string
  action?: React.ReactNode
}) {
  return (
    <div className="flex items-start justify-between mb-5">
      <div className="flex items-center gap-3">
        {Icon && (
          <div className="h-10 w-10 rounded bg-neutral-50 flex items-center justify-center">
            <Icon className="h-5 w-5 text-neutral-500" />
          </div>
        )}
        <div>
          <h3 className="text-base font-semibold text-neutral-900">{title}</h3>
          {subtitle && <p className="text-sm text-neutral-500">{subtitle}</p>}
        </div>
      </div>
      {action && <div className="flex-shrink-0">{action}</div>}
    </div>
  )
}

function InfoRow({
  label,
  value,
  icon: Icon,
  isMonetary = false,
  centavos,
}: {
  label: string
  value?: React.ReactNode
  icon?: React.ElementType
  isMonetary?: boolean
  centavos?: number
}) {
  return (
    <div className="flex items-start gap-3 py-2.5">
      {Icon && <Icon className="h-4 w-4 text-neutral-400 mt-0.5 flex-shrink-0" />}
      <div className="flex-1 min-w-0">
        <p className="text-xs text-neutral-500">{label}</p>
        <div className="text-sm font-medium text-neutral-900 mt-0.5 truncate">
          {isMonetary && centavos !== undefined ? (
            <CurrencyAmount centavos={centavos} />
          ) : (
            value ?? '—'
          )}
        </div>
      </div>
    </div>
  )
}

function InfoGrid({ children }: { children: React.ReactNode }) {
  return <div className="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-1">{children}</div>
}

function TabButton({
  active,
  onClick,
  icon: Icon,
  label,
  count,
}: {
  active: boolean
  onClick: () => void
  icon: React.ElementType
  label: string
  count?: number
}) {
  return (
    <button
      onClick={onClick}
      className={`flex items-center gap-2 px-4 py-2.5 text-sm font-medium rounded transition-colors ${
        active
          ? 'bg-neutral-100 text-neutral-900 border border-neutral-200'
          : 'text-neutral-600 hover:bg-neutral-50 hover:text-neutral-900'
      }`}
    >
      <Icon className="h-4 w-4" />
      {label}
      {count !== undefined && (
        <span
          className={`ml-1 px-1.5 py-0.5 text-xs rounded ${
            active ? 'bg-neutral-200 text-neutral-800' : 'bg-neutral-100 text-neutral-600'
          }`}
        >
          {count}
        </span>
      )}
    </button>
  )
}

function QuickStat({
  icon: Icon,
  label,
  value,
}: {
  icon: React.ElementType
  label: string
  value: string
}) {
  return (
    <div className="flex items-center gap-4 p-4 rounded bg-neutral-50 border border-neutral-200">
      <div className="h-12 w-12 rounded bg-neutral-100 text-neutral-700 flex items-center justify-center">
        <Icon className="h-6 w-6" />
      </div>
      <div>
        <p className="text-xs text-neutral-500">{label}</p>
        <p className="text-lg font-semibold text-neutral-900">{value}</p>
      </div>
    </div>
  )
}

function GovernmentIdBadge({
  label,
  hasValue,
}: {
  label: string
  hasValue: boolean
}) {
  return (
    <div
      className={`flex items-center gap-2 px-3 py-2 rounded text-sm ${
        hasValue ? 'bg-neutral-100 text-neutral-900 border border-neutral-200' : 'bg-neutral-50 text-neutral-500'
      }`}
    >
      {hasValue ? (
        <>
          <BadgeCheck className="h-4 w-4 text-neutral-700" />
          <span className="font-medium">{label}</span>
          <span className="text-xs text-neutral-500 ml-auto">On file</span>
        </>
      ) : (
        <>
          <AlertCircle className="h-4 w-4 text-neutral-400" />
          <span>{label}</span>
          <span className="text-xs text-neutral-400 ml-auto">Missing</span>
        </>
      )}
    </div>
  )
}

function DocumentStatusCard() {
  // This would be populated from actual API data
  const documents = [
    { name: 'Employment Contract', status: 'verified', date: '2024-01-15' },
    { name: 'Government ID', status: 'verified', date: '2024-01-15' },
    { name: 'Tax Documents', status: 'pending', date: null },
    { name: 'Bank Forms', status: 'verified', date: '2024-01-20' },
  ]

  const verifiedCount = documents.filter((d) => d.status === 'verified').length

  return (
    <Card>
      <CardHeader
        icon={FileText}
        title="Document Status"
        subtitle={`${verifiedCount} of ${documents.length} documents verified`}
      />
      <div className="space-y-3">
        {documents.map((doc) => (
          <div
            key={doc.name}
            className="flex items-center justify-between py-2 border-b border-neutral-50 last:border-0"
          >
            <div className="flex items-center gap-3">
              {doc.status === 'verified' ? (
                <FileCheck className="h-4 w-4 text-emerald-600" />
              ) : (
                <Clock4 className="h-4 w-4 text-amber-500" />
              )}
              <span className="text-sm text-neutral-700">{doc.name}</span>
            </div>
            <StatusBadge status={doc.status === 'verified' ? 'approved' : 'pending'}>
              {doc.status === 'verified' ? 'Verified' : 'Pending'}
            </StatusBadge>
          </div>
        ))}
      </div>
    </Card>
  )
}

// ── Main Component ────────────────────────────────────────────────────────────

export default function MyProfilePage() {
  const { data: employee, isLoading, isError } = useMyProfile()
  const updateProfile = useUpdateMyProfile()
  const [isEditing, setIsEditing] = useState(false)
  const [activeTab, setActiveTab] = useState<'overview' | 'employment' | 'documents'>('overview')

  const { register, handleSubmit, reset, formState: { errors, isSubmitting } } =
    useForm<EditFormValues>({
      resolver: zodResolver(editSchema),
      mode: 'onBlur',
    })

  const startEditing = () => {
    if (!employee) return
    reset({
      personal_email: employee.personal_email ?? '',
      personal_phone: employee.personal_phone ?? '',
      present_address: employee.present_address ?? '',
      bank_name: employee.bank_name ?? '',
      bank_account_no: employee.bank_account_no ?? '',
    })
    setIsEditing(true)
  }

  const onSubmit = async (values: EditFormValues) => {
    const payload: UpdateProfilePayload = {}
    if (values.personal_email !== undefined) payload.personal_email = values.personal_email || null
    if (values.personal_phone !== undefined) payload.personal_phone = values.personal_phone || null
    if (values.present_address !== undefined) payload.present_address = values.present_address || null
    if (values.bank_name !== undefined) payload.bank_name = values.bank_name || null
    if (values.bank_account_no !== undefined) payload.bank_account_no = values.bank_account_no || null

    try {
      await updateProfile.mutateAsync(payload)
      toast.success('Profile updated successfully')
      setIsEditing(false)
    } catch (err) {
      toast.error(parseApiError(err).message)
    }
  }

  // ── Loading State ───────────────────────────────────────────────────────────

  if (isLoading) {
    return (
      <div className="max-w-6xl mx-auto">
        <div className="h-48 bg-neutral-100 rounded mb-6 animate-pulse" />
        <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
          <div className="lg:col-span-2 space-y-6">
            <SkeletonLoader rows={6} />
          </div>
          <div className="space-y-6">
            <SkeletonLoader rows={4} />
          </div>
        </div>
      </div>
    )
  }

  // ── Error State ─────────────────────────────────────────────────────────────

  if (isError || !employee) {
    return (
      <div className="max-w-2xl mx-auto text-center py-16">
        <div className="h-16 w-16 bg-neutral-100 rounded flex items-center justify-center mx-auto mb-4">
          <AlertCircle className="h-8 w-8 text-neutral-700" />
        </div>
        <h2 className="text-lg font-semibold text-neutral-900 mb-2">Unable to Load Profile</h2>
        <p className="text-sm text-neutral-500 max-w-sm mx-auto">
          Your account may not have a linked employee record. Please contact HR if you believe this
          is an error.
        </p>
      </div>
    )
  }

  // ── Render ──────────────────────────────────────────────────────────────────

  const inputCls =
    'w-full border border-neutral-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-neutral-400 focus:border-neutral-400 transition-all'

  // Calculate tenure
  const hireDate = new Date(employee.date_hired)
  const today = new Date()
  const yearsDiff = today.getFullYear() - hireDate.getFullYear()
  const monthsDiff = today.getMonth() - hireDate.getMonth()
  const tenureText =
    yearsDiff > 0
      ? `${yearsDiff} year${yearsDiff > 1 ? 's' : ''}${monthsDiff > 0 ? ` ${monthsDiff} mo` : ''}`
      : monthsDiff > 0
        ? `${monthsDiff} month${monthsDiff > 1 ? 's' : ''}`
        : 'Less than a month'

  return (
    <div className="max-w-6xl mx-auto">
      <PageHeader title="My Profile" />
      {/* ── Profile Header ─────────────────────────────────────────────────────── */}
      <div className="relative mb-8">
        {/* Cover Background */}
        <div className="h-32 bg-neutral-900 rounded" />

        {/* Profile Info */}
        <div className="px-6 pb-6">
          <div className="flex flex-col sm:flex-row sm:items-end gap-4 -mt-10 mb-4">
            <div className="relative">
              <Avatar name={employee.full_name} size="xl" />
              <div className="absolute -bottom-1 -right-1 h-6 w-6 bg-neutral-600 border-2 border-white rounded" title="Active" />
            </div>
            <div className="flex-1 min-w-0">
              <div className="flex items-center gap-3 flex-wrap">
                <h1 className="text-lg font-semibold text-neutral-900">{employee.full_name}</h1>
                <StatusBadge
                  label={employee.employment_status}
                  autoVariant
                />
              </div>
              <p className="text-neutral-500 mt-1">
                {employee.position?.title ?? 'No position assigned'} •{' '}
                {employee.department?.name ?? 'No department'}
              </p>
            </div>
            <div className="flex items-center gap-2">
              {!isEditing && (
                <button
                  type="button"
                  onClick={startEditing}
                  className="inline-flex items-center gap-2 bg-white border border-neutral-300 text-neutral-700 hover:bg-neutral-50 hover:text-neutral-900 font-medium px-4 py-2 rounded transition-colors"
                >
                  <Pencil className="h-4 w-4" />
                  Edit Profile
                </button>
              )}
            </div>
          </div>

          {/* Quick Stats */}
          <div className="grid grid-cols-2 sm:grid-cols-4 gap-4 mt-6">
            <QuickStat
              icon={Clock}
              label="Tenure"
              value={tenureText}
            />
            <QuickStat
              icon={Award}
              label="Employee ID"
              value={employee.employee_code}
            />
            <QuickStat
              icon={Building2}
              label="Department"
              value={employee.department?.name ?? 'N/A'}
            />
            <QuickStat
              icon={Banknote}
              label="Monthly Rate"
              value={`₱${(employee.basic_monthly_rate / 100).toLocaleString()}`}
            />
          </div>
        </div>
      </div>

      {/* ── Tab Navigation ─────────────────────────────────────────────────────── */}
      <div className="flex items-center gap-2 mb-6 overflow-x-auto pb-2">
        <TabButton
          active={activeTab === 'overview'}
          onClick={() => setActiveTab('overview')}
          icon={User}
          label="Overview"
        />
        <TabButton
          active={activeTab === 'employment'}
          onClick={() => setActiveTab('employment')}
          icon={Briefcase}
          label="Employment"
        />
        <TabButton
          active={activeTab === 'documents'}
          onClick={() => setActiveTab('documents')}
          icon={FileText}
          label="Documents"
          count={4}
        />
      </div>

      {/* ── Edit Mode ──────────────────────────────────────────────────────────── */}
      {isEditing ? (
        <Card>
          <CardHeader
            icon={Pencil}
            title="Edit Profile Information"
            subtitle="Update your contact and bank details"
          />
          <form onSubmit={handleSubmit(onSubmit)} className="space-y-6">
            {/* Contact Information */}
            <div>
              <h4 className="text-sm font-semibold text-neutral-900 mb-4 flex items-center gap-2">
                <Mail className="h-4 w-4 text-neutral-400" />
                Contact Information
              </h4>
              <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                  <label htmlFor="personal_email" className="block text-sm font-medium text-neutral-700 mb-1">
                    Personal Email
                  </label>
                  <input
                    id="personal_email"
                    type="email"
                    className={inputCls}
                    placeholder="your@email.com"
                    {...register('personal_email')}
                  />
                  {errors.personal_email && (
                    <p className="mt-1 text-xs text-red-600">{errors.personal_email.message}</p>
                  )}
                </div>
                <div>
                  <label htmlFor="personal_phone" className="block text-sm font-medium text-neutral-700 mb-1">
                    Personal Phone
                  </label>
                  <input
                    id="personal_phone"
                    className={inputCls}
                    placeholder="+63 912 345 6789"
                    {...register('personal_phone')}
                  />
                  {errors.personal_phone && (
                    <p className="mt-1 text-xs text-red-600">{errors.personal_phone.message}</p>
                  )}
                </div>
              </div>
              <div className="mt-4">
                <label htmlFor="present_address" className="block text-sm font-medium text-neutral-700 mb-1">
                  Present Address
                </label>
                <textarea
                  id="present_address"
                  rows={3}
                  className={`${inputCls} resize-none`}
                  placeholder="Your current address"
                  {...register('present_address')}
                />
                {errors.present_address && (
                  <p className="mt-1 text-xs text-red-600">{errors.present_address.message}</p>
                )}
              </div>
            </div>

            <div className="border-t border-neutral-100" />

            {/* Bank Information */}
            <div>
              <h4 className="text-sm font-semibold text-neutral-900 mb-4 flex items-center gap-2">
                <CreditCard className="h-4 w-4 text-neutral-400" />
                Bank Information
              </h4>
              <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                  <label htmlFor="bank_name" className="block text-sm font-medium text-neutral-700 mb-1">
                    Bank Name
                  </label>
                  <input
                    id="bank_name"
                    className={inputCls}
                    placeholder="e.g., BDO, BPI, Metrobank"
                    {...register('bank_name')}
                  />
                  {errors.bank_name && (
                    <p className="mt-1 text-xs text-red-600">{errors.bank_name.message}</p>
                  )}
                </div>
                <div>
                  <label htmlFor="bank_account_no" className="block text-sm font-medium text-neutral-700 mb-1">
                    Bank Account Number
                  </label>
                  <input
                    id="bank_account_no"
                    className={inputCls}
                    placeholder="Your account number"
                    {...register('bank_account_no')}
                  />
                  {errors.bank_account_no && (
                    <p className="mt-1 text-xs text-red-600">{errors.bank_account_no.message}</p>
                  )}
                </div>
              </div>
            </div>

            {/* Actions */}
            <div className="flex items-center gap-3 pt-4 border-t border-neutral-100">
              <button
                type="submit"
                disabled={isSubmitting}
                className="inline-flex items-center gap-2 bg-neutral-900 hover:bg-neutral-800 disabled:opacity-50 disabled:cursor-not-allowed disabled:cursor-not-allowed text-white font-medium px-5 py-2.5 rounded transition-colors"
              >
                <Check className="h-4 w-4" />
                {isSubmitting ? 'Saving…' : 'Save Changes'}
              </button>
              <button
                type="button"
                onClick={() => setIsEditing(false)}
                className="inline-flex items-center gap-2 border border-neutral-300 text-neutral-700 hover:bg-neutral-50 font-medium px-5 py-2.5 rounded transition-colors"
              >
                <X className="h-4 w-4" />
                Cancel
              </button>
            </div>
          </form>
        </Card>
      ) : (
        /* ── View Mode ────────────────────────────────────────────────────────── */
        <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
          {/* Main Content */}
          <div className="lg:col-span-2 space-y-6">
            {activeTab === 'overview' && (
              <>
                {/* Personal Information */}
                <Card>
                  <CardHeader
                    icon={User}
                    title="Personal Information"
                    subtitle="Your basic personal details"
                  />
                  <InfoGrid>
                    <InfoRow
                      icon={Mail}
                      label="Personal Email"
                      value={employee.personal_email}
                    />
                    <InfoRow
                      icon={Phone}
                      label="Personal Phone"
                      value={employee.personal_phone}
                    />
                    <InfoRow
                      icon={MapPin}
                      label="Present Address"
                      value={employee.present_address}
                    />
                    <InfoRow
                      icon={MapPin}
                      label="Permanent Address"
                      value={employee.permanent_address}
                    />
                    <InfoRow
                      icon={Calendar}
                      label="Date of Birth"
                      value={
                        employee.date_of_birth
                          ? new Date(employee.date_of_birth).toLocaleDateString('en-PH', {
                              year: 'numeric',
                              month: 'long',
                              day: 'numeric',
                            })
                          : undefined
                      }
                    />
                    <InfoRow
                      icon={User}
                      label="Gender"
                      value={
                        employee.gender
                          ? employee.gender.charAt(0).toUpperCase() + employee.gender.slice(1)
                          : undefined
                      }
                    />
                    <InfoRow
                      icon={Heart}
                      label="Civil Status"
                      value={
                        employee.civil_status
                          ? employee.civil_status.replace(/_/g, ' ').toLowerCase()
                          : undefined
                      }
                    />
                    <InfoRow
                      icon={Shield}
                      label="Citizenship"
                      value={employee.citizenship}
                    />
                  </InfoGrid>
                </Card>

                {/* Bank Information */}
                <Card>
                  <CardHeader
                    icon={CreditCard}
                    title="Bank Information"
                    subtitle="Your salary disbursement details"
                  />
                  <InfoGrid>
                    <InfoRow
                      icon={Building2}
                      label="Bank Name"
                      value={employee.bank_name}
                    />
                    <InfoRow
                      icon={CreditCard}
                      label="Account Number"
                      value={
                        employee.bank_account_no
                          ? `•••• ${employee.bank_account_no.slice(-4)}`
                          : undefined
                      }
                    />
                  </InfoGrid>
                </Card>

                {/* Government IDs */}
                <Card>
                  <CardHeader
                    icon={Lock}
                    title="Government IDs"
                    subtitle="Your registered government identification numbers"
                  />
                  <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <GovernmentIdBadge label="SSS Number" hasValue={employee.has_sss_no} />
                    <GovernmentIdBadge label="TIN" hasValue={employee.has_tin} />
                    <GovernmentIdBadge label="PhilHealth" hasValue={employee.has_philhealth_no} />
                    <GovernmentIdBadge label="Pag-IBIG" hasValue={employee.has_pagibig_no} />
                  </div>
                  <div className="mt-4 p-3 bg-neutral-100 rounded">
                    <p className="text-xs text-neutral-700 flex items-start gap-2">
                      <AlertCircle className="h-4 w-4 flex-shrink-0 mt-0.5" />
                      For security reasons, government ID numbers are not displayed. Contact HR if
                      you need to update these details.
                    </p>
                  </div>
                </Card>
              </>
            )}

            {activeTab === 'employment' && (
              <>
                {/* Employment Details */}
                <Card>
                  <CardHeader
                    icon={Briefcase}
                    title="Employment Details"
                    subtitle="Your work information and classification"
                  />
                  <InfoGrid>
                    <InfoRow
                      icon={Building2}
                      label="Department"
                      value={employee.department?.name}
                    />
                    <InfoRow
                      icon={Briefcase}
                      label="Position"
                      value={employee.position?.title}
                    />
                    <InfoRow
                      icon={User}
                      label="Reports To"
                      value={employee.supervisor?.full_name}
                    />
                    <InfoRow
                      icon={FileText}
                      label="Employment Type"
                      value={
                        employee.employment_type
                          ? employee.employment_type.replace(/_/g, ' ').replace(/\b\w/g, (l) =>
                              l.toUpperCase()
                            )
                          : undefined
                      }
                    />
                    <InfoRow
                      icon={TrendingUp}
                      label="Employment Status"
                      value={
                        employee.employment_status
                          ? employee.employment_status.replace(/_/g, ' ').replace(/\b\w/g, (l) =>
                              l.toUpperCase()
                            )
                          : undefined
                      }
                    />
                    <InfoRow
                      icon={Calendar}
                      label="Date Hired"
                      value={new Date(employee.date_hired).toLocaleDateString('en-PH', {
                        year: 'numeric',
                        month: 'long',
                        day: 'numeric',
                      })}
                    />
                    <InfoRow
                      icon={Calendar}
                      label="Regularization Date"
                      value={
                        employee.regularization_date
                          ? new Date(employee.regularization_date).toLocaleDateString('en-PH', {
                              year: 'numeric',
                              month: 'long',
                              day: 'numeric',
                            })
                          : 'Not yet regularized'
                      }
                    />
                    <InfoRow
                      icon={Home}
                      label="Pay Basis"
                      value={
                        employee.pay_basis
                          ? employee.pay_basis.charAt(0).toUpperCase() + employee.pay_basis.slice(1)
                          : undefined
                      }
                    />
                    {employee.current_shift && (
                      <InfoRow
                        icon={Clock4}
                        label="Shift Schedule"
                        value={employee.current_shift.shift_name ?? 'Unknown'}
                      />
                    )}
                  </InfoGrid>
                </Card>

                {/* Compensation */}
                <Card>
                  <CardHeader
                    icon={Banknote}
                    title="Compensation"
                    subtitle="Your salary and rate information"
                  />
                  <InfoGrid>
                    <InfoRow
                      icon={Banknote}
                      label="Basic Monthly Rate"
                      isMonetary
                      centavos={employee.basic_monthly_rate}
                    />
                    <InfoRow
                      icon={TrendingUp}
                      label="Daily Rate"
                      isMonetary
                      centavos={(employee.daily_rate || 0) * 100}
                    />
                    <InfoRow
                      icon={Clock}
                      label="Hourly Rate"
                      isMonetary
                      centavos={(employee.hourly_rate || 0) * 100}
                    />
                    <InfoRow
                      icon={Award}
                      label="Salary Grade"
                      value={employee.salary_grade?.name ?? 'Not assigned'}
                    />
                    <InfoRow
                      icon={Shield}
                      label="BIR Tax Status"
                      value={employee.bir_status ?? 'Not set'}
                    />
                  </InfoGrid>
                </Card>
              </>
            )}

            {activeTab === 'documents' && (
              <DocumentStatusCard />
            )}
          </div>

          {/* Sidebar */}
          <div className="space-y-6">
            {/* Employment Summary Card */}
            <Card>
              <CardHeader icon={Briefcase} title="At a Glance" />
              <div className="space-y-4">
                <div className="flex items-center justify-between py-2 border-b border-neutral-100">
                  <span className="text-sm text-neutral-500">Employee Code</span>
                  <span className="text-sm font-mono font-medium text-neutral-900">
                    {employee.employee_code}
                  </span>
                </div>
                <div className="flex items-center justify-between py-2 border-b border-neutral-100">
                  <span className="text-sm text-neutral-500">Date Hired</span>
                  <span className="text-sm font-medium text-neutral-900">
                    {new Date(employee.date_hired).toLocaleDateString('en-PH', {
                      month: 'short',
                      day: 'numeric',
                      year: 'numeric',
                    })}
                  </span>
                </div>
                <div className="flex items-center justify-between py-2 border-b border-neutral-100">
                  <span className="text-sm text-neutral-500">Tenure</span>
                  <span className="text-sm font-medium text-neutral-900">{tenureText}</span>
                </div>
                <div className="flex items-center justify-between py-2 border-b border-neutral-100">
                  <span className="text-sm text-neutral-500">Employment Type</span>
                  <span className="text-sm font-medium text-neutral-900">
                    {employee.employment_type?.replace(/_/g, ' ').replace(/\b\w/g, (l) =>
                      l.toUpperCase()
                    )}
                  </span>
                </div>
                <div className="flex items-center justify-between py-2">
                  <span className="text-sm text-neutral-500">Pay Basis</span>
                  <span className="text-sm font-medium text-neutral-900">
                    {employee.pay_basis?.charAt(0).toUpperCase() + employee.pay_basis?.slice(1)}
                  </span>
                </div>
              </div>
            </Card>

            {/* Account Status */}
            <Card>
              <CardHeader icon={Shield} title="Account Status" />
              <div className="space-y-3">
                <div className="flex items-center justify-between">
                  <span className="text-sm text-neutral-600">Status</span>
                  <StatusBadge status={employee.employment_status}>{employee.employment_status}</StatusBadge>
                </div>
                <div className="flex items-center justify-between">
                  <span className="text-sm text-neutral-600">Onboarding</span>
                  <span className="text-sm font-medium text-neutral-900">
                    {employee.onboarding_status
                      ?.replace(/_/g, ' ')
                      .replace(/\b\w/g, (l) => l.toUpperCase())}
                  </span>
                </div>
                <div className="flex items-center justify-between">
                  <span className="text-sm text-neutral-600">Active</span>
                  <span
                    className={`inline-flex items-center gap-1.5 text-sm font-medium ${
                      employee.is_active ? 'text-neutral-900' : 'text-neutral-500'
                    }`}
                  >
                    <span
                      className={`h-2 w-2 rounded-full ${
                        employee.is_active ? 'bg-neutral-900' : 'bg-neutral-400'
                      }`}
                    />
                    {employee.is_active ? 'Yes' : 'No'}
                  </span>
                </div>
              </div>
            </Card>

            {/* Quick Links */}
            <Card>
              <CardHeader icon={FileText} title="Quick Links" />
              <div className="space-y-2">
                <a
                  href="#/employee/payslips"
                  className="flex items-center gap-3 p-3 rounded hover:bg-neutral-50 transition-colors group"
                >
                  <div className="h-8 w-8 rounded bg-neutral-100 flex items-center justify-center group-hover:bg-neutral-200 transition-colors">
                    <Banknote className="h-4 w-4 text-neutral-700" />
                  </div>
                  <div className="flex-1">
                    <p className="text-sm font-medium text-neutral-900">My Payslips</p>
                    <p className="text-xs text-neutral-500">View payment history</p>
                  </div>
                </a>
                <a
                  href="#/employee/attendance"
                  className="flex items-center gap-3 p-3 rounded hover:bg-neutral-50 transition-colors group"
                >
                  <div className="h-8 w-8 rounded bg-neutral-100 flex items-center justify-center group-hover:bg-neutral-200 transition-colors">
                    <Clock className="h-4 w-4 text-neutral-700" />
                  </div>
                  <div className="flex-1">
                    <p className="text-sm font-medium text-neutral-900">My Attendance</p>
                    <p className="text-xs text-neutral-500">Check time records</p>
                  </div>
                </a>
                <a
                  href="#/employee/leaves"
                  className="flex items-center gap-3 p-3 rounded hover:bg-neutral-50 transition-colors group"
                >
                  <div className="h-8 w-8 rounded bg-neutral-100 flex items-center justify-center group-hover:bg-neutral-200 transition-colors">
                    <Calendar className="h-4 w-4 text-neutral-700" />
                  </div>
                  <div className="flex-1">
                    <p className="text-sm font-medium text-neutral-900">My Leaves</p>
                    <p className="text-xs text-neutral-500">View leave balances</p>
                  </div>
                </a>
                <a
                  href="#/employee/loans"
                  className="flex items-center gap-3 p-3 rounded hover:bg-neutral-50 transition-colors group"
                >
                  <div className="h-8 w-8 rounded bg-neutral-100 flex items-center justify-center group-hover:bg-neutral-200 transition-colors">
                    <TrendingUp className="h-4 w-4 text-neutral-700" />
                  </div>
                  <div className="flex-1">
                    <p className="text-sm font-medium text-neutral-900">My Loans</p>
                    <p className="text-xs text-neutral-500">Check loan status</p>
                  </div>
                </a>
              </div>
            </Card>
          </div>
        </div>
      )}
    </div>
  )
}
