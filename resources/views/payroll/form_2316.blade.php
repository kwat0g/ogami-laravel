<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<style>
  * { margin: 0; padding: 0; box-sizing: border-box; }
  body { font-family: DejaVu Sans, sans-serif; font-size: 9pt; color: #111; }
  .page { width: 100%; padding: 14px 18px; }

  h1 { font-size: 11pt; text-align: center; font-weight: bold; text-transform: uppercase; letter-spacing: .04em; }
  h2 { font-size: 8.5pt; text-align: center; margin-bottom: 2px; }
  .subtitle { text-align: center; font-size: 8pt; color: #444; margin-bottom: 10px; }

  table { width: 100%; border-collapse: collapse; }
  td, th { border: 1px solid #888; padding: 3px 5px; vertical-align: middle; }
  th { background: #1E3A5F; color: #fff; font-weight: bold; text-align: center; font-size: 8.5pt; }

  .label { font-weight: bold; background: #f5f7fa; width: 40%; }
  .value { width: 60%; }
  .right { text-align: right; }
  .center { text-align: center; }
  .section-title { background: #d0dff5; font-weight: bold; font-size: 8.5pt; padding: 4px 5px; }
  .total-row td { background: #EBF2FF; font-weight: bold; }
  .highlight-row td { background: #fff6e6; font-weight: bold; }

  .form-no { font-size: 8pt; color: #555; text-align: right; margin-bottom: 2px; }
  .sig-line { border-top: 1px solid #333 !important; padding-top: 2px !important; }
  .mt10 { margin-top: 10px; }
</style>
</head>
<body>
<div class="page">

  {{-- Header --}}
  <div class="form-no">BIR Form No. 2316 &nbsp;|&nbsp; Taxable Year: {{ $year }}</div>
  <h1>Certificate of Compensation Payment / Tax Withheld</h1>
  <h2>Republic of the Philippines — Department of Finance — Bureau of Internal Revenue</h2>
  <div class="subtitle">For Compensation Payment With or Without Tax Withheld</div>

  {{-- Part I: Employer --}}
  <table class="mt10" style="margin-bottom:8px;">
    <tr>
      <td class="section-title" colspan="4">Part I &mdash; Employer Information</td>
    </tr>
    <tr>
      <td class="label">Employer TIN</td>
      <td class="value">{{ $settings['company_tin'] }}</td>
      <td class="label">RDO Code</td>
      <td class="value">{{ $settings['rdo_code'] }}</td>
    </tr>
    <tr>
      <td class="label">Registered Name</td>
      <td class="value" colspan="3">{{ $settings['company_name'] }}</td>
    </tr>
    <tr>
      <td class="label">Registered Address</td>
      <td class="value" colspan="3">{{ $settings['company_address'] }}</td>
    </tr>
  </table>

  {{-- Part II: Employee --}}
  <table style="margin-bottom:8px;">
    <tr>
      <td class="section-title" colspan="4">Part II &mdash; Employee Information</td>
    </tr>
    <tr>
      <td class="label">Employee TIN</td>
      <td class="value">{{ $employee->tin ?: '—' }}</td>
      <td class="label">BIR Status</td>
      <td class="value">{{ $employee->bir_status }}</td>
    </tr>
    <tr>
      <td class="label">Employee Name</td>
      <td class="value" colspan="3">{{ $employee->last_name }}, {{ $employee->first_name }}</td>
    </tr>
    <tr>
      <td class="label">Department / Position</td>
      <td class="value" colspan="3">
        {{ $employee->department_name }} &mdash; {{ $employee->position_title }}
      </td>
    </tr>
    <tr>
      <td class="label">Employment Period</td>
      <td class="value" colspan="3">
        January 1 &ndash; December 31, {{ $year }}
      </td>
    </tr>
  </table>

  {{-- Part III: Compensation Income --}}
  <table style="margin-bottom:8px;">
    <tr>
      <td class="section-title" colspan="2">Part III &mdash; Compensation Income &amp; Related Deductions</td>
    </tr>
    <tr>
      <td class="label">Annual Gross Compensation Income</td>
      <td class="right value">{{ number_format($employee->annual_gross_centavos / 100, 2) }}</td>
    </tr>
    <tr>
      <td class="label">SSS Contributions (Employee Share)</td>
      <td class="right value">{{ number_format($employee->annual_sss_ee_centavos / 100, 2) }}</td>
    </tr>
    <tr>
      <td class="label">PhilHealth Contributions (Employee Share)</td>
      <td class="right value">{{ number_format($employee->annual_philhealth_ee_centavos / 100, 2) }}</td>
    </tr>
    <tr>
      <td class="label">Pag-IBIG Contributions (Employee Share)</td>
      <td class="right value">{{ number_format($employee->annual_pagibig_ee_centavos / 100, 2) }}</td>
    </tr>
    <tr>
      <td class="label">13th Month Pay (Exempt up to ₱90,000)</td>
      <td class="right value">{{ number_format($thirteenthMonthCentavos / 100, 2) }}</td>
    </tr>
    <tr>
      <td class="label">Total Non-Taxable / Exempt Compensation</td>
      <td class="right value">
        {{ number_format(($employee->annual_gross_centavos - $employee->ytd_taxable_income_centavos) / 100, 2) }}
      </td>
    </tr>
    <tr class="highlight-row">
      <td>Total Taxable Compensation Income</td>
      <td class="right">{{ number_format($employee->ytd_taxable_income_centavos / 100, 2) }}</td>
    </tr>
  </table>

  {{-- Part IV: Tax Withheld --}}
  <table style="margin-bottom:12px;">
    <tr>
      <td class="section-title" colspan="2">Part IV &mdash; Tax Withheld</td>
    </tr>
    <tr>
      <td class="label">Total Taxes Withheld for the Year</td>
      <td class="right value">{{ number_format($employee->ytd_tax_withheld_centavos / 100, 2) }}</td>
    </tr>
    <tr class="total-row">
      <td>Net Taxable Compensation / Tax Due for the Year</td>
      <td class="right">{{ number_format($employee->ytd_taxable_income_centavos / 100, 2) }}</td>
    </tr>
    <tr class="total-row">
      <td>Amount of Tax Withheld (Over / Under)</td>
      <td class="right center">
        @php
          $over = $employee->ytd_tax_withheld_centavos - $taxDueCentavos;
          $label = $over >= 0 ? 'Over' : 'Under';
          $display = abs($over);
        @endphp
        {{ $label }}: {{ number_format($display / 100, 2) }}
      </td>
    </tr>
  </table>

  {{-- Certification --}}
  <table>
    <tr>
      <td colspan="3" style="font-size:8pt; padding:6px;">
        I declare, under the penalties of perjury, that this certificate has been made in good faith,
        verified by me, and to the best of my knowledge and belief is true, correct, and based on authentic records.
      </td>
    </tr>
    <tr>
      <td style="width:34%; text-align:center; padding-top:30px; border-top:none;">
        <div class="sig-line">Employer / Authorized Representative Signature</div>
        <div style="margin-top:3px;">Date: ___________________</div>
      </td>
      <td style="width:32%; border:none;"></td>
      <td style="width:34%; text-align:center; padding-top:30px; border-top:none;">
        <div class="sig-line">Employee Signature (Acknowledgement)</div>
        <div style="margin-top:3px;">Date: ___________________</div>
      </td>
    </tr>
  </table>

</div>
</body>
</html>
