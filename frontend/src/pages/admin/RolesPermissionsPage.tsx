import { useState, useMemo, useCallback } from 'react'
import { Shield, RotateCcw, Save, Search, ChevronDown, ChevronRight, Users, AlertTriangle, Check, Building2, Power, Trash2, Plus } from 'lucide-react'
import { toast } from 'sonner'
import {
  useRoles, usePermissionsList, useRoleDetail, useUpdateRolePermissions, useResetRolePermissions,
  useDepartmentProfiles, useDepartmentProfileDetail, useUpsertDepartmentProfile, useToggleDepartmentProfile, useDeleteDepartmentProfile,
  useAdminDepartments,
} from '@/hooks/useAdmin'
import type { Role, DepartmentProfile, GroupedPermissions } from '@/hooks/useAdmin'
import { PageHeader } from '@/components/ui/PageHeader'
import ConfirmDestructiveDialog from '@/components/ui/ConfirmDestructiveDialog'
import SkeletonLoader from '@/components/ui/SkeletonLoader'
import { firstErrorMessage } from '@/lib/errorHandler'

// ── Role display names & badge colors ──────────────────────────────────────
const ROLE_LABELS: Record<string, string> = {
  super_admin: 'Super Admin', admin: 'Admin', executive: 'Executive',
  vice_president: 'Vice President', manager: 'Manager', officer: 'Officer',
  head: 'Department Head', staff: 'Staff', vendor: 'Vendor Portal', client: 'Client Portal',
}

const ROLE_BADGE_COLORS: Record<string, string> = {
  super_admin: 'bg-red-100 text-red-700 border-red-200',
  admin: 'bg-neutral-100 text-neutral-700 border-neutral-200',
  executive: 'bg-purple-100 text-purple-700 border-purple-200',
  vice_president: 'bg-indigo-100 text-indigo-700 border-indigo-200',
  manager: 'bg-blue-100 text-blue-700 border-blue-200',
  officer: 'bg-sky-100 text-sky-700 border-sky-200',
  head: 'bg-teal-100 text-teal-700 border-teal-200',
  staff: 'bg-neutral-100 text-neutral-600 border-neutral-200',
  vendor: 'bg-amber-100 text-amber-700 border-amber-200',
  client: 'bg-emerald-100 text-emerald-700 border-emerald-200',
}

const MODULE_LABELS: Record<string, string> = {
  system: 'System Administration', employees: 'HR / Employees', hr: 'HR Full Access / Training',
  attendance: 'Attendance', overtime: 'Overtime', leaves: 'Leave Management',
  leave_balances: 'Leave Balances', loans: 'Loans', loan_types: 'Loan Types',
  payroll: 'Payroll', payslips: 'Payslips', recruitment: 'Recruitment',
  journal_entries: 'Journal Entries', chart_of_accounts: 'Chart of Accounts',
  fiscal_periods: 'Fiscal Periods', vendors: 'Vendors', vendor_invoices: 'Vendor Invoices',
  vendor_payments: 'Vendor Payments', vendor_portal: 'Vendor Portal',
  customers: 'Customers', customer_invoices: 'Customer Invoices',
  client_portal: 'Client Portal', bank_accounts: 'Bank Accounts',
  bank_reconciliations: 'Bank Reconciliations', reports: 'Reports',
  bir_2307: 'BIR 2307', budget: 'Budget', fixed_assets: 'Fixed Assets',
  procurement: 'Procurement', inventory: 'Inventory', production: 'Production',
  qc: 'Quality Control', maintenance: 'Maintenance', mold: 'Mold',
  delivery: 'Delivery', iso: 'ISO / IATF', crm: 'CRM', sales: 'Sales',
  ap: 'Accounts Payable', ar: 'Accounts Receivable', approvals: 'Approvals', self: 'Self-Service',
}

const DEPT_SCOPED_ROLES = [
  { name: 'manager', label: 'Manager' },
  { name: 'officer', label: 'Officer' },
  { name: 'head', label: 'Department Head' },
  { name: 'vice_president', label: 'Vice President' },
]

