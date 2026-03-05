<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<style>
  * { margin: 0; padding: 0; box-sizing: border-box; }
  body { font-family: DejaVu Sans, sans-serif; font-size: 9pt; color: #111; }
  .page { width: 100%; padding: 14px 18px; }

  h1 { font-size: 11pt; text-align: center; font-weight: bold; text-transform: uppercase; letter-spacing: .05em; }
  h2 { font-size: 8.5pt; text-align: center; margin-bottom: 4px; }
  .subtitle { text-align: center; font-size: 8pt; color: #444; margin-bottom: 10px; }

  table { width: 100%; border-collapse: collapse; }
  td, th { border: 1px solid #888; padding: 3px 5px; vertical-align: middle; }
  th { background: #1E3A5F; color: #fff; font-weight: bold; text-align: center; font-size: 8.5pt; }

  .label { font-weight: bold; width: 42%; }
  .value { width: 58%; }
  .right { text-align: right; }
  .center { text-align: center; }
  .total-row td { background: #EBF2FF; font-weight: bold; }
  .section-title { background: #d0dff5; font-weight: bold; font-size: 8.5pt; padding: 4px 5px; }

  .header-block { margin-bottom: 10px; }
  .header-block table td { border: none; padding: 2px 4px; font-size: 8.5pt; }

  .sig-row { margin-top: 30px; }
  .sig-row table td { border: none; text-align: center; padding-top: 6px; font-size: 8pt; }
  .sig-line { border-top: 1px solid #333 !important; padding-top: 2px !important; }

  .form-no { font-size: 8pt; color: #555; text-align: right; margin-bottom: 2px; }
  .badge { display: inline-block; border: 1px solid #555; padding: 1px 6px; font-size: 8pt; }
</style>
</head>
<body>
<div class="page">

  {{-- Form header --}}
  <div class="form-no">BIR Form No. 1601-C</div>
  <h1>Monthly Remittance Return of Income Taxes Withheld on Compensation</h1>
  <h2>Republic of the Philippines — Department of Finance — Bureau of Internal Revenue</h2>
  <div class="subtitle">
    For the Month of: <strong>{{ $monthLabel }}</strong> &nbsp;|&nbsp;
    Tax Year: <strong>{{ $year }}</strong>
  </div>

  {{-- Part I: Background Information --}}
  <table style="margin-bottom:10px;">
    <tr>
      <td class="section-title" colspan="4">Part I &mdash; Background Information</td>
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
    <tr>
      <td class="label">Category of Withholding Agent</td>
      <td class="value">Private</td>
      <td class="label">No. of Payees/Employees</td>
      <td class="value">{{ $totalEmployees }}</td>
    </tr>
  </table>

  {{-- Part II: Computation --}}
  <table style="margin-bottom:10px;">
    <tr>
      <td class="section-title" colspan="2">Part II &mdash; Computation of Tax Withheld on Compensation</td>
    </tr>
    <tr>
      <th style="width:70%; text-align:left; padding-left:6px;">Description</th>
      <th style="width:30%;">Amount</th>
    </tr>
    <tr>
      <td>Total Gross Compensation Income (All Employees)</td>
      <td class="right">{{ number_format($totalGrossPay / 100, 2) }}</td>
    </tr>
    <tr>
      <td>Total Non-Taxable / Exempt Compensation</td>
      <td class="right">{{ number_format(($totalGrossPay - $totalTaxableIncome) / 100, 2) }}</td>
    </tr>
    <tr>
      <td>Total Taxable Compensation Income</td>
      <td class="right">{{ number_format($totalTaxableIncome / 100, 2) }}</td>
    </tr>
    <tr class="total-row">
      <td>Tax Required to be Withheld / Remitted</td>
      <td class="right">{{ number_format($totalTaxWithheld / 100, 2) }}</td>
    </tr>
  </table>

  {{-- Schedule: Per-employee breakdown --}}
  <table style="margin-bottom:12px; font-size:8pt;">
    <tr>
      <td class="section-title" colspan="6">Schedule &mdash; List of Payees</td>
    </tr>
    <tr>
      <th>#</th>
      <th>Employee Name</th>
      <th>TIN</th>
      <th>BIR Status</th>
      <th class="right">Taxable Compensation</th>
      <th class="right">Tax Withheld</th>
    </tr>
    @foreach ($employees as $i => $emp)
    <tr>
      <td class="center">{{ $i + 1 }}</td>
      <td>{{ $emp->last_name }}, {{ $emp->first_name }}</td>
      <td class="center">{{ $emp->tin ?: '—' }}</td>
      <td class="center">{{ $emp->bir_status }}</td>
      <td class="right">{{ number_format($emp->ytd_taxable_income_centavos / 100, 2) }}</td>
      <td class="right">{{ number_format($emp->withholding_tax_centavos / 100, 2) }}</td>
    </tr>
    @endforeach
    <tr class="total-row">
      <td colspan="4" class="right"><strong>Total</strong></td>
      <td class="right"><strong>{{ number_format($totalTaxableIncome / 100, 2) }}</strong></td>
      <td class="right"><strong>{{ number_format($totalTaxWithheld / 100, 2) }}</strong></td>
    </tr>
  </table>

  {{-- Certification --}}
  <div class="sig-row">
    <table>
      <tr>
        <td style="width:60%;">
          <p style="font-size:8pt;">I declare, under the penalties of perjury, that this return has been made in good faith,
          verified by me, and to the best of my knowledge and belief is true, correct, and based on authentic records.</p>
        </td>
        <td style="width:40%;"></td>
      </tr>
      <tr>
        <td></td>
        <td class="center">
          <div style="height:30px;"></div>
          <div class="sig-line">Signature over Printed Name</div>
          <div style="margin-top:4px;">Title / Designation</div>
          <div style="margin-top:4px;">Date Signed: ____________________</div>
        </td>
      </tr>
    </table>
  </div>

</div>
</body>
</html>
