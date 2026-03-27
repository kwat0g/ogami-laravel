<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Delivery Receipt — {{ $dr->dr_reference }}</title>
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

  table { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
  th { font-size: 7.5pt; font-weight: bold; text-align: left; padding: 4px 8px;
       background: #f0f4f9; color: #333; border-bottom: 1px solid #c9d8e8; }
  th.right, td.right { text-align: right; }
  th.center, td.center { text-align: center; }
  td { font-size: 8.5pt; padding: 4px 8px; border-bottom: 1px solid #e8ecf0; }
  tr:last-child td { border-bottom: none; }

  .badge { display: inline-block; padding: 1px 5px; border-radius: 3px; font-size: 7pt; font-weight: bold; }
  .badge-draft { background: #f0f0f0; color: #666; }
  .badge-confirmed { background: #d1fae5; color: #065f46; }
  .badge-delivered { background: #dbeafe; color: #1e40af; }
  .badge-inbound { background: #e0e7ff; color: #3730a3; }
  .badge-outbound { background: #fef3c7; color: #92400e; }

  .section-title { font-size: 8pt; font-weight: bold; text-transform: uppercase; letter-spacing: 0.8px;
                   color: #fff; background: #2d6a9f; padding: 3px 8px; margin-bottom: 0; }

  .signature-row { display: table; width: 100%; margin-top: 40px; }
  .signature-cell { display: table-cell; width: 33%; text-align: center; padding: 0 10px; }
  .signature-line { border-top: 1px solid #333; margin-top: 40px; padding-top: 4px; }
  .signature-label { font-size: 7pt; color: #666; text-transform: uppercase; letter-spacing: 0.3px; }
  .signature-name { font-size: 8pt; font-weight: bold; color: #111; }

  .footer { font-size: 7pt; color: #999; text-align: center; border-top: 1px solid #e8ecf0; padding-top: 8px; margin-top: 20px; }
</style>
</head>
<body>
<div class="page">
  {{-- Company Header --}}
  <div class="company-header">
    <div class="company-name">OGAMI MANUFACTURING CORPORATION</div>
    <div class="company-sub">{{ config('app.company_address', 'Philippines') }}</div>
    <div class="company-sub">TIN: {{ config('app.company_tin', '') }} | Tel: {{ config('app.company_phone', '') }}</div>
  </div>

  <div class="doc-title">Delivery Receipt</div>

  {{-- Header Info --}}
  <div class="two-col">
    <div class="col-left">
      <div class="info-label">DR Reference</div>
      <div class="info-value">{{ $dr->dr_reference }}</div>

      <div class="info-label" style="margin-top:6px">Direction</div>
      <div class="info-value">
        <span class="badge {{ $dr->direction === 'inbound' ? 'badge-inbound' : 'badge-outbound' }}">
          {{ strtoupper($dr->direction) }}
        </span>
      </div>

      @if($dr->vendor)
      <div class="info-label" style="margin-top:6px">Vendor</div>
      <div class="info-value">{{ $dr->vendor->name }}</div>
      @endif

      @if($dr->customer)
      <div class="info-label" style="margin-top:6px">Customer</div>
      <div class="info-value">{{ $dr->customer->name }}</div>
      @if($dr->customer->address)
      <div style="font-size:8pt;color:#555;">{{ $dr->customer->address }}</div>
      @endif
      @endif
    </div>

    <div class="col-right">
      <div class="info-label">Status</div>
      <div class="info-value">
        <span class="badge {{ $dr->status === 'confirmed' ? 'badge-confirmed' : ($dr->status === 'delivered' ? 'badge-delivered' : 'badge-draft') }}">
          {{ strtoupper(str_replace('_', ' ', $dr->status)) }}
        </span>
      </div>

      <div class="info-label" style="margin-top:6px">Receipt Date</div>
      <div class="info-value">{{ \Carbon\Carbon::parse($dr->receipt_date)->format('M d, Y') }}</div>

      @if($dr->delivery_note_number)
      <div class="info-label" style="margin-top:6px">Delivery Note #</div>
      <div class="info-value">{{ $dr->delivery_note_number }}</div>
      @endif

      @if($dr->purchaseOrder)
      <div class="info-label" style="margin-top:6px">PO Reference</div>
      <div class="info-value">{{ $dr->purchaseOrder->po_reference }}</div>
      @endif

      <div class="info-label" style="margin-top:6px">Printed On</div>
      <div class="info-value">{{ now()->format('M d, Y h:i A') }}</div>
    </div>
  </div>

  {{-- Line Items --}}
  <div class="section-title">Items</div>
  <table>
    <thead>
      <tr>
        <th style="width:5%">#</th>
        <th style="width:15%">Item Code</th>
        <th style="width:35%">Description</th>
        <th class="center" style="width:10%">UOM</th>
        <th class="right" style="width:12%">Qty</th>
        <th class="center" style="width:10%">Condition</th>
        <th style="width:13%">Remarks</th>
      </tr>
    </thead>
    <tbody>
      @forelse($dr->items as $i => $item)
      <tr>
        <td>{{ $i + 1 }}</td>
        <td>{{ $item->itemMaster?->item_code ?? '—' }}</td>
        <td>{{ $item->itemMaster?->name ?? $item->poItem?->item_description ?? '—' }}</td>
        <td class="center">{{ $item->unit_of_measure }}</td>
        <td class="right">{{ number_format((float) $item->quantity_received, 2) }}</td>
        <td class="center">
          <span class="badge {{ $item->condition === 'good' ? 'badge-confirmed' : 'badge-draft' }}">
            {{ strtoupper($item->condition ?? 'N/A') }}
          </span>
        </td>
        <td style="font-size:7.5pt;">{{ $item->remarks ?? '' }}</td>
      </tr>
      @empty
      <tr><td colspan="7" style="text-align:center;color:#999;">No items</td></tr>
      @endforelse
    </tbody>
  </table>

  {{-- Condition Notes --}}
  @if($dr->condition_notes)
  <div class="section-title">Condition Notes</div>
  <div style="padding:8px;font-size:8.5pt;border:1px solid #e8ecf0;margin-bottom:14px;">
    {{ $dr->condition_notes }}
  </div>
  @endif

  {{-- Signatures --}}
  <div class="signature-row">
    <div class="signature-cell">
      <div class="signature-line">
        <div class="signature-name">{{ $dr->receivedBy?->name ?? '________________' }}</div>
        <div class="signature-label">Received By</div>
      </div>
    </div>
    <div class="signature-cell">
      <div class="signature-line">
        <div class="signature-name">{{ $dr->confirmedBy?->name ?? '________________' }}</div>
        <div class="signature-label">Confirmed By</div>
      </div>
    </div>
    <div class="signature-cell">
      <div class="signature-line">
        <div class="signature-name">________________</div>
        <div class="signature-label">Customer Signature</div>
      </div>
    </div>
  </div>

  {{-- Footer --}}
  <div class="footer">
    Generated by Ogami ERP &mdash; {{ $dr->dr_reference }} &mdash; {{ now()->format('Y-m-d H:i:s') }}
  </div>
</div>
</body>
</html>