function extractAction(permission: string): string {
  const dotPos = permission.indexOf('.')
  return dotPos >= 0 ? permission.substring(dotPos + 1) : permission
}

function formatAction(action: string): string {
  return action.split(/[._-]/).map((w) => w.charAt(0).toUpperCase() + w.slice(1)).join(' ')
}

type TabKey = 'global' | 'department'

// ── Main Page ──────────────────────────────────────────────────────────────
export default function RolesPermissionsPage() {
  const [activeTab, setActiveTab] = useState<TabKey>('global')

  return (
    <div className="space-y-6">
      <PageHeader title="Roles & Permissions" />

      {/* Tab switcher */}
      <div className="flex gap-1 bg-neutral-100 rounded-lg p-1 w-fit">
        <button
          onClick={() => setActiveTab('global')}
          className={`px-4 py-2 text-sm font-medium rounded-md transition-colors ${
            activeTab === 'global' ? 'bg-white shadow-sm text-neutral-900' : 'text-neutral-500 hover:text-neutral-700'
          }`}
        >
          <Shield className="w-4 h-4 inline mr-1.5" />
          Global Permissions
        </button>
        <button
          onClick={() => setActiveTab('department')}
          className={`px-4 py-2 text-sm font-medium rounded-md transition-colors ${
            activeTab === 'department' ? 'bg-white shadow-sm text-neutral-900' : 'text-neutral-500 hover:text-neutral-700'
          }`}
        >
          <Building2 className="w-4 h-4 inline mr-1.5" />
          Department Profiles
        </button>
      </div>

      {activeTab === 'global' ? <GlobalPermissionsTab /> : <DepartmentProfilesTab />}
    </div>
  )
}

// ══════════════════════════════════════════════════════════════════════════════
// GLOBAL PERMISSIONS TAB (existing functionality)
// ══════════════════════════════════════════════════════════════════════════════
function GlobalPermissionsTab() {
  const { data: roles = [], isLoading: rolesLoading } = useRoles()
  const [selectedRole, setSelectedRole] = useState<string | null>(null)

  return (
    <div className="grid grid-cols-1 lg:grid-cols-12 gap-6">
      <div className="lg:col-span-4 xl:col-span-3">
        <div className="bg-white rounded-lg shadow-sm border border-neutral-200">
          <div className="px-4 py-3 border-b border-neutral-200">
            <h2 className="text-sm font-semibold text-neutral-800 flex items-center gap-2">
              <Shield className="w-4 h-4" /> Roles
            </h2>
          </div>
          {rolesLoading ? (
            <div className="p-4"><SkeletonLoader rows={8} /></div>
          ) : (
            <div className="divide-y divide-neutral-100">
              {roles.map((role: Role) => (
                <button
                  key={role.id}
                  onClick={() => setSelectedRole(role.name)}
                  className={`w-full text-left px-4 py-3 hover:bg-neutral-50 transition-colors ${
                    selectedRole === role.name ? 'bg-blue-50 border-l-2 border-l-blue-500' : ''
                  }`}
                >
                  <span className={`inline-flex items-center px-2 py-0.5 rounded text-xs font-medium border ${ROLE_BADGE_COLORS[role.name] ?? 'bg-neutral-100 text-neutral-600 border-neutral-200'}`}>
                    {ROLE_LABELS[role.name] ?? role.name}
                  </span>
                  <div className="mt-1.5 flex items-center gap-3 text-xs text-neutral-500">
                    <span className="flex items-center gap-1"><Users className="w-3 h-3" />{role.users_count ?? 0} users</span>
                    <span>{role.permissions_count ?? 0} permissions</span>
                  </div>
                </button>
              ))}
            </div>
          )}
        </div>
      </div>
      <div className="lg:col-span-8 xl:col-span-9">
        {selectedRole ? (
          <GlobalPermissionMatrix roleName={selectedRole} />
        ) : (
          <div className="bg-white rounded-lg shadow-sm border border-neutral-200 p-12 text-center">
            <Shield className="w-12 h-12 mx-auto text-neutral-300 mb-4" />
            <p className="text-neutral-500 text-sm">Select a role from the left panel to view and manage its permissions.</p>
          </div>
        )}
      </div>
    </div>
  )
}

