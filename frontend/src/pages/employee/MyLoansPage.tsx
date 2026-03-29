import { useState } from 'react'
import { useLoans, useLoanSchedule, useCancelLoan } from '@/hooks/useLoans'
import { useAuthStore } from '@/stores/authStore'
import SkeletonLoader from '@/components/ui/SkeletonLoader'
import StatusBadge from '@/components/ui/StatusBadge'
import CurrencyAmount from '@/components/ui/CurrencyAmount'
import FileLoanModal from '@/components/modals/FileLoanModal'
import { Plus, AlertCircle } from 'lucide-react'
import { PageHeader } from '@/components/ui/PageHeader'
import type { LoanStatus } from '@/types/loan'

const STATUS_FILTER_OPTIONS: { label: string; value: LoanStatus | 'all' }[] = [
  { label: 'All', value: 'all' },
  { label: 'Active', value: 'active' },
  { label: 'Paid', value: 'fully_paid' },
  { label: 'Rejected', value: 'rejected' },
  { label: 'Cancelled', value: 'cancelled' },
]

function LoanAccordion({ ulid }: { ulid: string }) {
  const [open, setOpen] = useState(false)
  const { data: schedule, isLoading } = useLoanSchedule(open ? ulid : null)

  return (
    <div>
      <button onClick={() => setOpen((o) => !o)} className="text-xs text-neutral-600 hover:underline mt-2">
        {open ? '▲ Hide schedule' : '▼ View schedule'}
      </button>
      {open && (
        <div className="mt-3">
          {isLoading ? <SkeletonLoader rows={3} /> : (
            <table className="min-w-full text-xs">
              <thead>
                <tr className="text-neutral-400">
                  {['#', 'Due', 'Amortization', 'Balance', 'Paid?'].map((h) => (
                    <th key={h} className="pr-4 pb-1 text-left font-medium">{h}</th>
                  ))}
                </tr>
              </thead>
              <tbody>
                {(schedule ?? []).map((entry) => (
                  <tr key={entry.installment_no} className={entry.is_paid ? 'text-neutral-700' : 'text-neutral-700'}>
                    <td className="pr-4 py-0.5">{entry.installment_no}</td>
                    <td className="pr-4 py-0.5">{entry.due_date}</td>
                    <td className="pr-4 py-0.5"><CurrencyAmount centavos={entry.amortization} /></td>
                    <td className="pr-4 py-0.5"><CurrencyAmount centavos={entry.balance} /></td>
                    <td className="py-0.5">{entry.is_paid ? <span className="text-neutral-700">✓</span> : '—'}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          )}
        </div>
      )}
    </div>
  )
}

export default function MyLoansPage() {
  const { user } = useAuthStore()
  const employeeId = user?.employee_id as number | undefined

  const [page, setPage] = useState(1)
  const [statusFilter, setStatusFilter] = useState<LoanStatus | 'all'>('all')
  const [isModalOpen, setIsModalOpen] = useState(false)
  const [confirmCancelUlid, setConfirmCancelUlid] = useState<string | null>(null)

  const { data, isLoading, refetch } = useLoans({
    employee_id: employeeId,
    per_page: 10,
    page,
    status: statusFilter !== 'all' ? statusFilter : undefined,
  })
  const cancelLoan = useCancelLoan()

  if (!employeeId) {
    return <div className="text-neutral-500 text-sm mt-4">No employee profile linked to your account.</div>
  }

  const rows = data?.data ?? []

  const ACTIVE_STATUSES = ['pending', 'approved', 'active']
  const activeLoan = rows.find(l => ACTIVE_STATUSES.includes(l.status))

  return (
    <div>
      <PageHeader title="My Loans" />
      {/* Header */}
      <div className="flex items-center justify-between mb-6">
        <button
          onClick={() => setIsModalOpen(true)}
          disabled={!!activeLoan}
          title={activeLoan ? `You already have an active ${activeLoan.loan_type?.name ?? 'loan'}` : undefined}
          className="bg-neutral-900 hover:bg-neutral-800 disabled:bg-neutral-300 disabled:cursor-not-allowed disabled:cursor-not-allowed text-white text-sm font-medium px-4 py-2 rounded transition-colors inline-flex items-center gap-2"
        >
          <Plus className="h-4 w-4" />
          Apply for Loan
        </button>
      </div>

      {/* Status filter */}
      <div className="flex items-center gap-2 mb-4 flex-wrap">
        {STATUS_FILTER_OPTIONS.map((opt) => (
          <button
            key={opt.value}
            onClick={() => { setStatusFilter(opt.value); setPage(1) }}
            className={`px-3 py-1.5 rounded text-xs font-medium transition-colors border ${
              statusFilter === opt.value
                ? 'bg-neutral-900 border-neutral-900 text-white'
                : 'bg-white border-neutral-200 text-neutral-600 hover:bg-neutral-50'
            }`}
          >
            {opt.label}
          </button>
        ))}
      </div>

      {isLoading ? <SkeletonLoader rows={6} /> : (
        <>
          {activeLoan && (
            <div className="flex items-start gap-3 bg-neutral-50 border border-neutral-200 rounded px-4 py-3 mb-4 text-sm text-neutral-800">
              <AlertCircle className="h-4 w-4 mt-0.5 flex-shrink-0 text-neutral-500" />
              <span>
                You have an active <span className="font-semibold">{activeLoan.loan_type?.name ?? 'loan'}</span> ({activeLoan.reference_no}).
                You can only apply for a new loan once it is fully settled.
              </span>
            </div>
          )}
          {rows.length === 0 && (
            <div className="bg-white border border-neutral-200 rounded p-8 text-center text-neutral-400">
              No loan applications yet. Click "+ Apply for Loan" to get started.
            </div>
          )}

          <div className="space-y-4">
            {rows.map((loan) => (
              <div key={loan.id} className="bg-white border border-neutral-200 rounded p-5">
                <div className="flex items-start justify-between">
                  <div>
                    <div className="flex items-center gap-3 mb-1">
                      <span className="font-semibold text-neutral-900">{loan.loan_type?.name ?? '—'}</span>
                      <StatusBadge status={loan.status}>{loan.status?.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase())}</StatusBadge>
                    </div>
                    <p className="text-xs text-neutral-500">Loan #{loan.id} · {loan.loan_date}</p>
                  </div>
                  <div className="flex items-start gap-3">
                    {['pending', 'supervisor_approved'].includes(loan.status) && (
                      confirmCancelUlid === loan.ulid ? (
                        <div className="flex items-center gap-2">
                          <span className="text-xs text-neutral-500">Cancel this loan?</span>
                          <button
                            onClick={() => {
                              cancelLoan.mutate(loan.ulid, {
                                onSuccess: () => { setConfirmCancelUlid(null); refetch() },
                              })
                            }}
                            disabled={cancelLoan.isPending}
                            className="text-xs px-2.5 py-1 bg-neutral-900 hover:bg-neutral-800 text-white rounded disabled:opacity-50 disabled:cursor-not-allowed"
                          >
                            {cancelLoan.isPending ? 'Cancelling…' : 'Yes, cancel'}
                          </button>
                          <button
                            onClick={() => setConfirmCancelUlid(null)}
                            disabled={cancelLoan.isPending}
                            className="text-xs px-2.5 py-1 border border-neutral-300 rounded hover:bg-neutral-50 disabled:opacity-50 disabled:cursor-not-allowed"
                          >
                            Keep
                          </button>
                        </div>
                      ) : (
                        <button
                          onClick={() => setConfirmCancelUlid(loan.ulid)}
                          className="text-xs px-3 py-1 border border-neutral-300 text-neutral-600 hover:bg-neutral-50 transition-colors rounded"
                        >
                          Cancel
                        </button>
                      )
                    )}
                    <div className="text-right">
                      <p className="text-sm text-neutral-500">Outstanding</p>
                      <p className="text-lg font-bold text-neutral-700"><CurrencyAmount centavos={loan.outstanding_balance_centavos} /></p>
                    </div>
                  </div>
                </div>

                <div className="grid grid-cols-4 gap-4 mt-4 text-sm">
                  <div>
                    <p className="text-xs text-neutral-400">Principal</p>
                    <p className="font-medium text-neutral-700"><CurrencyAmount centavos={loan.principal_centavos} /></p>
                  </div>
                  <div>
                    <p className="text-xs text-neutral-400">Monthly Amort.</p>
                    <p className="font-medium text-neutral-700"><CurrencyAmount centavos={loan.monthly_amortization_centavos} /></p>
                  </div>
                  <div>
                    <p className="text-xs text-neutral-400">Term</p>
                    <p className="font-medium text-neutral-700">{loan.term_months} months</p>
                  </div>
                  <div>
                    <p className="text-xs text-neutral-400">Interest Rate</p>
                    <p className="font-medium text-neutral-700">{(loan.interest_rate_annual * 100).toFixed(0)}% <span className="text-xs text-neutral-400 font-normal">p.a.</span></p>
                  </div>
                </div>

                {/* Progress bar for repayment */}
                {loan.status === 'active' && loan.principal_centavos > 0 && (
                  <div className="mt-3">
                    <div className="flex justify-between text-xs text-neutral-400 mb-1">
                      <span>Repaid</span>
                      <span>{Math.round(((loan.principal_centavos - loan.outstanding_balance_centavos) / loan.principal_centavos) * 100)}%</span>
                    </div>
                    <div className="bg-neutral-100 rounded h-2">
                      <div
                        className="bg-neutral-600 h-2 rounded"
                        style={{ width: `${Math.min(100, ((loan.principal_centavos - loan.outstanding_balance_centavos) / loan.principal_centavos) * 100)}%` }}
                      />
                    </div>
                  </div>
                )}

                {/* Amortization schedule accordion */}
                {['active', 'fully_paid'].includes(loan.status) && (
                  <LoanAccordion ulid={loan.ulid} />
                )}
              </div>
            ))}
          </div>

          {/* Pagination */}
          {(data?.meta?.last_page ?? 1) > 1 && (
            <div className="flex items-center justify-between mt-4 text-sm text-neutral-600">
              <span>Page {data?.meta?.current_page} of {data?.meta?.last_page}</span>
              <div className="flex gap-2">
                <button disabled={page <= 1} onClick={() => setPage((p) => p - 1)}
                  className="px-3 py-1.5 border border-neutral-300 rounded hover:bg-neutral-50 disabled:opacity-40 disabled:cursor-not-allowed">Prev</button>
                <button disabled={page >= (data?.meta?.last_page ?? 1)} onClick={() => setPage((p) => p + 1)}
                  className="px-3 py-1.5 border border-neutral-300 rounded hover:bg-neutral-50 disabled:opacity-40 disabled:cursor-not-allowed">Next</button>
              </div>
            </div>
          )}
        </>
      )}

      {/* File Loan Modal */}
      <FileLoanModal
        isOpen={isModalOpen}
        onClose={() => setIsModalOpen(false)}
        onSuccess={refetch}
      />
    </div>
  )
}
