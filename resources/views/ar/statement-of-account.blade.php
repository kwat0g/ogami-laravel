<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Statement of Account — {{ $customer->name }}</title>
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: 'DejaVu Sans', sans-serif; font-size: 9pt; color: #1a1a1a; background: #fff; }
  .page { width: 100%; max-width: 720px; margin: 0 auto; padding: 24px; }

  .company-header { text-align: center; border-bottom: 2px solid #1e3a5f; padding-bottom: 10px; margin-bottom: 14px; }
  .company-name { font-size: 15pt; font-weight: bold; color: #1e3a5f; letter-spacing: 0.5px; }
  .company-sub { font-size: 8pt; color: #555; margin-top: 2px; }
  .doc-title { font-size: 11pt; font-weight: bold; text-align: center; letter-spacing: 2px;
               text-transform: uppercase; color: #fff; background: #1e3a5f; padding: 5px 0; margin-bottom: 14px; }

  .two-col { display: table; width: 100%; margin-bottom: 14px; }
  .col-left  { display: table-cell; vertical-align: top; width: 55%; padding-right: 20px; }
  .col-right { display: table-cell; vertical-align: top; width: 45%; text-align: right; }
  .info-label { font-size: 7.5pt; color: #666; text-transform: uppercase; letter-spacing: 0.4px; }
  .info-value { font-size: 9pt; font-weight: bold; color: #111; margin-bottom: 4px; }

  .section-title { font-size: 8pt; font-weight: bold; text-transform: uppercase; letter-spacing: 0.8px;
                   color: #fff; background: #2d6a9f; padding: 3px 8px; margin-bottom: 0; }

  table { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
  th { font-size: 7.5pt; font-weight: bold; text-align: left; padding: 4px 8px;
       background: #f0f4f9; color: #333; border-bottom: 1px solid #c9d8e8; }
  th.right, td.right { text-align: right; }
  td { font-size: 8.5pt; padding: 4px 8px; border-bottom: 1px solid #e8ecf0; }
  tr:last-child td { border-bottom: none; }

  .aging-grid { display: table; width: 100%; border: 1px solid #c9d8e8; border-radius: 3px; margin-bottom: 14px; }
  .aging-cell { display: table-cell; text-align: center; padding: 7px 6px; border-right: 1px solid #c9d8e8; }
  .aging-cell:last-child { border-right: none; }
  .aging-label { font-size: 7pt; color: #666; text-transform: uppercase; letter-spacing: 0.3px; }
  .aging-value { font-size: 9pt; font-weight: bold; color: #1e3a5f; }
  .aging-overdue { color: #b91c1c; }

  .badge { display: inline-block; padding: 1px 5px; border-radius: 3px; font-size: 7pt; font-weight: bold; }
  .badge-overdue { background: #fee2e2; color: #b91c1c; }
  .badge-current { background: #d1fae5; color: #065f46; }

  .grand-total { text-align: right; margin-bottom: 14px; padding: 8px 12px;
                 background: #1e3a5f; color: #fff; border-radius: 3px; }
  .grand-total .label { font-size: 8pt; letter-spacing: 1px; text-transform: uppercase; }
  .grand-total .amount { font-size: 14pt; font-weight: bold; }

  .footer { margin-top: 24px; border-top: 1px solid #c9d8e8; padding-top: 10px;
            font-size: 7.5pt; color: #888; text-align: center; }
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

  <div class="doc-title">Statement of Account</div>

  {{-- Customer + Statement info --}}
  <div class="two-col">
    <div class="col-left">
      <div class="info-label">Customer</div>
      <div class="info-value">{{ $customer->name }}</div>
      @if($customer->contact_person)
      <div class="info-label">Contact</div>
      <div class="info-value">{{ $customer->contact_person }}</div>
      @endif
      @if($customer->address)
      <div class="info-label">Address</div>
      <div class="info-value" style="font-weight: normal; font-size: 8pt;">{{ $customer->address }}</div>
      @endif
      @if($customer->tin)
      <div class="info-label">TIN</div>
      <div class="info-value">{{ $customer->tin }}</div>
      @endif
    </div>
    <div class="col-right">
      <div class="info-label">Statement Date</div>
      <div class="info-value">{{ $asOf->format('F d, Y') }}</div>
      <div class="info-label">Credit Limit</div>
      <div class="info-value">₱{{ number_format($customer->credit_limit, 2) }}</div>
      <div class="info-label">Total Outstanding</div>
      <div class="info-value" style="color: #b91c1c; font-size: 12pt;">₱{{ number_format($totalOutstanding, 2) }}</div>
    </div>
  </div>

  {{-- Aging Summary --}}
  <div class="section-title">Aging Summary</div>
  <div class="aging-grid">
    <div class="aging-cell">
      <div class="aging-label">Current (0–30)</div>
      <div class="aging-value">₱{{ number_format($agingBuckets['current'] ?? 0, 2) }}</div>
    </div>
    <div class="aging-cell">
      <div class="aging-label">31–60 Days</div>
      <div class="aging-value {{ ($agingBuckets['bucket_31_60'] ?? 0) > 0 ? 'aging-overdue' : '' }}">
        ₱{{ number_format($agingBuckets['bucket_31_60'] ?? 0, 2) }}
      </div>
    </div>
    <div class="aging-cell">
      <div class="aging-label">61–90 Days</div>
      <div class="aging-value {{ ($agingBuckets['bucket_61_90'] ?? 0) > 0 ? 'aging-overdue' : '' }}">
        ₱{{ number_format($agingBuckets['bucket_61_90'] ?? 0, 2) }}
      </div>
    </div>
    <div class="aging-cell">
      <div class="aging-label">91–120 Days</div>
      <div class="aging-value {{ ($agingBuckets['bucket_91_120'] ?? 0) > 0 ? 'aging-overdue' : '' }}">
        ₱{{ number_format($agingBuckets['bucket_91_120'] ?? 0, 2) }}
      </div>
    </div>
    <div class="aging-cell">
      <div class="aging-label">120+ Days</div>
      <div class="aging-value {{ ($agingBuckets['over_120'] ?? 0) > 0 ? 'aging-overdue' : '' }}">
        ₱{{ number_format($agingBuckets['over_120'] ?? 0, 2) }}
      </div>
    </div>
  </div>

  {{-- Open Invoices --}}
  <div class="section-title">Open Invoices</div>
  <table>
    <thead>
      <tr>
        <th>Invoice #</th>
        <th>Date</th>
        <th>Due Date</th>
        <th>Days Past Due</th>
        <th class="right">Amount</th>
        <th class="right">Paid</th>
        <th class="right">Balance</th>
      </tr>
    </thead>
    <tbody>
      @forelse($invoices as $inv)
      <tr>
        <td>{{ $inv['invoice_number'] ?? '—' }}</td>
        <td>{{ \Carbon\Carbon::parse($inv['invoice_date'])->format('M d, Y') }}</td>
        <td>{{ \Carbon\Carbon::parse($inv['due_date'])->format('M d, Y') }}</td>
        <td>
          @if($inv['days_past_due'] > 0)
            <span class="badge badge-overdue">{{ $inv['days_past_due'] }} days</span>
          @else
            <span class="badge badge-current">Current</span>
          @endif
        </td>
        <td class="right">₱{{ number_format($inv['total_amount'], 2) }}</td>
        <td class="right">₱{{ number_format($inv['total_paid'], 2) }}</td>
        <td class="right" style="font-weight: bold;">₱{{ number_format($inv['balance_due'], 2) }}</td>
      </tr>
      @empty
      <tr><td colspan="7" style="text-align: center; color: #888; padding: 12px;">No open invoices.</td></tr>
      @endforelse
    </tbody>
  </table>

  {{-- Grand total --}}
  <div class="grand-total">
    <span class="label">Total Amount Due&nbsp;&nbsp;</span>
    <span class="amount">₱{{ number_format($totalOutstanding, 2) }}</span>
  </div>

  {{-- Recent Payments --}}
  @if(count($recentPayments) > 0)
  <div class="section-title">Recent Payments (Last 90 Days)</div>
  <table>
    <thead>
      <tr>
        <th>Date</th>
        <th>Reference</th>
        <th>Invoice #</th>
        <th class="right">Amount</th>
      </tr>
    </thead>
    <tbody>
      @foreach($recentPayments as $payment)
      <tr>
        <td>{{ \Carbon\Carbon::parse($payment->payment_date)->format('M d, Y') }}</td>
        <td>{{ $payment->reference_number ?? '—' }}</td>
        <td>{{ $payment->invoice?->invoice_number ?? '—' }}</td>
        <td class="right">₱{{ number_format($payment->amount, 2) }}</td>
      </tr>
      @endforeach
    </tbody>
  </table>
  @endif

  <div class="footer">
    This statement is generated as of {{ $asOf->format('F d, Y') }}.
    Please remit payment within the terms agreed upon.
    For inquiries, contact our Accounting Department.
  </div>

</div>
</body>
</html>