// ── Global Permission Matrix (role-level) ──────────────────────────────────
function GlobalPermissionMatrix({ roleName }: { roleName: string }) {
  const { data: permData, isLoading: permLoading } = usePermissionsList()
  const { data: roleDetail, isLoading: roleLoading } = useRoleDetail(roleName)
  const updateMut = useUpdateRolePermissions()
  const resetMut = useResetRolePermissions()

  const [editMode, setEditMode] = useState<boolean>(false)
  const [localPermissions, setLocalPermissions] = useState<Set<string>>(new Set<string>())
  const [search, setSearch] = useState<string>('')

  const currentPermissions = useMemo(() => new Set(roleDetail?.permissions ?? []), [roleDetail])
  const defaultPermissions = useMemo(() => roleDetail?.default_permissions ? new Set(roleDetail.default_permissions) : null, [roleDetail])

  const activePerms = editMode ? localPermissions : currentPermissions

  const isDirty = useMemo(() => {
    if (!editMode) return false
    if (localPermissions.size !== currentPermissions.size) return true
    for (const p of localPermissions) { if (!currentPermissions.has(p)) return true }
    return false
  }, [editMode, localPermissions, currentPermissions])

  const enterEditMode = useCallback(() => { setLocalPermissions(new Set(currentPermissions)); setEditMode(true) }, [currentPermissions])
  const cancelEdit = useCallback(() => { setEditMode(false); setLocalPermissions(new Set()) }, [])

  const handleSave = useCallback(() => {
    updateMut.mutate(
      { roleName, permissions: Array.from(localPermissions) },
      {
        onSuccess: (data: { message: string }) => { toast.success(data.message); setEditMode(false) },
        onError: (e: unknown) => toast.error(firstErrorMessage(e) || 'Failed to update permissions.'),
      },
    )
  }, [roleName, localPermissions, updateMut])

  const handleReset = useCallback(() => {
    resetMut.mutate(roleName, {
      onSuccess: (data: { message: string }) => { toast.success(data.message); setEditMode(false) },
      onError: (e: unknown) => toast.error(firstErrorMessage(e) || 'Failed to reset permissions.'),
    })
  }, [roleName, resetMut])

  if (permLoading || roleLoading) return <div className="bg-white rounded-lg shadow-sm border border-neutral-200 p-6"><SkeletonLoader rows={12} /></div>
  if (!roleDetail) return <div className="bg-white rounded-lg shadow-sm border border-neutral-200 p-12 text-center"><p className="text-neutral-500 text-sm">Role not found.</p></div>

  return (
    <div className="bg-white rounded-lg shadow-sm border border-neutral-200">
      {/* Header */}
      <div className="px-4 py-3 border-b border-neutral-200">
        <div className="flex items-center justify-between flex-wrap gap-3">
          <div className="flex items-center gap-3">
            <span className={`inline-flex items-center px-2.5 py-1 rounded text-sm font-medium border ${ROLE_BADGE_COLORS[roleName] ?? 'bg-neutral-100 text-neutral-600 border-neutral-200'}`}>
              {ROLE_LABELS[roleName] ?? roleName}
            </span>
            <span className="text-xs text-neutral-500">{roleDetail.permissions.length} permissions &middot; {roleDetail.users_count} users</span>
          </div>
          <div className="flex items-center gap-2">
            {!editMode && <button onClick={enterEditMode} className="px-3 py-1.5 text-xs font-medium text-white bg-blue-600 rounded hover:bg-blue-700 transition-colors">Edit Permissions</button>}
            {editMode && (
              <>
                <button onClick={cancelEdit} className="px-3 py-1.5 text-xs font-medium text-neutral-600 bg-neutral-100 rounded hover:bg-neutral-200 transition-colors">Cancel</button>
                <button onClick={handleSave} disabled={!isDirty || updateMut.isPending} className="px-3 py-1.5 text-xs font-medium text-white bg-blue-600 rounded hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors flex items-center gap-1">
                  <Save className="w-3 h-3" />{updateMut.isPending ? 'Saving...' : 'Save Changes'}
                </button>
              </>
            )}
            <ConfirmDestructiveDialog title="Reset to Defaults" description={`This will replace all current permissions for "${ROLE_LABELS[roleName] ?? roleName}" with the seeder baseline.`} confirmLabel="Reset" onConfirm={handleReset}>
              <button disabled={resetMut.isPending} className="px-3 py-1.5 text-xs font-medium text-amber-700 bg-amber-50 border border-amber-200 rounded hover:bg-amber-100 transition-colors flex items-center gap-1">
                <RotateCcw className="w-3 h-3" />{resetMut.isPending ? 'Resetting...' : 'Reset to Defaults'}
              </button>
            </ConfirmDestructiveDialog>
          </div>
        </div>
      </div>

      {/* Warnings */}
      {roleName === 'admin' && (
        <div className="mx-4 mt-3 p-3 bg-amber-50 border border-amber-200 rounded-lg text-xs text-amber-800 flex items-start gap-2">
          <AlertTriangle className="w-4 h-4 flex-shrink-0 mt-0.5" />
          <span>The <strong>admin</strong> role is the system custodian. It should only have system.* permissions and limited business data access.</span>
        </div>
      )}

      <PermissionCheckboxGrid
        allPermissions={permData?.data as Record<string, string[]> | undefined}
        activePerms={activePerms}
        editMode={editMode}
        search={search}
        onSearchChange={setSearch}
        onTogglePermission={(p: string) => setLocalPermissions((prev: Set<string>) => { const n = new Set(prev); n.has(p) ? n.delete(p) : n.add(p); return n })}
        onToggleModule={(perms: string[], checked: boolean) => setLocalPermissions((prev: Set<string>) => { const n = new Set(prev); perms.forEach((p) => checked ? n.add(p) : n.delete(p)); return n })}
        defaultPermissions={defaultPermissions}
        currentPermissions={currentPermissions}
        isDirty={isDirty}
      />
    </div>
  )
}

