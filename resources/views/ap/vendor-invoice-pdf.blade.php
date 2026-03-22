<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>AP Invoice — {{ $invoice->or_number ?? '#' . $invoice->id }}</title>
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: 'DejaVu Sans', sans-serif; font-size: 9pt; color: #1a1a1a; background: #fff; }
  .page { width: 100%; max-width: 720px; margin: 0 auto; padding: 24px; }

  .company-header { text-align: center; border-bottom: 2px solid #1e3a5f; padding-bottom: 10px; margin-bottom: 14px; }
  .company-name { font-size: 15pt; font-weight: bold; color: #1e3a5f; letter-spacing: 0.5px; }
  .company-sub { font-size: 8pt; color: #555; margin-top: 2px; }
  .doc-title { font-size: 11pt; font-weight: bold; text-align: center; letter-spacing: 2px;
               text-transform: uppercase; color: #fff; background: #1e3a5f; padding: 5px 0; margin-bottom: 14px; }

  .meta-grid { display: table; width: 100%; margin-bottom: 14px; border: 1px solid #c9d8e8; }
  .meta-col { display: table-cell; vertical-align: top; width: 50%; padding: 10px 14px; }
  .meta-col:first-child { border-right: 1px solid #c9d8e8; }
  .meta-row { margin-bottom: 5px; }
  .meta-label { font-size: 7.5pt; color: #666; text-transform: uppercase; letter-spacing: 0.4px; }
  .meta-value { font-size: 9pt; font-weight: bold; color: #111; }

  .badge { display: inline-block; padding: 1px 7px; border-radius: 3px; font-size: 8pt; font-weight: bold; }
  .badge-draft            { background: #f3f4f6; color: #374151; }
  .badge-pending_approval { background: #fef3c7; color: #b45309; }
  .badge-head_noted       { background: #e0e7ff; color: #4338ca; }
  .badge-manager_checked  { background: #dbeafe; color: #1d4ed8; }
  .badge-officer_reviewed { background: #d1fae5; color: #065f46; }
  .badge-approved         { background: #bbf7d0; color: #14532d; }
  .badge-partially_paid   { background: #fde68a; color: #92400e; }
  .badge-paid             { background: #6ee7b7; color: #064e3b; }
  .badge-rejected         { background: #fee2e2; color: #7f1d1d; }

  .section-title { font-size: 8pt; font-weight: bold; text-transform: uppercase; letter-spacing: 0.8px;
                   color: #fff; background: #2d6a9f; padding: 3px 8px; margin-bottom: 0; }

  .vendor-box { border: 1px solid #c9d8e8; padding: 10px 14px; margin-bottom: 14px; }
  .vendor-name { font-size: 11pt; font-weight: bold; color: #1e3a5f; }
  .vendor-detail { font-size: 8pt; color: #555; margin-top: 2px; }

  table { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
  th { font-size: 7.5pt; font-weight: bold; text-align: left; padding: 4px 8px;
       background: #f0f4f9; color: #333; border-bottom: 1px solid #c9d8e8; }
  th.right, td.right { text-align: right; }
  td { font-size: 8.5pt; padding: 4px 8px; border-bottom: 1px solid #e8ecf0; }
  tr:last-child td { border-bottom: none; }
  .total-row td { font-weight: bold; background: #f7f9fc; border-top: 1px solid #c9d8e8; }
  .grand-total td { font-weight: bold; background: #e8f0fa; border-top: 2px solid #1e3a5f; font-size: 10pt; }

  .summary-box { border: 1px solid #c9d8e8; margin-bottom: 14px; }
  .summary-row { display: table; width: 100%; border-bottom: 1px solid #e8ecf0; }
  .summary-row:last-child { border-bottom: none; }
  .summary-label { display: table-cell; padding: 5px 12px; font-size: 8pt; color: #666; width: 60%; }
  .summary-value { display: table-cell; padding: 5px 12px; font-size: 9pt; font-weight: bold; color: #111; text-align: right; width: 40%; }
  .summary-value.highlight { color: #14532d; font-size: 10pt; }
  .summary-value.due { color: #b91c1c; font-size: 10pt; }

  .footer { margin-top: 20px; border-top: 1px solid #c9d8e8; padding-top: 8px; font-size: 7.5pt; color: #aaa;
            text-align: center; }

  @page { margin: 15mm; }
</style>
</head>
<body>
<div class="page">

  {{-- Company Header --}}
  <div class="company-header">
    <div class="company-name">{{ $settings['company_name'] ?? 'Ogami Manufacturing Corp.' }}</div>
    <div class="company-sub">{{ $settings['company_address'] ?? '' }}</div>
  </div>

  <div class="doc-title">Accounts Payable Invoice</div>

  {{-- Meta --}}
  <div class="meta-grid">
    <div class="meta-col">
      <div class="meta-row">
        <div class="meta-label">Invoice / OR Number</div>
        <div class="meta-value">{{ $invoice->or_number ?? '—' }}</div>
      </div>
      <div class="meta-row">
        <div class="meta-label">Invoice Date</div>
        <div class="meta-value">{{ \Carbon\Carbon::parse($invoice->invoice_date)->format('F j, Y') }}</div>
      </div>
      <div class="meta-row">
        <div class="meta-label">Due Date</div>
        <div class="meta-value">{{ \Carbon\Carbon::parse($invoice->due_date)->format('F j, Y') }}</div>
      </div>
      @if($invoice->purchaseOrder)
      <div class="meta-row">
        <div class="meta-label">Related PO</div>
        <div class="meta-value">{{ $invoice->purchaseOrder->po_reference }}</div>
      </div>
      @endif
    </div>
    <div class="meta-col">
      <div class="meta-row">
        <div class="meta-label">Status</div>
        <div class="meta-value">
          <span class="badge badge-{{ $invoice->status }}">{{ strtoupper(str_replace('_', ' ', $invoice->status)) }}</span>
        </div>
      </div>
      <div class="meta-row">
        <div class="meta-label">Fiscal Period</div>
        <div class="meta-value">{{ $invoice->fiscalPeriod?->name ?? '—' }}</div>
      </div>
      <div class="meta-row">
        <div class="meta-label">Payment Terms</div>
        <div class="meta-value">{{ $invoice->payment_terms ?? '—' }}</div>
      </div>
    </div>
  </div>

  {{-- Vendor --}}
  <div class="section-title">Vendor</div>
  <div class="vendor-box" style="margin-top:0;">
    <div class="vendor-name">{{ $invoice->vendor?->name ?? '—' }}</div>
    @if($invoice->vendor?->address)
      <div class="vendor-detail">{{ $invoice->vendor->address }}</div>
    @endif
    @if($invoice->vendor?->tin)
      <div class="vendor-detail">TIN: {{ $invoice->vendor->tin }}</div>
    @endif
  </div>

  {{-- Amount Summary --}}
  <div class="section-title">Amount Summary</div>
  <div class="summary-box" style="margin-top:0;">
    <div class="summary-row">
      <div class="summary-label">Gross Amount</div>
      <div class="summary-value">₱{{ number_format($invoice->gross_amount, 2) }}</div>
    </div>
    @if($invoice->vat_amount > 0)
    <div class="summary-row">
      <div class="summary-label">VAT ({{ $invoice->vat_rate ?? 12 }}%)</div>
      <div class="summary-value">₱{{ number_format($invoice->vat_amount, 2) }}</div>
    </div>
    @endif
    @if($invoice->ewt_amount > 0)
    <div class="summary-row">
      <div class="summary-label">Expanded Withholding Tax (EWT)</div>
      <div class="summary-value" style="color:#b91c1c;">— ₱{{ number_format($invoice->ewt_amount, 2) }}</div>
    </div>
    @endif
    <div class="summary-row">
      <div class="summary-label" style="font-weight:bold;">Net Amount Payable</div>
      <div class="summary-value highlight">₱{{ number_format($invoice->net_amount, 2) }}</div>
    </div>
    @if(isset($invoice->amount_paid) && $invoice->amount_paid > 0)
    <div class="summary-row">
      <div class="summary-label">Amount Paid</div>
      <div class="summary-value">₱{{ number_format($invoice->amount_paid, 2) }}</div>
    </div>
    <div class="summary-row">
      <div class="summary-label" style="font-weight:bold;">Balance Due</div>
      <div class="summary-value due">₱{{ number_format($invoice->balance_due, 2) }}</div>
    </div>
    @endif
  </div>

  {{-- Payment History --}}
  @if($invoice->payments && $invoice->payments->count() > 0)
  <div class="section-title">Payment History</div>
  <table>
    <thead>
      <tr>
        <th>Date</th>
        <th>Reference</th>
        <th>Method</th>
        <th class="right">Amount</th>
      </tr>
    </thead>
    <tbody>
      @foreach($invoice->payments as $pmt)
      <tr>
        <td>{{ \Carbon\Carbon::parse($pmt->payment_date)->format('M j, Y') }}</td>
        <td>{{ $pmt->reference_number ?? '—' }}</td>
        <td>{{ $pmt->payment_method ?? '—' }}</td>
        <td class="right">₱{{ number_format($pmt->amount, 2) }}</td>
      </tr>
      @endforeach
    </tbody>
  </table>
  @endif

  {{-- Notes --}}
  @if($invoice->notes)
  <div class="section-title">Notes</div>
  <div style="border:1px solid #e8ecf0;padding:8px 10px;margin-bottom:14px;background:#fafbfc;font-size:9pt;margin-top:0;">{{ $invoice->notes }}</div>
  @endif

  <div class="footer">
    Printed {{ now()->format('F j, Y g:i A') }} · {{ $invoice->or_number ?? '#' . $invoice->id }} · Ogami ERP
  </div>

</div>
</body>
</html>
