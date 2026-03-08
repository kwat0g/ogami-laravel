import { useState } from 'react'
import { FileDown, RefreshCw, TrendingUp, Banknote, ShieldCheck, Heart, Home, Eye } from 'lucide-react'
import { useMyPayslips, useMyYtdSummary, useDownloadMyPayslip } from '@/hooks/useEmployeeSelf'
import SkeletonLoader from '@/components/ui/SkeletonLoader'
import CurrencyAmount from '@/components/ui/CurrencyAmount'
import PayslipBreakdownModal from '@/components/modals/PayslipBreakdownModal'
import type { PayrollDetail } from '@/types/payroll'
import { PageHeader } from '@/components/ui/PageHeader'

const CURRENT_YEAR = new Date().getFullYear()
const YEARS = Array.from({ length: 5 }, (_, i) => CURRENT_YEAR - i)

function formatDate(iso: string) {
  return new Date(iso).toLocaleDateString('en-PH', {
    year: 'numeric', month: 'short', day: 'numeric',
  })
}

// ─── YTD Card ─────────────────────────────────────────────────────────────────

function YtdCard({ year }: { year: number }) {
  const { data, isLoading } = useMyYtdSummary(year)

  if (isLoading) return <SkeletonLoader rows={2} />
  if (!data) return null

  return (
    <div className="bg-white border border-neutral-200 rounded p-5 mb-6">
      <div className="flex items-center gap-2 mb-4">
        <TrendingUp className="h-5 w-5 text-neutral-700" />
        <h2 className="text-base font-semibold text-neutral-800">
          Year-to-Date Summary — {year}
        </h2>
      </div>
      <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-4">
        <YtdItem
          label="Gross Pay"
          centavos={data.ytd_gross_centavos}
          icon={Banknote}
          iconColor="text-neutral-700"
        />
        <YtdItem
          label="Net Pay"
          centavos={data.ytd_net_centavos}
          icon={Banknote}
          iconColor="text-neutral-700"
        />
        <YtdItem
          label="SSS"
          centavos={data.ytd_sss_centavos}
          icon={ShieldCheck}
          iconColor="text-neutral-500"
        />
        <YtdItem
          label="PhilHealth"
          centavos={data.ytd_philhealth_centavos}
          icon={Heart}
          iconColor="text-neutral-500"
        />
        <YtdItem
          label="Pag-IBIG"
          centavos={data.ytd_pagibig_centavos}
          icon={Home}
          iconColor="text-neutral-500"
        />
        <YtdItem
          label="Tax Withheld"
          centavos={data.ytd_withholding_tax_centavos}
          icon={ShieldCheck}
          iconColor="text-neutral-500"
        />
      </div>
    </div>
  )
}

function YtdItem({
  label,
  centavos,
  icon: Icon,
  iconColor,
}: {
  label: string
  centavos: number
  icon: React.ElementType
  iconColor: string
}) {
  return (
    <div className="flex flex-col gap-1">
      <div className="flex items-center gap-1.5 text-xs text-neutral-500">
        <Icon className={`h-3.5 w-3.5 ${iconColor}`} />
        {label}
      </div>
      <div className="text-base font-semibold text-neutral-800 tabular-nums">
        <CurrencyAmount centavos={centavos} />
      </div>
    </div>
  )
}

// ─── Payslip Row ─────────────────────────────────────────────────────────────

interface PayslipRowProps {
  detail: PayrollDetail
  onView: (id: number) => void
}

function PayslipRow({ detail, onView }: PayslipRowProps) {
  const { download, isLoading } = useDownloadMyPayslip(detail.id)

  const run = (detail as PayrollDetail & {
    payroll_run?: {
      reference_no: string
      pay_period_label: string
      pay_date: string
      run_type: string
    }
  }).payroll_run

  return (
    <tr className="hover:bg-neutral-50 even:bg-neutral-100 transition-colors cursor-pointer" onClick={() => onView(detail.id)}>
      <td className="px-4 py-3">
        <div className="font-medium text-neutral-900 font-mono text-sm">
          {run?.reference_no ?? `Run #${detail.payroll_run_id}`}
        </div>
        {run?.run_type === 'thirteenth_month' && (
          <span className="inline-flex items-center text-xs bg-neutral-100 text-neutral-700 px-1.5 py-0.5 rounded font-medium mt-0.5">
            13th Month
          </span>
        )}
      </td>
      <td className="px-4 py-3 text-sm text-neutral-600">
        {run?.pay_period_label ?? '—'}
      </td>
      <td className="px-4 py-3 text-sm text-neutral-500">
        {run?.pay_date ? formatDate(run.pay_date) : '—'}
      </td>
      <td className="px-4 py-3 text-right">
        <CurrencyAmount centavos={detail.gross_pay_centavos} />
      </td>
      <td className="px-4 py-3 text-right">
        <CurrencyAmount centavos={detail.total_deductions_centavos} />
      </td>
      <td className="px-4 py-3 text-right font-semibold text-neutral-900">
        <CurrencyAmount centavos={detail.net_pay_centavos} />
      </td>
      <td className="px-4 py-3 text-center">
        <div className="flex items-center justify-center gap-1">
          <button
            onClick={(e) => {
              e.stopPropagation()
              onView(detail.id)
            }}
            title="View details"
            className="inline-flex items-center gap-1 border border-neutral-200 text-neutral-600 hover:bg-neutral-50 text-xs font-medium px-2 py-1.5 rounded transition-colors"
          >
            <Eye className="h-3.5 w-3.5" />
            View
          </button>
          <button
            onClick={(e) => {
              e.stopPropagation()
              void download()
            }}
            disabled={isLoading}
            title="Download PDF payslip"
            className="inline-flex items-center gap-1 border border-neutral-200 text-neutral-600 hover:bg-neutral-50 text-xs font-medium px-2 py-1.5 rounded transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
          >
            {isLoading
              ? <RefreshCw className="h-3.5 w-3.5 animate-spin" />
              : <FileDown className="h-3.5 w-3.5" />
            }
            PDF
          </button>
        </div>
      </td>
    </tr>
  )
}

