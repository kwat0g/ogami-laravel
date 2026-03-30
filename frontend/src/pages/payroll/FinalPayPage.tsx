import { useState } from 'react'
import { PageHeader } from '@/components/ui/PageHeader'
import { useEmployees } from '@/hooks/useEmployees'
import { useFinalPay } from '@/hooks/useEnhancements'

interface FinalPayBreakdown {
  employee_name: string
  separation_date: string
  last_working_date: string
  basic_pay: number
  prorated_13th_month: number
  leave_monetization: number
  separation_pay: number
  total_gross: number
  sss_deduction: number
  philhealth_deduction: number
  pagibig_deduction: number
  tax_deduction: number
  loan_deductions: number
  total_deductions: number
  net_final_pay: number
}

function formatPeso(amount: number): string {
  return new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' }).format(amount)
}

export default function FinalPayPage() {
  const [selectedEmployeeId, setSelectedEmployeeId] = useState<number | null>(null)
  const [lastWorkingDate, setLastWorkingDate] = useState(new Date().toISOString().slice(0, 10))
  const { data: employeesData } = useEmployees({ per_page: 200, employment_status: 'active' })
  const { data: finalPay, isLoading, isError } = useFinalPay(
    selectedEmployeeId ?? 0,
    lastWorkingDate
  )

  const employees = employeesData?.data ?? []
  const fp = finalPay as FinalPayBreakdown | undefined

  return (
    <div className="p-6 space-y-6 max-w-3xl mx-auto">
      <PageHeader title="Final Pay Computation" />

      <div className="bg-white dark:bg-neutral-800 rounded-lg border p-5 space-y-4">
        <div className="grid grid-cols-2 gap-4">
          <div>
            <label className="block text-sm font-medium mb-1">Employee *</label>
            <select
              value={selectedEmployeeId ?? ''}
              onChange={e => setSelectedEmployeeId(e.target.value ? Number(e.target.value) : null)}
              className="w-full border rounded px-3 py-2 text-sm"
            >
              <option value="">Select separated/separating employee</option>
              {employees.map((emp: { id: number; full_name: string; employment_status: string }) => (
                <option key={emp.id} value={emp.id}>{emp.full_name} ({emp.employment_status})</option>
              ))}
            </select>
          </div>
          <div>
            <label className="block text-sm font-medium mb-1">Last Working Date</label>
            <input
              type="date"
              value={lastWorkingDate}
              onChange={e => setLastWorkingDate(e.target.value)}
              className="w-full border rounded px-3 py-2 text-sm"
            />
          </div>
        </div>
      </div>

      {selectedEmployeeId && isLoading && (
        <div className="animate-pulse space-y-3">
          {[1,2,3,4].map(i => <div key={i} className="h-10 bg-neutral-200 rounded" />)}
        </div>
      )}

      {isError && selectedEmployeeId && (
        <div className="bg-red-50 border border-red-200 rounded-lg p-4 text-red-700 text-sm">
          Unable to compute final pay. Ensure the employee has salary data and attendance records.
        </div>
      )}

      {fp && (
        <div className="bg-white dark:bg-neutral-800 rounded-lg border divide-y">
          <div className="p-5">
            <h3 className="text-sm font-semibold text-neutral-700 dark:text-neutral-300 uppercase tracking-wide mb-3">Earnings</h3>
            <dl className="space-y-2 text-sm">
              <div className="flex justify-between"><dt>Basic Pay (prorated)</dt><dd className="font-mono">{formatPeso(fp.basic_pay ?? 0)}</dd></div>
              <div className="flex justify-between"><dt>Prorated 13th Month</dt><dd className="font-mono">{formatPeso(fp.prorated_13th_month ?? 0)}</dd></div>
              <div className="flex justify-between"><dt>Leave Monetization</dt><dd className="font-mono">{formatPeso(fp.leave_monetization ?? 0)}</dd></div>
              <div className="flex justify-between"><dt>Separation Pay</dt><dd className="font-mono">{formatPeso(fp.separation_pay ?? 0)}</dd></div>
              <div className="flex justify-between border-t pt-2 font-semibold"><dt>Total Gross</dt><dd className="font-mono">{formatPeso(fp.total_gross ?? 0)}</dd></div>
            </dl>
          </div>

          <div className="p-5">
            <h3 className="text-sm font-semibold text-neutral-700 dark:text-neutral-300 uppercase tracking-wide mb-3">Deductions</h3>
            <dl className="space-y-2 text-sm">
              <div className="flex justify-between"><dt>SSS</dt><dd className="font-mono text-red-600">{formatPeso(fp.sss_deduction ?? 0)}</dd></div>
              <div className="flex justify-between"><dt>PhilHealth</dt><dd className="font-mono text-red-600">{formatPeso(fp.philhealth_deduction ?? 0)}</dd></div>
              <div className="flex justify-between"><dt>Pag-IBIG</dt><dd className="font-mono text-red-600">{formatPeso(fp.pagibig_deduction ?? 0)}</dd></div>
              <div className="flex justify-between"><dt>Withholding Tax</dt><dd className="font-mono text-red-600">{formatPeso(fp.tax_deduction ?? 0)}</dd></div>
              <div className="flex justify-between"><dt>Loan Balance</dt><dd className="font-mono text-red-600">{formatPeso(fp.loan_deductions ?? 0)}</dd></div>
              <div className="flex justify-between border-t pt-2 font-semibold"><dt>Total Deductions</dt><dd className="font-mono text-red-600">{formatPeso(fp.total_deductions ?? 0)}</dd></div>
            </dl>
          </div>

          <div className="p-5 bg-green-50 dark:bg-green-900/20">
            <div className="flex justify-between items-center">
              <span className="text-lg font-semibold">Net Final Pay</span>
              <span className="text-2xl font-bold text-green-700 dark:text-green-400 font-mono">{formatPeso(fp.net_final_pay ?? 0)}</span>
            </div>
          </div>
        </div>
      )}

      {!selectedEmployeeId && (
        <div className="bg-neutral-50 dark:bg-neutral-800 rounded-lg border p-8 text-center text-neutral-500">
          Select a separated or separating employee to compute their final pay breakdown.
        </div>
      )}
    </div>
  )
}
