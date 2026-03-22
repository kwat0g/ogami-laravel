<?php

declare(strict_types=1);

namespace App\Http\Controllers\Attendance;

use App\Domains\Attendance\Models\AttendanceLog;
use App\Domains\Attendance\Services\AttendanceImportService;
use App\Domains\HR\Models\Employee;
use App\Http\Controllers\Controller;
use App\Http\Requests\Attendance\AttendanceImportRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Export class for attendance template with pre-filled active employees.
 *
 * @implements FromCollection<int, array<string, mixed>>
 */
final class AttendanceTemplateExport implements FromCollection, WithHeadings, WithStyles, WithTitle
{
    /**
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    public function collection(): \Illuminate\Support\Collection
    {
        // Get all active employees (employment_status = active or on_leave)
        $employees = Employee::query()
            ->where('is_active', true)
            ->whereIn('employment_status', ['active', 'on_leave', 'suspended'])
            ->orderBy('employee_code')
            ->get(['employee_code', 'first_name', 'last_name']);

        // Pre-fill template rows with employee codes and names
        return $employees->map(fn (Employee $emp): array => [
            'employee_code' => $emp->employee_code,
            'employee_name' => trim($emp->first_name.' '.$emp->last_name),
            'work_date' => '',
            'time_in' => '',
            'time_out' => '',
            'notes' => '',
        ]);
    }

    /**
     * @return list<string>
     */
    public function headings(): array
    {
        return [
            'employee_code',
            'employee_name',
            'work_date',
            'time_in',
            'time_out',
            'notes',
        ];
    }

    public function title(): string
    {
        return 'Attendance Template';
    }

    public function styles(Worksheet $sheet): array
    {
        // Style the header row
        $sheet->getStyle('A1:F1')->getFont()->setBold(true);
        $sheet->getStyle('A1:F1')->getFill()
            ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()->setRGB('E2E8F0');

        // Auto-size columns
        foreach (range('A', 'F') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        return [];
    }
}

final class AttendanceImportController extends Controller
{
    public function __construct(
        private readonly AttendanceImportService $service,
    ) {}

    /**
     * POST /api/v1/attendance/import
     *
     * Accepts a CSV file, stores it temporarily, and dispatches
     * a Horizon batch to process each row asynchronously.
     */
    public function store(AttendanceImportRequest $request): JsonResponse
    {
        $this->authorize('import', AttendanceLog::class);

        /** @var UploadedFile $file */
        $file = $request->file('file');

        // Store in a temporary location within the default disk
        $storagePath = $file->store('attendance/imports');

        if ($storagePath === false) {
            return response()->json(['message' => 'Failed to store uploaded file.'], 500);
        }

        $result = $this->service->importFromStorage(
            $storagePath,
            (int) $request->user()->id,
        );

        return response()->json([
            'message' => 'Attendance import queued.',
            'batch_id' => $result['batch_id'],
            'total_rows' => $result['total_rows'],
            'dispatched' => $result['dispatched_rows'],
            'skipped' => $result['skipped_rows'],
            'errors' => $result['errors'],
        ], 202);
    }

    /**
     * GET /api/v1/attendance/template
     *
     * Downloads an Excel template with all active employees pre-filled.
     * Users can fill in work_date, time_in, time_out and upload via the import endpoint.
     */
    public function downloadTemplate(): BinaryFileResponse
    {
        $this->authorize('import', AttendanceLog::class);

        $filename = 'attendance_template_'.now()->format('Y-m-d').'.xlsx';

        return Excel::download(new AttendanceTemplateExport(), $filename);
    }
}
