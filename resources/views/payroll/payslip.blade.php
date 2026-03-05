<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Payslip — {{ $detail->employee->first_name }} {{ $detail->employee->last_name }}</title>
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: 'DejaVu Sans', sans-serif; font-size: 9pt; color: #1a1a1a; background: #fff; }
  .page { width: 100%; max-width: 700px; margin: 0 auto; padding: 24px; }

  /* Header */
  .company-header { text-align: center; border-bottom: 2px solid #1e3a5f; padding-bottom: 10px; margin-bottom: 14px; }
  .company-name { font-size: 15pt; font-weight: bold; color: #1e3a5f; letter-spacing: 0.5px; }
  .company-sub { font-size: 8pt; color: #555; margin-top: 2px; }
  .doc-title { font-size: 11pt; font-weight: bold; text-align: center; letter-spacing: 2px; text-transform: uppercase;
               color: #fff; background: #1e3a5f; padding: 5px 0; margin-bottom: 14px; }

  /* Info grid */
  .info-grid { display: table; width: 100%; margin-bottom: 14px; }
  .info-col { display: table-cell; vertical-align: top; width: 50%; }
  .info-col:last-child { padding-left: 16px; }
  .info-row { margin-bottom: 4px; }
  .info-label { font-size: 7.5pt; color: #666; text-transform: uppercase; letter-spacing: 0.4px; }
  .info-value { font-size: 9pt; font-weight: bold; color: #111; }

  /* Section headings */
  .section-title { font-size: 8pt; font-weight: bold; text-transform: uppercase; letter-spacing: 0.8px;
                   color: #fff; background: #2d6a9f; padding: 3px 8px; margin-bottom: 0; }

  /* Tables */
  table { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
  th { font-size: 7.5pt; font-weight: bold; text-align: left; padding: 3px 8px;
       background: #f0f4f9; color: #333; border-bottom: 1px solid #c9d8e8; }
  th.right, td.right { text-align: right; }
  td { font-size: 8.5pt; padding: 3px 8px; border-bottom: 1px solid #e8ecf0; }
  tr:last-child td { border-bottom: none; }
  .total-row td { font-weight: bold; background: #f7f9fc; border-top: 1.5px solid #2d6a9f; }
  .total-row td.right { font-size: 9.5pt; color: #1e3a5f; }
  .grand-total { text-align: center; margin-bottom: 12px; padding: 8px; background: #1e3a5f; color: #fff; border-radius: 3px; }
  .grand-total .label { font-size: 8pt; letter-spacing: 1px; text-transform: uppercase; }
  .grand-total .amount { font-size: 16pt; font-weight: bold; letter-spacing: 0.5px; }

  /* YTD */
  .ytd-grid { display: table; width: 100%; border: 1px solid #c9d8e8; border-radius: 3px; }
  .ytd-cell { display: table-cell; padding: 7px 12px; border-right: 1px solid #c9d8e8; }
  .ytd-cell:last-child { border-right: none; }
  .ytd-label { font-size: 7pt; color: #666; text-transform: uppercase; letter-spacing: 0.4px; }
  .ytd-value { font-size: 9pt; font-weight: bold; color: #1e3a5f; }

  /* Flags */
  .flag { display: inline-block; padding: 1px 5px; font-size: 7pt; font-weight: bold; border-radius: 2px; margin-right: 3px; }
  .flag-mwe { background: #fee2e2; color: #b91c1c; }
  .flag-def { background: #fef3c7; color: #92400e; }

  /* Footer */
  .footer { margin-top: 18px; border-top: 1px solid #c9d8e8; padding-top: 10px;
            font-size: 7.5pt; color: #888; text-align: center; }
  .signature-row { display: table; width: 100%; margin-top: 24px; }
  .signature-cell { display: table-cell; text-align: center; width: 33%; padding-top: 24px; border-top: 1px solid #555; }
  .signature-label { font-size: 7.5pt; color: #666; }
</style>
</head>
<body>
<div class="page">

  {{-- Company header --}}
  <div class="company-header">
    <div class="company-name">{{ $settings['company_name'] ?? 'Ogami Manufacturing Philippines Corp.' }}</div>
    <div class="company-sub">{{ $settings['company_address'] ?? '' }}</div>
    @if(!empty($settings['company_tin']))
    <div class="company-sub">TIN: {{ $settings['company_tin'] }}</div>
    @endif
  </div>

  <div class="doc-title">Payslip</div>

  {{-- Employee + Period info --}}
  <div class="info-grid">
    <div class="info-col">
      <div class="info-row">
        <div class="info-label">Employee</div>
        <div class="info-value">{{ $detail->employee->last_name }}, {{ $detail->employee->first_name }} {{ $detail->employee->middle_name ?? '' }}</div>
      </div>
      <div class="info-row">
        <div class="info-label">Employee Code</div>
        <div class="info-value">{{ $detail->employee->employee_code }}</div>
      </div>
      <div class="info-row">
        <div class="info-label">Department</div>
        <div class="info-value">{{ $departmentName ?? '—' }}</div>
      </div>
      <div class="info-row">
        <div class="info-label">Position</div>
        <div class="info-value">{{ $positionName ?? '—' }}</div>
      </div>
      <div class="info-row">
        <div class="info-label">Employment Type</div>
        <div class="info-value">{{ ucfirst(str_replace('_', ' ', $detail->employee->employment_type)) }}</div>
      </div>
    </div>
    <div class="info-col">
      <div class="info-row">
        <div class="info-label">Pay Period</div>
        <div class="info-value">{{ $run->pay_period_label }}</div>
      </div>
      <div class="info-row">
        <div class="info-label">Cutoff</div>
        <div class="info-value">{{ \Carbon\Carbon::parse($run->cutoff_start)->format('M j') }} – {{ \Carbon\Carbon::parse($run->cutoff_end)->format('M j, Y') }}</div>
      </div>
      <div class="info-row">
        <div class="info-label">Pay Date</div>
        <div class="info-value">{{ \Carbon\Carbon::parse($run->pay_date)->format('F j, Y') }}</div>
      </div>
      <div class="info-row">
        <div class="info-label">Days Worked / Period</div>
        <div class="info-value">{{ $detail->days_worked }}
          @if($detail->leave_days_paid > 0) + {{ $detail->leave_days_paid }} leave @endif
          / {{ $detail->working_days_in_period }}</div>
      </div>
      @if($detail->days_absent > 0)
      <div class="info-row">
        <div class="info-label">Days Absent</div>
        <div class="info-value" style="color:#b91c1c">{{ $detail->days_absent }}</div>
      </div>
      @endif
      @if($detail->days_late_minutes > 0)
      <div class="info-row">
        <div class="info-label">Late (Minutes)</div>
        <div class="info-value" style="color:#b91c1c">{{ $detail->days_late_minutes }} min</div>
      </div>
      @endif
      @if($detail->undertime_minutes > 0)
      <div class="info-row">
        <div class="info-label">Undertime</div>
        <div class="info-value" style="color:#b91c1c">{{ intdiv($detail->undertime_minutes, 60) }}h {{ $detail->undertime_minutes % 60 }}m</div>
      </div>
      @endif
      @if($detail->is_below_min_wage || $detail->has_deferred_deductions)
      <div class="info-row" style="margin-top:4px">
        @if($detail->is_below_min_wage)
          <span class="flag flag-mwe">MWE</span>
        @endif
        @if($detail->has_deferred_deductions)
          <span class="flag flag-def">DEF</span>
        @endif
      </div>
      @endif
    </div>
  </div>

  {{-- Attendance Summary --}}
  <div class="section-title">Attendance Summary</div>
  <table>
    <thead>
      <tr>
        <th style="width:50%">Description</th>
        <th class="right">Value</th>
      </tr>
    </thead>
    <tbody>
      <tr>
        <td>Days Worked / Working Days in Period</td>
        <td class="right">{{ $detail->days_worked }} / {{ $detail->working_days_in_period }}</td>
      </tr>
      <tr>
        <td>Days Absent</td>
        <td class="right" style="{{ $detail->days_absent > 0 ? 'color:#b91c1c;font-weight:bold' : '' }}">{{ $detail->days_absent }}</td>
      </tr>
      <tr>
        <td>Late (Minutes)</td>
        <td class="right" style="{{ $detail->days_late_minutes > 0 ? 'color:#b91c1c' : '' }}">{{ $detail->days_late_minutes }} min</td>
      </tr>
      <tr>
        <td>Undertime</td>
        <td class="right" style="{{ $detail->undertime_minutes > 0 ? 'color:#b91c1c' : '' }}">
          {{ intdiv($detail->undertime_minutes, 60) }}h {{ $detail->undertime_minutes % 60 }}m
        </td>
      </tr>
      @if($detail->leave_days_paid > 0)
      <tr>
        <td>Paid Leave Days</td>
        <td class="right">{{ $detail->leave_days_paid }}</td>
      </tr>
      @endif
      @if($detail->leave_days_unpaid > 0)
      <tr>
        <td>Unpaid Leave Days</td>
        <td class="right" style="color:#b91c1c">{{ $detail->leave_days_unpaid }}</td>
      </tr>
      @endif
      @if($detail->regular_holiday_days > 0)
      <tr>
        <td>Regular Holidays</td>
        <td class="right">{{ $detail->regular_holiday_days }}</td>
      </tr>
      @endif
      @if($detail->special_holiday_days > 0)
      <tr>
        <td>Special Holidays</td>
        <td class="right">{{ $detail->special_holiday_days }}</td>
      </tr>
      @endif
    </tbody>
  </table>

  {{-- Overtime Breakdown --}}
  @if($detail->overtime_regular_minutes > 0 || $detail->overtime_rest_day_minutes > 0 || $detail->overtime_holiday_minutes > 0 || $detail->night_diff_minutes > 0)
  <div class="section-title">Overtime & Premiums</div>
  <table>
    <thead>
      <tr>
        <th style="width:50%">Type</th>
        <th class="right">Hours</th>
      </tr>
    </thead>
    <tbody>
      @if($detail->overtime_regular_minutes > 0)
      <tr>
        <td>Regular Overtime</td>
        <td class="right">{{ intdiv($detail->overtime_regular_minutes, 60) }}h {{ $detail->overtime_regular_minutes % 60 }}m</td>
      </tr>
      @endif
      @if($detail->overtime_rest_day_minutes > 0)
      <tr>
        <td>Rest Day Overtime</td>
        <td class="right">{{ intdiv($detail->overtime_rest_day_minutes, 60) }}h {{ $detail->overtime_rest_day_minutes % 60 }}m</td>
      </tr>
      @endif
      @if($detail->overtime_holiday_minutes > 0)
      <tr>
        <td>Holiday Overtime</td>
        <td class="right">{{ intdiv($detail->overtime_holiday_minutes, 60) }}h {{ $detail->overtime_holiday_minutes % 60 }}m</td>
      </tr>
      @endif
      @if($detail->night_diff_minutes > 0)
      <tr>
        <td>Night Differential</td>
        <td class="right">{{ intdiv($detail->night_diff_minutes, 60) }}h {{ $detail->night_diff_minutes % 60 }}m</td>
      </tr>
      @endif
    </tbody>
  </table>
  @endif

  {{-- Earnings --}}
  <div class="section-title">Earnings</div>
  <table>
    <thead>
      <tr>
        <th>Description</th>
        <th class="right">Amount (₱)</th>
      </tr>
    </thead>
    <tbody>
      <tr>
        <td>Basic Pay ({{ ucfirst($detail->pay_basis) }})</td>
        <td class="right">{{ number_format($detail->basic_pay_centavos / 100, 2) }}</td>
      </tr>
      @if($detail->overtime_pay_centavos > 0)
      <tr>
        <td>Overtime Pay</td>
        <td class="right">{{ number_format($detail->overtime_pay_centavos / 100, 2) }}</td>
      </tr>
      @endif
      @if($detail->holiday_pay_centavos > 0)
      <tr>
        <td>Holiday Pay</td>
        <td class="right">{{ number_format($detail->holiday_pay_centavos / 100, 2) }}</td>
      </tr>
      @endif
      @if($detail->night_diff_pay_centavos > 0)
      <tr>
        <td>Night Differential</td>
        <td class="right">{{ number_format($detail->night_diff_pay_centavos / 100, 2) }}</td>
      </tr>
      @endif
      <tr class="total-row">
        <td>Gross Pay</td>
        <td class="right">{{ number_format($detail->gross_pay_centavos / 100, 2) }}</td>
      </tr>
    </tbody>
  </table>

  {{-- Contributions Breakdown --}}
  <div class="section-title">Government Contributions</div>
  <table>
    <thead>
      <tr>
        <th style="width:40%">Type</th>
        <th class="right" style="width:30%">Employee</th>
        <th class="right" style="width:30%">Employer</th>
      </tr>
    </thead>
    <tbody>
      <tr>
        <td>SSS</td>
        <td class="right">{{ number_format($detail->sss_ee_centavos / 100, 2) }}</td>
        <td class="right" style="color:#666">{{ number_format($detail->sss_er_centavos / 100, 2) }}</td>
      </tr>
      <tr>
        <td>PhilHealth</td>
        <td class="right">{{ number_format($detail->philhealth_ee_centavos / 100, 2) }}</td>
        <td class="right" style="color:#666">{{ number_format($detail->philhealth_er_centavos / 100, 2) }}</td>
      </tr>
      <tr>
        <td>Pag-IBIG</td>
        <td class="right">{{ number_format($detail->pagibig_ee_centavos / 100, 2) }}</td>
        <td class="right" style="color:#666">{{ number_format($detail->pagibig_er_centavos / 100, 2) }}</td>
      </tr>
    </tbody>
  </table>

  {{-- Deductions --}}
  <div class="section-title">Deductions</div>
  <table>
    <thead>
      <tr>
        <th>Description</th>
        <th class="right">Amount (₱)</th>
      </tr>
    </thead>
    <tbody>
      <tr>
        <td>SSS Employee Share</td>
        <td class="right">{{ number_format($detail->sss_ee_centavos / 100, 2) }}</td>
      </tr>
      <tr>
        <td>PhilHealth Employee Share</td>
        <td class="right">{{ number_format($detail->philhealth_ee_centavos / 100, 2) }}</td>
      </tr>
      <tr>
        <td>Pag-IBIG Employee Share</td>
        <td class="right">{{ number_format($detail->pagibig_ee_centavos / 100, 2) }}</td>
      </tr>
      <tr>
        <td>Withholding Tax</td>
        <td class="right">{{ number_format($detail->withholding_tax_centavos / 100, 2) }}</td>
      </tr>
      @if($detail->loan_deductions_centavos > 0)
      <tr>
        <td>Loan Deductions
          @if($detail->has_deferred_deductions)
            <span class="flag flag-def" style="margin-left:4px">DEFERRED</span>
          @endif
        </td>
        <td class="right">{{ number_format($detail->loan_deductions_centavos / 100, 2) }}</td>
      </tr>
      @endif
      @if($detail->other_deductions_centavos > 0)
      <tr>
        <td>Other Deductions</td>
        <td class="right">{{ number_format($detail->other_deductions_centavos / 100, 2) }}</td>
      </tr>
      @endif
      <tr class="total-row">
        <td>Total Deductions</td>
        <td class="right">{{ number_format($detail->total_deductions_centavos / 100, 2) }}</td>
      </tr>
    </tbody>
  </table>

  {{-- Net Pay --}}
  <div class="grand-total">
    <div class="label">Net Pay</div>
    <div class="amount">₱ {{ number_format($detail->net_pay_centavos / 100, 2) }}</div>
  </div>

  {{-- YTD Summary --}}
  <div class="section-title" style="margin-bottom:6px">Year-To-Date Summary</div>
  <div class="ytd-grid" style="margin-bottom:14px">
    <div class="ytd-cell">
      <div class="ytd-label">YTD Taxable Income</div>
      <div class="ytd-value">₱ {{ number_format($detail->ytd_taxable_income_centavos / 100, 2) }}</div>
    </div>
    <div class="ytd-cell">
      <div class="ytd-label">YTD Tax Withheld</div>
      <div class="ytd-value">₱ {{ number_format($detail->ytd_tax_withheld_centavos / 100, 2) }}</div>
    </div>
  </div>

  {{-- Signature row --}}
  <div class="signature-row">
    <div class="signature-cell">
      <div class="signature-label">Prepared by (HR Manager)</div>
    </div>
    <div class="signature-cell">
      <div class="signature-label">Approved by (Accounting Manager)</div>
    </div>
    <div class="signature-cell">
      <div class="signature-label">Received by (Employee)</div>
    </div>
  </div>

  <div class="footer">
    Reference: {{ $run->reference_no }} · Generated: {{ now()->format('F j, Y \a\t g:i A') }} · This is a computer-generated document.
  </div>
</div>
</body>
</html>
