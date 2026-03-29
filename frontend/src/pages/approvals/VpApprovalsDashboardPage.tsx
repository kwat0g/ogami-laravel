import { useState, useEffect } from 'react'
import { useNavigate } from 'react-router-dom'
import { toast } from 'sonner'
import { firstErrorMessage, parseApiError } from '@/lib/errorHandler'
import { ClipboardCheck, AlertTriangle, Search, X, Bell } from 'lucide-react'
import { PageHeader } from '@/components/ui/PageHeader'
import { useDebounce } from '@/hooks/useDebounce'
import { useVpPurchaseRequests, useVpLoans, useVpMrqs, useVpPayrollRuns, useVpPendingCounts } from '@/hooks/useVpApprovals'
import {
  useTeamLeaveRequests,
  useGaProcessLeaveRequest,
  useVpNoteLeaveRequest,
  useRejectLeaveRequest,
} from '@/hooks/useLeave'
import type { GaProcessPayload } from '@/hooks/useLeave'
import {
  usePendingExecutiveOvertimeRequests,
  useExecutiveApproveOvertimeRequest,
  useExecutiveRejectOvertimeRequest,
} from '@/hooks/useOvertime'
import type { OvertimeFilters } from '@/types/hr'
import { SodActionButton } from '@/components/ui/SodActionButton'
import StatusBadge from '@/components/ui/StatusBadge'
import { useAuthStore } from '@/stores/authStore'
import SkeletonLoader from '@/components/ui/SkeletonLoader'

type TabId = 'purchase-requests' | 'loans' | 'mrq' | 'payroll' | 'leave' | 'overtime'

// ── Per-tab filter config ─────────────────────────────────────────────────────

const STATUS_OPTIONS: Record<TabId, { value: string; label: string }[]> = {
  'purchase-requests': [
    { value: '',                label: 'All Statuses' },
    { value: 'draft',           label: 'Draft' },
    { value: 'pending_review',  label: 'Pending Review' },
    { value: 'reviewed',        label: 'Reviewed' },
    { value: 'budget_verified', label: 'Budget Verified (Pending VP)' },
    { value: 'approved',        label: 'Approved' },
    { value: 'returned',        label: 'Returned' },
    { value: 'rejected',        label: 'Rejected' },
    { value: 'cancelled',       label: 'Cancelled' },
    { value: 'converted_to_po', label: 'Converted to PO' },
  ],
  'loans': [
    { value: '',                      label: 'All Statuses' },
    { value: 'pending',               label: 'Pending' },
    { value: 'head_noted',            label: 'Head Noted' },
    { value: 'manager_checked',       label: 'Manager Checked' },
    { value: 'officer_reviewed',      label: 'Officer Reviewed (Pending VP)' },
    { value: 'supervisor_approved',   label: 'Supervisor Approved' },
    { value: 'approved',              label: 'Approved' },
    { value: 'ready_for_disbursement',label: 'Ready for Disbursement' },
    { value: 'active',                label: 'Active' },
    { value: 'fully_paid',            label: 'Fully Paid' },
    { value: 'cancelled',             label: 'Cancelled' },
    { value: 'written_off',           label: 'Written Off' },
  ],
  'mrq': [
    { value: '',               label: 'All Statuses' },
    { value: 'draft',          label: 'Draft' },
    { value: 'submitted',      label: 'Submitted' },
    { value: 'noted',          label: 'Noted' },
    { value: 'checked',        label: 'Checked' },
    { value: 'reviewed',       label: 'Reviewed (Pending VP)' },
    { value: 'approved',       label: 'Approved' },
    { value: 'rejected',       label: 'Rejected' },
    { value: 'cancelled',      label: 'Cancelled' },
    { value: 'fulfilled',      label: 'Fulfilled' },
    { value: 'converted_to_pr',label: 'Converted to PR' },
  ],
  'payroll': [
    { value: '',              label: 'All Statuses' },
    { value: 'DRAFT',         label: 'Draft' },
    { value: 'SCOPE_SET',     label: 'Scope Set' },
    { value: 'PROCESSING',    label: 'Processing' },
    { value: 'COMPUTED',      label: 'Computed' },
    { value: 'REVIEW',        label: 'Under Review' },
    { value: 'SUBMITTED',     label: 'Submitted' },
    { value: 'HR_APPROVED',   label: 'HR Approved' },
    { value: 'ACCTG_APPROVED',label: 'Acctg Approved (Pending VP)' },
    { value: 'VP_APPROVED',   label: 'VP Approved' },
    { value: 'DISBURSED',     label: 'Disbursed' },
    { value: 'PUBLISHED',     label: 'Published' },
    { value: 'RETURNED',      label: 'Returned' },
    { value: 'REJECTED',      label: 'Rejected' },
  ],
  'leave': [
    { value: '',               label: 'All Statuses' },
    { value: 'pending',        label: 'Pending' },
    { value: 'head_noted',     label: 'Head Noted' },
    { value: 'manager_checked',label: 'Manager Checked (Pending GA)' },
    { value: 'ga_processed',   label: 'GA Processed (Pending VP)' },
    { value: 'approved',       label: 'Approved' },
    { value: 'rejected',       label: 'Rejected' },
    { value: 'cancelled',      label: 'Cancelled' },
  ],
  'overtime': [
    { value: '', label: 'Pending Exec Approval' },
  ],
}

