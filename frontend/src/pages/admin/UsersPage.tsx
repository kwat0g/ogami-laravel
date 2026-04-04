import { useState, useRef, useEffect } from 'react'
import { useDebounce } from '@/hooks/useDebounce'
import { toast } from 'sonner'
import {
  useAdminUsers,
  useCreateAdminUser,
  useProvisionPortalAccount,
  useResetPassword,
  useDisableAdminUser,
  useDeleteAdminUser,
  useAssignRole,
  useUnlockUser,
  useRoles,
  useAdminDepartments,
  useEmployeesAvailable,
  useAvailableVendors,
  useAvailableCustomers,
  type AdminUser,
  type AvailableEmployee,
  type AvailableVendor,
  type AvailableCustomer,
  type PortalAccountCredentials,
  type CreateUserPayload,
  type Department,
} from '@/hooks/useAdmin'
import { useAuthStore } from '@/stores/authStore'
import SkeletonLoader from '@/components/ui/SkeletonLoader'
import StatusBadge from '@/components/ui/StatusBadge'
import { PageHeader } from '@/components/ui/PageHeader'
import { Building2, User, Settings, CheckCircle, ChevronLeft, ChevronRight, Loader2 } from 'lucide-react'
import { firstErrorMessage } from '@/lib/errorHandler'
import ConfirmDialog from '@/components/ui/ConfirmDialog'

// ── Role badge colors ─────────────────────────────────────────────────────────
const roleBadgeClass: Record<string, string> = {
  admin:          'bg-neutral-100 text-neutral-700',
  executive:      'bg-neutral-100 text-neutral-700',
  vice_president: 'bg-neutral-100 text-neutral-700',
  manager:        'bg-neutral-100 text-neutral-700',
  officer:        'bg-neutral-100 text-neutral-700',
  head:           'bg-neutral-100 text-neutral-700',
  staff:          'bg-neutral-100 text-neutral-600',
}

// ── Department-scoped manager roles ──────────────────────────────────────────
// In the new 7-role model, manager/officer/head are generic cross-department
// roles. No forced department mapping — admin assigns department manually.
const ROLE_DEPT_MAP: Record<string, string> = {}
const SCOPED_MANAGER_ROLES = Object.keys(ROLE_DEPT_MAP)

// ── Role-change modal state ───────────────────────────────────────────────────
interface RoleModal {
  userId:      number
  userName:    string
  currentRole: string
}

// ── Create wizard state ───────────────────────────────────────────────────────
type WizardStep = 1 | 2 | 3

interface WizardState {
  step:         WizardStep
  department:   Department | null
  employee:     AvailableEmployee | null
  vendor:       AvailableVendor | null
  customer:     AvailableCustomer | null
  targetSearch: string
  name:         string
  email:        string
  password:     string
  role:         string
  // roles like admin/executive don't need an employee
  skipEmployee: boolean
}

const emptyWizard = (): WizardState => ({
  step: 1, department: null, employee: null,
  vendor: null, customer: null, targetSearch: '',
  name: '', email: '', password: '', role: '', skipEmployee: false,
})

type PortalRole = 'vendor' | 'client'

const PORTAL_ROLES: PortalRole[] = ['vendor', 'client']

function isPortalRole(role: string): role is PortalRole {
  return PORTAL_ROLES.includes(role as PortalRole)
}

// Roles that don't require a linked employee (system/board-level + external portals)
const ROLES_WITHOUT_EMPLOYEE = ['admin', 'executive', ...PORTAL_ROLES]

// ── Form validation helpers ───────────────────────────────────────────────────
interface ValidationError {
  field: string
  message: string
}

function validateUserForm(data: WizardState): ValidationError[] {
  const errors: ValidationError[] = []
  
  if (!data.role) {
    errors.push({ field: 'role', message: 'Role is required.' })
  }
  
  if (!isPortalRole(data.role)) {
    if (!data.skipEmployee && !data.department) {
      errors.push({ field: 'department', message: 'Department is required.' })
    }
    
    if (!data.skipEmployee && !data.employee && data.step >= 2) {
      errors.push({ field: 'employee', message: 'Employee selection is required.' })
    }
    
    if (data.step === 3) {
      if (!data.name.trim()) {
        errors.push({ field: 'name', message: 'Name is required.' })
      }
      if (!data.email.trim()) {
        errors.push({ field: 'email', message: 'Email is required.' })
      } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(data.email)) {
        errors.push({ field: 'email', message: 'Please enter a valid email address.' })
      }
      if (!data.password) {
        errors.push({ field: 'password', message: 'Password is required.' })
      } else if (data.password.length < 8) {
        errors.push({ field: 'password', message: 'Password must be at least 8 characters.' })
      }
    }
  } else {
    // Portal role validation
    if (data.step === 2) {
      const target = data.role === 'vendor' ? data.vendor : data.customer
      if (!target) {
        errors.push({ field: 'target', message: `${data.role === 'vendor' ? 'Vendor' : 'Customer'} selection is required.` })
      }
    }
  }
  
  return errors
}

