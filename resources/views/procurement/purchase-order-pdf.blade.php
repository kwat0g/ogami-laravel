<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Purchase Order — {{ $po->po_reference }}</title>
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
  .badge-sent             { background: #dbeafe; color: #1d4ed8; }
  .badge-in_transit       { background: #fef3c7; color: #b45309; }
  .badge-partially_received { background: #e0e7ff; color: #4338ca; }
  .badge-fully_received   { background: #d1fae5; color: #065f46; }
  .badge-closed           { background: #f3f4f6; color: #6b7280; }
  .badge-cancelled        { background: #fee2e2; color: #7f1d1d; }

  .section-title { font-size: 8pt; font-weight: bold; text-transform: uppercase; letter-spacing: 0.8px;
                   color: #fff; background: #2d6a9f; padding: 3px 8px; margin-bottom: 0; }

  table { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
  th { font-size: 7.5pt; font-weight: bold; text-align: left; padding: 4px 8px;
       background: #f0f4f9; color: #333; border-bottom: 1px solid #c9d8e8; }
  th.right, td.right { text-align: right; }
  td { font-size: 8.5pt; padding: 4px 8px; border-bottom: 1px solid #e8ecf0; }
  tr:last-child td { border-bottom: none; }
  .total-row td { font-weight: bold; background: #f7f9fc; border-top: 1.5px solid #2d6a9f; }

  .vendor-box { border: 1px solid #c9d8e8; padding: 10px 14px; margin-bottom: 14px; }
  .vendor-name { font-size: 11pt; font-weight: bold; color: #1e3a5f; }
  .vendor-detail { font-size: 8pt; color: #555; margin-top: 2px; }

  .text-block { font-size: 9pt; border: 1px solid #e8ecf0; padding: 8px 10px; margin-bottom: 14px;
                background: #fafbfc; color: #333; }

  .terms-grid { display: table; width: 100%; border: 1px solid #c9d8e8; margin-bottom: 14px; }
  .terms-cell { display: table-cell; padding: 8px 12px; border-right: 1px solid #c9d8e8; vertical-align: top; }
  .terms-cell:last-child { border-right: none; }
  .terms-label { font-size: 7.5pt; color: #888; text-transform: uppercase; letter-spacing: 0.4px; }
  .terms-value { font-size: 9pt; font-weight: bold; color: #111; margin-top: 2px; }

  .sig-row { display: table; width: 100%; margin-top: 30px; }
  .sig-cell { display: table-cell; text-align: center; width: 33%; }
  .sig-line { border-top: 1px solid #555; padding-top: 4px; margin: 0 20px; margin-top: 30px; }
  .sig-name { font-size: 8pt; font-weight: bold; }
  .sig-title { font-size: 7.5pt; color: #666; }

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

  <div class="doc-title">Purchase Order</div>

  {{-- Meta Info --}}
  <div class="meta-grid">
    <div class="meta-col">
      <div class="meta-row">
        <div class="meta-label">PO Reference</div>
        <div class="meta-value">{{ $po->po_reference }}</div>
      </div>
      <div class="meta-row">
        <div class="meta-label">PO Date</div>
        <div class="meta-value">{{ \Carbon\Carbon::parse($po->po_date)->format('F j, Y') }}</div>
      </div>
      <div class="meta-row">
        <div class="meta-label">Delivery Date</div>
        <div class="meta-value">{{ \Carbon\Carbon::parse($po->delivery_date)->format('F j, Y') }}</div>
      </div>
      @if($po->purchaseRequest)
      <div class="meta-row">
        <div class="meta-label">From PR</div>
        <div class="meta-value">{{ $po->purchaseRequest->pr_reference }}</div>
      </div>
      @endif
    </div>
    <div class="meta-col">
      <div class="meta-row">
        <div class="meta-label">Status</div>
        <div class="meta-value">
          <span class="badge badge-{{ $po->status }}">{{ strtoupper(str_replace('_', ' ', $po->status)) }}</span>
        </div>
      </div>
      <div class="meta-row">
        <div class="meta-label">Payment Terms</div>
        <div class="meta-value">{{ $po->payment_terms ?? '—' }}</div>
      </div>
      <div class="meta-row">
        <div class="meta-label">Total PO Amount</div>
        <div class="meta-value" style="font-size:12pt;color:#1e3a5f;">₱{{ number_format($po->total_po_amount / 100, 2) }}</div>
      </div>
      <div class="meta-row">
        <div class="meta-label">Prepared By</div>
        <div class="meta-value">{{ $po->createdBy?->name ?? '—' }}</div>
      </div>
    </div>
  </div>

  {{-- Vendor --}}
  <div class="section-title">Vendor / Supplier</div>
  <div class="vendor-box" style="margin-top:0;">
    <div class="vendor-name">{{ $po->vendor?->name ?? '—' }}</div>
    @if($po->vendor?->address)
      <div class="vendor-detail">{{ $po->vendor->address }}</div>
    @endif
    @if($po->vendor?->contact_person)
      <div class="vendor-detail">Contact: {{ $po->vendor->contact_person }}</div>
    @endif
    @if($po->vendor?->email)
      <div class="vendor-detail">Email: {{ $po->vendor->email }}</div>
    @endif
    @if($po->vendor?->phone)
      <div class="vendor-detail">Phone: {{ $po->vendor->phone }}</div>
    @endif
  </div>

  {{-- Delivery Address --}}
  @if($po->delivery_address)
  <div class="section-title">Deliver To</div>
  <div class="text-block" style="margin-top:0;">{{ $po->delivery_address }}</div>
  @endif

  {{-- Line Items --}}
  <div class="section-title">Ordered Items</div>
  <table>
    <thead>
      <tr>
        <th style="width:5%">#</th>
        <th style="width:40%">Description</th>
        <th style="width:10%">UOM</th>
        <th class="right" style="width:12%">Qty</th>
        <th class="right" style="width:12%">Rcvd</th>
        <th class="right" style="width:16%">Unit Cost (₱)</th>
        <th class="right" style="width:16%">Total (₱)</th>
      </tr>
    </thead>
    <tbody>
      @foreach($po->items as $item)
      <tr>
        <td>{{ $loop->iteration }}</td>
        <td>{{ $item->item_description }}</td>
        <td>{{ $item->unit_of_measure }}</td>
        <td class="right">{{ number_format($item->quantity_ordered, 2) }}</td>
        <td class="right">{{ number_format($item->quantity_received, 2) }}</td>
        <td class="right">{{ number_format($item->agreed_unit_cost / 100, 2) }}</td>
        <td class="right">{{ number_format($item->total_cost / 100, 2) }}</td>
      </tr>
      @endforeach
    </tbody>
    <tfoot>
      <tr class="total-row">
        <td colspan="6" class="right">TOTAL</td>
        <td class="right">₱{{ number_format($po->total_po_amount / 100, 2) }}</td>
      </tr>
    </tfoot>
  </table>

  {{-- Notes --}}
  @if($po->notes)
  <div class="section-title">Notes / Special Instructions</div>
  <div class="text-block" style="margin-top:0;">{{ $po->notes }}</div>
  @endif

  {{-- Signature Lines --}}
  <div class="sig-row">
    <div class="sig-cell">
      <div class="sig-line">
        <div class="sig-name">{{ $po->createdBy?->name ?? '___________________' }}</div>
        <div class="sig-title">Prepared By</div>
      </div>
    </div>
    <div class="sig-cell">
      <div class="sig-line">
        <div class="sig-name">___________________</div>
        <div class="sig-title">Approved By</div>
      </div>
    </div>
    <div class="sig-cell">
      <div class="sig-line">
        <div class="sig-name">___________________</div>
        <div class="sig-title">Received By (Vendor)</div>
      </div>
    </div>
  </div>

  <div class="footer">
    Printed {{ now()->format('F j, Y g:i A') }} · {{ $po->po_reference }} · Ogami ERP
  </div>

</div>
</body>
</html>
