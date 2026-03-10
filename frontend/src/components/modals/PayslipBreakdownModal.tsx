import { usePayslipDetail } from '@/hooks/useEmployeeSelf'
import SkeletonLoader from '@/components/ui/SkeletonLoader'
import CurrencyAmount from '@/components/ui/CurrencyAmount'
import { X, Clock, Briefcase, TrendingUp, MinusCircle, Wallet, Building2 } from 'lucide-react'

interface Props {
  detailId: number | null
  isOpen: boolean
  onClose: () => void
}

function formatDate(iso: string | null | undefined) {
  if (!iso) return '—'
  return new Date(iso).toLocaleDateString('en-PH', {
    year: 'numeric', month: 'short', day: 'numeric',
  })
}

function formatMinutes(minutes: number) {
  if (minutes <= 0) return '0h'
  const h = Math.floor(minutes / 60)
  const m = minutes % 60
  if (h > 0 && m > 0) return `${h}h ${m}m`
  if (h > 0) return `${h}h`
  return `${m}m`
}

function SectionTitle({ icon: Icon, title }: { icon: React.ElementType; title: string }) {
  return (
    <div className="flex items-center gap-2 mb-2">
      <Icon className="h-3.5 w-3.5 text-neutral-600" />
      <h3 className="text-xs font-semibold text-neutral-700">{title}</h3>
    </div>
  )
}

function InfoRow({ label, value, className = '' }: { label: string; value: React.ReactNode; className?: string }) {
  return (
    <div className={`flex justify-between items-center py-1 ${className}`}>
      <span className="text-xs text-neutral-500">{label}</span>
      <span className="text-xs font-semibold text-neutral-900">{value}</span>
    </div>
  )
}

function AmountRow({ label, centavos, isNegative = false, isBold = false }: { 
  label: string; 
  centavos: number; 
  isNegative?: boolean;
  isBold?: boolean;
}) {
  return (
    <div className={`flex justify-between items-center py-1 ${isBold ? 'border-t border-neutral-200 mt-1 pt-1.5' : ''}`}>
      <span className={`text-xs ${isBold ? 'font-semibold text-neutral-800' : 'text-neutral-500'}`}>{label}</span>
      <span className={`text-xs tabular-nums ${isBold ? 'font-semibold text-neutral-900' : 'font-semibold text-neutral-900'} ${isNegative ? 'text-red-600' : ''}`}>
        {isNegative && '-'}<CurrencyAmount centavos={centavos} />
      </span>
    </div>
  )
}

