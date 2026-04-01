<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Delivery Receipt - {{ $receipt->dr_reference }}</title>
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: 'DejaVu Sans', sans-serif; font-size: 9pt; color: #111827; background: #ffffff; }
  .page { width: 100%; max-width: 760px; margin: 0 auto; padding: 20px; }

  .header { border: 1px solid #c7d2fe; background: #eef2ff; padding: 14px 16px; border-radius: 8px; margin-bottom: 12px; }
  .header-title { font-size: 17pt; font-weight: bold; color: #1e3a8a; letter-spacing: 0.6px; }
  .header-sub { font-size: 8pt; color: #4b5563; margin-top: 2px; }

  .doc-band { margin: 10px 0 12px; border-radius: 6px; background: #1e3a8a; color: #ffffff; text-transform: uppercase; letter-spacing: 1.4px; font-weight: bold; text-align: center; padding: 6px 0; }

  .meta-grid { display: table; width: 100%; margin-bottom: 14px; border: 1px solid #dbeafe; border-radius: 8px; overflow: hidden; }
  .meta-col { display: table-cell; width: 50%; vertical-align: top; padding: 10px 12px; }
  .meta-col:first-child { border-right: 1px solid #dbeafe; }
  .meta-row { margin-bottom: 5px; }
  .meta-row:last-child { margin-bottom: 0; }
  .meta-label { font-size: 7.5pt; text-transform: uppercase; letter-spacing: 0.4px; color: #6b7280; }
  .meta-value { font-size: 9pt; font-weight: bold; color: #111827; }

  .status-pill { display: inline-block; border-radius: 999px; padding: 2px 10px; font-size: 8pt; font-weight: bold; }
  .status-dispatched { background: #dbeafe; color: #1d4ed8; }
  .status-partially_delivered { background: #fef3c7; color: #b45309; }
  .status-delivered { background: #d1fae5; color: #065f46; }

  .section-title { margin-top: 12px; margin-bottom: 0; background: #1f2937; color: #ffffff; padding: 5px 10px; font-size: 8pt; text-transform: uppercase; letter-spacing: 0.8px; }

  .panel { border: 1px solid #e5e7eb; padding: 10px 12px; margin-bottom: 10px; }
  .panel-grid { display: table; width: 100%; }
  .panel-cell { display: table-cell; width: 50%; vertical-align: top; padding-right: 8px; }
  .panel-cell:last-child { padding-right: 0; }
  .line-label { font-size: 7.5pt; text-transform: uppercase; color: #6b7280; }
  .line-value { font-size: 9pt; font-weight: bold; color: #111827; margin-bottom: 5px; }

  table { width: 100%; border-collapse: collapse; margin-bottom: 12px; border: 1px solid #e5e7eb; }
  thead th { background: #f9fafb; color: #374151; text-transform: uppercase; letter-spacing: 0.35px; font-size: 7.5pt; text-align: left; padding: 7px 8px; border-bottom: 1px solid #e5e7eb; }
  tbody td { font-size: 8.5pt; color: #111827; padding: 7px 8px; border-bottom: 1px solid #f3f4f6; }
  tbody tr:last-child td { border-bottom: none; }
  .right { text-align: right; }
  .mono { font-family: 'DejaVu Sans Mono', monospace; }

  .signatures { display: table; width: 100%; margin-top: 28px; }
  .sig-cell { display: table-cell; width: 33%; text-align: center; padding: 0 10px; }
  .sig-line { border-top: 1px solid #6b7280; margin-top: 22px; padding-top: 4px; }
  .sig-name { font-size: 8pt; font-weight: bold; }
  .sig-role { font-size: 7.5pt; color: #6b7280; }

  .footer { margin-top: 14px; border-top: 1px solid #e5e7eb; padding-top: 7px; font-size: 7.5pt; color: #9ca3af; text-align: center; }

  @page { margin: 12mm; }
</style>
</head>
<body>
<div class="page">

  <div class="header">
    <div class="header-title">{{ $settings['company_name'] ?? 'Ogami Manufacturing Corp.' }}</div>
    <div class="header-sub">{{ $settings['company_address'] ?? '' }}</div>
    @if(!empty($settings['company_tin']) || !empty($settings['company_phone']))
      <div class="header-sub">
        @if(!empty($settings['company_tin']))TIN: {{ $settings['company_tin'] }}@endif
        @if(!empty($settings['company_tin']) && !empty($settings['company_phone'])) | @endif
        @if(!empty($settings['company_phone']))Phone: {{ $settings['company_phone'] }}@endif
      </div>
    @endif
  </div>

  <div class="doc-band">Delivery Receipt</div>

  <div class="meta-grid">
    <div class="meta-col">
      <div class="meta-row">
        <div class="meta-label">DR Reference</div>
        <div class="meta-value mono">{{ $receipt->dr_reference }}</div>
      </div>
      <div class="meta-row">
        <div class="meta-label">Receipt Date</div>
        <div class="meta-value">{{ $receipt->receipt_date ? \Carbon\Carbon::parse((string) $receipt->receipt_date)->format('F j, Y') : '-' }}</div>
      </div>
      <div class="meta-row">
        <div class="meta-label">Direction</div>
        <div class="meta-value">{{ strtoupper($receipt->direction) }}</div>
      </div>
      <div class="meta-row">
        <div class="meta-label">Handled By</div>
        <div class="meta-value">{{ $receipt->receivedBy?->name ?? '-' }}</div>
      </div>
    </div>
    <div class="meta-col">
      <div class="meta-row">
        <div class="meta-label">Status</div>
        <div class="meta-value">
          <span class="status-pill status-{{ $receipt->status }}">{{ strtoupper(str_replace('_', ' ', $receipt->status)) }}</span>
        </div>
      </div>
      <div class="meta-row">
        <div class="meta-label">Shipment Tracking</div>
        <div class="meta-value mono">{{ $shipment?->tracking_number ?? '-' }}</div>
      </div>
      <div class="meta-row">
        <div class="meta-label">Carrier</div>
        <div class="meta-value">{{ $shipment?->carrier ?? 'Company Fleet' }}</div>
      </div>
      <div class="meta-row">
        <div class="meta-label">Driver / Vehicle</div>
        <div class="meta-value">{{ $receipt->driver_name ?? '-' }} @if($receipt->vehicle?->plate_number) / {{ $receipt->vehicle->plate_number }} @endif</div>
      </div>
    </div>
  </div>

  <div class="section-title">Client Information</div>
  <div class="panel">
    <div class="panel-grid">
      <div class="panel-cell">
        <div class="line-label">Client Name</div>
        <div class="line-value">{{ $receipt->customer?->name ?? '-' }}</div>

        <div class="line-label">Contact Person</div>
        <div class="line-value">{{ $receipt->customer?->contact_person ?? '-' }}</div>

        <div class="line-label">Email</div>
        <div class="line-value">{{ $receipt->customer?->email ?? '-' }}</div>
      </div>
      <div class="panel-cell">
        <div class="line-label">Phone Number</div>
        <div class="line-value">{{ $receipt->customer?->phone ?? '-' }}</div>

        <div class="line-label">Delivery Address</div>
        <div class="line-value">{{ $receipt->deliverySchedule?->delivery_address ?? $receipt->customer?->address ?? '-' }}</div>

        <div class="line-label">Billing Address</div>
        <div class="line-value">{{ $receipt->customer?->billing_address ?? '-' }}</div>
      </div>
    </div>
  </div>

  <div class="section-title">Order Details</div>
  <div class="panel">
    <div class="panel-grid">
      <div class="panel-cell">
        <div class="line-label">Sales Order</div>
        <div class="line-value mono">{{ $receipt->salesOrder?->order_number ?? $receipt->salesOrder?->so_reference ?? '-' }}</div>

        <div class="line-label">Client Order</div>
        <div class="line-value mono">{{ $receipt->deliverySchedule?->clientOrder?->order_reference ?? '-' }}</div>
      </div>
      <div class="panel-cell">
        <div class="line-label">Delivery Schedule</div>
        <div class="line-value mono">{{ $receipt->deliverySchedule?->ds_reference ?? '-' }}</div>

        <div class="line-label">Target Delivery Date</div>
        <div class="line-value">{{ $receipt->deliverySchedule?->target_delivery_date ? \Carbon\Carbon::parse((string) $receipt->deliverySchedule->target_delivery_date)->format('F j, Y') : '-' }}</div>
      </div>
    </div>
  </div>

  <div class="section-title">Delivered Items</div>
  <table>
    <thead>
      <tr>
        <th style="width:6%">#</th>
        <th style="width:40%">Item Description</th>
        <th style="width:14%" class="right">Qty Expected</th>
        <th style="width:14%" class="right">Qty Received</th>
        <th style="width:10%">UOM</th>
        <th style="width:16%">Lot/Batch</th>
      </tr>
    </thead>
    <tbody>
      @forelse($receipt->items as $item)
        <tr>
          <td>{{ $loop->iteration }}</td>
          <td>{{ $item->itemMaster?->name ?? ('Item #'.$item->item_master_id) }}</td>
          <td class="right">{{ number_format((float) $item->quantity_expected, 2) }}</td>
          <td class="right">{{ number_format((float) $item->quantity_received, 2) }}</td>
          <td>{{ $item->unit_of_measure ?? '-' }}</td>
          <td class="mono">{{ $item->lot_batch_number ?? '-' }}</td>
        </tr>
      @empty
        <tr>
          <td colspan="6" style="text-align:center;color:#9ca3af;">No line items recorded.</td>
        </tr>
      @endforelse
    </tbody>
  </table>

  @if(!empty($receipt->remarks))
    <div class="section-title">Remarks</div>
    <div class="panel">{{ $receipt->remarks }}</div>
  @endif

  <div class="signatures">
    <div class="sig-cell">
      <div class="sig-line">
        <div class="sig-name">{{ $receipt->receivedBy?->name ?? '________________' }}</div>
        <div class="sig-role">Prepared By</div>
      </div>
    </div>
    <div class="sig-cell">
      <div class="sig-line">
        <div class="sig-name">________________</div>
        <div class="sig-role">Transport / Driver</div>
      </div>
    </div>
    <div class="sig-cell">
      <div class="sig-line">
        <div class="sig-name">________________</div>
        <div class="sig-role">Client Receiver</div>
      </div>
    </div>
  </div>

  <div class="footer">
    Generated on {{ now()->format('F j, Y g:i A') }} | {{ $receipt->dr_reference }} | Ogami ERP Delivery
  </div>

</div>
</body>
</html>
