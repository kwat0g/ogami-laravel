import { useParams, useLocation } from 'react-router-dom'
import ExecutiveReadOnlyBanner from '@/components/ui/ExecutiveReadOnlyBanner'
import { PageHeader } from '@/components/ui/PageHeader'
import {
  useLoan,
  useLoanSchedule,
  useEmployeeLoanHistory,
  useApproveLoan,
  useRejectLoan,
  useAccountingApproveLoan,
  useDisburseLoan,
  useHeadNoteLoan,
  useManagerCheckLoan,
  useOfficerReviewLoan,
  useVpApproveLoan,
} from '@/hooks/useLoans'
import { useAuthStore } from '@/stores/authStore'
import SkeletonLoader from '@/components/ui/SkeletonLoader'
import StatusBadge from '@/components/ui/StatusBadge'
import CurrencyAmount from '@/components/ui/CurrencyAmount'
import { useState } from 'react'
import { toast } from 'sonner'
import { firstErrorMessage } from '@/lib/errorHandler'
import ConfirmDialog from '@/components/ui/ConfirmDialog'
import ConfirmDestructiveDialog from '@/components/ui/ConfirmDestructiveDialog'
import ApprovalStepForm from '@/components/ui/ApprovalStepForm'
import StatusTimeline from '@/components/ui/StatusTimeline'
import { getLoanSteps, isRejectedStatus } from '@/lib/workflowSteps'