export default function PayslipBreakdownModal({ detailId, isOpen, onClose }: Props) {
  const { data, isLoading, isError } = usePayslipDetail(detailId)
  const payslip = data?.data

  if (!isOpen) return null

  return (
    <div className="fixed inset-0 z-50 bg-black/50 overflow-y-auto">
      <div className="min-h-screen flex items-center justify-center p-4">
        <div className="bg-white rounded-lg shadow-lg w-full max-w-4xl max-h-[90vh] overflow-y-auto border border-neutral-200">
          {/* Header */}
          <div className="flex items-center justify-between px-4 py-3 border-b border-neutral-200">
            <div>
              <h2 className="text-base font-semibold text-neutral-900">Payslip Breakdown</h2>
              {payslip && (
                <p className="text-xs text-neutral-500 mt-0.5">
                  {payslip.payroll_run.pay_period_label} • Pay Date: {formatDate(payslip.payroll_run.pay_date)}
                </p>
              )}
            </div>
            <button
              onClick={onClose}
              className="text-neutral-400 hover:text-neutral-600"
              title="Close"
            >
              <X className="h-4 w-4" />
            </button>
          </div>

          {/* Content */}
          <div className="p-4 max-h-[70vh] overflow-y-auto">
            {isLoading ? (
              <SkeletonLoader rows={12} />
            ) : isError ? (
              <div className="text-center py-8 text-red-600">
                Failed to load payslip details. Please try again.
              </div>
            ) : !payslip ? (
              <div className="text-center py-8 text-neutral-500">
                No payslip data available.
              </div>
            ) : (
              <div className="space-y-4">
                {/* Employee & Period Info - Compact Header */}
                <div className="grid grid-cols-1 md:grid-cols-2 gap-4 bg-neutral-50 rounded-lg p-4 border border-neutral-200">
                  <div>
                    <div className="flex items-center gap-1.5 mb-1">
                      <span className="text-[10px] font-medium text-neutral-600">Employee</span>
                    </div>
                    <p className="font-semibold text-neutral-900 text-sm">
                      {payslip.employee.last_name}, {payslip.employee.first_name}
                    </p>
                    <p className="text-xs text-neutral-600">{payslip.employee.employee_code}</p>
                    <p className="text-xs text-neutral-600">{payslip.employee.position_name ?? '—'}</p>
                    <p className="text-xs text-neutral-600">{payslip.employee.department_name ?? '—'}</p>
                  </div>
                  <div>
                    <div className="flex items-center gap-1.5 mb-1">
                      <span className="text-[10px] font-medium text-neutral-600">Pay Period</span>
                    </div>
                    <p className="font-semibold text-neutral-900 text-sm">{payslip.payroll_run.pay_period_label}</p>
                    <p className="text-xs text-neutral-600">
                      {formatDate(payslip.payroll_run.cutoff_start)} – {formatDate(payslip.payroll_run.cutoff_end)}
                    </p>
                    <p className="text-xs text-neutral-600">Pay Date: {formatDate(payslip.payroll_run.pay_date)}</p>
                    <p className="text-xs text-neutral-600 capitalize">Pay Basis: {payslip.pay_basis}</p>
                  </div>
                </div>

                {/* Three Column Layout */}
                <div className="grid grid-cols-1 lg:grid-cols-3 gap-4">
                  {/* Left Column - Attendance & Overtime */}
                  <div className="space-y-3">
                    {/* Attendance Breakdown */}
                    <div className="bg-white border border-neutral-200 rounded-lg p-3">
                      <SectionTitle icon={Clock} title="Attendance" />
                      <div className="space-y-0.5">
                        <div className="flex justify-between items-center py-1">
                          <span className="text-xs text-neutral-500">Days Worked / Working Days</span>
                          <span className="text-xs font-semibold text-neutral-900">{payslip.attendance.days_worked} / {payslip.working_days_in_period}</span>
                        </div>
                        <div className="flex justify-between items-center py-1">
                          <span className="text-xs text-neutral-500">Days Absent</span>
                          <span className={`text-xs font-semibold ${payslip.attendance.days_absent > 0 ? 'text-red-600' : 'text-neutral-900'}`}>
                            {payslip.attendance.days_absent}
                          </span>
                        </div>
                        <div className="flex justify-between items-center py-1">
                          <span className="text-xs text-neutral-500">Late (Minutes)</span>
                          <span className={`text-xs font-semibold ${payslip.attendance.days_late_minutes > 0 ? 'text-red-600' : 'text-neutral-900'}`}>
                            {payslip.attendance.days_late_minutes} min
                          </span>
                        </div>
                        <div className="flex justify-between items-center py-1">
                          <span className="text-xs text-neutral-500">Undertime</span>
                          <span className={`text-xs font-semibold ${payslip.attendance.undertime_minutes > 0 ? 'text-red-600' : 'text-neutral-900'}`}>
                            {formatMinutes(payslip.attendance.undertime_minutes)}
                          </span>
                        </div>
                        <div className="flex justify-between items-center py-1">
                          <span className="text-xs text-neutral-500">Paid Leave</span>
                          <span className="text-xs font-semibold text-neutral-900">{payslip.attendance.leave_days_paid} days</span>
                        </div>
                        {payslip.attendance.leave_days_unpaid > 0 && (
                          <div className="flex justify-between items-center py-1">
                            <span className="text-xs text-neutral-500">Unpaid Leave</span>
                            <span className="text-xs font-semibold text-red-600">{payslip.attendance.leave_days_unpaid} days</span>
                          </div>
                        )}
                        {payslip.attendance.regular_holiday_days > 0 && (
                          <InfoRow label="Regular Holidays" value={payslip.attendance.regular_holiday_days} />
                        )}
                        {payslip.attendance.special_holiday_days > 0 && (
                          <InfoRow label="Special Holidays" value={payslip.attendance.special_holiday_days} />
                        )}
                      </div>
                    </div>

                    {/* Overtime Breakdown */}
                    {(payslip.overtime.regular_minutes > 0 || payslip.overtime.rest_day_minutes > 0 || 
                      payslip.overtime.holiday_minutes > 0 || payslip.overtime.night_diff_minutes > 0) && (
                      <div className="bg-white border border-neutral-200 rounded-lg p-3">
                        <SectionTitle icon={TrendingUp} title="Overtime & Premiums" />
                        <div className="space-y-0.5">
                          {payslip.overtime.regular_minutes > 0 && (
                            <InfoRow label="Regular OT" value={formatMinutes(payslip.overtime.regular_minutes)} />
                          )}
                          {payslip.overtime.rest_day_minutes > 0 && (
                            <InfoRow label="Rest Day OT" value={formatMinutes(payslip.overtime.rest_day_minutes)} />
                          )}
                          {payslip.overtime.holiday_minutes > 0 && (
                            <InfoRow label="Holiday OT" value={formatMinutes(payslip.overtime.holiday_minutes)} />
                          )}
                          {payslip.overtime.night_diff_minutes > 0 && (
                            <InfoRow label="Night Differential" value={formatMinutes(payslip.overtime.night_diff_minutes)} />
                          )}
                        </div>
                      </div>
                    )}

                    {/* YTD Summary */}
                    <div className="bg-neutral-50 border border-neutral-200 rounded-lg p-3">
                      <SectionTitle icon={Briefcase} title="Year-to-Date" />
                      <div className="space-y-1">
                        <div className="flex justify-between items-center">
                          <span className="text-xs text-neutral-500">Taxable Income</span>
                          <span className="text-xs font-semibold text-neutral-900 tabular-nums">
                            <CurrencyAmount centavos={payslip.ytd.ytd_taxable_income_centavos} />
                          </span>
                        </div>
                        <div className="flex justify-between items-center">
                          <span className="text-xs text-neutral-500">Tax Withheld</span>
                          <span className="text-xs font-semibold text-neutral-900 tabular-nums">
                            <CurrencyAmount centavos={payslip.ytd.ytd_tax_withheld_centavos} />
                          </span>
                        </div>
                      </div>
                    </div>
                  </div>

                  {/* Middle Column - Earnings */}
                  <div className="space-y-3">
                    <div className="bg-white border border-neutral-200 rounded-lg p-3">
                      <SectionTitle icon={Wallet} title="Earnings" />
                      <div className="space-y-0.5">
                        <AmountRow label="Basic Pay" centavos={payslip.earnings.basic_pay_centavos} />
                        {payslip.earnings.overtime_pay_centavos > 0 && (
                          <AmountRow label="Overtime Pay" centavos={payslip.earnings.overtime_pay_centavos} />
                        )}
                        {payslip.earnings.holiday_pay_centavos > 0 && (
                          <AmountRow label="Holiday Pay" centavos={payslip.earnings.holiday_pay_centavos} />
                        )}
                        {payslip.earnings.night_diff_pay_centavos > 0 && (
                          <AmountRow label="Night Differential" centavos={payslip.earnings.night_diff_pay_centavos} />
                        )}
                        <AmountRow label="Gross Pay" centavos={payslip.earnings.gross_pay_centavos} isBold />
                      </div>
                    </div>

                    {/* Other Deductions */}
                    {(payslip.deductions.loan_deductions_centavos > 0 || payslip.deductions.other_deductions_centavos > 0) && (
                      <div className="bg-white border border-neutral-200 rounded-lg p-3">
                        <SectionTitle icon={MinusCircle} title="Other Deductions" />
                        <div className="space-y-0.5">
                          {payslip.deductions.loan_deductions_centavos > 0 && (
                            <AmountRow label="Loan Deductions" centavos={payslip.deductions.loan_deductions_centavos} />
                          )}
                          {payslip.deductions.other_deductions_centavos > 0 && (
                            <AmountRow label="Other Deductions" centavos={payslip.deductions.other_deductions_centavos} />
                          )}
                        </div>
                      </div>
                    )}
                  </div>

                  {/* Right Column - Deductions & Net Pay */}
                  <div className="space-y-3">
                    {/* Government Deductions */}
                    <div className="bg-white border border-neutral-200 rounded-lg p-3">
                      <SectionTitle icon={Building2} title="Government Contributions" />
                      <div className="space-y-1">
                        {/* SSS */}
                        <div className="bg-neutral-50 rounded p-2">
                          <div className="flex justify-between items-center">
                            <span className="text-xs font-medium text-neutral-700">SSS</span>
                            <span className="text-xs font-semibold text-neutral-900 tabular-nums">
                              <CurrencyAmount centavos={payslip.deductions.sss_ee_centavos} />
                            </span>
                          </div>
                          <div className="flex justify-between items-center mt-0.5">
                            <span className="text-[10px] text-neutral-500">Employer Share</span>
                            <span className="text-[10px] text-neutral-500 tabular-nums">
                              <CurrencyAmount centavos={payslip.deductions.sss_er_centavos} />
                            </span>
                          </div>
                        </div>
                        
                        {/* PhilHealth */}
                        <div className="bg-neutral-50 rounded p-2">
                          <div className="flex justify-between items-center">
                            <span className="text-xs font-medium text-neutral-700">PhilHealth</span>
                            <span className="text-xs font-semibold text-neutral-900 tabular-nums">
                              <CurrencyAmount centavos={payslip.deductions.philhealth_ee_centavos} />
                            </span>
                          </div>
                          <div className="flex justify-between items-center mt-0.5">
                            <span className="text-[10px] text-neutral-500">Employer Share</span>
                            <span className="text-[10px] text-neutral-500 tabular-nums">
                              <CurrencyAmount centavos={payslip.deductions.philhealth_er_centavos} />
                            </span>
                          </div>
                        </div>
                        
                        {/* Pag-IBIG */}
                        <div className="bg-neutral-50 rounded p-2">
                          <div className="flex justify-between items-center">
                            <span className="text-xs font-medium text-neutral-700">Pag-IBIG</span>
                            <span className="text-xs font-semibold text-neutral-900 tabular-nums">
                              <CurrencyAmount centavos={payslip.deductions.pagibig_ee_centavos} />
                            </span>
                          </div>
                          <div className="flex justify-between items-center mt-0.5">
                            <span className="text-[10px] text-neutral-500">Employer Share</span>
                            <span className="text-[10px] text-neutral-500 tabular-nums">
                              <CurrencyAmount centavos={payslip.deductions.pagibig_er_centavos} />
                            </span>
                          </div>
                        </div>

                        {/* Tax */}
                        <div className="flex justify-between items-center py-1 border-t border-neutral-200 mt-1">
                          <span className="text-xs text-neutral-600">Withholding Tax</span>
                          <span className="text-xs font-semibold text-neutral-900 tabular-nums">
                            <CurrencyAmount centavos={payslip.deductions.withholding_tax_centavos} />
                          </span>
                        </div>
                      </div>
                    </div>

                    {/* Net Pay Summary - Prominent */}
                    <div className="bg-neutral-800 rounded-lg p-4 text-white">
                      <div className="flex items-center gap-1.5 mb-2">
                        <Wallet className="h-4 w-4 text-neutral-400" />
                        <span className="text-[10px] font-medium text-neutral-400">Net Pay</span>
                      </div>
                      
                      <div className="space-y-1.5 mb-3">
                        <div className="flex justify-between items-center">
                          <span className="text-xs text-neutral-300">Gross Pay</span>
                          <span className="text-xs font-medium tabular-nums">
                            <CurrencyAmount centavos={payslip.summary.gross_pay_centavos} />
                          </span>
                        </div>
                        <div className="flex justify-between items-center">
                          <span className="text-xs text-neutral-300">Total Deductions</span>
                          <span className="text-xs font-medium tabular-nums text-red-300">
                            -<CurrencyAmount centavos={payslip.summary.total_deductions_centavos} />
                          </span>
                        </div>
                      </div>
                      
                      <div className="border-t border-neutral-600 pt-2">
                        <div className="flex justify-between items-center">
                          <span className="text-sm font-semibold">Net Pay</span>
                          <span className="text-xl font-semibold tabular-nums">
                            <CurrencyAmount centavos={payslip.summary.net_pay_centavos} />
                          </span>
                        </div>
                      </div>
                      
                      {payslip.summary.is_below_min_wage && (
                        <div className="mt-2 text-[10px] bg-red-500/40 rounded px-2 py-1 text-center">
                          Minimum Wage Earner (Tax Exempt)
                        </div>
                      )}
                      {payslip.summary.has_deferred_deductions && (
                        <div className="mt-1 text-[10px] bg-amber-500/40 rounded px-2 py-1 text-center">
                          Has Deferred Deductions
                        </div>
                      )}
                    </div>
                  </div>
                </div>
              </div>
            )}
          </div>

          {/* Footer */}
          <div className="px-4 py-3 border-t border-neutral-200 flex justify-between items-center">
            <p className="text-[10px] text-neutral-500">
              Reference: {payslip?.payroll_run.reference_no ?? '—'}
            </p>
            <button
              onClick={onClose}
              className="px-4 py-1.5 bg-neutral-200 hover:bg-neutral-300 text-neutral-800 text-xs font-medium rounded transition-colors"
            >
              Close
            </button>
          </div>
        </div>
      </div>
    </div>
  )
}