const SEARCH_PLACEHOLDERS: Record<TabId, string> = {
  'purchase-requests': 'Search by PR reference or department…',
  'loans':             'Search by employee or reference…',
  'mrq':               'Search by MR reference or purpose…',
  'payroll':           'Search by reference or pay period…',
  'leave':             'Search by employee name…',
  'overtime':          'Search by employee name…',
}

const URGENCY_OPTIONS = [
  { value: '', label: 'All Urgencies' },
  { value: 'normal', label: 'Normal' },
  { value: 'urgent', label: 'Urgent' },
  { value: 'critical', label: 'Critical' },
]

interface TabFilter { status: string; page: number; urgency?: string }

const DEFAULT_TAB_FILTERS: Record<TabId, TabFilter> = {
  'purchase-requests': { status: '', page: 1, urgency: '' },
  'loans':             { status: '', page: 1 },
  'mrq':               { status: '', page: 1 },
  'payroll':           { status: '', page: 1 },
  'leave':             { status: '', page: 1 },
  'overtime':          { status: '', page: 1 },
}

// ── Pending-approval statuses per tab ─────────────────────────────────────────
const VP_ACTION_STATUSES: Record<TabId, string[]> = {
  'purchase-requests': ['budget_verified'],
  'loans':             ['officer_reviewed'],
  'mrq':               ['reviewed'],
  'payroll':           ['ACCTG_APPROVED'],
  'leave':             ['manager_checked', 'ga_processed'],
  'overtime':          [],
}

function ActionBadge() {
  return (
    <span className="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-xs font-medium bg-amber-100 text-amber-800 border border-amber-200 ml-1.5">
      <Bell className="w-3 h-3" />
      Action needed
    </span>
  )
}

// ── Component ─────────────────────────────────────────────────────────────────

