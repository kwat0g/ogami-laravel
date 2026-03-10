import { useState } from 'react'
import { toast } from 'sonner'
import {
  useAdminUsers,
  useCreateAdminUser,
  useUpdateAdminUser,
  useDeleteAdminUser,
  useAssignRole,
  useUnlockUser,
  useRoles,
  useAdminDepartments,
  useEmployeesAvailable,
  type AdminUser,
  type AvailableEmployee,
  type CreateUserPayload,
  type Department,
} from '@/hooks/useAdmin'
import { useAuthStore } from '@/stores/authStore'
import SkeletonLoader from '@/components/ui/SkeletonLoader'
import StatusBadge from '@/components/ui/StatusBadge'
import { PageHeader } from '@/components/ui/PageHeader'
import { Building2, User, Settings, CheckCircle, ChevronLeft, ChevronRight, Loader2 } from 'lucide-react'

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

// ── Edit form state ───────────────────────────────────────────────────────────
interface EditFormState {
  id:               number
  name:             string
  email:            string
  password:         string
  current_password: string
}

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
  name:         string
  email:        string
  password:     string
  role:         string
  // roles like admin/executive don't need an employee
  skipEmployee: boolean
}

const emptyWizard = (): WizardState => ({
  step: 1, department: null, employee: null,
  name: '', email: '', password: '', role: '', skipEmployee: false,
})

// Roles that don't require a linked employee (system/board-level)
const ROLES_WITHOUT_EMPLOYEE = ['admin', 'executive']

