<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Purchase Request — {{ $pr->pr_reference }}</title>
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: 'DejaVu Sans', sans-serif; font-size: 9pt; color: #1a1a1a; background: #fff; }
  .page { width: 100%; max-width: 720px; margin: 0 auto; padding: 24px; }

  /* Header */
  .company-header { text-align: center; border-bottom: 2px solid #1e3a5f; padding-bottom: 10px; margin-bottom: 14px; }
  .company-name { font-size: 15pt; font-weight: bold; color: #1e3a5f; letter-spacing: 0.5px; }
  .company-sub { font-size: 8pt; color: #555; margin-top: 2px; }
  .doc-title { font-size: 11pt; font-weight: bold; text-align: center; letter-spacing: 2px;
               text-transform: uppercase; color: #fff; background: #1e3a5f; padding: 5px 0; margin-bottom: 14px; }

  /* Meta grid */
  .meta-grid { display: table; width: 100%; margin-bottom: 14px; border: 1px solid #c9d8e8; }
  .meta-col { display: table-cell; vertical-align: top; width: 50%; padding: 10px 14px; }
  .meta-col:first-child { border-right: 1px solid #c9d8e8; }
  .meta-row { margin-bottom: 5px; }
  .meta-label { font-size: 7.5pt; color: #666; text-transform: uppercase; letter-spacing: 0.4px; }
  .meta-value { font-size: 9pt; font-weight: bold; color: #111; }

  /* Status badge */
  .badge { display: inline-block; padding: 1px 7px; border-radius: 3px; font-size: 8pt; font-weight: bold; }
  .badge-draft       { background: #f3f4f6; color: #374151; }
  .badge-submitted   { background: #dbeafe; color: #1d4ed8; }
  .badge-noted       { background: #e0e7ff; color: #4338ca; }
  .badge-checked     { background: #fef3c7; color: #b45309; }
  .badge-reviewed    { background: #fce7f3; color: #be185d; }
  .badge-budget_checked { background: #d1fae5; color: #065f46; }
  .badge-returned    { background: #fee2e2; color: #b91c1c; }
  .badge-approved    { background: #bbf7d0; color: #14532d; }
  .badge-rejected    { background: #fee2e2; color: #7f1d1d; }
  .badge-cancelled   { background: #f3f4f6; color: #6b7280; }
  .badge-converted_to_po { background: #bfdbfe; color: #1e40af; }

  /* Urgency badge */
  .urgency-normal   { background: #f3f4f6; color: #374151; }
  .urgency-urgent   { background: #fef3c7; color: #b45309; }
  .urgency-critical { background: #fee2e2; color: #b91c1c; }

  /* Section */
  .section-title { font-size: 8pt; font-weight: bold; text-transform: uppercase; letter-spacing: 0.8px;
                   color: #fff; background: #2d6a9f; padding: 3px 8px; margin-bottom: 0; }

  /* Items table */
  table { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
  th { font-size: 7.5pt; font-weight: bold; text-align: left; padding: 4px 8px;
       background: #f0f4f9; color: #333; border-bottom: 1px solid #c9d8e8; }
  th.right, td.right { text-align: right; }
  td { font-size: 8.5pt; padding: 4px 8px; border-bottom: 1px solid #e8ecf0; }
  tr:last-child td { border-bottom: none; }
  .total-row td { font-weight: bold; background: #f7f9fc; border-top: 1.5px solid #2d6a9f; }

  /* Justification */
  .text-block { font-size: 9pt; border: 1px solid #e8ecf0; padding: 8px 10px; margin-bottom: 14px;
                background: #fafbfc; color: #333; }

  /* Approval chain */
  .approval-grid { display: table; width: 100%; border: 1px solid #c9d8e8; margin-bottom: 14px; }
  .approval-cell { display: table-cell; padding: 8px 10px; border-right: 1px solid #c9d8e8; vertical-align: top; }
  .approval-cell:last-child { border-right: none; }
  .approval-label { font-size: 7.5pt; color: #888; text-transform: uppercase; letter-spacing: 0.4px; }
  .approval-name  { font-size: 8.5pt; font-weight: bold; color: #111; margin-top: 2px; }
  .approval-date  { font-size: 7.5pt; color: #666; }
  .approval-comments { font-size: 7.5pt; color: #555; font-style: italic; margin-top: 2px; }

  /* Signature section */
  .sig-row { display: table; width: 100%; margin-top: 30px; }
  .sig-cell { display: table-cell; text-align: center; width: 25%; }
  .sig-line { border-top: 1px solid #555; padding-top: 4px; margin: 0 20px; margin-top: 30px; }
  .sig-name { font-size: 8pt; font-weight: bold; }
  .sig-title { font-size: 7.5pt; color: #666; }

  /* Footer */
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

  <div class="doc-title">Purchase Request</div>

  {{-- Meta Information --}}
  <div class="meta-grid">
    <div class="meta-col">
      <div class="meta-row">
        <div class="meta-label">PR Reference</div>
        <div class="meta-value">{{ $pr->pr_reference }}</div>
      </div>
      <div class="meta-row">
        <div class="meta-label">Department</div>
        <div class="meta-value">{{ $pr->department?->name ?? '—' }}</div>
      </div>
      <div class="meta-row">
        <div class="meta-label">Requested By</div>
        <div class="meta-value">{{ $pr->requestedBy?->name ?? '—' }}</div>
      </div>
      <div class="meta-row">
        <div class="meta-label">Date Requested</div>
        <div class="meta-value">{{ $pr->created_at?->format('F j, Y') }}</div>
      </div>
    </div>
    <div class="meta-col">
      <div class="meta-row">
        <div class="meta-label">Status</div>
        <div class="meta-value">
          <span class="badge badge-{{ $pr->status }}">{{ strtoupper(str_replace('_', ' ', $pr->status)) }}</span>
        </div>
      </div>
      <div class="meta-row">
        <div class="meta-label">Urgency</div>
        <div class="meta-value">
          <span class="badge urgency-{{ $pr->urgency }}">{{ strtoupper($pr->urgency) }}</span>
        </div>
      </div>
      <div class="meta-row">
        <div class="meta-label">Total Estimated Cost</div>
        <div class="meta-value">₱{{ number_format($pr->total_estimated_cost, 2) }}</div>
      </div>
      @if($pr->return_reason)
      <div class="meta-row">
        <div class="meta-label">Return Reason</div>
        <div class="meta-value" style="color:#b91c1c;">{{ $pr->return_reason }}</div>
      </div>
      @endif
    </div>
  </div>

  {{-- Justification --}}
  <div class="section-title">Justification / Purpose</div>
  <div class="text-block" style="margin-top:0;">{{ $pr->justification }}</div>

  @if($pr->notes)
  <div class="section-title">Additional Notes</div>
  <div class="text-block" style="margin-top:0;">{{ $pr->notes }}</div>
  @endif

  {{-- Line Items --}}
  <div class="section-title">Requested Items</div>
  <table>
    <thead>
      <tr>
        <th style="width:5%">#</th>
        <th style="width:35%">Description</th>
        <th style="width:10%">UOM</th>
        <th class="right" style="width:12%">Qty</th>
        <th class="right" style="width:18%">Unit Cost (₱)</th>
        <th class="right" style="width:20%">Total (₱)</th>
      </tr>
    </thead>
    <tbody>
      @foreach($pr->items as $item)
      <tr>
        <td>{{ $loop->iteration }}</td>
        <td>
          {{ $item->item_description }}
          @if($item->specifications)
            <br><span style="font-size:7.5pt;color:#666">{{ $item->specifications }}</span>
          @endif
        </td>
        <td>{{ $item->unit_of_measure }}</td>
        <td class="right">{{ number_format($item->quantity, 2) }}</td>
        <td class="right">{{ number_format($item->estimated_unit_cost, 2) }}</td>
        <td class="right">{{ number_format($item->quantity * $item->estimated_unit_cost, 2) }}</td>
      </tr>
      @endforeach
    </tbody>
    <tfoot>
      <tr class="total-row">
        <td colspan="5" class="right">TOTAL ESTIMATED COST</td>
        <td class="right">₱{{ number_format($pr->total_estimated_cost, 2) }}</td>
      </tr>
    </tfoot>
  </table>

  {{-- Approval Chain --}}
  <div class="section-title">Approval Chain</div>
  <div class="approval-grid" style="margin-top:0;">
    <div class="approval-cell">
      <div class="approval-label">Requested By</div>
      <div class="approval-name">{{ $pr->requestedBy?->name ?? '—' }}</div>
      <div class="approval-date">{{ $pr->created_at?->format('M j, Y') }}</div>
    </div>
    <div class="approval-cell">
      <div class="approval-label">Noted (Head)</div>
      <div class="approval-name">{{ $pr->notedBy?->name ?? '—' }}</div>
      <div class="approval-date">{{ $pr->noted_at?->format('M j, Y') ?? '—' }}</div>
      @if($pr->noted_comments)
        <div class="approval-comments">{{ $pr->noted_comments }}</div>
      @endif
    </div>
    <div class="approval-cell">
      <div class="approval-label">Checked (Manager)</div>
      <div class="approval-name">{{ $pr->checkedBy?->name ?? '—' }}</div>
      <div class="approval-date">{{ $pr->checked_at?->format('M j, Y') ?? '—' }}</div>
      @if($pr->checked_comments)
        <div class="approval-comments">{{ $pr->checked_comments }}</div>
      @endif
    </div>
    <div class="approval-cell">
      <div class="approval-label">Reviewed (Officer)</div>
      <div class="approval-name">{{ $pr->reviewedBy?->name ?? '—' }}</div>
      <div class="approval-date">{{ $pr->reviewed_at?->format('M j, Y') ?? '—' }}</div>
      @if($pr->reviewed_comments)
        <div class="approval-comments">{{ $pr->reviewed_comments }}</div>
      @endif
    </div>
  </div>

  <div class="approval-grid">
    <div class="approval-cell">
      <div class="approval-label">Budget Check (Acctg.)</div>
      <div class="approval-name">{{ $pr->budgetCheckedBy?->name ?? '—' }}</div>
      <div class="approval-date">{{ $pr->budget_checked_at?->format('M j, Y') ?? '—' }}</div>
      @if($pr->budget_checked_comments)
        <div class="approval-comments">{{ $pr->budget_checked_comments }}</div>
      @endif
    </div>
    <div class="approval-cell">
      <div class="approval-label">Approved (VP)</div>
      <div class="approval-name">{{ $pr->vpApprovedBy?->name ?? '—' }}</div>
      <div class="approval-date">{{ $pr->vp_approved_at?->format('M j, Y') ?? '—' }}</div>
      @if($pr->vp_comments)
        <div class="approval-comments">{{ $pr->vp_comments }}</div>
      @endif
    </div>
    <div class="approval-cell">
      <div class="approval-label">Rejected By</div>
      <div class="approval-name">{{ $pr->rejectedBy?->name ?? '—' }}</div>
      <div class="approval-date">{{ $pr->rejected_at?->format('M j, Y') ?? '—' }}</div>
      @if($pr->rejection_reason)
        <div class="approval-comments" style="color:#b91c1c">{{ $pr->rejection_reason }}</div>
      @endif
    </div>
    <div class="approval-cell">
      <div class="approval-label">Returned By</div>
      <div class="approval-name">{{ $pr->returnedBy?->name ?? '—' }}</div>
      <div class="approval-date">{{ $pr->returned_at?->format('M j, Y') ?? '—' }}</div>
      @if($pr->return_reason)
        <div class="approval-comments" style="color:#b91c1c">{{ $pr->return_reason }}</div>
      @endif
    </div>
  </div>

  {{-- Signature Lines --}}
  <div class="sig-row">
    <div class="sig-cell">
      <div class="sig-line">
        <div class="sig-name">{{ $pr->requestedBy?->name ?? '___________________' }}</div>
        <div class="sig-title">Requested By</div>
      </div>
    </div>
    <div class="sig-cell">
      <div class="sig-line">
        <div class="sig-name">{{ $pr->notedBy?->name ?? '___________________' }}</div>
        <div class="sig-title">Department Head</div>
      </div>
    </div>
    <div class="sig-cell">
      <div class="sig-line">
        <div class="sig-name">{{ $pr->reviewedBy?->name ?? '___________________' }}</div>
        <div class="sig-title">Reviewed By</div>
      </div>
    </div>
    <div class="sig-cell">
      <div class="sig-line">
        <div class="sig-name">{{ $pr->vpApprovedBy?->name ?? '___________________' }}</div>
        <div class="sig-title">Approved By (VP)</div>
      </div>
    </div>
  </div>

  <div class="footer">
    Printed {{ now()->format('F j, Y g:i A') }} · {{ $pr->pr_reference }} · Ogami ERP
  </div>

</div>
</body>
</html>