export default function VpApprovalsDashboardPage(): React.ReactElement {
  const navigate = useNavigate()
  const [activeTab, setActiveTab] = useState<TabId>('purchase-requests')
  const { user, hasPermission } = useAuthStore()
  const currentUserId = user?.id ?? null

  const canLeaveApprove   = hasPermission('leaves.executive_approve') || hasPermission('leaves.ga_process') || hasPermission('leaves.vp_note')
  const canOvertimeApprove = hasPermission('overtime.executive_approve')
  const canVpNote  = hasPermission('leaves.vp_note')
  const canGaProcess = hasPermission('leaves.ga_process')

  // ── Search + filters ──────────────────────────────────────────────────────────
  const [search, setSearch] = useState('')
  const debouncedSearch = useDebounce(search, 350)
  const [tabFilters, setTabFilters] = useState<Record<TabId, TabFilter>>(DEFAULT_TAB_FILTERS)

  const currentFilter = tabFilters[activeTab]

  const handleTabChange = (tab: TabId) => {
    setActiveTab(tab)
    setSearch('')
  }

  const setTabStatus = (status: string) => {
    setTabFilters((prev) => ({ ...prev, [activeTab]: { ...prev[activeTab], status, page: 1 } }))
  }

  const setTabPage = (page: number) => {
    setTabFilters((prev) => ({ ...prev, [activeTab]: { ...prev[activeTab], page } }))
  }

  const setTabUrgency = (urgency: string) => {
    setTabFilters((prev) => ({ ...prev, [activeTab]: { ...prev[activeTab], urgency, page: 1 } }))
  }

  // Reset page to 1 when search changes
  useEffect(() => {
    setTabFilters((prev) => ({ ...prev, [activeTab]: { ...prev[activeTab], page: 1 } }))
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [debouncedSearch])

  // ── Leave state ──────────────────────────────────────────────────────────────
  const [processId, setProcessId] = useState<number | null>(null)
  const [actionTaken, setActionTaken] = useState<GaProcessPayload['action_taken']>('approved_with_pay')
  const [leaveRemarks, setLeaveRemarks] = useState('')
  const [vpNoteId, setVpNoteId] = useState<number | null>(null)
  const [vpRemarks, setVpRemarks] = useState('')

  // ── Overtime state ───────────────────────────────────────────────────────────
  const [overtimeFilters] = useState<OvertimeFilters>({ per_page: 25 })
  const [approvingOtId, setApprovingOtId] = useState<number | null>(null)
  const [approvedMins, setApprovedMins] = useState('')
  const [approveOtRemarks, setApproveOtRemarks] = useState('')
  const [rejectOtId, setRejectOtId] = useState<number | null>(null)
  const [rejectOtRemarks, setRejectOtRemarks] = useState('')

  // ── Queries ──────────────────────────────────────────────────────────────────
  const prQuery = useVpPurchaseRequests({
    status:  tabFilters['purchase-requests'].status,
    search:  debouncedSearch || undefined,
    urgency: tabFilters['purchase-requests'].urgency || undefined,
    page:    tabFilters['purchase-requests'].page,
  })
  const loanQuery = useVpLoans({
    status: tabFilters['loans'].status,
    search: debouncedSearch || undefined,
    page:   tabFilters['loans'].page,
  })
  const mrqQuery = useVpMrqs({
    status: tabFilters['mrq'].status,
    search: debouncedSearch || undefined,
    page:   tabFilters['mrq'].page,
  })
  const payrollQuery = useVpPayrollRuns({
    status: tabFilters['payroll'].status,
    search: debouncedSearch || undefined,
    page:   tabFilters['payroll'].page,
  })
  const leaveQuery = useTeamLeaveRequests({
    status:   tabFilters['leave'].status as string | undefined,
    search:   debouncedSearch || undefined,
    per_page: 25,
    page:     tabFilters['leave'].page,
  } as Parameters<typeof useTeamLeaveRequests>[0])
  const otQuery = usePendingExecutiveOvertimeRequests(overtimeFilters, canOvertimeApprove)

  // ── Mutations ────────────────────────────────────────────────────────────────
  const gaProcess   = useGaProcessLeaveRequest()
  const vpNote      = useVpNoteLeaveRequest()
  const rejectLeave = useRejectLeaveRequest()
  const approveOt   = useExecutiveApproveOvertimeRequest()
  const rejectOt    = useExecutiveRejectOvertimeRequest()

  // ── Counts (always pending-approval status, independent of filter selection) ──
  const pendingCounts = useVpPendingCounts()
  const otCount = otQuery.data?.meta?.total ?? 0

  // ── OT handlers ──────────────────────────────────────────────────────────────
  const submitApproveOt = async () => {
    if (!approvingOtId || !approvedMins || Number(approvedMins) < 1) {
      toast.error('Enter valid approved minutes.')
      return
    }
    try {
      await approveOt.mutateAsync({ id: approvingOtId, approved_minutes: Number(approvedMins), remarks: approveOtRemarks || undefined })
      toast.success('Overtime approved.')
      setApprovingOtId(null); setApprovedMins(''); setApproveOtRemarks('')
    } catch (_err) { toast.error(firstErrorMessage(err, 'Approval failed.')) }
  }

  const submitRejectOt = async () => {
    if (!rejectOtId || !rejectOtRemarks.trim()) {
      toast.error('Rejection reason is required.')
      return
    }
    try {
      await rejectOt.mutateAsync({ id: rejectOtId, remarks: rejectOtRemarks })
      toast.success('Overtime rejected.')
      setRejectOtId(null); setRejectOtRemarks('')
    } catch (_err) { toast.error(firstErrorMessage(err, 'Rejection failed.')) }
  }

  // ── Tab definitions ──────────────────────────────────────────────────────────
  const tabs: { id: TabId; label: string; count?: number; show: boolean }[] = [
    { id: 'purchase-requests', label: 'Purchase Requests', count: pendingCounts.pr,      show: hasPermission('procurement.purchase-request.view') },
    { id: 'loans',             label: 'Loans',             count: pendingCounts.loan,    show: hasPermission('loans.vp_approve') },
    { id: 'mrq',               label: 'Requisitions',      count: pendingCounts.mrq,     show: hasPermission('inventory.mrq.vp_approve') },
    { id: 'payroll',           label: 'Payroll',           count: pendingCounts.payroll, show: hasPermission('payroll.vp_approve') },
    { id: 'leave',             label: 'Leave',                                           show: canLeaveApprove },
    { id: 'overtime',          label: 'Overtime',          count: otCount,               show: canOvertimeApprove },
  ].filter((t) => t.show)

  // Ensure activeTab is valid after filtering
  const visibleTabIds = tabs.map((t) => t.id)
  const effectiveTab = visibleTabIds.includes(activeTab) ? activeTab : (visibleTabIds[0] ?? 'purchase-requests')

  // ── Filter bar visibility ─────────────────────────────────────────────────────
  // Leave/Overtime use their own server-side status filtering; show their status dropdown too
  const showStatusFilter = true
  const showUrgencyFilter = effectiveTab === 'purchase-requests'
  const showSearch = effectiveTab !== 'overtime' // OT uses dedicated endpoint, no search param

  return (
    <div>
      {/* Header */}
      <PageHeader
        title="Pending Approvals"
        subtitle="Items awaiting your sign-off -- or browse full record history"
        icon={<ClipboardCheck className="w-5 h-5 text-neutral-600" />}
      />

      {/* Tabs */}
      <div className="flex gap-1 bg-neutral-100 rounded p-1 w-fit mb-4 flex-wrap">
        {tabs.map((tab) => (
          <button
            key={tab.id}
            onClick={() => handleTabChange(tab.id)}
            className={`flex items-center gap-2 px-4 py-2 rounded text-sm font-medium transition-colors ${
              effectiveTab === tab.id
                ? 'bg-white text-neutral-900 shadow-sm'
                : 'text-neutral-500 hover:text-neutral-700'
            }`}
          >
            {tab.label}
            {(tab.count ?? 0) > 0 && (
              <span className={`px-1.5 py-0.5 rounded text-xs font-medium ${
                effectiveTab === tab.id ? 'bg-neutral-100 text-neutral-700' : 'bg-neutral-200 text-neutral-600'
              }`}>
                {tab.count}
              </span>
            )}
          </button>
        ))}
      </div>

      {/* ── Unified Filter Bar ────────────────────────────────────────────────── */}
      <div className="flex gap-2 mb-5 flex-wrap items-center">
        {/* Search */}
        {showSearch && (
          <div className="relative flex-1 min-w-[220px] max-w-sm">
            <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-neutral-400 pointer-events-none" />
            <input
              value={search}
              onChange={(e) => setSearch(e.target.value)}
              placeholder={SEARCH_PLACEHOLDERS[effectiveTab]}
              className="w-full pl-9 pr-8 py-2 border border-neutral-300 rounded text-sm focus:outline-none focus:ring-1 focus:ring-neutral-400 bg-white"
            />
            {search && (
              <button onClick={() => setSearch('')} className="absolute right-2.5 top-1/2 -translate-y-1/2 text-neutral-400 hover:text-neutral-600">
                <X className="w-3.5 h-3.5" />
              </button>
            )}
          </div>
        )}

        {/* Status filter */}
        {showStatusFilter && STATUS_OPTIONS[effectiveTab].length > 1 && (
          <select
            value={currentFilter.status}
            onChange={(e) => setTabStatus(e.target.value)}
            className="border border-neutral-300 rounded px-3 py-2 text-sm bg-white focus:ring-1 focus:ring-neutral-400 outline-none"
          >
            {STATUS_OPTIONS[effectiveTab].map((opt) => (
              <option key={opt.value} value={opt.value}>{opt.label}</option>
            ))}
          </select>
        )}

        {/* Urgency filter (PR tab only) */}
        {showUrgencyFilter && (
          <select
            value={currentFilter.urgency ?? ''}
            onChange={(e) => setTabUrgency(e.target.value)}
            className="border border-neutral-300 rounded px-3 py-2 text-sm bg-white focus:ring-1 focus:ring-neutral-400 outline-none"
          >
            {URGENCY_OPTIONS.map((opt) => (
              <option key={opt.value} value={opt.value}>{opt.label}</option>
            ))}
          </select>
        )}

        {/* Active filters hint */}
        {(debouncedSearch || currentFilter.status !== DEFAULT_TAB_FILTERS[effectiveTab].status) && (
          <button
            onClick={() => { setSearch(''); setTabFilters((prev) => ({ ...prev, [effectiveTab]: { ...DEFAULT_TAB_FILTERS[effectiveTab] } })) }}
            className="text-xs text-neutral-500 hover:text-neutral-800 underline underline-offset-2"
          >
            Reset filters
          </button>
        )}
      </div>

      {/* ── Purchase Requests Tab ─────────────────────────────────────────────── */}
      {effectiveTab === 'purchase-requests' && (
        <div>
          {prQuery.isLoading && <SkeletonLoader rows={5} />}
          {prQuery.isError && <ErrorRow message="Failed to load purchase requests." />}
          {!prQuery.isLoading && !prQuery.isError && (
            <>
              <SimpleTable headers={['PR Reference', 'Department', 'Urgency', 'Total Est. Cost', 'Requested By', 'Reviewed By', 'Status']}>
                {prQuery.data?.data?.length === 0 && <EmptyRow colSpan={7} message="No purchase requests found." />}
                {prQuery.data?.data?.map((pr) => {
                  const needsAction = VP_ACTION_STATUSES['purchase-requests'].includes(pr.status)
                  return (
                    <tr key={pr.id} onClick={() => navigate(`/procurement/purchase-requests/${pr.ulid}`, { state: { from: '/approvals/pending' } })} className={`cursor-pointer ${needsAction ? 'bg-amber-50 hover:bg-amber-100' : 'even:bg-neutral-50 hover:bg-neutral-100'}`}>
                      <td className="px-4 py-3 font-mono text-sm text-neutral-700 font-medium">{pr.pr_reference}</td>
                      <td className="px-4 py-3 text-sm text-neutral-600">{pr.department?.name ?? `#${pr.department_id}`}</td>
                      <td className="px-4 py-3">
                        <span className="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-neutral-100 text-neutral-700 capitalize">{pr.urgency}</span>
                      </td>
                      <td className="px-4 py-3 text-sm font-medium text-neutral-800">
                        ₱{Number(pr.total_estimated_cost).toLocaleString('en-PH', { minimumFractionDigits: 2 })}
                      </td>
                      <td className="px-4 py-3 text-sm text-neutral-600">{pr.requested_by?.name ?? '—'}</td>
                      <td className="px-4 py-3 text-sm text-neutral-600">{pr.reviewed_by?.name ?? '—'}</td>
                      <td className="px-4 py-3 whitespace-nowrap">
                        <StatusBadge status={pr.status}>{pr.status?.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase())}</StatusBadge>
                        {needsAction && <ActionBadge />}
                      </td>
                    </tr>
                  )
                })}
              </SimpleTable>
              {prQuery.data?.meta && prQuery.data.meta.last_page > 1 && (
                <Pagination meta={prQuery.data.meta} onPageChange={setTabPage} />
              )}
            </>
          )}
        </div>
      )}

      {/* ── Loans Tab ────────────────────────────────────────────────────────── */}
      {effectiveTab === 'loans' && (
        <div>
          {loanQuery.isLoading && <SkeletonLoader rows={5} />}
          {loanQuery.isError && <ErrorRow message="Failed to load loans." />}
          {!loanQuery.isLoading && !loanQuery.isError && (
            <>
              <SimpleTable headers={['Loan Reference', 'Employee', 'Type', 'Amount', 'Status']}>
                {loanQuery.data?.data?.length === 0 && <EmptyRow colSpan={5} message="No loans found." />}
                {loanQuery.data?.data?.map((loan) => {
                  const needsAction = VP_ACTION_STATUSES['loans'].includes(loan.status)
                  return (
                    <tr key={loan.id} onClick={() => navigate(`/hr/loans/${loan.ulid ?? loan.id}`, { state: { from: '/approvals/pending' } })} className={`cursor-pointer ${needsAction ? 'bg-amber-50 hover:bg-amber-100' : 'even:bg-neutral-50 hover:bg-neutral-100'}`}>
                      <td className="px-4 py-3 font-mono text-sm text-neutral-700 font-medium">{loan.reference_no ?? `LOAN-${loan.id}`}</td>
                      <td className="px-4 py-3 text-sm text-neutral-600">{loan.employee?.full_name ?? `#${loan.employee_id}`}</td>
                      <td className="px-4 py-3 text-sm text-neutral-600">{loan.loan_type?.name ?? '—'}</td>
                      <td className="px-4 py-3 text-sm font-medium text-neutral-800">₱{Number(loan.principal_php).toLocaleString('en-PH', { minimumFractionDigits: 2 })}</td>
                      <td className="px-4 py-3 whitespace-nowrap">
                        <StatusBadge status={loan.status}>{loan.status?.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase())}</StatusBadge>
                        {needsAction && <ActionBadge />}
                      </td>
                    </tr>
                  )
                })}
              </SimpleTable>
              {loanQuery.data?.meta && loanQuery.data.meta.last_page > 1 && (
                <Pagination meta={loanQuery.data.meta} onPageChange={setTabPage} />
              )}
            </>
          )}
        </div>
      )}

      {/* ── MRQ Tab ──────────────────────────────────────────────────────────── */}
      {effectiveTab === 'mrq' && (
        <div>
          {mrqQuery.isLoading && <SkeletonLoader rows={5} />}
          {mrqQuery.isError && <ErrorRow message="Failed to load material requisitions." />}
          {!mrqQuery.isLoading && !mrqQuery.isError && (
            <>
              <SimpleTable headers={['MR Reference', 'Department', 'Purpose', 'Reviewed By', 'Status']}>
                {mrqQuery.data?.data?.length === 0 && <EmptyRow colSpan={5} message="No material requisitions found." />}
                {mrqQuery.data?.data?.map((mrq) => {
                  const needsAction = VP_ACTION_STATUSES['mrq'].includes(mrq.status)
                  return (
                    <tr key={mrq.id} onClick={() => navigate(`/inventory/requisitions/${mrq.ulid}`, { state: { from: '/approvals/pending' } })} className={`cursor-pointer ${needsAction ? 'bg-amber-50 hover:bg-amber-100' : 'even:bg-neutral-50 hover:bg-neutral-100'}`}>
                      <td className="px-4 py-3 font-mono text-sm text-neutral-700 font-medium">{mrq.mr_reference}</td>
                      <td className="px-4 py-3 text-sm text-neutral-600">{mrq.department?.name ?? `#${mrq.department_id}`}</td>
                      <td className="px-4 py-3 text-sm text-neutral-600 max-w-xs truncate">{mrq.purpose}</td>
                      <td className="px-4 py-3 text-sm text-neutral-600">{mrq.reviewed_by?.name ?? '—'}</td>
                      <td className="px-4 py-3 whitespace-nowrap">
                        <StatusBadge status={mrq.status}>{mrq.status?.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase())}</StatusBadge>
                        {needsAction && <ActionBadge />}
                      </td>
                    </tr>
                  )
                })}
              </SimpleTable>
              {mrqQuery.data?.meta && mrqQuery.data.meta.last_page > 1 && (
                <Pagination meta={mrqQuery.data.meta} onPageChange={setTabPage} />
              )}
            </>
          )}
        </div>
      )}

      {/* ── Payroll Tab ──────────────────────────────────────────────────────── */}
      {effectiveTab === 'payroll' && (
        <div>
          {payrollQuery.isLoading && <SkeletonLoader rows={5} />}
          {payrollQuery.isError && <ErrorRow message="Failed to load payroll runs." />}
          {!payrollQuery.isLoading && !payrollQuery.isError && (
            <>
              <SimpleTable headers={['Run Reference', 'Pay Period', 'Pay Date', 'Employees', 'Net Pay Total', 'Status']}>
                {(payrollQuery.data?.data ?? []).length === 0 && <EmptyRow colSpan={6} message="No payroll runs found." />}
                {(payrollQuery.data?.data ?? []).map((run) => {
                  const needsAction = VP_ACTION_STATUSES['payroll'].includes(run.status)
                  return (
                    <tr key={run.id} onClick={() => navigate(`/payroll/runs/${run.ulid}/vp-review`, { state: { from: '/approvals/pending' } })} className={`cursor-pointer ${needsAction ? 'bg-amber-50 hover:bg-amber-100' : 'even:bg-neutral-50 hover:bg-neutral-100'}`}>
                      <td className="px-4 py-3 font-mono text-sm text-neutral-700 font-medium">{run.reference_no}</td>
                      <td className="px-4 py-3 text-sm text-neutral-600">{run.pay_period_label}</td>
                      <td className="px-4 py-3 text-sm text-neutral-600">{new Date(run.pay_date).toLocaleDateString('en-PH')}</td>
                      <td className="px-4 py-3 text-sm text-neutral-600">{run.total_employees}</td>
                      <td className="px-4 py-3 text-sm font-medium text-neutral-800">
                        ₱{(run.net_pay_total_centavos / 100).toLocaleString('en-PH', { minimumFractionDigits: 2 })}
                      </td>
                      <td className="px-4 py-3 whitespace-nowrap">
                        <StatusBadge status={run.status}>{run.status?.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase())}</StatusBadge>
                        {needsAction && <ActionBadge />}
                      </td>
                    </tr>
                  )
                })}
              </SimpleTable>
              {payrollQuery.data?.meta && payrollQuery.data.meta.last_page > 1 && (
                <Pagination meta={payrollQuery.data.meta} onPageChange={setTabPage} />
              )}
            </>
          )}
        </div>
      )}

      {/* ── Leave Tab ────────────────────────────────────────────────────────── */}
      {effectiveTab === 'leave' && (
        <div>
          {leaveQuery.isLoading && <SkeletonLoader rows={5} />}
          {leaveQuery.isError && <ErrorRow message="Failed to load leave requests." />}
          {!leaveQuery.isLoading && !leaveQuery.isError && (
            <>
              <SimpleTable headers={['Employee', 'Department', 'Leave Type', 'From', 'To', 'Days', 'Status', 'Actions']}>
                {(leaveQuery.data?.data ?? []).length === 0 && <EmptyRow colSpan={8} message="No leave requests found." />}
                {(leaveQuery.data?.data ?? []).map((row) => {
                  const needsAction = VP_ACTION_STATUSES['leave'].includes(row.status)
                  return (
                  <tr key={row.id} className={needsAction ? 'bg-amber-50 hover:bg-amber-100' : 'even:bg-neutral-50 hover:bg-neutral-50'}>
                    <td className="px-4 py-3 text-sm font-medium text-neutral-900">{row.employee?.full_name ?? `#${row.employee_id}`}</td>
                    <td className="px-4 py-3 text-sm text-neutral-600">{row.employee?.department?.name ?? '—'}</td>
                    <td className="px-4 py-3 text-sm text-neutral-600">{row.leave_type?.name ?? '—'}</td>
                    <td className="px-4 py-3 text-sm text-neutral-600">{row.date_from}</td>
                    <td className="px-4 py-3 text-sm text-neutral-600">{row.date_to}</td>
                    <td className="px-4 py-3 text-sm text-neutral-600">{row.total_days}</td>
                    <td className="px-4 py-3 whitespace-nowrap">
                      <StatusBadge status={row.status}>{row.status?.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase())}</StatusBadge>
                      {needsAction && <ActionBadge />}
                    </td>
                    <td className="px-4 py-3">
                      <div className="flex gap-2">
                        {canGaProcess && row.status === 'manager_checked' && (
                          <>
                            <button onClick={() => setProcessId(row.id)} disabled={gaProcess.isPending} className="px-2 py-1 text-xs bg-neutral-900 text-white rounded hover:bg-neutral-800 disabled:opacity-50">Process</button>
                            <button onClick={() => rejectLeave.mutate({ id: row.id, remarks: '' }, { onSuccess: () => toast.success('Rejected.'), onError: (err) => toast.error(parseApiError(err).message) })} disabled={rejectLeave.isPending} className="px-2 py-1 text-xs bg-neutral-600 text-white rounded hover:bg-neutral-700 disabled:opacity-50">Reject</button>
                          </>
                        )}
                        {canVpNote && row.status === 'ga_processed' && (
                          <>
                            <SodActionButton initiatedById={row.submitted_by} label="VP Approve" onClick={() => { setVpNoteId(row.id); setVpRemarks('') }} isLoading={vpNote.isPending} variant="success" />
                            <SodActionButton initiatedById={row.submitted_by} label="Reject" onClick={() => rejectLeave.mutate({ id: row.id, remarks: '' }, { onSuccess: () => toast.success('Rejected by VP.'), onError: (err) => toast.error(parseApiError(err).message) })} isLoading={rejectLeave.isPending} variant="danger" />
                          </>
                        )}
                      </div>
                    </td>
                  </tr>
                  )
                })}
              </SimpleTable>
              {leaveQuery.data?.meta && leaveQuery.data.meta.last_page > 1 && (
                <Pagination meta={leaveQuery.data.meta} onPageChange={setTabPage} />
              )}
            </>
          )}
        </div>
      )}

      {/* ── Overtime Tab ─────────────────────────────────────────────────────── */}
      {effectiveTab === 'overtime' && (
        <div>
          {otQuery.isLoading && <SkeletonLoader rows={5} />}
          {otQuery.isError && <ErrorRow message="Failed to load overtime requests." />}
          {!otQuery.isLoading && !otQuery.isError && (
            <SimpleTable headers={['Employee', 'Date', 'Type', 'Requested Mins', 'Reason', 'Status', 'Actions']}>
              {(otQuery.data?.data ?? []).length === 0 && <EmptyRow colSpan={7} message="No overtime requests pending executive approval." />}
              {(otQuery.data?.data ?? []).map((ot) => (
                <tr key={ot.id} className="even:bg-neutral-50 hover:bg-neutral-50">
                  <td className="px-4 py-3 text-sm font-medium text-neutral-900">{ot.employee?.full_name ?? `#${ot.employee_id}`}</td>
                  <td className="px-4 py-3 text-sm text-neutral-600">{ot.overtime_date}</td>
                  <td className="px-4 py-3 text-sm text-neutral-600 capitalize">{ot.overtime_type?.replace(/_/g, ' ') ?? '—'}</td>
                  <td className="px-4 py-3 text-sm text-neutral-600">{ot.requested_minutes} min</td>
                  <td className="px-4 py-3 text-sm text-neutral-600 max-w-xs truncate">{ot.reason ?? '—'}</td>
                  <td className="px-4 py-3"><StatusBadge status={ot.status}>{ot.status?.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase())}</StatusBadge></td>
                  <td className="px-4 py-3">
                    <div className="flex gap-2">
                      <SodActionButton initiatedById={ot.employee_id ?? null} label="Approve" onClick={() => { setApprovingOtId(ot.id); setApprovedMins(String(ot.requested_minutes)); setApproveOtRemarks('') }} isLoading={approveOt.isPending} variant="primary" />
                      <SodActionButton initiatedById={ot.employee_id ?? null} label="Reject" onClick={() => { setRejectOtId(ot.id); setRejectOtRemarks('') }} isLoading={rejectOt.isPending} variant="danger" />
                    </div>
                  </td>
                </tr>
              ))}
            </SimpleTable>
          )}
        </div>
      )}

      {/* ── Modals ───────────────────────────────────────────────────────────── */}

      {/* GA Process Leave */}
      {processId && (
        <div className="fixed inset-0 bg-black/40 flex items-center justify-center z-50 p-4">
          <div className="bg-white rounded max-w-md w-full p-6 space-y-4">
            <h3 className="text-base font-medium text-neutral-900">Process Leave Request (GA Officer)</h3>
            <div>
              <label className="block text-sm font-medium text-neutral-700 mb-1">Action Taken</label>
              <select value={actionTaken} onChange={(e) => setActionTaken(e.target.value as GaProcessPayload['action_taken'])}
                className="w-full border border-neutral-300 rounded px-3 py-2 text-sm focus:ring-1 focus:ring-neutral-400 outline-none">
                <option value="approved_with_pay">Approved With Pay</option>
                <option value="approved_without_pay">Approved Without Pay</option>
                <option value="disapproved">Disapproved</option>
              </select>
            </div>
            <div>
              <label className="block text-sm font-medium text-neutral-700 mb-1">Remarks (optional)</label>
              <textarea value={leaveRemarks} onChange={(e) => setLeaveRemarks(e.target.value)} rows={3} className="w-full border border-neutral-300 rounded px-3 py-2 text-sm focus:ring-1 focus:ring-neutral-400 outline-none resize-none" />
            </div>
            <div className="flex justify-end gap-3">
              <button onClick={() => { setProcessId(null); setLeaveRemarks(''); setActionTaken('approved_with_pay') }} className="text-sm px-4 py-2 border border-neutral-300 rounded hover:bg-neutral-50">Cancel</button>
              <button disabled={gaProcess.isPending} onClick={() => { if (!processId) return; gaProcess.mutate({ id: processId, action_taken: actionTaken, remarks: leaveRemarks }, { onSuccess: () => { toast.success('Leave request processed.'); setProcessId(null); setLeaveRemarks(''); setActionTaken('approved_with_pay') }, onError: (err) => toast.error(parseApiError(err).message) }) }}
                className="text-sm px-4 py-2 bg-neutral-900 text-white rounded hover:bg-neutral-800 disabled:opacity-50">
                {gaProcess.isPending ? 'Processing…' : 'Submit'}
              </button>
            </div>
          </div>
        </div>
      )}

      {/* VP Note Leave */}
      {vpNoteId && (
        <div className="fixed inset-0 bg-black/40 flex items-center justify-center z-50 p-4">
          <div className="bg-white rounded max-w-md w-full p-6 space-y-4">
            <h3 className="text-base font-medium text-neutral-900">VP Final Notation</h3>
            <p className="text-sm text-neutral-500">Approving will deduct the employee's leave balance for approved-with-pay requests.</p>
            <div>
              <label className="block text-sm font-medium text-neutral-700 mb-1">Remarks (optional)</label>
              <textarea value={vpRemarks} onChange={(e) => setVpRemarks(e.target.value)} rows={3} className="w-full border border-neutral-300 rounded px-3 py-2 text-sm focus:ring-1 focus:ring-neutral-400 outline-none resize-none" />
            </div>
            <div className="flex justify-end gap-3">
              <button onClick={() => { setVpNoteId(null); setVpRemarks('') }} className="text-sm px-4 py-2 border border-neutral-300 rounded hover:bg-neutral-50">Cancel</button>
              <button disabled={vpNote.isPending} onClick={() => { if (!vpNoteId) return; vpNote.mutate({ id: vpNoteId, remarks: vpRemarks || undefined }, { onSuccess: () => { toast.success('Approved by VP.'); setVpNoteId(null); setVpRemarks('') }, onError: (err) => toast.error(parseApiError(err).message) }) }}
                className="text-sm px-4 py-2 bg-neutral-900 text-white rounded hover:bg-neutral-800 disabled:opacity-50">
                {vpNote.isPending ? 'Approving…' : 'Approve (VP)'}
              </button>
            </div>
          </div>
        </div>
      )}

      {/* Approve OT */}
      {approvingOtId && (
        <div className="fixed inset-0 bg-black/40 flex items-center justify-center z-50 p-4">
          <div className="bg-white rounded max-w-md w-full p-6 space-y-4">
            <h3 className="text-base font-medium text-neutral-900">Approve Overtime</h3>
            <div>
              <label className="block text-sm font-medium text-neutral-700 mb-1">Approved Minutes</label>
              <input type="number" min={1} value={approvedMins} onChange={(e) => setApprovedMins(e.target.value)} className="w-full border border-neutral-300 rounded px-3 py-2 text-sm focus:ring-1 focus:ring-neutral-400 outline-none" />
            </div>
            <div>
              <label className="block text-sm font-medium text-neutral-700 mb-1">Remarks (optional)</label>
              <textarea value={approveOtRemarks} onChange={(e) => setApproveOtRemarks(e.target.value)} rows={2} className="w-full border border-neutral-300 rounded px-3 py-2 text-sm focus:ring-1 focus:ring-neutral-400 outline-none resize-none" />
            </div>
            <div className="flex justify-end gap-3">
              <button onClick={() => setApprovingOtId(null)} className="text-sm px-4 py-2 border border-neutral-300 rounded hover:bg-neutral-50">Cancel</button>
              <button disabled={approveOt.isPending} onClick={submitApproveOt} className="text-sm px-4 py-2 bg-neutral-900 text-white rounded hover:bg-neutral-800 disabled:opacity-50">
                {approveOt.isPending ? 'Approving…' : 'Approve'}
              </button>
            </div>
          </div>
        </div>
      )}

      {/* Reject OT */}
      {rejectOtId && (
        <RejectModal title="Reject Overtime" placeholder="Rejection reason (required)" value={rejectOtRemarks} onChange={setRejectOtRemarks} minLength={1}
          onCancel={() => { setRejectOtId(null); setRejectOtRemarks('') }}
          onConfirm={submitRejectOt} isPending={rejectOt.isPending} />
      )}
    </div>
  )
}