export default function UsersPage() {
  const { hasPermission } = useAuthStore()

  const canCreate     = hasPermission('system.manage_users')
  const canUpdate     = hasPermission('system.manage_users')
  const canDisable    = hasPermission('system.manage_users')
  const canAssignRole = hasPermission('system.assign_roles')

  // Separate local state for search input to prevent focus loss
  const [searchInput, setSearchInput] = useState('')
  const [isArchiveView, setIsArchiveView] = useState(false)
  const [filters, setFilters] = useState({ search: '', role: '', page: 1, per_page: 15 })
  
  // Debounce search to prevent excessive API calls
  const debouncedSearch = useDebounce(searchInput, 400)
  
  // Update filters when debounced search changes
  useEffect(() => {
    setFilters((f) => ({ ...f, search: debouncedSearch, page: 1 }))
  }, [debouncedSearch])
  
  const { data, isLoading, isError, isFetching } = useAdminUsers(filters)
  
  // Ref for search input to maintain focus
  const searchInputRef = useRef<HTMLInputElement>(null)
  const { data: roles = [] }         = useRoles()
  const { data: departments = [] }   = useAdminDepartments()

  const create     = useCreateAdminUser()
  const provisionPortal = useProvisionPortalAccount()
  const reset     = useResetPassword()
  const disable    = useDisableAdminUser()
  const _removeUser = useDeleteAdminUser()
  const assignRole = useAssignRole()
  const unlock     = useUnlockUser()

  // Wizard state
  const [wizard, setWizard]         = useState<WizardState | null>(null)
  const [wizardError, setWizardError] = useState<string | null>(null)
  const [portalCredentials, setPortalCredentials] = useState<PortalAccountCredentials | null>(null)
  const [copiedCredentials, setCopiedCredentials] = useState(false)

  // Reset Password State
  const [resetResult, setResetResult] = useState<{ name: string; password: string } | null>(null)

  // Role modal
  const [roleModal, setRoleModal]   = useState<RoleModal | null>(null)
  const [newRole, setNewRole]       = useState('')

  // Destructive action targets
  // Delete action was removed per requirements

  const rows = data?.data ?? []
  const meta = data?.meta
  const debouncedTargetSearch = useDebounce(wizard?.targetSearch ?? '', 400)

  // ── Available employees for step 2 ───────────────────────────────────────
  const { data: availableEmployees = [], isLoading: empLoading } = useEmployeesAvailable(
    wizard?.step === 2 && !wizard.skipEmployee ? (wizard.department?.id ?? null) : null
  )

  const { data: availableVendors = [], isLoading: vendorsLoading } = useAvailableVendors(
    { search: debouncedTargetSearch, limit: 100 },
    Boolean(wizard?.step === 2 && wizard?.role === 'vendor')
  )

  const { data: availableCustomers = [], isLoading: customersLoading } = useAvailableCustomers(
    { search: debouncedTargetSearch, limit: 100 },
    Boolean(wizard?.step === 2 && wizard?.role === 'client')
  )

  const isPortalTargetLoading = vendorsLoading || customersLoading

  // ── Wizard helpers ────────────────────────────────────────────────────────
  const openCreate = () => { setWizard(emptyWizard()); setWizardError(null) }
  const closeWizard = () => { setWizard(null); setWizardError(null) }
  const setWizardField = <K extends keyof WizardState>(k: K, v: WizardState[K]) =>
    setWizard((w) => w ? { ...w, [k]: v } : w)

  const goStep1to2 = () => {
    if (!wizard) return
    
    // Client-side validation
    const errors = validateUserForm(wizard)
    const roleError = errors.find(e => e.field === 'role')
    if (roleError) {
      setWizardError(roleError.message)
      return
    }

    if (isPortalRole(wizard.role)) {
      setWizardError(null)
      setWizard((w) => w ? { ...w, step: 2, targetSearch: '', employee: null, department: null } : w)
      return
    }

    // Skip dept/employee for admin & executive
    if (wizard.skipEmployee) {
      setWizardError(null)
      setWizard((w) => w ? { ...w, step: 3 } : w)
      return
    }

    const deptError = errors.find(e => e.field === 'department')
    if (deptError) { setWizardError(deptError.message); return }
    
    setWizardError(null)
    setWizard((w) => w ? { ...w, step: 2 } : w)
  }

  const goStep2to3 = () => {
    if (!wizard) return

    if (isPortalRole(wizard.role)) {
      const selectedTarget = wizard.role === 'vendor' ? wizard.vendor : wizard.customer
      if (!selectedTarget) {
        setWizardError(`Please select a ${wizard.role === 'vendor' ? 'vendor' : 'customer'}.`)
        return
      }

      setWizardError(null)
      setWizard((w) => w ? { ...w, step: 3 } : w)
      return
    }

    if (!wizard.employee) { setWizardError('Please select an employee.'); return }
    const employee = wizard.employee
    setWizardError(null)
    // Pre-fill name from employee
    setWizard((w) => w ? {
      ...w,
      step: 3,
      name: w.name || `${employee.first_name} ${employee.last_name}`,
    } : w)
  }

  const handleWizardSubmit = () => {
    if (!wizard) return
    setWizardError(null)

    // Client-side validation
    const errors = validateUserForm(wizard)
    if (errors.length > 0) {
      setWizardError(errors[0].message)
      return
    }

    if (isPortalRole(wizard.role)) {
      const target = wizard.role === 'vendor' ? wizard.vendor : wizard.customer
      if (!target) {
        setWizardError(`Please select a ${wizard.role === 'vendor' ? 'vendor' : 'customer'} account.`)
        return
      }

      provisionPortal.mutate(
        { role: wizard.role, targetId: target.id },
        {
          onSuccess: (credentials) => {
            closeWizard()
            setPortalCredentials(credentials)
            setCopiedCredentials(false)
            toast.success(`${wizard.role === 'vendor' ? 'Vendor' : 'Client'} portal account created.`)
          },
          onError: (e: unknown) => {
            toast.error(firstErrorMessage(e) || 'Failed to create portal account.')
            setWizardError(firstErrorMessage(e) || 'Failed to create portal account.')
          },
        }
      )

      return
    }

    const payload: CreateUserPayload = {
      name:        wizard.name,
      email:       wizard.email,
      password:    wizard.password,
      role:        wizard.role,
      employee_id: wizard.employee?.id ?? null,
    }

    create.mutate(payload, {
      onSuccess: () => {
        closeWizard()
        toast.success('User created successfully.')
      },
      onError: (e: unknown) => {
        const msg = firstErrorMessage(e)
        setWizardError(msg)
      },
    })
  }

  const handleCopyCredentials = async () => {
    if (!portalCredentials) return

    try {
      await navigator.clipboard.writeText(`Email: ${portalCredentials.email}\nPassword: ${portalCredentials.password}`)
      setCopiedCredentials(true)
      setTimeout(() => setCopiedCredentials(false), 2000)
      toast.success('Credentials copied to clipboard.')
    } catch (err) {
      toast.error(firstErrorMessage(err, 'Failed to copy credentials. Please copy manually.'))
    }
  }

  // ── Reset Password helpers ──────────────────────────────────────────────
  const executeResetPassword = (u: AdminUser) => {
    reset.mutate(u.id, {
      onSuccess: (data) => {
        setResetResult({ name: u.name, password: data.password })
        toast.success('Password reset successfully.')
      },
      onError: (e: unknown) => toast.error(firstErrorMessage(e) || 'Failed to reset password.'),
    })
  }

  const executeDisable = (u: AdminUser) => {
    return disable.mutateAsync(u.id)
      .then(() => toast.success('User account disabled.'))
      .catch((e: unknown) => { toast.error(firstErrorMessage(e) || 'Failed to disable user account.'); throw e; })
  }

  const handleUnlock = (u: AdminUser) => {
    unlock.mutate(u.id, {
      onSuccess: () => toast.success('User account unlocked.'),
      onError: (e: unknown) => toast.error(firstErrorMessage(e) || 'Failed to unlock user account.'),
    })
  }

  const openRoleModal = (u: AdminUser) => {
    setRoleModal({ userId: u.id, userName: u.name, currentRole: u.roles[0]?.name ?? '' })
    setNewRole(u.roles[0]?.name ?? '')
  }

  const handleAssignRole = () => {
    if (!roleModal || !newRole) return
    assignRole.mutate(
      { userId: roleModal.userId, role: newRole },
      { 
        onSuccess: () => {
          setRoleModal(null)
          toast.success('Role assigned successfully.')
        },
        onError: (e: unknown) => toast.error(firstErrorMessage(e) || 'Failed to assign role.'),
      }
    )
  }

  const isLocked = (u: AdminUser) => u.locked_until && new Date(u.locked_until) > new Date()
  const isDisabled = (u: AdminUser) => {
    if (!u.locked_until) return false
    const diffInYears = (new Date(u.locked_until).getTime() - Date.now()) / (1000 * 60 * 60 * 24 * 365)
    return diffInYears > 1
  }

  // Only show skeleton on initial load, not during refetch (to preserve focus)
  if (isLoading && !isFetching) return <SkeletonLoader rows={10} />
  if (isError)   return <p className="text-neutral-700 text-sm mt-4">Failed to load users.</p>

  return (
    <div>
      <PageHeader title="Users" />

      {/* ── Filters ─────────────────────────────────────────────────────── */}
      <div className="flex gap-3 mb-4 items-center">
        <div className="relative">
          <input
            ref={searchInputRef}
            type="text"
            placeholder="Search name or email…"
            value={searchInput}
            onChange={(e) => setSearchInput(e.target.value)}
            className="border border-neutral-300 rounded px-3 py-2 text-sm w-64 focus:outline-none focus:ring-1 focus:ring-neutral-400 pr-8"
          />
          {isFetching && (
            <Loader2 className="absolute right-2 top-1/2 -translate-y-1/2 h-4 w-4 animate-spin text-neutral-400" />
          )}
        </div>
        <select
          value={filters.role}
          onChange={(e) => setFilters((f) => ({ ...f, role: e.target.value, page: 1 }))}
          className="border border-neutral-300 rounded px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-neutral-400"
        >
          <option value="">All Roles</option>
          {roles.map((r) => (
            <option key={r.id} value={r.name}>{r.name}</option>
          ))}
        </select>
        <div className="flex-1" />
        {canCreate && (
          <button
            onClick={openCreate}
            className="bg-neutral-900 hover:bg-neutral-800 text-white text-sm font-medium px-4 py-2 rounded transition-colors"
          >
            + Create User
          </button>
        )}
      </div>

      {/* ── Table ───────────────────────────────────────────────────────── */}
      <div className="bg-white border border-neutral-200 rounded-xl overflow-hidden">
        <table className="min-w-full text-sm">
          <thead className="bg-neutral-50 border-b border-neutral-200">
            <tr>
              {['Name / Email', 'Linked Employee', 'Role', 'Status', 'Last Login', 'Actions'].map((h) => (
                <th key={h} className="px-3 py-2.5 text-left text-xs font-semibold text-neutral-600">{h}</th>
              ))}
            </tr>
          </thead>
          <tbody className="divide-y divide-neutral-100">
            {rows.length === 0 && (
              <tr>
                <td colSpan={6} className="px-3 py-8 text-center text-neutral-400 text-sm">No users found.</td>
              </tr>
            )}
            {rows.map((u) => (
              <tr key={u.id} className="even:bg-neutral-100 hover:bg-neutral-50 transition-colors">
                <td className="px-3 py-2">
                  <p className="font-medium text-neutral-900">{u.name}</p>
                  <p className="text-xs text-neutral-500">{u.email}</p>
                </td>
                <td className="px-3 py-2">
                  {u.employee ? (
                    <div>
                      <p className="text-sm text-neutral-800 font-medium">{u.employee.first_name} {u.employee.last_name}</p>
                      <p className="text-xs text-neutral-500">{u.employee.employee_code} · {u.employee.department?.name ?? '—'}</p>
                    </div>
                  ) : (
                    <span className="text-xs text-neutral-400 italic">No employee record</span>
                  )}
                </td>
                <td className="px-3 py-2">
                  {u.roles.length > 0 ? (
                    <span className={`inline-flex items-center px-2.5 py-0.5 rounded text-xs font-medium ${roleBadgeClass[u.roles[0].name] ?? 'bg-neutral-100 text-neutral-600'}`}>
                      {u.roles[0].name}
                    </span>
                  ) : (
                    <span className="text-neutral-400 text-xs">—</span>
                  )}
                </td>
                <td className="px-3 py-2">
                  {isDisabled(u) ? (
                    <StatusBadge status="locked">Disabled</StatusBadge>
                  ) : isLocked(u) ? (
                    <StatusBadge status="locked">Locked</StatusBadge>
                  ) : (
                    <StatusBadge status="active">Active</StatusBadge>
                  )}
                  {u.failed_login_attempts > 0 && (
                    <span className="ml-2 text-xs text-neutral-600">{u.failed_login_attempts} failed</span>
                  )}
                </td>
                <td className="px-4 py-3 text-xs text-neutral-500">
                  {u.last_login_at ? new Date(u.last_login_at).toLocaleString() : '—'}
                </td>
                <td className="px-3 py-2">
                  <div className="flex gap-2">
                    {canUpdate && (
                      <ConfirmDialog
                        title="Reset Password?"
                        description={`This will generate a new temporary password for ${u.name}. The current password will no longer work.`}
                        confirmLabel="Reset Password"
                        variant="danger"
                        onConfirm={() => executeResetPassword(u)}
                      >
                        <button className="px-2 py-1 text-xs border border-neutral-200 rounded bg-white text-neutral-600 hover:bg-neutral-50 hover:border-neutral-300 hover:text-neutral-900 font-medium">
                          Reset Password
                        </button>
                      </ConfirmDialog>
                    )}
                    {canAssignRole && (
                      <button onClick={() => openRoleModal(u)} className="px-2 py-1 text-xs border border-neutral-200 rounded bg-white text-neutral-600 hover:bg-neutral-50 hover:border-neutral-300 hover:text-neutral-900 font-medium">Role</button>
                    )}
                    {canUpdate && isLocked(u) && (
                      <button onClick={() => handleUnlock(u)} disabled={unlock.isPending} className="px-2 py-1 text-xs border border-neutral-200 rounded bg-white text-neutral-600 hover:bg-neutral-50 hover:border-neutral-300 hover:text-neutral-900 font-medium disabled:opacity-50 disabled:cursor-not-allowed">
                        {isDisabled(u) ? 'Enable' : 'Unlock'}
                      </button>
                    )}
                    {canDisable && !isDisabled(u) && (
                      <ConfirmDialog
                        title="Disable User Account?"
                        description={`${u.name} (${u.email}) will not be able to sign in until their account is enabled.`}
                        confirmLabel="Disable Account"
                        variant="danger"
                        onConfirm={() => executeDisable(u)}
                      >
                        <button className="text-xs text-red-600 hover:text-red-700 font-medium">
                          Disable
                        </button>
                      </ConfirmDialog>
                    )}
                  </div>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>

      {/* ── Pagination ──────────────────────────────────────────────────── */}
      {meta && meta.last_page > 1 && (
        <div className="flex items-center justify-between mt-4 text-sm text-neutral-600">
          <span>Page {meta.current_page} of {meta.last_page} — {meta.total} total</span>
          <div className="flex gap-2">
            <button disabled={meta.current_page <= 1} onClick={() => setFilters((f) => ({ ...f, page: f.page! - 1 }))} className="px-3 py-1 border border-neutral-300 rounded disabled:opacity-40 disabled:cursor-not-allowed hover:bg-neutral-50">Previous</button>
            <button disabled={meta.current_page >= meta.last_page} onClick={() => setFilters((f) => ({ ...f, page: f.page! + 1 }))} className="px-3 py-1 border border-neutral-300 rounded disabled:opacity-40 disabled:cursor-not-allowed hover:bg-neutral-50">Next</button>
          </div>
        </div>
      )}

      {/* ── Create User Wizard ───────────────────────────────────────────── */}
      {wizard && (
        <Modal title="Create User Account" onClose={closeWizard} wide>
          <CreateUserWizard
            wizard={wizard}
            roles={roles}
            departments={departments}
            availableEmployees={availableEmployees}
            availableVendors={availableVendors}
            availableCustomers={availableCustomers}
            empLoading={empLoading}
            targetLoading={isPortalTargetLoading}
            isPending={create.isPending || provisionPortal.isPending}
            error={wizardError}
            onSetField={setWizardField}
            onNext1={goStep1to2}
            onNext2={goStep2to3}
            onSubmit={handleWizardSubmit}
            onBack={() => setWizard((w) => w ? { ...w, step: w.step > 1 ? (w.step - 1) as WizardStep : 1 } : w)}
            onClose={closeWizard}
          />
        </Modal>
      )}

      {/* ── Portal credentials modal ─────────────────────────────────────── */}
      {portalCredentials && (
        <Modal
          title={`${portalCredentials.role === 'vendor' ? 'Vendor' : 'Client'} Portal Account Created`}
          onClose={() => setPortalCredentials(null)}
        >
          <div className="space-y-4">
            <p className="text-sm text-neutral-700">
              Share these credentials securely. The user will be prompted to change password on first login.
            </p>

            <div className="bg-neutral-50 border border-neutral-200 rounded p-3 space-y-2">
              <div className="flex justify-between gap-2 text-sm">
                <span className="text-neutral-500">Email</span>
                <span className="text-neutral-900 font-medium break-all text-right">{portalCredentials.email}</span>
              </div>
              <div className="flex justify-between gap-2 text-sm">
                <span className="text-neutral-500">Password</span>
                <span className="text-neutral-900 font-mono font-medium break-all text-right">{portalCredentials.password}</span>
              </div>
            </div>

            <div className="flex justify-end gap-3 pt-1">
              <button onClick={() => setPortalCredentials(null)} className={btnSecondary}>Done</button>
              <button onClick={handleCopyCredentials} className={btnPrimary}>{copiedCredentials ? 'Copied!' : 'Copy Credentials'}</button>
            </div>
          </div>
        </Modal>
      )}

      {/* ── Password Reset Success Modal ─────────────────────────────────── */}
      {resetResult && (
        <Modal title="Password Reset Successful" onClose={() => setResetResult(null)}>
          <div className="space-y-4">
            <p className="text-sm text-neutral-600">
              The password for <strong>{resetResult.name}</strong> has been reset.
              Please share these credentials securely.
            </p>
            <div className="bg-neutral-50 border border-neutral-200 rounded p-4 flex items-center justify-between gap-3">
              <span className="font-mono text-lg font-bold select-all text-neutral-900">{resetResult.password}</span>
              <button
                onClick={() => { navigator.clipboard.writeText(resetResult.password); toast.success('Copied!') }}
                className="shrink-0 px-3 py-1.5 text-xs border border-neutral-300 rounded bg-white hover:bg-neutral-100 text-neutral-600 font-medium"
              >
                Copy
              </button>
            </div>
            <div className="flex justify-end pt-2">
              <button onClick={() => setResetResult(null)} className={btnPrimary}>Done</button>
            </div>
          </div>
        </Modal>
      )}

      {/* ── Role change modal ────────────────────────────────────────────── */}
      {roleModal && (
        <Modal title={`Change Role — ${roleModal.userName}`} onClose={() => setRoleModal(null)}>
          <div className="space-y-4">
            <p className="text-sm text-neutral-600">Current role: <strong>{roleModal.currentRole || '—'}</strong></p>
            <FormField label="New Role">
              <select value={newRole} onChange={(e) => setNewRole(e.target.value)} className={inputCls}>
                <option value="">Select role…</option>
                {roles.map((r) => (<option key={r.id} value={r.name}>{r.name}</option>))}
              </select>
            </FormField>
            <div className="flex justify-end gap-3 pt-2">
              <button onClick={() => setRoleModal(null)} className={btnSecondary}>Cancel</button>
              <button onClick={handleAssignRole} disabled={assignRole.isPending || !newRole} className={btnPrimary}>{assignRole.isPending ? 'Saving…' : 'Assign Role'}</button>
            </div>
          </div>
        </Modal>
      )}


    </div>
  )
}

// ── Create User Wizard component ──────────────────────────────────────────────
interface WizardProps {
  wizard:             WizardState
  roles:              Array<{ id: number; name: string }>
  departments:        Department[]
  availableEmployees: AvailableEmployee[]
  availableVendors:   AvailableVendor[]
  availableCustomers: AvailableCustomer[]
  empLoading:         boolean
  targetLoading:      boolean
  isPending:          boolean
  error:              string | null
  onSetField:         <K extends keyof WizardState>(k: K, v: WizardState[K]) => void
  onNext1:            () => void
  onNext2:            () => void
  onSubmit:           () => void
  onBack:             () => void
  onClose:            () => void
}

function CreateUserWizard({
  wizard, roles, departments, availableEmployees, availableVendors, availableCustomers, empLoading, targetLoading,
  isPending, error, onSetField, onNext1, onNext2, onSubmit, onBack, onClose,
}: WizardProps) {
  const portalRole = isPortalRole(wizard.role)
  const portalTargetName = wizard.role === 'vendor' ? 'Vendor' : 'Customer'

  const steps = portalRole
    ? [
      { label: 'Role', icon: Settings },
      { label: portalTargetName, icon: User },
      { label: 'Account', icon: Settings },
    ]
    : [
      { label: 'Department', icon: Building2 },
      { label: 'Employee', icon: User },
      { label: 'Account', icon: Settings },
    ]

  // Determine effective step count for non-portal skip roles (admin/executive).
  const effectiveStep = wizard.skipEmployee && !portalRole && wizard.step === 3 ? 2 : wizard.step
  const selectedPortalId = wizard.role === 'vendor' ? wizard.vendor?.id : wizard.customer?.id
  const selectedPortalName = wizard.role === 'vendor' ? wizard.vendor?.name : wizard.customer?.name
  const selectedPortalEmail = wizard.role === 'vendor' ? wizard.vendor?.email : wizard.customer?.email
  const selectedPortalContact = wizard.role === 'vendor'
    ? wizard.vendor?.contact_person
    : wizard.customer?.contact_person

  return (
    <div className="space-y-6">
      {/* Step indicator */}
      <div className="flex items-center">
        {steps.map((s, i) => {
          const stepNum = (i + 1) as WizardStep
          const done = effectiveStep > stepNum || (wizard.skipEmployee && !portalRole && stepNum === 2)
          const active = effectiveStep === stepNum && !(wizard.skipEmployee && !portalRole && stepNum === 2)
          return (
            <div key={s.label} className="flex items-center flex-1 min-w-0">
              <div className={`flex items-center gap-1.5 shrink-0 ${
                done   ? 'text-neutral-600' :
                active ? 'text-neutral-600'  : 'text-neutral-400'
              }`}>
                {done
                  ? <CheckCircle className="h-5 w-5" />
                  : <div className={`h-5 w-5 rounded-full border-2 flex items-center justify-center text-[10px] font-bold ${active ? 'border-neutral-600 text-neutral-600' : 'border-neutral-300 text-neutral-400'}`}>{stepNum}</div>
                }
                <span className={`text-xs font-medium ${wizard.skipEmployee && !portalRole && stepNum === 2 ? 'line-through opacity-40' : ''}`}>{s.label}</span>
              </div>
              {i < steps.length - 1 && <div className={`flex-1 mx-2 h-px ${done ? 'bg-neutral-400' : 'bg-neutral-200'}`} />}
            </div>
          )
        })}
      </div>

      {/* ── Step 1: Department ──────────────────────────────────────────── */}
      {wizard.step === 1 && (
        <div className="space-y-4">
          {!portalRole ? (
            <div className="bg-neutral-50 border border-neutral-200 rounded p-3 text-sm text-neutral-800">
              Every ERP user must be linked to an employee record. Start by selecting their department.
              <br />
              <span className="text-xs text-neutral-600">Admin and Executive accounts can be created without an employee record.</span>
            </div>
          ) : (
            <div className="bg-neutral-50 border border-neutral-200 rounded p-3 text-sm text-neutral-800">
              Portal accounts are linked to an existing {portalTargetName.toLowerCase()} record.
              The account email comes from that record, and password is auto-generated by the backend.
            </div>
          )}

          <FormField label="Role *">
            <select
              value={wizard.role}
              onChange={(e) => {
                const role = e.target.value
                const nextIsPortal = isPortalRole(role)
                onSetField('role', role)
                onSetField('skipEmployee', ROLES_WITHOUT_EMPLOYEE.includes(role))
                onSetField('employee', null)
                onSetField('vendor', null)
                onSetField('customer', null)
                onSetField('targetSearch', '')

                if (nextIsPortal) {
                  onSetField('department', null)
                  return
                }

                // Auto-select the required department for dept-scoped manager roles
                if (ROLE_DEPT_MAP[role]) {
                  const requiredCode = ROLE_DEPT_MAP[role]
                  const matchingDept = departments.find((d) => d.code === requiredCode) ?? null
                  onSetField('department', matchingDept)
                }
              }}
              className={inputCls}
            >
              <option value="">Select role…</option>
              {roles.map((r) => (<option key={r.id} value={r.name}>{r.name}</option>))}
            </select>
          </FormField>

          {/* Hint for dept-scoped manager roles */}
          {!portalRole && SCOPED_MANAGER_ROLES.includes(wizard.role) && (
            <div className="bg-neutral-50 border border-neutral-200 rounded p-3 text-xs text-neutral-800">
              <strong>{wizard.role}</strong> is restricted to the{' '}
              <strong>
                {departments.find((d) => d.code === ROLE_DEPT_MAP[wizard.role])?.name ?? ROLE_DEPT_MAP[wizard.role]}
              </strong>{' '}
              department. The department has been auto-selected and cannot be changed for this role.
            </div>
          )}

          {!wizard.skipEmployee && !portalRole && (
            <FormField label="Department *">
              <select
                value={wizard.department?.id ?? ''}
                disabled={SCOPED_MANAGER_ROLES.includes(wizard.role)}
                onChange={(e) => {
                  const d = departments.find((d) => d.id === Number(e.target.value)) ?? null
                  onSetField('department', d)
                  // If the new dept conflicts with the current scoped manager role, clear the role
                  if (wizard.role && ROLE_DEPT_MAP[wizard.role] && d?.code !== ROLE_DEPT_MAP[wizard.role]) {
                    onSetField('role', '')
                    onSetField('skipEmployee', false)
                  }
                }}
                className={`${inputCls} ${SCOPED_MANAGER_ROLES.includes(wizard.role) ? 'opacity-60 cursor-not-allowed' : ''}`}
              >
                <option value="">Select department…</option>
                {departments.map((d) => (<option key={d.id} value={d.id}>{d.name}</option>))}
              </select>
            </FormField>
          )}

          {wizard.skipEmployee && !portalRole && (
            <div className="bg-neutral-50 border border-neutral-200 rounded p-3 text-sm text-neutral-800">
              <strong>{wizard.role}</strong> accounts do not require a linked employee. You'll fill account details in the next step.
            </div>
          )}

          {wizard.skipEmployee && portalRole && (
            <div className="bg-neutral-50 border border-neutral-200 rounded p-3 text-sm text-neutral-800">
              Continue to select which {portalTargetName.toLowerCase()} should receive this portal account.
            </div>
          )}

          {error && <p className="text-red-600 text-sm">{error}</p>}

          <div className="flex justify-end gap-3 pt-2">
            <button onClick={onClose} className={btnSecondary}>Cancel</button>
            <button 
              onClick={onNext1} 
              disabled={!wizard.role || (!wizard.skipEmployee && !wizard.department)} 
              className={btnPrimary}
            >
              Next <ChevronRight className="inline h-4 w-4 ml-1" />
            </button>
          </div>
        </div>
      )}

      {/* ── Step 2: Employee selection ──────────────────────────────────── */}
      {wizard.step === 2 && !wizard.skipEmployee && !portalRole && (
        <div className="space-y-4">
          <p className="text-sm text-neutral-600">
            Select an employee from <strong>{wizard.department?.name}</strong> to link to the new user account.
            Only employees without existing accounts are shown.
          </p>

          {empLoading ? (
            <div className="flex items-center gap-2 text-sm text-neutral-400 py-4">
              <Loader2 className="h-4 w-4 animate-spin" /> Loading employees…
            </div>
          ) : availableEmployees.length === 0 ? (
            <div className="bg-neutral-50 border border-neutral-200 rounded p-4 text-sm text-neutral-800">
              No available employees in <strong>{wizard.department?.name}</strong>. All employees in this department already have user accounts, or there are no active employees.
            </div>
          ) : (
            <div className="space-y-2 max-h-64 overflow-y-auto pr-1">
              {availableEmployees.map((emp) => (
                <button
                  key={emp.id}
                  type="button"
                  onClick={() => onSetField('employee', emp)}
                  className={`w-full text-left px-4 py-3 rounded border transition-colors ${
                    wizard.employee?.id === emp.id
                      ? 'border-neutral-500 bg-neutral-50'
                      : 'border-neutral-200 hover:border-neutral-400 hover:bg-neutral-50'
                  }`}
                >
                  <p className="font-medium text-neutral-800 text-sm">{emp.first_name} {emp.last_name}</p>
                  <p className="text-xs text-neutral-500">{emp.employee_code} · {emp.department_name}</p>
                </button>
              ))}
            </div>
          )}

          {error && <p className="text-red-600 text-sm">{error}</p>}

          <div className="flex justify-between gap-3 pt-2">
            <button onClick={onBack} className={btnSecondary}>
              <ChevronLeft className="inline h-4 w-4 mr-1" /> Back
            </button>
            <button onClick={onNext2} disabled={!wizard.employee} className={btnPrimary}>
              Next <ChevronRight className="inline h-4 w-4 ml-1" />
            </button>
          </div>
        </div>
      )}

      {/* ── Step 2: Portal target selection (Vendor / Client) ───────────── */}
      {wizard.step === 2 && portalRole && (
        <div className="space-y-4">
          <p className="text-sm text-neutral-600">
            Select the {portalTargetName.toLowerCase()} record to provision. Only records with email and without existing portal user are shown.
          </p>

          <FormField label={`Search ${portalTargetName}`}>
            <input
              value={wizard.targetSearch}
              onChange={(e) => onSetField('targetSearch', e.target.value)}
              className={inputCls}
              placeholder={`Search ${portalTargetName.toLowerCase()} by name or email...`}
            />
          </FormField>

          {targetLoading ? (
            <div className="flex items-center gap-2 text-sm text-neutral-400 py-4">
              <Loader2 className="h-4 w-4 animate-spin" /> Loading {portalTargetName.toLowerCase()}s...
            </div>
          ) : wizard.role === 'vendor' ? (
            availableVendors.length === 0 ? (
              <div className="bg-neutral-50 border border-neutral-200 rounded p-4 text-sm text-neutral-800">
                No available vendors found. Ensure vendor records are active, accredited, and have an email.
              </div>
            ) : (
              <div className="space-y-2 max-h-64 overflow-y-auto pr-1">
                {availableVendors.map((vendor) => (
                  <button
                    key={vendor.id}
                    type="button"
                    onClick={() => {
                      onSetField('vendor', vendor)
                      onSetField('customer', null)
                    }}
                    className={`w-full text-left px-4 py-3 rounded border transition-colors ${
                      wizard.vendor?.id === vendor.id
                        ? 'border-neutral-500 bg-neutral-50'
                        : 'border-neutral-200 hover:border-neutral-400 hover:bg-neutral-50'
                    }`}
                  >
                    <div className="flex items-center justify-between gap-2">
                      <p className="font-medium text-neutral-800 text-sm">{vendor.name}</p>
                      <span className="text-[11px] px-2 py-0.5 rounded bg-neutral-100 text-neutral-600">
                        {vendor.accreditation_status}
                      </span>
                    </div>
                    <p className="text-xs text-neutral-500 mt-0.5">{vendor.email}</p>
                    {vendor.contact_person && <p className="text-xs text-neutral-500">Contact: {vendor.contact_person}</p>}
                  </button>
                ))}
              </div>
            )
          ) : availableCustomers.length === 0 ? (
            <div className="bg-neutral-50 border border-neutral-200 rounded p-4 text-sm text-neutral-800">
              No available customers found. Ensure customer records are active and have an email.
            </div>
          ) : (
            <div className="space-y-2 max-h-64 overflow-y-auto pr-1">
              {availableCustomers.map((customer) => (
                <button
                  key={customer.id}
                  type="button"
                  onClick={() => {
                    onSetField('customer', customer)
                    onSetField('vendor', null)
                  }}
                  className={`w-full text-left px-4 py-3 rounded border transition-colors ${
                    wizard.customer?.id === customer.id
                      ? 'border-neutral-500 bg-neutral-50'
                      : 'border-neutral-200 hover:border-neutral-400 hover:bg-neutral-50'
                  }`}
                >
                  <p className="font-medium text-neutral-800 text-sm">{customer.name}</p>
                  <p className="text-xs text-neutral-500 mt-0.5">{customer.email}</p>
                  {customer.contact_person && <p className="text-xs text-neutral-500">Contact: {customer.contact_person}</p>}
                </button>
              ))}
            </div>
          )}

          {error && <p className="text-red-600 text-sm">{error}</p>}

          <div className="flex justify-between gap-3 pt-2">
            <button onClick={onBack} className={btnSecondary}>
              <ChevronLeft className="inline h-4 w-4 mr-1" /> Back
            </button>
            <button onClick={onNext2} disabled={!selectedPortalId} className={btnPrimary}>
              Next <ChevronRight className="inline h-4 w-4 ml-1" />
            </button>
          </div>
        </div>
      )}

      {/* ── Step 3: Account details ─────────────────────────────────────── */}
      {wizard.step === 3 && (
        <div className="space-y-4">
          {!portalRole && wizard.employee && (
            <div className="bg-neutral-50 border border-neutral-200 rounded px-4 py-3 text-sm">
              <p className="font-medium text-neutral-800">Linked Employee</p>
              <p className="text-neutral-700">{wizard.employee.first_name} {wizard.employee.last_name} · {wizard.employee.employee_code} · {wizard.employee.department_name}</p>
            </div>
          )}

          {portalRole && selectedPortalName && selectedPortalEmail && (
            <>
              <div className="bg-neutral-50 border border-neutral-200 rounded px-4 py-3 text-sm space-y-1">
                <p className="font-medium text-neutral-800">Selected {portalTargetName}</p>
                <p className="text-neutral-700">{selectedPortalName}</p>
                <p className="text-neutral-500 text-xs">{selectedPortalEmail}</p>
                {selectedPortalContact && <p className="text-neutral-500 text-xs">Contact: {selectedPortalContact}</p>}
              </div>

              <div className="bg-neutral-50 border border-neutral-200 rounded p-3 text-sm text-neutral-800">
                Password will be auto-generated and returned once after creation.
                The account will require a password change at first login.
              </div>
            </>
          )}

          {!portalRole && (
            <>
              <FormField label="Full Name *">
                <input
                  value={wizard.name}
                  onChange={(e) => onSetField('name', e.target.value)}
                  className={inputCls}
                  placeholder="Full name"
                />
              </FormField>

              <FormField label="Email *">
                <input
                  type="email"
                  value={wizard.email}
                  onChange={(e) => onSetField('email', e.target.value)}
                  className={inputCls}
                  placeholder="user@ogamierp.local"
                />
              </FormField>

              <FormField label="Password *">
                <input
                  type="password"
                  value={wizard.password}
                  onChange={(e) => onSetField('password', e.target.value)}
                  className={inputCls}
                  placeholder="Min 8 chars, upper/lower/number/symbol"
                />
              </FormField>
            </>
          )}

          <FormField label="Role">
            <input value={wizard.role} readOnly className={`${inputCls} bg-neutral-50 text-neutral-600 cursor-default`} />
            <p className="text-xs text-neutral-400 mt-1">Role was selected in Step 1.</p>
          </FormField>

          {error && <p className="text-red-600 text-sm">{error}</p>}

          <div className="flex justify-between gap-3 pt-2">
            <button
              onClick={onBack}
              className={btnSecondary}
              disabled={isPending}
            >
              <ChevronLeft className="inline h-4 w-4 mr-1" /> Back
            </button>
            <button
              onClick={onSubmit}
              disabled={isPending}
              className={btnPrimary}
            >
              {isPending
                ? <><Loader2 className="inline h-4 w-4 mr-2 animate-spin" />Creating…</>
                : (portalRole ? 'Create Portal Account' : 'Create User')}
            </button>
          </div>
        </div>
      )}
    </div>
  )
}

// ── Shared small components ───────────────────────────────────────────────────
function Modal({ title, onClose, children, wide }: { title: string; onClose: () => void; children: React.ReactNode; wide?: boolean }) {
  return (
    <div className="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
      <div className={`bg-white rounded w-full ${wide ? 'max-w-lg' : 'max-w-md'}`}>
        <div className="flex items-center justify-between px-6 py-4 border-b">
          <h2 className="text-base font-semibold text-neutral-900">{title}</h2>
          <button onClick={onClose} className="text-neutral-400 hover:text-neutral-600 text-xl leading-none">&times;</button>
        </div>
        <div className="px-6 py-5">{children}</div>
      </div>
    </div>
  )
}

function FormField({ label, children }: { label: string; children: React.ReactNode }) {
  return (
    <div>
      <label className="block text-xs font-medium text-neutral-700 mb-1">{label}</label>
      {children}
    </div>
  )
}

const inputCls     = 'w-full border border-neutral-300 rounded px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-neutral-400'
const btnPrimary   = 'bg-neutral-900 hover:bg-neutral-800 disabled:opacity-50 disabled:cursor-not-allowed disabled:cursor-not-allowed text-white text-sm font-medium px-4 py-2 rounded transition-colors flex items-center'
const btnSecondary = 'border border-neutral-300 hover:bg-neutral-50 text-sm font-medium px-4 py-2 rounded transition-colors'