// ─── Page ─────────────────────────────────────────────────────────────────────

export default function MyPayslipsPage() {
  const [year, setYear] = useState(CURRENT_YEAR)
  const [page, setPage] = useState(1)
  const [selectedPayslipId, setSelectedPayslipId] = useState<number | null>(null)
  const [isModalOpen, setIsModalOpen] = useState(false)

  const { data, isLoading, isError } = useMyPayslips(year, page)

  const handleViewPayslip = (id: number) => {
    setSelectedPayslipId(id)
    setIsModalOpen(true)
  }

  const handleCloseModal = () => {
    setIsModalOpen(false)
    setSelectedPayslipId(null)
  }

  return (
    <div>
      <PageHeader title="My Payslips" />
      {/* Header */}
      <div className="flex items-center justify-between mb-6">

        <select
          value={year}
          onChange={(e) => { setYear(Number(e.target.value)); setPage(1) }}
          className="border border-neutral-200 rounded px-3 py-1.5 text-sm text-neutral-700 bg-white focus:outline-none focus:ring-1 focus:ring-neutral-400"
        >
          {YEARS.map((y) => (
            <option key={y} value={y}>{y}</option>
          ))}
        </select>
      </div>

      {/* YTD Summary */}
      <YtdCard year={year} />

      {/* Payslip list */}
      <div className="bg-white border border-neutral-200 rounded overflow-hidden">
        <div className="px-5 py-4 border-b border-neutral-100">
          <h2 className="text-sm font-semibold text-neutral-700">
            Pay History — {year}
            {data && (
              <span className="ml-2 text-neutral-400 font-normal">
                ({data.meta.total} records)
              </span>
            )}
          </h2>
        </div>

        {isLoading ? (
          <div className="p-4">
            <SkeletonLoader rows={5} />
          </div>
        ) : isError ? (
          <div className="p-6 text-center text-neutral-600 text-sm">
            Failed to load payslips. Please try again.
          </div>
        ) : !data?.data.length ? (
          <div className="p-8 text-center text-neutral-500 text-sm">
            No payslips found for {year}.
          </div>
        ) : (
          <>
            <div className="overflow-x-auto">
              <table className="w-full text-sm">
                <thead className="bg-neutral-50 border-b border-neutral-100">
                  <tr>
                    <th className="px-4 py-3 text-left text-xs font-semibold text-neutral-600">
                      Reference
                    </th>
                    <th className="px-4 py-3 text-left text-xs font-semibold text-neutral-600">
                      Pay Period
                    </th>
                    <th className="px-4 py-3 text-left text-xs font-semibold text-neutral-600">
                      Pay Date
                    </th>
                    <th className="px-4 py-3 text-right text-xs font-semibold text-neutral-600">
                      Gross
                    </th>
                    <th className="px-4 py-3 text-right text-xs font-semibold text-neutral-600">
                      Deductions
                    </th>
                    <th className="px-4 py-3 text-right text-xs font-semibold text-neutral-600">
                      Net Pay
                    </th>
                    <th className="px-4 py-3 text-center text-xs font-semibold text-neutral-600">
                      Payslip
                    </th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-neutral-100">
                  {data.data.map((detail) => (
                    <PayslipRow key={detail.id} detail={detail} onView={handleViewPayslip} />
                  ))}
                </tbody>
              </table>
            </div>

            {/* Pagination */}
            {data.meta.last_page > 1 && (
              <div className="px-4 py-3 border-t border-neutral-100 flex items-center justify-between text-sm text-neutral-600">
                <span>Page {page} of {data.meta.last_page}</span>
                <div className="flex gap-2">
                  <button
                    disabled={page <= 1}
                    onClick={() => setPage((p) => p - 1)}
                    className="px-3 py-1 rounded border border-neutral-200 disabled:opacity-40 hover:bg-neutral-50 transition-colors"
                  >
                    Previous
                  </button>
                  <button
                    disabled={page >= data.meta.last_page}
                    onClick={() => setPage((p) => p + 1)}
                    className="px-3 py-1 rounded border border-neutral-200 disabled:opacity-40 hover:bg-neutral-50 transition-colors"
                  >
                    Next
                  </button>
                </div>
              </div>
            )}
          </>
        )}
      </div>

      {/* Payslip Breakdown Modal */}
      <PayslipBreakdownModal
        detailId={selectedPayslipId}
        isOpen={isModalOpen}
        onClose={handleCloseModal}
      />
    </div>
  )
}