// ══════════════════════════════════════════════════════════════════════════════
// DEPARTMENT PROFILES TAB (new)
// ══════════════════════════════════════════════════════════════════════════════
function DepartmentProfilesTab() {
  const { data: profiles = [], isLoading: profilesLoading } = useDepartmentProfiles()
  const { data: departments = [] } = useAdminDepartments()
  const { data: permData } = usePermissionsList()
  const toggleMut = useToggleDepartmentProfile()
  const deleteMut = useDeleteDepartmentProfile()
  const upsertMut = useUpsertDepartmentProfile()

  const [selectedProfileId, setSelectedProfileId] = useState<number | null>(null)
  const [showCreate, setShowCreate] = useState<boolean>(false)
  const [newDeptId, setNewDeptId] = useState<number | ''>('')
  const [newRole, setNewRole] = useState<string>('')

  const { data: profileDetail, isLoading: detailLoading } = useDepartmentProfileDetail(selectedProfileId)

  const [editMode, setEditMode] = useState<boolean>(false)
  const [localPermissions, setLocalPermissions] = useState<Set<string>>(new Set<string>())
  const [search, setSearch] = useState<string>('')

  const currentPermissions = useMemo(() => new Set(profileDetail?.permissions ?? []), [profileDetail])

  const activePerms = editMode ? localPermissions : currentPermissions

  const isDirty = useMemo(() => {
    if (!editMode) return false
    if (localPermissions.size !== currentPermissions.size) return true
    for (const p of localPermissions) { if (!currentPermissions.has(p)) return true }
    return false
  }, [editMode, localPermissions, currentPermissions])

  const enterEditMode = useCallback(() => { setLocalPermissions(new Set(currentPermissions)); setEditMode(true) }, [currentPermissions])
  const cancelEdit = useCallback(() => { setEditMode(false); setLocalPermissions(new Set()) }, [])

  const handleSave = useCallback(() => {
    if (!profileDetail) return
    upsertMut.mutate(
      {
        department_id: profileDetail.department_id,
        role: profileDetail.role,
        permissions: Array.from(localPermissions),
        profile_label: profileDetail.profile_label,
        is_active: profileDetail.is_active,
      },
      {
        onSuccess: () => { toast.success('Department profile saved.'); setEditMode(false) },
        onError: (e: unknown) => toast.error(firstErrorMessage(e) || 'Failed to save profile.'),
      },
    )
  }, [profileDetail, localPermissions, upsertMut])

  const handleCreate = useCallback(() => {
    if (!newDeptId || !newRole) return
    upsertMut.mutate(
      { department_id: newDeptId as number, role: newRole, permissions: [], profile_label: null, is_active: true },
      {
        onSuccess: (data: { data: { id: number } }) => {
          toast.success('Profile created. Add permissions now.')
          setShowCreate(false)
          setNewDeptId('')
          setNewRole('')
          setSelectedProfileId(data.data.id)
        },
        onError: (e: unknown) => toast.error(firstErrorMessage(e) || 'Failed to create profile.'),
      },
    )
  }, [newDeptId, newRole, upsertMut])

  return (
    <div className="grid grid-cols-1 lg:grid-cols-12 gap-6">
      {/* Left panel — Profile list */}
      <div className="lg:col-span-4 xl:col-span-3">
        <div className="bg-white rounded-lg shadow-sm border border-neutral-200">
          <div className="px-4 py-3 border-b border-neutral-200 flex items-center justify-between">
            <h2 className="text-sm font-semibold text-neutral-800 flex items-center gap-2">
              <Building2 className="w-4 h-4" /> Department Profiles
            </h2>
            <button onClick={() => setShowCreate(!showCreate)} className="p-1 rounded hover:bg-neutral-100 text-neutral-500 hover:text-neutral-700" title="Create new profile">
              <Plus className="w-4 h-4" />
            </button>
          </div>

          {/* Create form */}
          {showCreate && (
            <div className="p-3 border-b border-neutral-200 bg-neutral-50 space-y-2">
              <select value={newDeptId} onChange={(e) => setNewDeptId(e.target.value ? Number(e.target.value) : '')} className="w-full text-xs border border-neutral-300 rounded px-2 py-1.5">
                <option value="">Select Department...</option>
                {departments.map((d: { id: number; name: string }) => <option key={d.id} value={d.id}>{d.name}</option>)}
              </select>
              <select value={newRole} onChange={(e) => setNewRole(e.target.value)} className="w-full text-xs border border-neutral-300 rounded px-2 py-1.5">
                <option value="">Select Role...</option>
                {DEPT_SCOPED_ROLES.map((r) => <option key={r.name} value={r.name}>{r.label}</option>)}
              </select>
              <button onClick={handleCreate} disabled={!newDeptId || !newRole || upsertMut.isPending} className="w-full text-xs bg-blue-600 text-white rounded px-2 py-1.5 hover:bg-blue-700 disabled:opacity-50">
                {upsertMut.isPending ? 'Creating...' : 'Create Profile'}
              </button>
            </div>
          )}

          {profilesLoading ? (
            <div className="p-4"><SkeletonLoader rows={6} /></div>
          ) : profiles.length === 0 ? (
            <div className="p-6 text-center text-neutral-400 text-xs">No department profiles yet. Click + to create one.</div>
          ) : (
            <div className="divide-y divide-neutral-100 max-h-[60vh] overflow-y-auto">
              {profiles.map((p: DepartmentProfile) => (
                <button
                  key={p.id}
                  onClick={() => { setSelectedProfileId(p.id); setEditMode(false) }}
                  className={`w-full text-left px-4 py-3 hover:bg-neutral-50 transition-colors ${
                    selectedProfileId === p.id ? 'bg-blue-50 border-l-2 border-l-blue-500' : ''
                  }`}
                >
                  <div className="flex items-center justify-between">
                    <div className="flex items-center gap-2">
                      <span className="text-xs font-semibold text-neutral-700">{p.department_code}</span>
                      <span className="text-xs text-neutral-400">+</span>
                      <span className={`inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium border ${ROLE_BADGE_COLORS[p.role] ?? 'bg-neutral-100 text-neutral-600 border-neutral-200'}`}>
                        {ROLE_LABELS[p.role] ?? p.role}
                      </span>
                    </div>
                    <span className={`text-[10px] font-medium px-1.5 py-0.5 rounded ${p.is_active ? 'bg-green-100 text-green-700' : 'bg-neutral-100 text-neutral-500'}`}>
                      {p.is_active ? 'Active' : 'Inactive'}
                    </span>
                  </div>
                  <div className="mt-1 text-xs text-neutral-500">
                    {p.profile_label && <span className="mr-2">{p.profile_label}</span>}
                    {p.permissions_count} permissions
                  </div>
                </button>
              ))}
            </div>
          )}
        </div>
      </div>

      {/* Right panel — Profile detail & permission matrix */}
      <div className="lg:col-span-8 xl:col-span-9">
        {selectedProfileId && profileDetail ? (
          <div className="bg-white rounded-lg shadow-sm border border-neutral-200">
            {/* Header */}
            <div className="px-4 py-3 border-b border-neutral-200">
              <div className="flex items-center justify-between flex-wrap gap-3">
                <div className="flex items-center gap-3">
                  <span className="text-sm font-semibold text-neutral-800">{profileDetail.department_name}</span>
                  <span className="text-neutral-300">+</span>
                  <span className={`inline-flex items-center px-2 py-0.5 rounded text-xs font-medium border ${ROLE_BADGE_COLORS[profileDetail.role] ?? 'bg-neutral-100 text-neutral-600 border-neutral-200'}`}>
                    {ROLE_LABELS[profileDetail.role] ?? profileDetail.role}
                  </span>
                  <span className="text-xs text-neutral-500">{profileDetail.permissions.length} permissions</span>
                  <span className={`text-[10px] font-medium px-1.5 py-0.5 rounded ${profileDetail.is_active ? 'bg-green-100 text-green-700' : 'bg-neutral-100 text-neutral-500'}`}>
                    {profileDetail.is_active ? 'Active' : 'Inactive'}
                  </span>
                </div>
                <div className="flex items-center gap-2">
                  {!editMode && <button onClick={enterEditMode} className="px-3 py-1.5 text-xs font-medium text-white bg-blue-600 rounded hover:bg-blue-700 transition-colors">Edit Permissions</button>}
                  {editMode && (
                    <>
                      <button onClick={cancelEdit} className="px-3 py-1.5 text-xs font-medium text-neutral-600 bg-neutral-100 rounded hover:bg-neutral-200 transition-colors">Cancel</button>
                      <button onClick={handleSave} disabled={!isDirty || upsertMut.isPending} className="px-3 py-1.5 text-xs font-medium text-white bg-blue-600 rounded hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors flex items-center gap-1">
                        <Save className="w-3 h-3" />{upsertMut.isPending ? 'Saving...' : 'Save Changes'}
                      </button>
                    </>
                  )}
                  <button
                    onClick={() => toggleMut.mutate(profileDetail.id, {
                      onSuccess: (data: { message: string }) => toast.success(data.message),
                      onError: (e: unknown) => toast.error(firstErrorMessage(e) || 'Failed.'),
                    })}
                    disabled={toggleMut.isPending}
                    className="px-3 py-1.5 text-xs font-medium text-neutral-600 bg-neutral-100 border border-neutral-200 rounded hover:bg-neutral-200 transition-colors flex items-center gap-1"
                  >
                    <Power className="w-3 h-3" />{profileDetail.is_active ? 'Deactivate' : 'Activate'}
                  </button>
                  <ConfirmDestructiveDialog title="Delete Profile" description={`Permanently delete the ${profileDetail.department_name} + ${ROLE_LABELS[profileDetail.role] ?? profileDetail.role} profile?`} confirmLabel="Delete" onConfirm={() => {
                    deleteMut.mutate(profileDetail.id, {
                      onSuccess: () => { toast.success('Profile deleted.'); setSelectedProfileId(null) },
                      onError: (e: unknown) => toast.error(firstErrorMessage(e) || 'Failed.'),
                    })
                  }}>
                    <button className="px-3 py-1.5 text-xs font-medium text-red-600 bg-red-50 border border-red-200 rounded hover:bg-red-100 transition-colors flex items-center gap-1">
                      <Trash2 className="w-3 h-3" />Delete
                    </button>
                  </ConfirmDestructiveDialog>
                </div>
              </div>
            </div>

            {/* Info banner */}
            <div className="mx-4 mt-3 p-3 bg-blue-50 border border-blue-200 rounded-lg text-xs text-blue-800 flex items-start gap-2">
              <Building2 className="w-4 h-4 flex-shrink-0 mt-0.5" />
              <span>This profile defines what permissions a <strong>{ROLE_LABELS[profileDetail.role] ?? profileDetail.role}</strong> in the <strong>{profileDetail.department_name}</strong> department effectively gets. Only permissions checked here AND in the global role will be active.</span>
            </div>

            <PermissionCheckboxGrid
              allPermissions={permData?.data as Record<string, string[]> | undefined}
              activePerms={activePerms}
              editMode={editMode}
              search={search}
              onSearchChange={setSearch}
              onTogglePermission={(p: string) => setLocalPermissions((prev: Set<string>) => { const n = new Set(prev); n.has(p) ? n.delete(p) : n.add(p); return n })}
              onToggleModule={(perms: string[], checked: boolean) => setLocalPermissions((prev: Set<string>) => { const n = new Set(prev); perms.forEach((p) => checked ? n.add(p) : n.delete(p)); return n })}
              defaultPermissions={null}
              currentPermissions={currentPermissions}
              isDirty={isDirty}
            />
          </div>
        ) : detailLoading ? (
          <div className="bg-white rounded-lg shadow-sm border border-neutral-200 p-6"><SkeletonLoader rows={12} /></div>
        ) : (
          <div className="bg-white rounded-lg shadow-sm border border-neutral-200 p-12 text-center">
            <Building2 className="w-12 h-12 mx-auto text-neutral-300 mb-4" />
            <p className="text-neutral-500 text-sm">Select a department profile from the left panel, or create a new one.</p>
            <p className="text-neutral-400 text-xs mt-2">Department profiles define which permissions a role gets within a specific department (e.g., HR + Manager = HR-specific permissions).</p>
          </div>
        )}
      </div>
    </div>
  )
}