export default function UsersPage() {
  const { hasPermission } = useAuthStore()

  const _canCreate     = hasPermission('system.manage_users')
  const canUpdate     = hasPermission('system.manage_users')
  const canDelete     = hasPermission('system.manage_users')
  const canAssignRole = hasPermission('system.assign_roles')

  const [filters, setFilters] = useState({ search: '', role: '', page: 1, per_page: 15 })
  const { data, isLoading, isError } = useAdminUsers(filters)
  const { data: roles = [] }         = useRoles()
  const { data: departments = [] }   = useAdminDepartments()

  const create     = useCreateAdminUser()
  const update     = useUpdateAdminUser()
  const remove     = useDeleteAdminUser()
  const assignRole = useAssignRole()
  const unlock     = useUnlockUser()

  // Wizard state
  const [wizard, setWizard]         = useState<WizardState | null>(null)
  const [wizardError, setWizardError] = useState<string | null>(null)

  // Edit modal state
  const [editForm, setEditForm]     = useState<EditFormState | null>(null)
  const [editError, setEditError]   = useState<string | null>(null)

  // Role modal
  const [roleModal, setRoleModal]   = useState<RoleModal | null>(null)
  const [newRole, setNewRole]       = useState('')

  const rows = data?.data ?? []
  const meta = data?.meta

  // ── Available employees for step 2 ───────────────────────────────────────
  const { data: availableEmployees = [], isLoading: empLoading } = useEmployeesAvailable(
    wizard?.step === 2 && !wizard.skipEmployee ? (wizard.department?.id ?? null) : null
  )

  // ── Wizard helpers ────────────────────────────────────────────────────────
  const _openCreate = () => { setWizard(emptyWizard()); setWizardError(null) }
  const closeWizard = () => { setWizard(null); setWizardError(null) }
  const setWizardField = <K extends keyof WizardState>(k: K, v: WizardState[K]) =>
    setWizard((w) => w ? { ...w, [k]: v } : w)

  const goStep1to2 = () => {
    if (!wizard) return
    // Skip dept/employee for admin & executive
    if (wizard.skipEmployee) {
      setWizard((w) => w ? { ...w, step: 3 } : w)
      return
    }
    if (!wizard.department) { setWizardError('Please select a department.'); return }
    setWizardError(null)
    setWizard((w) => w ? { ...w, step: 2 } : w)
  }

  const goStep2to3 = () => {
    if (!wizard) return
    if (!wizard.employee) { setWizardError('Please select an employee.'); return }
    setWizardError(null)
    // Pre-fill name from employee
    setWizard((w) => w ? {
      ...w,
      step: 3,
      name: w.name || `${wizard.employee!.first_name} ${wizard.employee!.last_name}`,
    } : w)
  }

  const handleWizardSubmit = () => {
    if (!wizard) return
    setWizardError(null)

    if (!wizard.name.trim())  { setWizardError('Name is required.'); return }
    if (!wizard.email.trim()) { setWizardError('Email is required.'); return }
    if (!wizard.password)     { setWizardError('Password is required.'); return }
    if (!wizard.role)         { setWizardError('Role is required.'); return }

    if (!wizard.skipEmployee && !wizard.employee) {
      setWizardError('An employee record is required. Please go back and select one.'); return
    }

    const payload: CreateUserPayload = {
      name:        wizard.name,
      email:       wizard.email,
      password:    wizard.password,
      role:        wizard.role,
      employee_id: wizard.employee?.id ?? null,
    }

    create.mutate(payload, {
      onSuccess: closeWizard,
      onError: (e: unknown) => setWizardError(apiMsg(e) ?? 'Failed to create user.'),
    })
  }

  // ── Edit helpers ──────────────────────────────────────────────────────────
  const openEdit = (u: AdminUser) => {
    setEditForm({ id: u.id, name: u.name, email: u.email, password: '', current_password: '' })
    setEditError(null)
  }
  const closeEdit = () => { setEditForm(null); setEditError(null) }

  const handleEditSave = () => {
    if (!editForm) return
    const payload: Record<string, string> = { name: editForm.name, email: editForm.email }
    if (editForm.password.trim()) {
      if (!editForm.current_password.trim()) {
        setEditError('Your current password is required to set a new password.')
        return
      }
      if (!confirm(`You are about to reset the password for this account.\n\nMake sure you have informed the user of their new password.\n\nContinue?`)) return
      payload.password         = editForm.password
      payload.current_password = editForm.current_password
    }
    update.mutate(
      { id: editForm.id, ...payload },
      {
        onSuccess: () => {
          closeEdit()
          toast.success(
            payload.password
              ? 'Password updated successfully. Inform the user of their new password.'
              : 'User details updated.'
          )
        },
        onError: (e: unknown) => {
          const msg = apiMsg(e) ?? 'Update failed.'
          setEditError(msg)
          toast.error(msg)
        },
      }
    )
  }

  const handleDelete = (u: AdminUser) => {
    if (confirm(`Delete user ${u.email}? This will unlink their employee account. This cannot be undone.`)) {
      remove.mutate(u.id)
    }
  }

  const openRoleModal = (u: AdminUser) => {
    setRoleModal({ userId: u.id, userName: u.name, currentRole: u.roles[0]?.name ?? '' })
    setNewRole(u.roles[0]?.name ?? '')
  }

  const handleAssignRole = () => {
    if (!roleModal || !newRole) return
    assignRole.mutate(
      { userId: roleModal.userId, role: newRole },
      { onSuccess: () => setRoleModal(null) }
    )
  }

  const isLocked = (u: AdminUser) => u.locked_until && new Date(u.locked_until) > new Date()

  if (isLoading) return <SkeletonLoader rows={10} />
  if (isError)   return <p className="text-neutral-700 text-sm mt-4">Failed to load users.</p>

  return (
    <div>
      <PageHeader title="Users" />

      {/* ── Filters ─────────────────────────────────────────────────────── */}
      <div className="flex gap-3 mb-4">
        <input
          type="text"
          placeholder="Search name or email…"
          value={filters.search}
          onChange={(e) => setFilters((f) => ({ ...f, search: e.target.value, page: 1 }))}
          className="border border-neutral-300 rounded px-3 py-2 text-sm w-64 focus:outline-none focus:ring-1 focus:ring-neutral-400"
        />
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
      </div>

      {/* ── Table ───────────────────────────────────────────────────────── */}
      <div className="bg-white border border-neutral-200 rounded overflow-hidden">
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
                  {isLocked(u) ? <StatusBadge status="locked">Locked</StatusBadge> : <StatusBadge status="active">Active</StatusBadge>}
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
                      <button onClick={() => openEdit(u)} className="px-2 py-1 text-xs border border-neutral-200 rounded bg-white text-neutral-600 hover:bg-neutral-50 hover:border-neutral-300 hover:text-neutral-900 font-medium">Edit</button>
                    )}
                    {canAssignRole && (
                      <button onClick={() => openRoleModal(u)} className="px-2 py-1 text-xs border border-neutral-200 rounded bg-white text-neutral-600 hover:bg-neutral-50 hover:border-neutral-300 hover:text-neutral-900 font-medium">Role</button>
                    )}
                    {canUpdate && isLocked(u) && (
                      <button onClick={() => unlock.mutate(u.id)} disabled={unlock.isPending} className="px-2 py-1 text-xs border border-neutral-200 rounded bg-white text-neutral-600 hover:bg-neutral-50 hover:border-neutral-300 hover:text-neutral-900 font-medium disabled:opacity-50 disabled:cursor-not-allowed">Unlock</button>
                    )}
                    {canDelete && (
                      <button onClick={() => handleDelete(u)} className="text-xs text-red-600 hover:text-red-700 font-medium">Delete</button>
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
            empLoading={empLoading}
            isPending={create.isPending}
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

      {/* ── Edit User modal ──────────────────────────────────────────────── */}
      {editForm && (
        <Modal title="Edit User" onClose={closeEdit}>
          <div className="space-y-4">
            <FormField label="Name *">
              <input value={editForm.name} onChange={(e) => setEditForm((f) => f ? { ...f, name: e.target.value } : f)} className={inputCls} placeholder="Full name" />
            </FormField>
            <FormField label="Email *">
              <input type="email" value={editForm.email} onChange={(e) => setEditForm((f) => f ? { ...f, email: e.target.value } : f)} className={inputCls} placeholder="user@ogamierp.local" />
            </FormField>
            <FormField label="New Password (leave blank to keep)">
              <input type="password" value={editForm.password} onChange={(e) => setEditForm((f) => f ? { ...f, password: e.target.value } : f)} className={inputCls} placeholder="Leave blank to keep current password" />
            </FormField>
            {editForm.password.trim() && (
              <FormField label="Your Current Password *">
                <input type="password" value={editForm.current_password} onChange={(e) => setEditForm((f) => f ? { ...f, current_password: e.target.value } : f)} className={inputCls} placeholder="Confirm your own password to authorise this change" />
              </FormField>
            )}
            {editError && <p className="text-red-600 text-sm">{editError}</p>}
            <div className="flex justify-end gap-3 pt-2">
              <button onClick={closeEdit} className={btnSecondary}>Cancel</button>
              <button onClick={handleEditSave} disabled={update.isPending} className={btnPrimary}>{update.isPending ? 'Saving…' : 'Save'}</button>
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
  empLoading:         boolean
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
  wizard, roles, departments, availableEmployees, empLoading,
  isPending, error, onSetField, onNext1, onNext2, onSubmit, onBack, onClose,
}: WizardProps) {
  const STEPS = [
    { label: 'Department', icon: Building2 },
    { label: 'Employee',   icon: User },
    { label: 'Account',    icon: Settings },
  ]

  // Determine effective step count for admin/executive (no employee step)
  const effectiveStep = wizard.skipEmployee && wizard.step === 3 ? 2 : wizard.step

  return (
    <div className="space-y-6">
      {/* Step indicator */}
      <div className="flex items-center">
        {STEPS.map((s, i) => {
          const stepNum = (i + 1) as WizardStep
          const done    = effectiveStep > stepNum || (wizard.skipEmployee && stepNum === 2)
          const active  = effectiveStep === stepNum && !(wizard.skipEmployee && stepNum === 2)
          const _StepIcon    = s.icon
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
                <span className={`text-xs font-medium ${wizard.skipEmployee && stepNum === 2 ? 'line-through opacity-40' : ''}`}>{s.label}</span>
              </div>
              {i < STEPS.length - 1 && <div className={`flex-1 mx-2 h-px ${done ? 'bg-neutral-400' : 'bg-neutral-200'}`} />}
            </div>
          )
        })}
      </div>

      {/* ── Step 1: Department ──────────────────────────────────────────── */}
      {wizard.step === 1 && (
        <div className="space-y-4">
          <div className="bg-neutral-50 border border-neutral-200 rounded p-3 text-sm text-neutral-800">
            Every ERP user must be linked to an employee record. Start by selecting their department.
            <br />
            <span className="text-xs text-neutral-600">Admin and Executive accounts can be created without an employee record.</span>
          </div>

          <FormField label="Role *">
            <select
              value={wizard.role}
              onChange={(e) => {
                const role = e.target.value
                onSetField('role', role)
                onSetField('skipEmployee', ROLES_WITHOUT_EMPLOYEE.includes(role))
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
          {SCOPED_MANAGER_ROLES.includes(wizard.role) && (
            <div className="bg-neutral-50 border border-neutral-200 rounded p-3 text-xs text-neutral-800">
              <strong>{wizard.role}</strong> is restricted to the{' '}
              <strong>
                {departments.find((d) => d.code === ROLE_DEPT_MAP[wizard.role])?.name ?? ROLE_DEPT_MAP[wizard.role]}
              </strong>{' '}
              department. The department has been auto-selected and cannot be changed for this role.
            </div>
          )}

          {!wizard.skipEmployee && (
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

          {wizard.skipEmployee && (
            <div className="bg-neutral-50 border border-neutral-200 rounded p-3 text-sm text-neutral-800">
              <strong>{wizard.role}</strong> accounts do not require a linked employee. You'll fill account details in the next step.
            </div>
          )}

          {error && <p className="text-red-600 text-sm">{error}</p>}

          <div className="flex justify-end gap-3 pt-2">
            <button onClick={onClose} className={btnSecondary}>Cancel</button>
            <button onClick={onNext1} disabled={!wizard.role || (!wizard.skipEmployee && !wizard.department)} className={btnPrimary}>
              Next <ChevronRight className="inline h-4 w-4 ml-1" />
            </button>
          </div>
        </div>
      )}

      {/* ── Step 2: Employee selection ──────────────────────────────────── */}
      {wizard.step === 2 && !wizard.skipEmployee && (
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

      {/* ── Step 3: Account details ─────────────────────────────────────── */}
      {wizard.step === 3 && (
        <div className="space-y-4">
          {wizard.employee && (
            <div className="bg-neutral-50 border border-neutral-200 rounded px-4 py-3 text-sm">
              <p className="font-medium text-neutral-800">Linked Employee</p>
              <p className="text-neutral-700">{wizard.employee.first_name} {wizard.employee.last_name} · {wizard.employee.employee_code} · {wizard.employee.department_name}</p>
            </div>
          )}

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
              {isPending ? <><Loader2 className="inline h-4 w-4 mr-2 animate-spin" />Creating…</> : 'Create User'}
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

// ── Extract API error message ─────────────────────────────────────────────────
function apiMsg(err: unknown): string | null {
  if (err && typeof err === 'object' && 'response' in err) {
    const resp = (err as { response?: { data?: { message?: string } } }).response
    return resp?.data?.message ?? null
  }
  return null
}
