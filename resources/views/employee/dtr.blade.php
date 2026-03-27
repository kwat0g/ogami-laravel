<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>DTR — {{ $employee->last_name }}, {{ $employee->first_name }}</title>
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: 'DejaVu Sans', sans-serif; font-size: 9pt; color: #1a1a1a; background: #fff; }
  .page { width: 100%; max-width: 720px; margin: 0 auto; padding: 24px; }

  .company-header { text-align: center; border-bottom: 2px solid #1e3a5f; padding-bottom: 10px; margin-bottom: 14px; }
  .company-name { font-size: 15pt; font-weight: bold; color: #1e3a5f; letter-spacing: 0.5px; }
  .company-sub { font-size: 8pt; color: #555; margin-top: 2px; }
  .doc-title { font-size: 11pt; font-weight: bold; text-align: center; letter-spacing: 2px;
               text-transform: uppercase; color: #fff; background: #1e3a5f; padding: 5px 0; margin-bottom: 14px; }

  .info-grid { display: table; width: 100%; margin-bottom: 14px; }
  .info-col { display: table-cell; vertical-align: top; width: 50%; }
  .info-row { margin-bottom: 4px; }
  .info-label { font-size: 7.5pt; color: #666; text-transform: uppercase; letter-spacing: 0.4px; }
  .info-value { font-size: 9pt; font-weight: bold; color: #111; }

  table { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
  th { font-size: 7.5pt; font-weight: bold; text-align: center; padding: 4px 6px;
       background: #f0f4f9; color: #333; border: 1px solid #c9d8e8; }
  td { font-size: 8pt; padding: 3px 6px; border: 1px solid #e8ecf0; text-align: center; }
  tr:nth-child(even) { background: #fafbfc; }

  .absent { background: #fee2e2; }
  .late { color: #b91c1c; font-weight: bold; }
  .weekend { background: #f3f4f6; color: #9ca3af; }

  .summary-grid { display: table; width: 100%; border: 1px solid #c9d8e8; border-radius: 3px; margin-bottom: 14px; }
  .summary-cell { display: table-cell; text-align: center; padding: 7px 6px; border-right: 1px solid #c9d8e8; }
  .summary-cell:last-child { border-right: none; }
  .summary-label { font-size: 7pt; color: #666; text-transform: uppercase; }
  .summary-value { font-size: 10pt; font-weight: bold; color: #1e3a5f; }

  .signature-row { display: table; width: 100%; margin-top: 30px; }
  .signature-cell { display: table-cell; text-align: center; width: 33%; padding-top: 24px; border-top: 1px solid #555; }
  .signature-label { font-size: 7.5pt; color: #666; }

  .footer { margin-top: 18px; font-size: 7.5pt; color: #888; text-align: center; }
</style>
</head>
<body>
<div class="page">

  <div class="company-header">
    <div class="company-name">{{ $settings['company_name'] ?? 'Ogami Manufacturing Philippines Corp.' }}</div>
    <div class="company-sub">{{ $settings['company_address'] ?? '' }}</div>
  </div>

  <div class="doc-title">Daily Time Record</div>

  <div class="info-grid">
    <div class="info-col">
      <div class="info-row">
        <div class="info-label">Employee</div>
        <div class="info-value">{{ $employee->last_name }}, {{ $employee->first_name }} {{ $employee->middle_name ?? '' }}</div>
      </div>
      <div class="info-row">
        <div class="info-label">Employee Code</div>
        <div class="info-value">{{ $employee->employee_code }}</div>
      </div>
      <div class="info-row">
        <div class="info-label">Department</div>
        <div class="info-value">{{ $employee->department?->name ?? '—' }}</div>
      </div>
    </div>
    <div class="info-col">
      <div class="info-row">
        <div class="info-label">Period</div>
        <div class="info-value">{{ $periodLabel }}</div>
      </div>
      <div class="info-row">
        <div class="info-label">Position</div>
        <div class="info-value">{{ $employee->position?->title ?? $employee->position?->name ?? '—' }}</div>
      </div>
    </div>
  </div>

  {{-- Summary --}}
  <div class="summary-grid">
    <div class="summary-cell">
      <div class="summary-label">Days Worked</div>
      <div class="summary-value">{{ $summary['days_worked'] }}</div>
    </div>
    <div class="summary-cell">
      <div class="summary-label">Days Absent</div>
      <div class="summary-value" style="{{ $summary['days_absent'] > 0 ? 'color: #b91c1c;' : '' }}">{{ $summary['days_absent'] }}</div>
    </div>
    <div class="summary-cell">
      <div class="summary-label">Late (min)</div>
      <div class="summary-value">{{ $summary['total_late_minutes'] }}</div>
    </div>
    <div class="summary-cell">
      <div class="summary-label">Undertime (min)</div>
      <div class="summary-value">{{ $summary['total_undertime_minutes'] }}</div>
    </div>
    <div class="summary-cell">
      <div class="summary-label">OT (min)</div>
      <div class="summary-value">{{ $summary['total_overtime_minutes'] }}</div>
    </div>
  </div>

  {{-- Daily entries --}}
  <table>
    <thead>
      <tr>
        <th>Date</th>
        <th>Day</th>
        <th>Time In</th>
        <th>Time Out</th>
        <th>Late (min)</th>
        <th>UT (min)</th>
        <th>OT (min)</th>
        <th>Remarks</th>
      </tr>
    </thead>
    <tbody>
      @foreach($entries as $entry)
      <tr class="{{ $entry['is_absent'] ? 'absent' : '' }} {{ $entry['is_weekend'] ? 'weekend' : '' }}">
        <td>{{ $entry['date'] }}</td>
        <td>{{ $entry['day_name'] }}</td>
        <td>{{ $entry['time_in'] ?? '—' }}</td>
        <td>{{ $entry['time_out'] ?? '—' }}</td>
        <td class="{{ $entry['late_minutes'] > 0 ? 'late' : '' }}">{{ $entry['late_minutes'] ?: '' }}</td>
        <td>{{ $entry['undertime_minutes'] ?: '' }}</td>
        <td>{{ $entry['overtime_minutes'] ?: '' }}</td>
        <td style="font-size: 7pt; text-align: left;">{{ $entry['remarks'] ?? '' }}</td>
      </tr>
      @endforeach
    </tbody>
  </table>

  <div class="signature-row">
    <div class="signature-cell">
      <div class="signature-label">Employee Signature</div>
    </div>
    <div class="signature-cell">
      <div class="signature-label">Supervisor</div>
    </div>
    <div class="signature-cell">
      <div class="signature-label">HR Officer</div>
    </div>
  </div>

  <div class="footer">
    Generated on {{ now()->format('F d, Y h:i A') }} — This is a system-generated document.
  </div>

</div>
</body>
</html>
