<?php

declare(strict_types=1);

namespace App\Http\Controllers\Attendance;

use App\Domains\Attendance\Models\AttendanceLog;
use App\Domains\Attendance\Services\AttendanceImportService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Attendance\AttendanceImportRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile;

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
}