// ══════════════════════════════════════════════════════════════════════════════
// REUSABLE PERMISSION CHECKBOX GRID
// ══════════════════════════════════════════════════════════════════════════════
interface PermissionCheckboxGridProps {
  allPermissions: Record<string, string[]> | undefined
  activePerms: Set<string>
  editMode: boolean
  search: string
  onSearchChange: (s: string) => void
  onTogglePermission: (perm: string) => void
  onToggleModule: (perms: string[], checked: boolean) => void
  defaultPermissions: Set<string> | null
  currentPermissions: Set<string>
  isDirty: boolean
}

function PermissionCheckboxGrid({
  allPermissions, activePerms, editMode, search, onSearchChange,
  onTogglePermission, onToggleModule, defaultPermissions, currentPermissions, isDirty,
}: PermissionCheckboxGridProps) {
  const [collapsedModules, setCollapsedModules] = useState<Set<string>>(new Set<string>())

  const filteredGroups = useMemo(() => {
    if (!allPermissions) return {}
    const groups: Record<string, string[]> = {}
    const lowerSearch = search.toLowerCase()
    for (const [module, perms] of Object.entries(allPermissions)) {
      const filtered = lowerSearch ? perms.filter((p) => p.toLowerCase().includes(lowerSearch)) : perms
      if (filtered.length > 0) groups[module] = filtered
    }
    return groups
  }, [allPermissions, search])

  const toggleCollapsed = useCallback((module: string) => {
    setCollapsedModules((prev: Set<string>) => {
      const next = new Set(prev)
      next.has(module) ? next.delete(module) : next.add(module)
      return next
    })
  }, [])

  return (
    <>
      {/* Search */}
      <div className="px-4 py-3 border-b border-neutral-100">
        <div className="relative max-w-sm">
          <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-neutral-400" />
          <input
            type="text" placeholder="Search permissions..." value={search}
            onChange={(e) => onSearchChange(e.target.value)}
            className="w-full pl-9 pr-3 py-2 text-sm border border-neutral-300 rounded-md focus:outline-none focus:ring-1 focus:ring-blue-400 focus:border-blue-400"
          />
        </div>
        {editMode && isDirty && (
          <p className="mt-2 text-xs text-blue-600 font-medium">
            Unsaved changes &mdash; {activePerms.size} permissions selected (was {currentPermissions.size})
          </p>
        )}
      </div>

      {/* Permission Groups */}
      <div className="max-h-[65vh] overflow-y-auto">
        {Object.keys(filteredGroups).length === 0 ? (
          <div className="p-8 text-center text-neutral-400 text-sm">{search ? 'No permissions match your search.' : 'No permissions found.'}</div>
        ) : (
          Object.entries(filteredGroups).map(([module, perms]) => {
            const isCollapsed = collapsedModules.has(module)
            const moduleCheckedCount = perms.filter((p) => activePerms.has(p)).length
            const allChecked = moduleCheckedCount === perms.length
            const someChecked = moduleCheckedCount > 0 && !allChecked

            return (
              <div key={module} className="border-b border-neutral-100 last:border-b-0">
                <button onClick={() => toggleCollapsed(module)} className="w-full flex items-center justify-between px-4 py-2.5 hover:bg-neutral-50 transition-colors">
                  <div className="flex items-center gap-2">
                    {isCollapsed ? <ChevronRight className="w-4 h-4 text-neutral-400" /> : <ChevronDown className="w-4 h-4 text-neutral-400" />}
                    <span className="text-sm font-semibold text-neutral-700">{MODULE_LABELS[module] ?? module}</span>
                    <span className="text-xs text-neutral-400">{moduleCheckedCount}/{perms.length}</span>
                  </div>
                  {editMode && (
                    <label className="flex items-center gap-1.5 text-xs text-neutral-500" onClick={(e) => e.stopPropagation()}>
                      <input
                        type="checkbox" checked={allChecked}
                        ref={(el) => { if (el) el.indeterminate = someChecked }}
                        onChange={(e) => onToggleModule(perms, e.target.checked)}
                        className="rounded border-neutral-300 text-blue-600 focus:ring-blue-500"
                      />
                      All
                    </label>
                  )}
                </button>
                {!isCollapsed && (
                  <div className="px-4 pb-3 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-1">
                    {perms.map((perm) => {
                      const isChecked = activePerms.has(perm)
                      const isDefault = defaultPermissions?.has(perm) ?? false
                      const isAdded = editMode && isChecked && !currentPermissions.has(perm)
                      const isRemoved = editMode && !isChecked && currentPermissions.has(perm)
                      const diffFromDefault = defaultPermissions ? (isChecked && !isDefault ? 'added' : !isChecked && isDefault ? 'removed' : null) : null

                      return (
                        <label key={perm} className={`flex items-center gap-2 px-2 py-1 rounded text-xs cursor-pointer transition-colors ${
                          isAdded ? 'bg-green-50 text-green-800' : isRemoved ? 'bg-red-50 text-red-800' : 'hover:bg-neutral-50 text-neutral-700'
                        }`}>
                          <input type="checkbox" checked={isChecked} disabled={!editMode} onChange={() => onTogglePermission(perm)}
                            className="rounded border-neutral-300 text-blue-600 focus:ring-blue-500 disabled:opacity-50" />
                          <span className="truncate" title={perm}>{formatAction(extractAction(perm))}</span>
                          {diffFromDefault === 'added' && !editMode && <span className="ml-auto flex-shrink-0 text-[10px] text-green-600 font-medium">+custom</span>}
                          {diffFromDefault === 'removed' && !editMode && <span className="ml-auto flex-shrink-0 text-[10px] text-red-500 font-medium">-default</span>}
                          {isAdded && editMode && <Check className="ml-auto w-3 h-3 text-green-600 flex-shrink-0" />}
                        </label>
                      )
                    })}
                  </div>
                )}
              </div>
            )
          })
        )}
      </div>
    </>
  )
}