export default function LoanDetailPage() {
  const { ulid: id } = useParams<{ ulid: string }>()
  const location = useLocation()
  const loanListPath = location.pathname.startsWith('/accounting') ? '/accounting/loans' : '/hr/loans'
  const { user, hasPermission } = useAuthStore()
  const loanId = id ?? null
  const { data: loan, isLoading, isError, refetch } = useLoan(loanId)

  const isRequester = user?.id === loan?.requested_by
  const canApprove = hasPermission('loans.approve') && !isRequester
  const canAccountingApprove = hasPermission('loans.accounting_approve') && !isRequester && user?.id !== loan?.approved_by
  const canDisburse = (hasPermission('loans.accounting_approve') || hasPermission('loans.hr_approve')) && !isRequester
  const canHeadNote = hasPermission('loans.head_note') && !isRequester
  const canManagerCheck = hasPermission('loans.manager_check') && !isRequester && user?.id !== loan?.head_noted_by
  const canOfficerReview = hasPermission('loans.officer_review') && !isRequester && user?.id !== loan?.manager_checked_by
  const canVpApprove = hasPermission('loans.vp_approve') && !isRequester && user?.id !== loan?.officer_reviewed_by

  const { data: schedule, isLoading: schedLoading } = useLoanSchedule(loanId)
  const { data: loanHistory } = useEmployeeLoanHistory(loanId)

  const approve = useApproveLoan()
  const accountingApprove = useAccountingApproveLoan()
  const reject = useRejectLoan()
  const disburse = useDisburseLoan()
  const headNote = useHeadNoteLoan()
  const managerCheck = useManagerCheckLoan()
  const officerReview = useOfficerReviewLoan()
  const vpApprove = useVpApproveLoan()

  const computeFirstDeductionDate = (cutoff: '1st' | '2nd'): string => {
    const now = new Date()
    const year = now.getMonth() === 11 ? now.getFullYear() + 1 : now.getFullYear()
    const month = (now.getMonth() + 2 > 12 ? 1 : now.getMonth() + 2).toString().padStart(2, '0')
    const day = cutoff === '2nd' ? '16' : '01'
    return `${year}-${month}-${day}`
  }

  const [showApproveModal, setShowApproveModal] = useState(false)
  const [approveDate, setApproveDate] = useState('')
  const [approveRemarks, setApproveRemarks] = useState('')
  const [showRejectModal, setShowRejectModal] = useState(false)
  const [showAccountingModal, setShowAccountingModal] = useState(false)
  const [remarks, setRemarks] = useState('')
  const [accountingRemarks, setAccountingRemarks] = useState('')
  const [showHeadNoteModal, setShowHeadNoteModal] = useState(false)
  const [headNoteRemarks, setHeadNoteRemarks] = useState('')
  const [showManagerCheckModal, setShowManagerCheckModal] = useState(false)
  const [managerCheckRemarks, setManagerCheckRemarks] = useState('')
  const [showOfficerReviewModal, setShowOfficerReviewModal] = useState(false)
  const [officerReviewRemarks, setOfficerReviewRemarks] = useState('')
  const [showVpApproveModal, setShowVpApproveModal] = useState(false)
  const [vpApproveRemarks, setVpApproveRemarks] = useState('')

  // Handler functions with toast notifications
  const handleApprove = async () => {
    if (!loan || !approveDate) return
    try {
      await approve.mutateAsync({ id: loan.ulid, first_deduction_date: approveDate, remarks: approveRemarks || undefined })
      toast.success('Loan approved successfully')
      setShowApproveModal(false)
      refetch()
    } catch (err: unknown) {
      const message = firstErrorMessage(err)
      toast.error(`Failed to approve loan: ${message}`)
    }
  }

  const handleReject = async () => {
    if (!loan || !remarks.trim()) return
    try {
      await reject.mutateAsync({ id: loan.ulid, remarks })
      toast.success('Loan rejected')
      setShowRejectModal(false)
      setRemarks('')
      refetch()
    } catch (err: unknown) {
      const message = firstErrorMessage(err)
      toast.error(`Failed to reject loan: ${message}`)
    }
  }

  const handleAccountingApprove = async () => {
    if (!loan) return
    try {
      await accountingApprove.mutateAsync({ id: loan.ulid, remarks: accountingRemarks })
      toast.success('Loan approved for disbursement')
      setShowAccountingModal(false)
      refetch()
    } catch (err: unknown) {
      const message = firstErrorMessage(err)
      toast.error(`Failed to approve loan: ${message}`)
    }
  }

  const handleDisburse = async () => {
    if (!loan) return
    try {
      await disburse.mutateAsync(loan.ulid)
      toast.success('Funds disbursed successfully')
      refetch()
    } catch (err: unknown) {
      const message = firstErrorMessage(err)
      toast.error(`Failed to disburse funds: ${message}`)
      throw err
    }
  }

  const handleHeadNote = async () => {
    if (!loan) return
    try {
      await headNote.mutateAsync({ id: loan.ulid, remarks: headNoteRemarks || undefined })
      toast.success('Head note recorded successfully')
      setShowHeadNoteModal(false)
      refetch()
    } catch (err: unknown) {
      const message = firstErrorMessage(err)
      toast.error(`Failed to record head note: ${message}`)
    }
  }

  const handleManagerCheck = async () => {
    if (!loan) return
    try {
      await managerCheck.mutateAsync({ id: loan.ulid, remarks: managerCheckRemarks || undefined })
      toast.success('Manager check recorded successfully')
      setShowManagerCheckModal(false)
      refetch()
    } catch (err: unknown) {
      const message = firstErrorMessage(err)
      toast.error(`Failed to record manager check: ${message}`)
    }
  }

  const handleOfficerReview = async () => {
    if (!loan) return
    try {
      await officerReview.mutateAsync({ id: loan.ulid, remarks: officerReviewRemarks || undefined })
      toast.success('Accounting review completed successfully')
      setShowOfficerReviewModal(false)
      refetch()
    } catch (err: unknown) {
      const message = firstErrorMessage(err)
      toast.error(`Failed to complete accounting review: ${message}`)
    }
  }

  const handleVpApprove = async () => {
    if (!loan) return
    try {
      await vpApprove.mutateAsync({ id: loan.ulid, remarks: vpApproveRemarks || undefined })
      toast.success('Loan approved by VP. Ready for disbursement.')
      setShowVpApproveModal(false)
      refetch()
    } catch (err: unknown) {
      const message = firstErrorMessage(err)
      toast.error(`Failed to approve loan: ${message}`)
    }
  }

  if (isLoading) return <SkeletonLoader rows={8} />
  if (isError || !loan) return <div className="text-red-600 text-sm mt-4">Failed to load loan details.</div>

  // Filter loan history to show only past/pending loans (not the current one)
  const hasLoanHistory = loanHistory && loanHistory.length > 0
  const activeOrPendingLoans = loanHistory?.filter(l => 
    ['pending', 'approved', 'ready_for_disbursement', 'active'].includes(l.status)
  ) || []
  const paidOffLoans = loanHistory?.filter(l => 
    ['fully_paid', 'written_off'].includes(l.status)
  ) || []

  return (
    <div className="max-w-7xl mx-auto">
      <ExecutiveReadOnlyBanner />
      <PageHeader
        title={loan.loan_type?.name ?? 'Loan Application'}
        subtitle={`${loan.employee?.full_name ?? `Employee #${loan.employee_id}`}${loan.reference_no ? ` · ${loan.reference_no}` : ''}`}
        backTo={loanListPath}
      />

      {/* Workflow Progress */}
      <div className="bg-white border border-neutral-200 rounded-lg p-4 mb-6">
        <h3 className="text-xs font-semibold text-neutral-500 uppercase tracking-wide mb-3">Approval Progress</h3>
        <StatusTimeline
          steps={getLoanSteps(loan)}
          currentStatus={loan.status}
          direction="horizontal"
          isRejected={isRejectedStatus(loan.status)}
        />
      </div>

      {/* Employee Loan History Alert */}
      {hasLoanHistory && (
        <div className="bg-amber-50 border border-amber-200 rounded-lg p-4 mb-6">
          <div className="flex items-start gap-3">
            <span className="text-amber-600 text-lg">⚠️</span>
            <div className="flex-1">
              <h3 className="text-sm font-semibold text-amber-900">Employee Loan History</h3>
              <p className="text-xs text-amber-700 mt-1">
                This employee has {loanHistory.length} previous loan record(s).
                {activeOrPendingLoans.length > 0 && (
                  <span className="font-semibold text-red-600"> {activeOrPendingLoans.length} active/pending loan(s) found.</span>
                )}
              </p>
              
              {/* Active/Pending Loans Table */}
              {activeOrPendingLoans.length > 0 && (
                <div className="mt-3 overflow-hidden rounded-lg border border-amber-200">
                  <table className="min-w-full text-xs">
                    <thead className="bg-amber-100">
                      <tr>
                        <th className="px-3 py-2 text-left font-medium text-amber-900">Ref #</th>
                        <th className="px-3 py-2 text-left font-medium text-amber-900">Type</th>
                        <th className="px-3 py-2 text-right font-medium text-amber-900">Principal</th>
                        <th className="px-3 py-2 text-left font-medium text-amber-900">Status</th>
                      </tr>
                    </thead>
                    <tbody className="divide-y divide-amber-200 bg-white">
                      {activeOrPendingLoans.map((histLoan) => (
                        <tr key={histLoan.id} className="hover:bg-amber-50">
                          <td className="px-3 py-2 font-medium text-amber-900">{histLoan.reference_no}</td>
                          <td className="px-3 py-2 text-amber-800">{histLoan.loan_type?.name}</td>
                          <td className="px-3 py-2 text-right text-amber-800">
                            <CurrencyAmount centavos={histLoan.principal_centavos} />
                          </td>
                          <td className="px-3 py-2">
                            <StatusBadge status={histLoan.status}>{histLoan.status?.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase())}</StatusBadge>
                          </td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              )}

              {/* Paid Off Loans (collapsible) */}
              {paidOffLoans.length > 0 && (
                <details className="mt-3">
                  <summary className="text-xs text-amber-700 cursor-pointer hover:text-amber-900">
                    View {paidOffLoans.length} paid off / written off loan(s)
                  </summary>
                  <div className="mt-2 overflow-hidden rounded-lg border border-amber-200">
                    <table className="min-w-full text-xs">
                      <thead className="bg-amber-100">
                        <tr>
                          <th className="px-3 py-2 text-left font-medium text-amber-900">Ref #</th>
                          <th className="px-3 py-2 text-left font-medium text-amber-900">Type</th>
                          <th className="px-3 py-2 text-right font-medium text-amber-900">Principal</th>
                          <th className="px-3 py-2 text-left font-medium text-amber-900">Status</th>
                        </tr>
                      </thead>
                      <tbody className="divide-y divide-amber-200 bg-white">
                        {paidOffLoans.map((histLoan) => (
                          <tr key={histLoan.id} className="hover:bg-amber-50">
                            <td className="px-3 py-2 font-medium text-amber-900">{histLoan.reference_no}</td>
                            <td className="px-3 py-2 text-amber-800">{histLoan.loan_type?.name}</td>
                            <td className="px-3 py-2 text-right text-amber-800">
                              <CurrencyAmount centavos={histLoan.principal_centavos} />
                            </td>
                            <td className="px-3 py-2">
                              <StatusBadge status={histLoan.status}>{histLoan.status?.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase())}</StatusBadge>
                            </td>
                          </tr>
                        ))}
                      </tbody>
                    </table>
                  </div>
                </details>
              )}
            </div>
          </div>
        </div>
      )}

      {/* Info card */}
      <div className="bg-white border border-neutral-200 rounded-lg p-6 mb-6">
        <div className="grid grid-cols-2 md:grid-cols-3 gap-6">
          <div>
            <p className="text-xs text-neutral-500 uppercase tracking-wide mb-1">Status</p>
            <StatusBadge status={loan.status}>{loan.status?.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase())}</StatusBadge>
          </div>
          <div>
            <p className="text-xs text-neutral-500 uppercase tracking-wide mb-1">Loan Type</p>
            <p className="text-sm font-medium text-neutral-900">{loan.loan_type?.name ?? '—'}</p>
          </div>
          <div>
            <p className="text-xs text-neutral-500 uppercase tracking-wide mb-1">Loan Date</p>
            <p className="text-sm text-neutral-700">
              {loan.loan_date ? new Date(loan.loan_date + 'T00:00:00').toLocaleDateString('en-PH', { year: 'numeric', month: 'long', day: 'numeric' }) : '—'}
            </p>
          </div>
          <div>
            <p className="text-xs text-neutral-500 uppercase tracking-wide mb-1">Principal</p>
            <p className="text-sm font-semibold text-neutral-900"><CurrencyAmount centavos={loan.principal_centavos} /></p>
          </div>
          <div>
            <p className="text-xs text-neutral-500 uppercase tracking-wide mb-1">Term</p>
            <p className="text-sm text-neutral-700">{loan.term_months} months</p>
          </div>
          <div>
            <p className="text-xs text-neutral-500 uppercase tracking-wide mb-1">Monthly Amortization</p>
            <p className="text-sm font-semibold text-neutral-900"><CurrencyAmount centavos={loan.monthly_amortization_centavos} /></p>
          </div>
          <div>
            <p className="text-xs text-neutral-500 uppercase tracking-wide mb-1">Outstanding Balance</p>
            <p className="text-sm font-bold text-neutral-900"><CurrencyAmount centavos={loan.outstanding_balance_centavos} /></p>
          </div>
          <div>
            <p className="text-xs text-neutral-500 uppercase tracking-wide mb-1">Total Payable</p>
            <p className="text-sm text-neutral-700"><CurrencyAmount centavos={loan.total_payable_centavos} /></p>
          </div>
          <div>
            <p className="text-xs text-neutral-500 uppercase tracking-wide mb-1">Deduction Cut-off</p>
            <p className="text-sm font-medium text-neutral-900">
              {loan.deduction_cutoff === '1st' ? '1st Cut-off (1–15)' : '2nd Cut-off (16–end)'}
            </p>
          </div>
          {loan.purpose && (
            <div className="col-span-2">
              <p className="text-xs text-neutral-500 uppercase tracking-wide mb-1">Purpose</p>
              <p className="text-sm text-neutral-700">{loan.purpose}</p>
            </div>
          )}
        </div>

        {/* Approval Timeline */}
        <div className="mt-6 pt-6 border-t border-neutral-100">
          <h3 className="text-sm font-semibold text-neutral-900 mb-4">
            Timeline {loan.workflow_version === 2 && <span className="text-xs font-normal text-neutral-500 ml-1">(v2 workflow)</span>}
          </h3>

          {/* ── v2 Timeline ── */}
          {loan.workflow_version === 2 ? (
            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">
              {/* v2 Step 1: Dept Head Note */}
              {(() => {
                const isDone = !!loan.head_noted_by
                const isPending = !isDone && loan.status === 'pending'
                return (
                  <div className={`p-4 rounded-lg border ${isDone ? 'bg-green-50 border-green-200' : isPending ? 'bg-amber-50 border-amber-200' : 'bg-neutral-50 border-neutral-200'}`}>
                    <div className="flex items-center gap-2 mb-2">
                      <span className={`w-3 h-3 rounded-full ${isDone ? 'bg-green-500' : isPending ? 'bg-amber-400 animate-pulse' : 'bg-neutral-300'}`} />
                      <span className="text-sm font-medium text-neutral-900">1. Dept Head Note</span>
                    </div>
                    {isDone ? (
                      <>
                        <p className="text-xs text-neutral-600">Noted by User #{loan.head_noted_by}</p>
                        <p className="text-xs text-neutral-500">{loan.head_noted_at?.slice(0, 10)}</p>
                        {loan.head_remarks && <p className="text-xs text-neutral-500 mt-1 italic">&ldquo;{loan.head_remarks}&rdquo;</p>}
                      </>
                    ) : (
                      <p className="text-xs text-neutral-500">{isPending ? 'Awaiting Dept Head' : 'Pending'}</p>
                    )}
                  </div>
                )
              })()}

              {/* v2 Step 2: Manager Check */}
              {(() => {
                const isDone = !!loan.manager_checked_by
                const isPending = !isDone && loan.status === 'head_noted'
                return (
                  <div className={`p-4 rounded-lg border ${isDone ? 'bg-green-50 border-green-200' : isPending ? 'bg-amber-50 border-amber-200' : 'bg-neutral-50 border-neutral-200'}`}>
                    <div className="flex items-center gap-2 mb-2">
                      <span className={`w-3 h-3 rounded-full ${isDone ? 'bg-green-500' : isPending ? 'bg-amber-400 animate-pulse' : 'bg-neutral-300'}`} />
                      <span className="text-sm font-medium text-neutral-900">2. Manager Check</span>
                    </div>
                    {isDone ? (
                      <>
                        <p className="text-xs text-neutral-600">Checked by User #{loan.manager_checked_by}</p>
                        <p className="text-xs text-neutral-500">{loan.manager_checked_at?.slice(0, 10)}</p>
                        {loan.manager_remarks && <p className="text-xs text-neutral-500 mt-1 italic">&ldquo;{loan.manager_remarks}&rdquo;</p>}
                      </>
                    ) : (
                      <p className="text-xs text-neutral-500">{isPending ? 'Awaiting Manager' : 'Pending'}</p>
                    )}
                  </div>
                )
              })()}

              {/* v2 Step 3: Officer Review */}
              {(() => {
                const isDone = !!loan.officer_reviewed_by
                const isPending = !isDone && loan.status === 'manager_checked'
                return (
                  <div className={`p-4 rounded-lg border ${isDone ? 'bg-green-50 border-green-200' : isPending ? 'bg-amber-50 border-amber-200' : 'bg-neutral-50 border-neutral-200'}`}>
                    <div className="flex items-center gap-2 mb-2">
                      <span className={`w-3 h-3 rounded-full ${isDone ? 'bg-green-500' : isPending ? 'bg-amber-400 animate-pulse' : 'bg-neutral-300'}`} />
                      <span className="text-sm font-medium text-neutral-900">3. Accounting Review</span>
                    </div>
                    {isDone ? (
                      <>
                        <p className="text-xs text-neutral-600">Reviewed by User #{loan.officer_reviewed_by}</p>
                        <p className="text-xs text-neutral-500">{loan.officer_reviewed_at?.slice(0, 10)}</p>
                        {loan.officer_remarks && <p className="text-xs text-neutral-500 mt-1 italic">&ldquo;{loan.officer_remarks}&rdquo;</p>}
                      </>
                    ) : (
                      <p className="text-xs text-neutral-500">{isPending ? 'Awaiting Accounting Officer' : 'Pending'}</p>
                    )}
                  </div>
                )
              })()}

              {/* v2 Step 4: VP Approve */}
              {(() => {
                const isDone = !!loan.vp_approved_by
                const isPending = !isDone && loan.status === 'officer_reviewed'
                return (
                  <div className={`p-4 rounded-lg border ${isDone ? 'bg-green-50 border-green-200' : isPending ? 'bg-amber-50 border-amber-200' : 'bg-neutral-50 border-neutral-200'}`}>
                    <div className="flex items-center gap-2 mb-2">
                      <span className={`w-3 h-3 rounded-full ${isDone ? 'bg-green-500' : isPending ? 'bg-amber-400 animate-pulse' : 'bg-neutral-300'}`} />
                      <span className="text-sm font-medium text-neutral-900">4. VP Approval</span>
                    </div>
                    {isDone ? (
                      <>
                        <p className="text-xs text-neutral-600">Approved by User #{loan.vp_approved_by}</p>
                        <p className="text-xs text-neutral-500">{loan.vp_approved_at?.slice(0, 10)}</p>
                        {loan.vp_remarks && <p className="text-xs text-neutral-500 mt-1 italic">&ldquo;{loan.vp_remarks}&rdquo;</p>}
                      </>
                    ) : (
                      <p className="text-xs text-neutral-500">{isPending ? 'Awaiting VP' : 'Pending'}</p>
                    )}
                  </div>
                )
              })()}

              {/* v2 Step 5: Disbursement */}
              {(() => {
                const terminal = loan.status === 'rejected' || loan.status === 'cancelled'
                const isDisbursed = !!loan.disbursed_at
                const isPending = !terminal && !isDisbursed && loan.status === 'ready_for_disbursement'
                return (
                  <div className={`p-4 rounded-lg border ${terminal ? 'bg-neutral-50 border-neutral-200' : isDisbursed ? 'bg-green-50 border-green-200' : isPending ? 'bg-amber-50 border-amber-200' : 'bg-neutral-50 border-neutral-200'}`}>
                    <div className="flex items-center gap-2 mb-2">
                      <span className={`w-3 h-3 rounded-full ${terminal ? 'bg-neutral-300' : isDisbursed ? 'bg-green-500' : isPending ? 'bg-amber-400 animate-pulse' : 'bg-neutral-300'}`} />
                      <span className="text-sm font-medium text-neutral-900">5. Disbursement</span>
                    </div>
                    {isDisbursed ? (
                      <>
                        <p className="text-xs text-neutral-600">Disbursed by {loan.disbursed_by ? `User #${loan.disbursed_by}` : '—'}</p>
                        <p className="text-xs text-neutral-500">{loan.disbursed_at?.slice(0, 10)}</p>
                        <p className="text-xs text-green-700 mt-2 font-medium">✓ Funds released</p>
                      </>
                    ) : (
                      <p className="text-xs text-neutral-500">{terminal ? 'N/A' : isPending ? 'Ready to disburse' : 'Awaiting VP approval'}</p>
                    )}
                  </div>
                )
              })()}
            </div>
          ) : (
            /* ── v1 Timeline ── */
            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
              {/* v1 Step 0: Department Head Note */}
              {(() => {
                const isNoted = !!loan.head_noted_by
                const isPending = !isNoted && loan.status === 'pending'
                return (
                  <div className={`p-4 rounded-lg border ${isNoted ? 'bg-amber-50 border-amber-200' : isPending ? 'bg-amber-50 border-amber-200' : 'bg-neutral-50 border-neutral-200'}`}>
                    <div className="flex items-center gap-2 mb-2">
                      <span className={`w-3 h-3 rounded-full ${isNoted ? 'bg-amber-500' : isPending ? 'bg-amber-400 animate-pulse' : 'bg-neutral-300'}`} />
                      <span className="text-sm font-medium text-neutral-900">0. Dept Head Note</span>
                    </div>
                    {isNoted ? (
                      <>
                        <p className="text-xs text-neutral-600">Noted by User #{loan.head_noted_by}</p>
                        <p className="text-xs text-neutral-500">{loan.head_noted_at?.slice(0, 10)}</p>
                        {loan.head_remarks && <p className="text-xs text-neutral-500 mt-1 italic">&ldquo;{loan.head_remarks}&rdquo;</p>}
                      </>
                    ) : (
                      <p className="text-xs text-neutral-500">{isPending ? 'Awaiting Dept Head note' : 'Not required / skipped'}</p>
                    )}
                  </div>
                )
              })()}
              {/* v1 Step 1: HR Approval */}
              {(() => {
                const isRejected = loan.status === 'rejected' && !!loan.approved_by
                const isApproved = !!loan.approved_by && !isRejected
                return (
                  <div className={`p-4 rounded-lg border ${isRejected ? 'bg-red-50 border-red-200' : isApproved ? 'bg-green-50 border-green-200' : loan.status === 'pending' ? 'bg-amber-50 border-amber-200' : 'bg-neutral-50 border-neutral-200'}`}>
                    <div className="flex items-center gap-2 mb-2">
                      <span className={`w-3 h-3 rounded-full ${isRejected ? 'bg-red-500' : isApproved ? 'bg-green-500' : loan.status === 'pending' ? 'bg-amber-400 animate-pulse' : 'bg-neutral-300'}`} />
                      <span className="text-sm font-medium text-neutral-900">1. HR Manager Approval</span>
                    </div>
                    {isRejected ? (
                      <>
                        <p className="text-xs text-red-700 font-medium">Rejected</p>
                        <p className="text-xs text-neutral-600">{loan.approver_name ?? `User #${loan.approved_by}`}</p>
                        <p className="text-xs text-neutral-500">{loan.approved_at?.slice(0, 10)}</p>
                        {loan.approver_remarks && <p className="text-xs text-neutral-500 mt-1 italic">&ldquo;{loan.approver_remarks}&rdquo;</p>}
                      </>
                    ) : isApproved ? (
                      <>
                        <p className="text-xs text-neutral-600">Approved by {loan.approver_name ?? `User #${loan.approved_by}`}</p>
                        <p className="text-xs text-neutral-500">{loan.approved_at?.slice(0, 10)}</p>
                        {loan.approver_remarks && <p className="text-xs text-neutral-500 mt-1 italic">&ldquo;{loan.approver_remarks}&rdquo;</p>}
                      </>
                    ) : (
                      <p className="text-xs text-neutral-500">{loan.status === 'pending' ? 'Awaiting HR Manager review' : 'Not yet reviewed'}</p>
                    )}
                  </div>
                )
              })()}
              {/* v1 Step 2: Accounting Approval */}
              {(() => {
                const terminal = loan.status === 'rejected' || loan.status === 'cancelled'
                const isApproved = !!loan.accounting_approved_by
                const isPending = !terminal && !isApproved && loan.status === 'approved'
                return (
                  <div className={`p-4 rounded-lg border ${terminal ? 'bg-neutral-50 border-neutral-200' : isApproved ? 'bg-green-50 border-green-200' : isPending ? 'bg-amber-50 border-amber-200' : 'bg-neutral-50 border-neutral-200'}`}>
                    <div className="flex items-center gap-2 mb-2">
                      <span className={`w-3 h-3 rounded-full ${terminal ? 'bg-neutral-300' : isApproved ? 'bg-green-500' : isPending ? 'bg-amber-400 animate-pulse' : 'bg-neutral-300'}`} />
                      <span className="text-sm font-medium text-neutral-900">2. Accounting Approval</span>
                    </div>
                    {isApproved ? (
                      <>
                        <p className="text-xs text-neutral-600">Approved by {loan.accounting_approver_name ?? `User #${loan.accounting_approved_by}`}</p>
                        <p className="text-xs text-neutral-500">{loan.accounting_approved_at?.slice(0, 10)}</p>
                        {loan.accounting_remarks && <p className="text-xs text-neutral-500 mt-1 italic">&ldquo;{loan.accounting_remarks}&rdquo;</p>}
                        {loan.journal_entry_id && <p className="text-xs text-neutral-600 mt-2 font-medium">GL Entry: JE #{loan.journal_entry_id}</p>}
                      </>
                    ) : (
                      <p className="text-xs text-neutral-500">{terminal ? 'N/A' : isPending ? 'Ready for Accounting review' : 'Waiting for HR approval'}</p>
                    )}
                  </div>
                )
              })()}
              {/* v1 Step 3: Disbursement */}
              {(() => {
                const terminal = loan.status === 'rejected' || loan.status === 'cancelled'
                const isDisbursed = !!loan.disbursed_at
                const isPending = !terminal && !isDisbursed && loan.status === 'ready_for_disbursement'
                return (
                  <div className={`p-4 rounded-lg border ${terminal ? 'bg-neutral-50 border-neutral-200' : isDisbursed ? 'bg-green-50 border-green-200' : isPending ? 'bg-amber-50 border-amber-200' : 'bg-neutral-50 border-neutral-200'}`}>
                    <div className="flex items-center gap-2 mb-2">
                      <span className={`w-3 h-3 rounded-full ${terminal ? 'bg-neutral-300' : isDisbursed ? 'bg-green-500' : isPending ? 'bg-amber-400 animate-pulse' : 'bg-neutral-300'}`} />
                      <span className="text-sm font-medium text-neutral-900">3. Fund Disbursement</span>
                    </div>
                    {isDisbursed ? (
                      <>
                        <p className="text-xs text-neutral-600">Disbursed by {loan.disbursed_by ? `User #${loan.disbursed_by}` : '—'}</p>
                        <p className="text-xs text-neutral-500">{loan.disbursed_at?.slice(0, 10)}</p>
                        <p className="text-xs text-green-700 mt-2 font-medium">✓ Funds released to employee</p>
                      </>
                    ) : (
                      <p className="text-xs text-neutral-500">{terminal ? 'N/A' : isPending ? 'Ready for disbursement' : 'Waiting for accounting approval'}</p>
                    )}
                  </div>
                )
              })()}
            </div>
          )}
        </div>

        {/* Actions */}
        <div className="mt-6 flex flex-wrap gap-3">
          {/* ── v2 workflow actions ── */}
          {loan.workflow_version === 2 && (
            <>
              {/* v2 Stage 1: Dept Head Note */}
              {canHeadNote && loan.status === 'pending' && (
                <ApprovalStepForm
                  title="Head Note -- Loan Application"
                  description="Review the employee's loan request and add your endorsement before forwarding to Manager."
                  confirmLabel="Confirm Head Note"
                  onConfirm={(_comments) => handleHeadNote()}
                  isLoading={headNote.isPending}
                  checklist={['Employee eligibility verified', 'Loan amount is reasonable for salary grade']}
                >
                  <button
                    disabled={headNote.isPending}
                    className="px-4 py-2 text-sm bg-amber-500 hover:bg-amber-600 text-white rounded disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2">
                    Head Note
                  </button>
                </ApprovalStepForm>
              )}

              {/* v2 Stage 2: Manager Check */}
              {canManagerCheck && loan.status === 'head_noted' && (
                <ApprovalStepForm
                  title="Manager Check -- Loan Application"
                  description="Review the loan application and department head's endorsement before forwarding to Accounting."
                  confirmLabel="Confirm Manager Check"
                  onConfirm={(_comments) => handleManagerCheck()}
                  isLoading={managerCheck.isPending}
                  checklist={['Head Note endorsement reviewed', 'No existing loan defaults']}
                >
                  <button
                    disabled={managerCheck.isPending}
                    className="px-4 py-2 text-sm bg-neutral-800 hover:bg-neutral-900 text-white rounded disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2">
                    Manager Check
                  </button>
                </ApprovalStepForm>
              )}

              {/* v2 Stage 3: Officer Review */}
              {canOfficerReview && loan.status === 'manager_checked' && (
                <ApprovalStepForm
                  title="Accounting Review -- Loan Application"
                  description="Review the loan terms, interest rate, and verify financial eligibility before VP approval."
                  confirmLabel="Confirm Accounting Review"
                  onConfirm={(_comments) => handleOfficerReview()}
                  isLoading={officerReview.isPending}
                  checklist={['Loan terms and interest rate verified', 'Monthly amortization within salary deduction limits', 'No outstanding balance on previous loans']}
                >
                  <button
                    disabled={officerReview.isPending}
                    className="px-4 py-2 text-sm bg-neutral-800 hover:bg-neutral-900 text-white rounded disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2">
                    Accounting Review
                  </button>
                </ApprovalStepForm>
              )}

              {/* v2 Stage 4: VP Approve */}
              {canVpApprove && loan.status === 'officer_reviewed' && (
                <ApprovalStepForm
                  title="VP Final Approval -- Loan Application"
                  description="Final approval step. This will generate the amortization schedule and mark the loan as ready for disbursement."
                  confirmLabel="Approve Loan"
                  onConfirm={(_comments) => handleVpApprove()}
                  isLoading={vpApprove.isPending}
                  checklist={['All previous review steps completed', 'Amortization schedule reviewed']}
                >
                  <button
                    disabled={vpApprove.isPending}
                    className="px-4 py-2 text-sm bg-neutral-900 hover:bg-neutral-800 text-white rounded disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2">
                    VP Approve
                  </button>
                </ApprovalStepForm>
              )}

              {/* Reject (any pending v2 step) */}
              {(loan.status === 'pending' || loan.status === 'head_noted' || loan.status === 'manager_checked' || loan.status === 'officer_reviewed') && (
                <ConfirmDestructiveDialog
                  title="Reject Loan Application?"
                  description="Are you sure you want to reject this loan application? This action cannot be undone."
                  confirmWord="REJECT"
                  confirmLabel="Confirm Rejection"
                  onConfirm={handleReject}
                >
                  <button
                    disabled={reject.isPending}
                    className="px-4 py-2 text-sm bg-red-600 hover:bg-red-700 text-white rounded flex items-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed">
                    <span>✕</span> Reject
                  </button>
                </ConfirmDestructiveDialog>
              )}
            </>
          )}

          {/* ── v1 workflow actions ── */}
          {loan.workflow_version !== 2 && (
            <>
              {/* Head Note (optional in v1) */}
              {canHeadNote && loan.status === 'pending' && (
                <ConfirmDialog
                  title="Head Note — Loan Application"
                  description="Add your endorsement note before forwarding to HR Manager review."
                  confirmLabel="Confirm Head Note"
                  onConfirm={handleHeadNote}
                >
                  <button
                    onClick={() => { setHeadNoteRemarks(''); setShowHeadNoteModal(true) }}
                    disabled={headNote.isPending}
                    className="px-4 py-2 text-sm bg-amber-500 hover:bg-amber-600 text-white rounded disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2">
                    <span>📋</span> Head Note
                  </button>
                </ConfirmDialog>
              )}

              {/* HR Manager Actions */}
              {canApprove && loan.status === 'pending' && (
                <>
                  <ConfirmDialog
                    title="Approve Loan Application"
                    description={`This will approve the loan application. Requested cut-off: ${loan.deduction_cutoff === '1st' ? '1st Cut-off (deducted every 1–15)' : '2nd Cut-off (deducted every 16–end)'}`}
                    confirmLabel="Confirm Approval"
                    onConfirm={handleApprove}
                  >
                    <button 
                      onClick={() => { setApproveDate(computeFirstDeductionDate(loan.deduction_cutoff)); setApproveRemarks(''); setShowApproveModal(true) }} 
                      disabled={approve.isPending}
                      className="px-4 py-2 text-sm bg-neutral-900 hover:bg-neutral-800 text-white rounded disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2">
                      <span>✓</span> Approve (HR)
                    </button>
                  </ConfirmDialog>
                  <ConfirmDestructiveDialog
                    title="Reject Loan Application?"
                    description="Are you sure you want to reject this loan application? This action cannot be undone."
                    confirmWord="REJECT"
                    confirmLabel="Confirm Rejection"
                    onConfirm={handleReject}
                  >
                    <button
                      disabled={approve.isPending || reject.isPending}
                      className="px-4 py-2 text-sm bg-red-600 hover:bg-red-700 text-white rounded flex items-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed">
                      <span>✕</span> Reject
                    </button>
                  </ConfirmDestructiveDialog>
                </>
              )}

              {/* Accounting Manager Actions */}
              {canAccountingApprove && loan.status === 'approved' && (
                <>
                  <ConfirmDialog
                    title="Approve for Disbursement"
                    description="This will create a GL entry debiting Loans Receivable and crediting Loans Payable. Please verify funds availability before proceeding."
                    confirmLabel="Confirm Approval"
                    onConfirm={handleAccountingApprove}
                  >
                    <button 
                      onClick={() => setShowAccountingModal(true)}
                      disabled={accountingApprove.isPending || reject.isPending}
                      className="px-4 py-2 text-sm bg-neutral-800 hover:bg-neutral-900 text-white rounded flex items-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed">
                      <span>✓</span> Approve for Disbursement (Accounting)
                    </button>
                  </ConfirmDialog>
                  <ConfirmDestructiveDialog
                    title="Reject Loan Application?"
                    description="Are you sure you want to reject this loan application? This action cannot be undone."
                    confirmWord="REJECT"
                    confirmLabel="Confirm Rejection"
                    onConfirm={handleReject}
                  >
                    <button
                      disabled={accountingApprove.isPending || reject.isPending}
                      className="px-4 py-2 text-sm bg-red-600 hover:bg-red-700 text-white rounded flex items-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed">
                      <span>✕</span> Reject
                    </button>
                  </ConfirmDestructiveDialog>
                </>
              )}
            </>
          )}

          {/* Disbursement Action (both v1 and v2) */}
          {canDisburse && loan.status === 'ready_for_disbursement' && (
            <ConfirmDialog
              title="Disburse Funds?"
              description="This will mark the loan as disbursed and release funds to the employee."
              confirmLabel="Confirm Disbursement"
              onConfirm={handleDisburse}
            >
              <button 
                disabled={disburse.isPending}
                className="px-4 py-2 text-sm bg-neutral-900 hover:bg-neutral-800 text-white rounded disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2">
                <span>💵</span> Disburse Funds
              </button>
            </ConfirmDialog>
          )}
        </div>
      </div>

      {/* Amortization schedule */}
      <h2 className="text-lg font-semibold text-neutral-900 mb-3">Amortization Schedule</h2>
      <div className="bg-white border border-neutral-200 rounded-xl overflow-hidden">
        {schedLoading ? <SkeletonLoader rows={6} /> : (
          <table className="min-w-full text-sm">
            <thead className="bg-neutral-50 border-b border-neutral-200">
              <tr>
                {['#', 'Due Date', 'Principal', 'Interest', 'Amortization', 'Balance', 'Paid'].map((h) => (
                  <th key={h} className="px-4 py-3 text-left text-xs font-semibold text-neutral-500 uppercase tracking-wider">{h}</th>
                ))}
              </tr>
            </thead>
            <tbody className="divide-y divide-neutral-100">
              {(!schedule || schedule.length === 0) ? (
                <tr><td colSpan={7} className="px-4 py-6 text-center text-neutral-400">No schedule available. Loan needs HR approval to generate schedule.</td></tr>
              ) : schedule.map((entry) => (
                <tr key={entry.installment_no} className={entry.is_paid ? 'bg-green-50' : 'hover:bg-neutral-50'}>
                  <td className="px-4 py-2 text-neutral-600">{entry.installment_no}</td>
                  <td className="px-4 py-2 text-neutral-600">{entry.due_date}</td>
                  <td className="px-4 py-2 text-neutral-700"><CurrencyAmount centavos={entry.principal} /></td>
                  <td className="px-4 py-2 text-neutral-700"><CurrencyAmount centavos={entry.interest} /></td>
                  <td className="px-4 py-2 font-medium text-neutral-900"><CurrencyAmount centavos={entry.amortization} /></td>
                  <td className="px-4 py-2 text-neutral-700"><CurrencyAmount centavos={entry.balance} /></td>
                  <td className="px-4 py-2">
                    {entry.is_paid
                      ? <span className="text-xs text-green-700 font-medium">✓ {entry.paid_at?.slice(0, 10)}</span>
                      : <span className="text-xs text-neutral-400">—</span>}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        )}
      </div>

      {/* HR Approve Modal */}
      {showApproveModal && (
        <div className="fixed inset-0 bg-black/40 flex items-center justify-center z-50">
          <div className="bg-white rounded-lg border border-neutral-200 p-6 w-full max-w-md">
            <h2 className="text-lg font-semibold text-neutral-900 mb-2">Approve Loan Application</h2>
            <div className="bg-neutral-50 border border-neutral-200 rounded px-3 py-2 mb-4 text-sm text-neutral-700">
              Requested cut-off: <span className="font-semibold">
                {loan.deduction_cutoff === '1st' ? '1st Cut-off (deducted every 1–15)' : '2nd Cut-off (deducted every 16–end)'}
              </span>
            </div>
            <label className="block text-xs font-medium text-neutral-700 mb-1">First Deduction Date <span className="text-red-500">*</span></label>
            <input
              type="date"
              value={approveDate}
              min={new Date().toISOString().slice(0, 10)}
              onChange={(e) => setApproveDate(e.target.value)}
              className="w-full border border-neutral-300 rounded px-3 py-2 text-sm outline-none focus:ring-1 focus:ring-neutral-400 mb-3"
            />
            <label className="block text-xs font-medium text-neutral-700 mb-1">Remarks (optional)</label>
            <textarea
              value={approveRemarks}
              onChange={(e) => setApproveRemarks(e.target.value)}
              placeholder="Optional remarks..."
              rows={2}
              className="w-full border border-neutral-300 rounded px-3 py-2 text-sm outline-none focus:ring-1 focus:ring-neutral-400"
            />
            <div className="flex justify-end gap-3 mt-4">
              <button onClick={() => setShowApproveModal(false)} className="px-4 py-2 text-sm text-neutral-600 hover:bg-neutral-100 rounded">
                Cancel
              </button>
              <button
                disabled={!approveDate || approve.isPending}
                onClick={handleApprove}
                className="px-4 py-2 text-sm bg-neutral-900 hover:bg-neutral-800 text-white rounded disabled:opacity-40"
              >
                {approve.isPending ? 'Approving…' : 'Confirm Approval'}
              </button>
            </div>
          </div>
        </div>
      )}

      {/* Head Note Modal */}
      {showHeadNoteModal && (
        <div className="fixed inset-0 bg-black/40 flex items-center justify-center z-50">
          <div className="bg-white rounded-lg border border-neutral-200 p-6 w-full max-w-md">
            <h2 className="text-lg font-semibold text-neutral-900 mb-2">Head Note — Loan Application</h2>
            <p className="text-sm text-neutral-600 mb-4">
              Add your endorsement note before forwarding to HR Manager review.
            </p>
            <label className="block text-xs font-medium text-neutral-700 mb-1">Remarks (optional)</label>
            <textarea
              value={headNoteRemarks}
              onChange={(e) => setHeadNoteRemarks(e.target.value)}
              placeholder="Add remarks or recommendations..."
              rows={3}
              className="w-full border border-neutral-300 rounded px-3 py-2 text-sm outline-none focus:ring-1 focus:ring-neutral-400"
            />
            <div className="flex justify-end gap-3 mt-4">
              <button onClick={() => setShowHeadNoteModal(false)} className="px-4 py-2 text-sm text-neutral-600 hover:bg-neutral-100 rounded">
                Cancel
              </button>
              <button
                disabled={headNote.isPending}
                onClick={handleHeadNote}
                className="px-4 py-2 text-sm bg-amber-500 hover:bg-amber-600 text-white rounded disabled:opacity-40"
              >
                {headNote.isPending ? 'Submitting…' : 'Confirm Head Note'}
              </button>
            </div>
          </div>
        </div>
      )}

      {/* Reject Modal */}
      {showRejectModal && (
        <div className="fixed inset-0 bg-black/40 flex items-center justify-center z-50">
          <div className="bg-white rounded-lg border border-neutral-200 p-6 w-full max-w-md">
            <h2 className="text-lg font-semibold text-neutral-900 mb-3">Reject Loan Application</h2>
            <p className="text-sm text-neutral-600 mb-4">
              Are you sure you want to reject this loan application? This action cannot be undone.
            </p>
            <textarea 
              value={remarks} 
              onChange={(e) => setRemarks(e.target.value)} 
              placeholder="Reason for rejection (required)..." 
              rows={3}
              className="w-full border border-neutral-300 rounded px-3 py-2 text-sm outline-none focus:ring-1 focus:ring-neutral-400" 
            />
            <div className="flex justify-end gap-3 mt-4">
              <button onClick={() => setShowRejectModal(false)} className="px-4 py-2 text-sm text-neutral-600 hover:bg-neutral-100 rounded">
                Cancel
              </button>
              <button 
                disabled={!remarks.trim() || reject.isPending}
                onClick={handleReject}
                className="px-4 py-2 text-sm bg-red-600 hover:bg-red-700 text-white rounded disabled:opacity-40"
              >
                {reject.isPending ? 'Rejecting…' : 'Confirm Rejection'}
              </button>
            </div>
          </div>
        </div>
      )}

      {/* Accounting Approval Modal */}
      {showAccountingModal && (
        <div className="fixed inset-0 bg-black/40 flex items-center justify-center z-50">
          <div className="bg-white rounded-lg border border-neutral-200 p-6 w-full max-w-md">
            <h2 className="text-lg font-semibold text-neutral-900 mb-2">Approve for Disbursement</h2>
            <p className="text-sm text-neutral-600 mb-4">
              This will create a GL entry debiting Loans Receivable and crediting Loans Payable.
              Please verify funds availability before proceeding.
            </p>
            <textarea 
              value={accountingRemarks} 
              onChange={(e) => setAccountingRemarks(e.target.value)} 
              placeholder="Optional remarks..." 
              rows={3}
              className="w-full border border-neutral-300 rounded px-3 py-2 text-sm outline-none focus:ring-1 focus:ring-neutral-400" 
            />
            <div className="flex justify-end gap-3 mt-4">
              <button onClick={() => setShowAccountingModal(false)} className="px-4 py-2 text-sm text-neutral-600 hover:bg-neutral-100 rounded">
                Cancel
              </button>
              <button 
                disabled={accountingApprove.isPending}
                onClick={handleAccountingApprove}
                className="px-4 py-2 text-sm bg-neutral-800 hover:bg-neutral-900 text-white rounded disabled:opacity-40"
              >
                {accountingApprove.isPending ? 'Approving…' : 'Confirm Approval'}
              </button>
            </div>
          </div>
        </div>
      )}

      {/* Manager Check Modal (v2) */}
      {showManagerCheckModal && (
        <div className="fixed inset-0 bg-black/40 flex items-center justify-center z-50">
          <div className="bg-white rounded-lg border border-neutral-200 p-6 w-full max-w-md">
            <h2 className="text-lg font-semibold text-neutral-900 mb-2">Manager Check — Loan Application</h2>
            <p className="text-sm text-neutral-600 mb-4">
              Review the loan application and add your endorsement before forwarding to Accounting for review.
            </p>
            <label className="block text-xs font-medium text-neutral-700 mb-1">Remarks (optional)</label>
            <textarea
              value={managerCheckRemarks}
              onChange={(e) => setManagerCheckRemarks(e.target.value)}
              placeholder="Add remarks or recommendations..."
              rows={3}
              className="w-full border border-neutral-300 rounded px-3 py-2 text-sm outline-none focus:ring-1 focus:ring-neutral-400"
            />
            <div className="flex justify-end gap-3 mt-4">
              <button onClick={() => setShowManagerCheckModal(false)} className="px-4 py-2 text-sm text-neutral-600 hover:bg-neutral-100 rounded">
                Cancel
              </button>
              <button
                disabled={managerCheck.isPending}
                onClick={handleManagerCheck}
                className="px-4 py-2 text-sm bg-neutral-800 hover:bg-neutral-900 text-white rounded disabled:opacity-40"
              >
                {managerCheck.isPending ? 'Submitting…' : 'Confirm Check'}
              </button>
            </div>
          </div>
        </div>
      )}

      {/* Officer Review Modal (v2) */}
      {showOfficerReviewModal && (
        <div className="fixed inset-0 bg-black/40 flex items-center justify-center z-50">
          <div className="bg-white rounded-lg border border-neutral-200 p-6 w-full max-w-md">
            <h2 className="text-lg font-semibold text-neutral-900 mb-2">Accounting Review — Loan Application</h2>
            <p className="text-sm text-neutral-600 mb-4">
              Review the loan terms and verify financial eligibility before forwarding to VP for final approval.
              This step generates the amortization schedule upon VP approval.
            </p>
            <label className="block text-xs font-medium text-neutral-700 mb-1">Remarks (optional)</label>
            <textarea
              value={officerReviewRemarks}
              onChange={(e) => setOfficerReviewRemarks(e.target.value)}
              placeholder="Add accounting remarks..."
              rows={3}
              className="w-full border border-neutral-300 rounded px-3 py-2 text-sm outline-none focus:ring-1 focus:ring-neutral-400"
            />
            <div className="flex justify-end gap-3 mt-4">
              <button onClick={() => setShowOfficerReviewModal(false)} className="px-4 py-2 text-sm text-neutral-600 hover:bg-neutral-100 rounded">
                Cancel
              </button>
              <button
                disabled={officerReview.isPending}
                onClick={handleOfficerReview}
                className="px-4 py-2 text-sm bg-neutral-800 hover:bg-neutral-900 text-white rounded disabled:opacity-40"
              >
                {officerReview.isPending ? 'Submitting…' : 'Confirm Review'}
              </button>
            </div>
          </div>
        </div>
      )}

      {/* VP Approve Modal (v2) */}
      {showVpApproveModal && (
        <div className="fixed inset-0 bg-black/40 flex items-center justify-center z-50">
          <div className="bg-white rounded-lg border border-neutral-200 p-6 w-full max-w-md">
            <h2 className="text-lg font-semibold text-neutral-900 mb-2">VP Final Approval — Loan Application</h2>
            <p className="text-sm text-neutral-600 mb-4">
              This is the final approval step. Approving will generate the amortization schedule
              and mark the loan as ready for disbursement.
            </p>
            <label className="block text-xs font-medium text-neutral-700 mb-1">Remarks (optional)</label>
            <textarea
              value={vpApproveRemarks}
              onChange={(e) => setVpApproveRemarks(e.target.value)}
              placeholder="Add VP remarks..."
              rows={3}
              className="w-full border border-neutral-300 rounded px-3 py-2 text-sm outline-none focus:ring-1 focus:ring-neutral-400"
            />
            <div className="flex justify-end gap-3 mt-4">
              <button onClick={() => setShowVpApproveModal(false)} className="px-4 py-2 text-sm text-neutral-600 hover:bg-neutral-100 rounded">
                Cancel
              </button>
              <button
                disabled={vpApprove.isPending}
                onClick={handleVpApprove}
                className="px-4 py-2 text-sm bg-neutral-900 hover:bg-neutral-800 text-white rounded disabled:opacity-40"
              >
                {vpApprove.isPending ? 'Approving…' : 'Confirm VP Approval'}
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  )
}