// ── Shared components ─────────────────────────────────────────────────────────

function SimpleTable({ headers, children }: { headers: string[]; children: React.ReactNode }) {
  return (
    <div className="bg-white border border-neutral-200 rounded overflow-hidden">
      <table className="min-w-full text-sm">
        <thead className="bg-neutral-50 border-b border-neutral-200">
          <tr>
            {headers.map((h) => (
              <th key={h} className="px-4 py-3 text-left text-xs font-semibold text-neutral-600">{h}</th>
            ))}
          </tr>
        </thead>
        <tbody className="divide-y divide-neutral-100">{children}</tbody>
      </table>
    </div>
  )
}

function EmptyRow({ colSpan, message }: { colSpan: number; message: string }) {
  return (
    <tr>
      <td colSpan={colSpan} className="px-4 py-8 text-center text-neutral-400 text-sm">{message}</td>
    </tr>
  )
}

function ErrorRow({ message }: { message: string }) {
  return (
    <div className="flex items-center gap-2 text-neutral-700 text-sm">
      <AlertTriangle className="w-4 h-4" />{message}
    </div>
  )
}

function Pagination({ meta, onPageChange }: { meta: { current_page: number; last_page: number; total?: number }; onPageChange: (p: number) => void }) {
  return (
    <div className="mt-4 flex items-center justify-between text-sm text-neutral-600">
      <span>
        Page {meta.current_page} of {meta.last_page}
        {meta.total !== undefined && <span className="text-neutral-400 ml-1">({meta.total} total)</span>}
      </span>
      <div className="flex gap-2">
        <button disabled={meta.current_page <= 1} onClick={() => onPageChange(meta.current_page - 1)} className="px-3 py-1 rounded border border-neutral-200 disabled:opacity-40 hover:bg-neutral-50">Previous</button>
        <button disabled={meta.current_page >= meta.last_page} onClick={() => onPageChange(meta.current_page + 1)} className="px-3 py-1 rounded border border-neutral-200 disabled:opacity-40 hover:bg-neutral-50">Next</button>
      </div>
    </div>
  )
}

