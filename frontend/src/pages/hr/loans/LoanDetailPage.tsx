import { useParams, useNavigate, useLocation } from 'react-router-dom'
import ExecutiveReadOnlyBanner from '@/components/ui/ExecutiveReadOnlyBanner'
import {
  useLoan,
  useLoanSchedule,
  useEmployeeLoanHistory,
  useApproveLoan,
  useRejectLoan,
  useAccountingApproveLoan,
  useDisburseLoan,
  useHeadNoteLoan,
} from '@/hooks/useLoans'
import { useAuthStore } from '@/stores/authStore'
import SkeletonLoader from '@/components/ui/SkeletonLoader'
import StatusBadge from '@/components/ui/StatusBadge'
import CurrencyAmount from '@/components/ui/CurrencyAmount'
import { useState } from 'react'

export default function LoanDetailPage() {
  const { ulid: id } = useParams<{ ulid: string }>()
  const navigate = useNavigate()
  const location = useLocation()
  const loanListPath = location.pathname.startsWith('/accounting') ? '/accounting/loans' : '/hr/loans'
  const { hasPermission } = useAuthStore()
  const canApprove = hasPermission('loans.approve')
  const canAccountingApprove = hasPermission('loans.accounting_approve')
  const canDisburse = hasPermission('loans.accounting_approve') || hasPermission('loans.hr_approve')
  const canReject = hasPermission('loans.reject') || hasPermission('loans.approve')
  const canHeadNote = hasPermission('loans.head_note')
  const _ = canReject
  const loanId = id ?? null
  const { data: loan, isLoading, isError } = useLoan(loanId)
  const { data: schedule, isLoading: schedLoading } = useLoanSchedule(loanId)
  const { data: loanHistory } = useEmployeeLoanHistory(loanId)

  const approve = useApproveLoan()
  const accountingApprove = useAccountingApproveLoan()
  const reject = useRejectLoan()
  const disburse = useDisburseLoan()
  const headNote = useHeadNoteLoan()

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
    <div>
      <ExecutiveReadOnlyBanner />
      {/* Header */}
      <div className="flex items-center justify-between mb-6">
        <div>
          <h1 className="text-2xl font-bold text-gray-900">{loan.loan_type?.name ?? 'Loan Application'}</h1>
          <p className="text-sm text-gray-500 mt-0.5">{loan.employee?.full_name ?? `Employee #${loan.employee_id}`}{loan.reference_no ? ` · ${loan.reference_no}` : ''}</p>
        </div>
        <button onClick={() => navigate(loanListPath)} className="text-sm text-gray-500 hover:text-gray-700">← Back</button>
      </div>

      {/* Employee Loan History Alert */}
      {hasLoanHistory && (
        <div className="bg-amber-50 border border-amber-200 rounded-xl p-4 mb-6">
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
                            <StatusBadge label={histLoan.status} />
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
                              <StatusBadge label={histLoan.status} />
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
      <div className="bg-white border border-gray-200 rounded-xl p-6 mb-6">
        <div className="grid grid-cols-2 md:grid-cols-3 gap-6">
          <div>
            <p className="text-xs text-gray-500 uppercase tracking-wide mb-1">Status</p>
            <StatusBadge label={loan.status} />
          </div>
          <div>
            <p className="text-xs text-gray-500 uppercase tracking-wide mb-1">Loan Type</p>
            <p className="text-sm font-medium text-gray-900">{loan.loan_type?.name ?? '—'}</p>
          </div>
          <div>
            <p className="text-xs text-gray-500 uppercase tracking-wide mb-1">Loan Date</p>
            <p className="text-sm text-gray-700">
              {loan.loan_date ? new Date(loan.loan_date + 'T00:00:00').toLocaleDateString('en-PH', { year: 'numeric', month: 'long', day: 'numeric' }) : '—'}
            </p>
          </div>
          <div>
            <p className="text-xs text-gray-500 uppercase tracking-wide mb-1">Principal</p>
            <p className="text-sm font-semibold text-gray-900"><CurrencyAmount centavos={loan.principal_centavos} /></p>
          </div>
          <div>
            <p className="text-xs text-gray-500 uppercase tracking-wide mb-1">Term</p>
            <p className="text-sm text-gray-700">{loan.term_months} months</p>
          </div>
          <div>
            <p className="text-xs text-gray-500 uppercase tracking-wide mb-1">Monthly Amortization</p>
            <p className="text-sm font-semibold text-gray-900"><CurrencyAmount centavos={loan.monthly_amortization_centavos} /></p>
          </div>
          <div>
            <p className="text-xs text-gray-500 uppercase tracking-wide mb-1">Outstanding Balance</p>
            <p className="text-sm font-bold text-blue-700"><CurrencyAmount centavos={loan.outstanding_balance_centavos} /></p>
          </div>
          <div>
            <p className="text-xs text-gray-500 uppercase tracking-wide mb-1">Total Payable</p>
            <p className="text-sm text-gray-700"><CurrencyAmount centavos={loan.total_payable_centavos} /></p>
          </div>
          <div>
            <p className="text-xs text-gray-500 uppercase tracking-wide mb-1">Deduction Cut-off</p>
            <p className="text-sm font-medium text-gray-900">
              {loan.deduction_cutoff === '1st' ? '1st Cut-off (1–15)' : '2nd Cut-off (16–end)'}
            </p>
          </div>
          {loan.purpose && (
            <div className="col-span-2">
              <p className="text-xs text-gray-500 uppercase tracking-wide mb-1">Purpose</p>
              <p className="text-sm text-gray-700">{loan.purpose}</p>
            </div>
          )}
        </div>

        {/* Approval Timeline */}
        <div className="mt-6 pt-6 border-t border-gray-100">
          <h3 className="text-sm font-semibold text-gray-900 mb-4">Timeline</h3>
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            {/* Step 0: Department Head Note */}
            {(() => {
              const isNoted = !!loan.head_noted_by
              const isPending = !isNoted && loan.status === 'pending'
              return (
                <div className={`p-4 rounded-lg border ${
                  isNoted ? 'bg-amber-50 border-amber-200'
                  : isPending ? 'bg-amber-50 border-amber-200'
                  : 'bg-gray-50 border-gray-200'
                }`}>
                  <div className="flex items-center gap-2 mb-2">
                    <span className={`w-3 h-3 rounded-full ${
                      isNoted ? 'bg-amber-500'
                      : isPending ? 'bg-amber-400 animate-pulse'
                      : 'bg-gray-300'
                    }`} />
                    <span className="text-sm font-medium text-gray-900">0. Dept Head Note</span>
                  </div>
                  {isNoted ? (
                    <>
                      <p className="text-xs text-gray-600">Noted by User #{loan.head_noted_by}</p>
                      <p className="text-xs text-gray-500">{loan.head_noted_at?.slice(0, 10)}</p>
                      {loan.head_remarks && (
                        <p className="text-xs text-gray-500 mt-1 italic">&ldquo;{loan.head_remarks}&rdquo;</p>
                      )}
                    </>
                  ) : (
                    <p className="text-xs text-gray-500">{isPending ? 'Awaiting Dept Head note' : 'Not required / skipped'}</p>
                  )}
                </div>
              )
            })()}
            {/* Step 1: HR Approval */}
            {(() => {
              const isRejected = loan.status === 'rejected' && !!loan.approved_by
              const isApproved = !!loan.approved_by && !isRejected
              return (
                <div className={`p-4 rounded-lg border ${
                  isRejected ? 'bg-red-50 border-red-200'
                  : isApproved ? 'bg-green-50 border-green-200'
                  : loan.status === 'pending' ? 'bg-amber-50 border-amber-200'
                  : 'bg-gray-50 border-gray-200'
                }`}>
                  <div className="flex items-center gap-2 mb-2">
                    <span className={`w-3 h-3 rounded-full ${
                      isRejected ? 'bg-red-500'
                      : isApproved ? 'bg-green-500'
                      : loan.status === 'pending' ? 'bg-amber-400 animate-pulse'
                      : 'bg-gray-300'
                    }`} />
                    <span className="text-sm font-medium text-gray-900">1. HR Manager Approval</span>
                  </div>
                  {isRejected ? (
                    <>
                      <p className="text-xs text-red-700 font-medium">Rejected</p>
                      <p className="text-xs text-gray-600">{loan.approver_name ?? `User #${loan.approved_by}`}</p>
                      <p className="text-xs text-gray-500">{loan.approved_at?.slice(0, 10)}</p>
                      {loan.approver_remarks && (
                        <p className="text-xs text-gray-500 mt-1 italic">&ldquo;{loan.approver_remarks}&rdquo;</p>
                      )}
                    </>
                  ) : isApproved ? (
                    <>
                      <p className="text-xs text-gray-600">Approved by {loan.approver_name ?? `User #${loan.approved_by}`}</p>
                      <p className="text-xs text-gray-500">{loan.approved_at?.slice(0, 10)}</p>
                      {loan.approver_remarks && (
                        <p className="text-xs text-gray-500 mt-1 italic">&ldquo;{loan.approver_remarks}&rdquo;</p>
                      )}
                    </>
                  ) : (
                    <p className="text-xs text-gray-500">{loan.status === 'pending' ? 'Awaiting HR Manager review' : 'Not yet reviewed'}</p>
                  )}
                </div>
              )
            })()}

            {/* Step 2: Accounting Approval */}
            {(() => {
              const terminal = loan.status === 'rejected' || loan.status === 'cancelled'
              const isApproved = !!loan.accounting_approved_by
              const isPending = !terminal && !isApproved && loan.status === 'approved'
              return (
                <div className={`p-4 rounded-lg border ${
                  terminal ? 'bg-gray-50 border-gray-200'
                  : isApproved ? 'bg-green-50 border-green-200'
                  : isPending ? 'bg-amber-50 border-amber-200'
                  : 'bg-gray-50 border-gray-200'
                }`}>
                  <div className="flex items-center gap-2 mb-2">
                    <span className={`w-3 h-3 rounded-full ${
                      terminal ? 'bg-gray-300'
                      : isApproved ? 'bg-green-500'
                      : isPending ? 'bg-amber-400 animate-pulse'
                      : 'bg-gray-300'
                    }`} />
                    <span className="text-sm font-medium text-gray-900">2. Accounting Approval</span>
                  </div>
                  {isApproved ? (
                    <>
                      <p className="text-xs text-gray-600">Approved by {loan.accounting_approver_name ?? `User #${loan.accounting_approved_by}`}</p>
                      <p className="text-xs text-gray-500">{loan.accounting_approved_at?.slice(0, 10)}</p>
                      {loan.accounting_remarks && (
                        <p className="text-xs text-gray-500 mt-1 italic">&ldquo;{loan.accounting_remarks}&rdquo;</p>
                      )}
                      {loan.journal_entry_id && (
                        <p className="text-xs text-blue-600 mt-2 font-medium">GL Entry: JE #{loan.journal_entry_id}</p>
                      )}
                    </>
                  ) : (
                    <p className="text-xs text-gray-500">
                      {terminal ? 'N/A' : isPending ? 'Ready for Accounting review' : 'Waiting for HR approval'}
                    </p>
                  )}
                </div>
              )
            })()}

            {/* Step 3: Disbursement */}
            {(() => {
              const terminal = loan.status === 'rejected' || loan.status === 'cancelled'
              const isDisbursed = !!loan.disbursed_at
              const isPending = !terminal && !isDisbursed && loan.status === 'ready_for_disbursement'
              return (
                <div className={`p-4 rounded-lg border ${
                  terminal ? 'bg-gray-50 border-gray-200'
                  : isDisbursed ? 'bg-green-50 border-green-200'
                  : isPending ? 'bg-amber-50 border-amber-200'
                  : 'bg-gray-50 border-gray-200'
                }`}>
                  <div className="flex items-center gap-2 mb-2">
                    <span className={`w-3 h-3 rounded-full ${
                      terminal ? 'bg-gray-300'
                      : isDisbursed ? 'bg-green-500'
                      : isPending ? 'bg-amber-400 animate-pulse'
                      : 'bg-gray-300'
                    }`} />
                    <span className="text-sm font-medium text-gray-900">3. Fund Disbursement</span>
                  </div>
                  {isDisbursed ? (
                    <>
                      <p className="text-xs text-gray-600">Disbursed by {loan.disbursed_by ? `User #${loan.disbursed_by}` : '—'}</p>
                      <p className="text-xs text-gray-500">{loan.disbursed_at?.slice(0, 10)}</p>
                      <p className="text-xs text-green-700 mt-2 font-medium">✓ Funds released to employee</p>
                    </>
                  ) : (
                    <p className="text-xs text-gray-500">
                      {terminal ? 'N/A' : isPending ? 'Ready for disbursement' : 'Waiting for accounting approval'}
                    </p>
                  )}
                </div>
              )
            })()}
          </div>
        </div>

        {/* Actions */}
        <div className="mt-6 flex flex-wrap gap-3">
          {/* Department Head Actions */}
          {canHeadNote && loan.status === 'pending' && (
            <button
              onClick={() => { setHeadNoteRemarks(''); setShowHeadNoteModal(true) }}
              disabled={headNote.isPending}
              className="px-4 py-2 text-sm bg-amber-500 hover:bg-amber-600 text-white rounded-lg disabled:opacity-50 flex items-center gap-2">
              <span>📋</span> Head Note
            </button>
          )}

          {/* HR Manager Actions */}
          {canApprove && loan.status === 'pending' && (
            <>
              <button onClick={() => { setApproveDate(computeFirstDeductionDate(loan.deduction_cutoff)); setApproveRemarks(''); setShowApproveModal(true) }} disabled={approve.isPending}
                className="px-4 py-2 text-sm bg-green-600 hover:bg-green-700 text-white rounded-lg disabled:opacity-50 flex items-center gap-2">
                <span>✓</span> Approve (HR)
              </button>
              <button onClick={() => setShowRejectModal(true)}
                disabled={approve.isPending || reject.isPending}
                className="px-4 py-2 text-sm bg-red-600 hover:bg-red-700 text-white rounded-lg flex items-center gap-2 disabled:opacity-50">
                <span>✕</span> Reject
              </button>
            </>
          )}

          {/* Accounting Manager Actions */}
          {canAccountingApprove && loan.status === 'approved' && (
            <>
              <button onClick={() => setShowAccountingModal(true)}
                disabled={accountingApprove.isPending || reject.isPending}
                className="px-4 py-2 text-sm bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg flex items-center gap-2 disabled:opacity-50">
                <span>✓</span> Approve for Disbursement (Accounting)
              </button>
              <button onClick={() => setShowRejectModal(true)}
                disabled={accountingApprove.isPending || reject.isPending}
                className="px-4 py-2 text-sm bg-red-600 hover:bg-red-700 text-white rounded-lg flex items-center gap-2 disabled:opacity-50">
                <span>✕</span> Reject
              </button>
            </>
          )}

          {/* Disbursement Action */}
          {canDisburse && loan.status === 'ready_for_disbursement' && (
            <button onClick={() => disburse.mutate(loan.ulid)} disabled={disburse.isPending}
              className="px-4 py-2 text-sm bg-blue-600 hover:bg-blue-700 text-white rounded-lg disabled:opacity-50 flex items-center gap-2">
              <span>💵</span> Disburse Funds
            </button>
          )}
        </div>
      </div>

      {/* Amortization schedule */}
      <h2 className="text-lg font-semibold text-gray-900 mb-3">Amortization Schedule</h2>
      <div className="bg-white border border-gray-200 rounded-xl overflow-hidden">
        {schedLoading ? <SkeletonLoader rows={6} /> : (
          <table className="min-w-full text-sm">
            <thead className="bg-gray-50 border-b border-gray-200">
              <tr>
                {['#', 'Due Date', 'Principal', 'Interest', 'Amortization', 'Balance', 'Paid'].map((h) => (
                  <th key={h} className="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">{h}</th>
                ))}
              </tr>
            </thead>
            <tbody className="divide-y divide-gray-100">
              {(!schedule || schedule.length === 0) ? (
                <tr><td colSpan={7} className="px-4 py-6 text-center text-gray-400">No schedule available. Loan needs HR approval to generate schedule.</td></tr>
              ) : schedule.map((entry) => (
                <tr key={entry.installment_no} className={entry.is_paid ? 'bg-green-50' : 'hover:bg-gray-50'}>
                  <td className="px-4 py-2 text-gray-600">{entry.installment_no}</td>
                  <td className="px-4 py-2 text-gray-600">{entry.due_date}</td>
                  <td className="px-4 py-2 text-gray-700"><CurrencyAmount centavos={entry.principal} /></td>
                  <td className="px-4 py-2 text-gray-700"><CurrencyAmount centavos={entry.interest} /></td>
                  <td className="px-4 py-2 font-medium text-gray-900"><CurrencyAmount centavos={entry.amortization} /></td>
                  <td className="px-4 py-2 text-gray-700"><CurrencyAmount centavos={entry.balance} /></td>
                  <td className="px-4 py-2">
                    {entry.is_paid
                      ? <span className="text-xs text-green-700 font-medium">✓ {entry.paid_at?.slice(0, 10)}</span>
                      : <span className="text-xs text-gray-400">—</span>}
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
          <div className="bg-white rounded-xl shadow-xl p-6 w-full max-w-md">
            <h2 className="text-lg font-semibold text-gray-900 mb-2">Approve Loan Application</h2>
            <div className="bg-blue-50 border border-blue-200 rounded-lg px-3 py-2 mb-4 text-sm text-blue-800">
              Requested cut-off: <span className="font-semibold">
                {loan.deduction_cutoff === '1st' ? '1st Cut-off (deducted every 1–15)' : '2nd Cut-off (deducted every 16–end)'}
              </span>
            </div>
            <label className="block text-xs font-medium text-gray-700 mb-1">First Deduction Date <span className="text-red-500">*</span></label>
            <input
              type="date"
              value={approveDate}
              min={new Date().toISOString().slice(0, 10)}
              onChange={(e) => setApproveDate(e.target.value)}
              className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-green-400 mb-3"
            />
            <label className="block text-xs font-medium text-gray-700 mb-1">Remarks (optional)</label>
            <textarea
              value={approveRemarks}
              onChange={(e) => setApproveRemarks(e.target.value)}
              placeholder="Optional remarks..."
              rows={2}
              className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-green-400"
            />
            <div className="flex justify-end gap-3 mt-4">
              <button onClick={() => setShowApproveModal(false)} className="px-4 py-2 text-sm text-gray-600 hover:bg-gray-100 rounded-lg">
                Cancel
              </button>
              <button
                disabled={!approveDate || approve.isPending}
                onClick={() => approve.mutate(
                  { id: loan.ulid, first_deduction_date: approveDate, remarks: approveRemarks || undefined },
                  { onSuccess: () => setShowApproveModal(false) }
                )}
                className="px-4 py-2 text-sm bg-green-600 hover:bg-green-700 text-white rounded-lg disabled:opacity-40"
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
          <div className="bg-white rounded-xl shadow-xl p-6 w-full max-w-md">
            <h2 className="text-lg font-semibold text-gray-900 mb-2">Head Note — Loan Application</h2>
            <p className="text-sm text-gray-600 mb-4">
              Add your endorsement note before forwarding to HR Manager review.
            </p>
            <label className="block text-xs font-medium text-gray-700 mb-1">Remarks (optional)</label>
            <textarea
              value={headNoteRemarks}
              onChange={(e) => setHeadNoteRemarks(e.target.value)}
              placeholder="Add remarks or recommendations..."
              rows={3}
              className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-amber-400"
            />
            <div className="flex justify-end gap-3 mt-4">
              <button onClick={() => setShowHeadNoteModal(false)} className="px-4 py-2 text-sm text-gray-600 hover:bg-gray-100 rounded-lg">
                Cancel
              </button>
              <button
                disabled={headNote.isPending}
                onClick={() => headNote.mutate(
                  { id: loan.ulid, remarks: headNoteRemarks || undefined },
                  { onSuccess: () => setShowHeadNoteModal(false) }
                )}
                className="px-4 py-2 text-sm bg-amber-500 hover:bg-amber-600 text-white rounded-lg disabled:opacity-40"
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
          <div className="bg-white rounded-xl shadow-xl p-6 w-full max-w-md">
            <h2 className="text-lg font-semibold text-gray-900 mb-3">Reject Loan Application</h2>
            <p className="text-sm text-gray-600 mb-4">
              Are you sure you want to reject this loan application? This action cannot be undone.
            </p>
            <textarea 
              value={remarks} 
              onChange={(e) => setRemarks(e.target.value)} 
              placeholder="Reason for rejection (required)..." 
              rows={3}
              className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-red-400" 
            />
            <div className="flex justify-end gap-3 mt-4">
              <button onClick={() => setShowRejectModal(false)} className="px-4 py-2 text-sm text-gray-600 hover:bg-gray-100 rounded-lg">
                Cancel
              </button>
              <button 
                disabled={!remarks.trim() || reject.isPending}
                onClick={() => reject.mutate(
                  { id: loan.ulid, remarks }, 
                  { onSuccess: () => setShowRejectModal(false) }
                )}
                className="px-4 py-2 text-sm bg-red-600 hover:bg-red-700 text-white rounded-lg disabled:opacity-40"
              >
                Confirm Rejection
              </button>
            </div>
          </div>
        </div>
      )}

      {/* Accounting Approval Modal */}
      {showAccountingModal && (
        <div className="fixed inset-0 bg-black/40 flex items-center justify-center z-50">
          <div className="bg-white rounded-xl shadow-xl p-6 w-full max-w-md">
            <h2 className="text-lg font-semibold text-gray-900 mb-2">Approve for Disbursement</h2>
            <p className="text-sm text-gray-600 mb-4">
              This will create a GL entry debiting Loans Receivable and crediting Loans Payable.
              Please verify funds availability before proceeding.
            </p>
            <textarea 
              value={accountingRemarks} 
              onChange={(e) => setAccountingRemarks(e.target.value)} 
              placeholder="Optional remarks..." 
              rows={3}
              className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-indigo-400" 
            />
            <div className="flex justify-end gap-3 mt-4">
              <button onClick={() => setShowAccountingModal(false)} className="px-4 py-2 text-sm text-gray-600 hover:bg-gray-100 rounded-lg">
                Cancel
              </button>
              <button 
                disabled={accountingApprove.isPending}
                onClick={() => accountingApprove.mutate(
                  { id: loan.ulid, remarks: accountingRemarks }, 
                  { onSuccess: () => setShowAccountingModal(false) }
                )}
                className="px-4 py-2 text-sm bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg disabled:opacity-40"
              >
                Confirm Approval
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  )
}
