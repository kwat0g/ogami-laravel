<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Invoice — {{ $invoice->invoice_number ?? '#' . $invoice->id }}</title>
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
  .col-left  { display: table-cell; vertical-align: top; width: 60%; padding-right: 20px; }
  .col-right { display: table-cell; vertical-align: top; width: 40%; text-align: right; }

  .client-label { font-size: 7.5pt; color: #888; text-transform: uppercase; letter-spacing: 0.4px; margin-bottom: 3px; }
  .client-name  { font-size: 12pt; font-weight: bold; color: #1e3a5f; }
  .client-detail { font-size: 8pt; color: #555; margin-top: 2px; }

  .inv-ref   { font-size: 14pt; font-weight: bold; color: #1e3a5f; }
  .inv-meta  { font-size: 8pt; color: #555; margin-top: 3px; }
  .inv-meta strong { color: #111; }

  .badge { display: inline-block; padding: 1px 7px; border-radius: 3px; font-size: 8pt; font-weight: bold; }
  .badge-draft      { background: #f3f4f6; color: #374151; }
  .badge-approved   { background: #d1fae5; color: #065f46; }
  .badge-partially_paid { background: #fde68a; color: #92400e; }
  .badge-paid       { background: #6ee7b7; color: #064e3b; }
  .badge-cancelled  { background: #fee2e2; color: #7f1d1d; }
  .badge-written_off { background: #f3f4f6; color: #6b7280; }

  .section-title { font-size: 8pt; font-weight: bold; text-transform: uppercase; letter-spacing: 0.8px;
                   color: #fff; background: #2d6a9f; padding: 3px 8px; margin-bottom: 0; }

  table { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
  th { font-size: 7.5pt; font-weight: bold; text-align: left; padding: 4px 8px;
       background: #f0f4f9; color: #333; border-bottom: 1px solid #c9d8e8; }
  th.right, td.right { text-align: right; }
  td { font-size: 8.5pt; padding: 4px 8px; border-bottom: 1px solid #e8ecf0; }
  tr:last-child td { border-bottom: none; }

  .totals-section { width: 280px; margin-left: auto; margin-bottom: 14px; border: 1px solid #c9d8e8; }
  .total-row { display: table; width: 100%; border-bottom: 1px solid #e8ecf0; }
  .total-row:last-child { border-bottom: none; background: #e8f0fa; }
  .total-label { display: table-cell; padding: 5px 12px; font-size: 8.5pt; color: #555; }
  .total-value { display: table-cell; padding: 5px 12px; font-size: 8.5pt; font-weight: bold; text-align: right; color: #111; }
  .grand-label { font-size: 10pt; font-weight: bold; color: #1e3a5f; }
  .grand-value { font-size: 10pt; font-weight: bold; color: #1e3a5f; }
  .due-value   { font-size: 10pt; font-weight: bold; color: #b91c1c; }

  .note-box { font-size: 8.5pt; border: 1px solid #e8ecf0; padding: 8px 10px; margin-bottom: 14px;
              background: #fafbfc; color: #555; }

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
    @if(!empty($settings['company_tin']) || !empty($settings['company_phone']))
    <div class="company-sub">
      @if(!empty($settings['company_tin']))TIN: {{ $settings['company_tin'] }}@endif
      @if(!empty($settings['company_tin']) && !empty($settings['company_phone'])) | @endif
      @if(!empty($settings['company_phone']))Tel: {{ $settings['company_phone'] }}@endif
    </div>
    @endif
  </div>

  <div class="doc-title">Sales Invoice</div>

  {{-- Bill To + Invoice Reference --}}
  <div class="two-col" style="border:1px solid #c9d8e8;margin-bottom:14px;">
    <div class="col-left" style="padding:12px 14px;border-right:1px solid #c9d8e8;">
      <div class="client-label">Bill To</div>
      <div class="client-name">{{ $invoice->customer?->company_name ?? $invoice->customer?->name ?? '—' }}</div>
      @if($invoice->customer?->contact_person)
        <div class="client-detail">Attn: {{ $invoice->customer->contact_person }}</div>
      @endif
      @if($invoice->customer?->address)
        <div class="client-detail">{{ $invoice->customer->address }}</div>
      @endif
      @if($invoice->customer?->email)
        <div class="client-detail">{{ $invoice->customer->email }}</div>
      @endif
      @if($invoice->customer?->tin)
        <div class="client-detail">TIN: {{ $invoice->customer->tin }}</div>
      @endif
    </div>
    <div class="col-right" style="padding:12px 14px;text-align:right;">
      <div class="client-label">Invoice No.</div>
      <div class="inv-ref">{{ $invoice->invoice_number ?? 'DRAFT' }}</div>
      <div class="inv-meta" style="margin-top:8px;">
        <span class="badge badge-{{ $invoice->status }}">{{ strtoupper(str_replace('_', ' ', $invoice->status)) }}</span>
      </div>
      <div class="inv-meta" style="margin-top:6px;">
        Invoice Date: <strong>{{ \Carbon\Carbon::parse($invoice->invoice_date)->format('M j, Y') }}</strong>
      </div>
      <div class="inv-meta">
        Due Date: <strong>{{ \Carbon\Carbon::parse($invoice->due_date)->format('M j, Y') }}</strong>
      </div>
      @if($invoice->payment_terms)
      <div class="inv-meta">Terms: <strong>{{ $invoice->payment_terms }}</strong></div>
      @endif
    </div>
  </div>

  {{-- Line Items --}}
    @php
    $linesWithAmounts = collect($pdfLines ?? []);
    @endphp

  <div class="section-title">Items / Services</div>
  <table>
    <thead>
      <tr>
        <th style="width:5%">#</th>
        <th style="width:42%">Description</th>
        <th style="width:10%">UOM</th>
        <th class="right" style="width:12%">Qty</th>
        <th class="right" style="width:15%">Unit Price (₱)</th>
        <th class="right" style="width:16%">Amount (₱)</th>
      </tr>
    </thead>
    <tbody>
      @if($linesWithAmounts->count() > 0)
        @foreach($linesWithAmounts as $item)
        <tr>
          <td>{{ $loop->iteration }}</td>
          <td>{{ $item->description ?? '—' }}</td>
          <td>{{ $item->uom ?? 'pcs' }}</td>
          <td class="right">{{ number_format((float) ($item->qty ?? 1), 2) }}</td>
          <td class="right">{{ number_format((float) ($item->unit_price ?? 0), 2) }}</td>
          <td class="right">{{ number_format((float) ($item->amount ?? 0), 2) }}</td>
        </tr>
        @endforeach
      @else
        <tr>
          <td colspan="6" style="text-align:center;color:#999;">No line items.</td>
        </tr>
      @endif
    </tbody>
  </table>

  {{-- Totals --}}
  <div class="totals-section">
    <div class="total-row">
      <div class="total-label">Subtotal</div>
      <div class="total-value">₱{{ number_format($invoice->subtotal ?? $invoice->total_amount, 2) }}</div>
    </div>
    @if(isset($invoice->vat_amount) && $invoice->vat_amount > 0)
    <div class="total-row">
      <div class="total-label">VAT (12%)</div>
      <div class="total-value">₱{{ number_format($invoice->vat_amount, 2) }}</div>
    </div>
    @endif
    @if(isset($invoice->discount_amount) && $invoice->discount_amount > 0)
    <div class="total-row">
      <div class="total-label">Discount</div>
      <div class="total-value" style="color:#b91c1c;">— ₱{{ number_format($invoice->discount_amount, 2) }}</div>
    </div>
    @endif
    <div class="total-row">
      <div class="total-label grand-label">TOTAL AMOUNT</div>
      <div class="total-value grand-value">₱{{ number_format($invoice->total_amount, 2) }}</div>
    </div>
    @if(isset($invoice->amount_paid) && $invoice->amount_paid > 0)
    <div class="total-row">
      <div class="total-label">Amount Paid</div>
      <div class="total-value">₱{{ number_format($invoice->amount_paid, 2) }}</div>
    </div>
    <div class="total-row">
      <div class="total-label grand-label">BALANCE DUE</div>
      <div class="total-value due-value">₱{{ number_format($invoice->balance_due, 2) }}</div>
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
        <th>Reference / OR#</th>
        <th>Method</th>
        <th class="right">Amount</th>
      </tr>
    </thead>
    <tbody>
      @foreach($invoice->payments as $pmt)
      <tr>
        <td>{{ \Carbon\Carbon::parse($pmt->payment_date)->format('M j, Y') }}</td>
        <td>{{ $pmt->reference_number ?? $pmt->or_number ?? '—' }}</td>
        <td>{{ $pmt->payment_method ?? '—' }}</td>
        <td class="right">₱{{ number_format($pmt->amount, 2) }}</td>
      </tr>
      @endforeach
    </tbody>
  </table>
  @endif

  {{-- Notes --}}
  @if($invoice->notes)
  <div class="note-box">{{ $invoice->notes }}</div>
  @endif

  <div class="footer">
    This invoice was generated by Ogami ERP · Printed {{ now()->format('F j, Y g:i A') }}
  </div>

</div>
</body>
</html>