function RejectModal({ title, placeholder, value, onChange, minLength, onCancel, onConfirm, isPending }: {
  title: string; placeholder: string; value: string; onChange: (v: string) => void
  minLength: number; onCancel: () => void; onConfirm: () => void; isPending: boolean
}) {
  return (
    <div className="fixed inset-0 bg-black/40 flex items-center justify-center z-50 p-4">
      <div className="bg-white rounded max-w-md w-full p-6 space-y-4">
        <h3 className="text-base font-medium text-neutral-900">{title}</h3>
        <textarea value={value} onChange={(e) => onChange(e.target.value)} rows={3} placeholder={placeholder}
          className="w-full text-sm border border-neutral-300 rounded px-3 py-2 focus:outline-none focus:ring-1 focus:ring-neutral-400 resize-none" />
        <div className="flex justify-end gap-3">
          <button onClick={onCancel} className="text-sm px-4 py-2 border border-neutral-300 rounded hover:bg-neutral-50">Cancel</button>
          <button disabled={value.length < minLength || isPending} onClick={onConfirm}
            className="text-sm px-4 py-2 bg-red-600 hover:bg-red-700 disabled:bg-red-300 disabled:cursor-not-allowed text-white font-medium rounded">
            {isPending ? 'Rejecting…' : 'Confirm Reject'}
          </button>
        </div>
      </div>
    </div>
  )
}
