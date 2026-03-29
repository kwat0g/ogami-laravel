<?php

declare(strict_types=1);

namespace App\Http\Controllers\Attendance;

use App\Domains\Attendance\Models\AttendanceCorrectionRequest;
use App\Domains\Attendance\Services\AttendanceCorrectionService;
use App\Http\Controllers\Controller;
use App\Http\Resources\Attendance\CorrectionRequestResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AttendanceCorrectionController extends Controller
{
    public function __construct(
        private readonly AttendanceCorrectionService $service,
    ) {}

    /**
     * List correction requests (employee sees own, HR sees all).
     */
    public function index(Request $request): JsonResponse
    {
        $query = AttendanceCorrectionRequest::with('attendanceLog', 'employee', 'reviewer');

        // Non-HR users only see their own
        if (! $request->user()->can('attendance.corrections.review')) {
            $employee = $request->user()->employee;
            abort_unless($employee, 403);
            $query->where('employee_id', $employee->id);
        }

        $requests = $query
            ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
            ->orderByDesc('created_at')
            ->paginate((int) $request->query('per_page', '15'));

        return CorrectionRequestResource::collection($requests)->response();
    }

    /**
     * Create a new correction request (draft).
     */
    public function store(Request $request): JsonResponse
    {
        $employee = $request->user()->employee;
        abort_unless($employee, 403, 'No linked employee record found.');

        $validated = $request->validate([
            'attendance_log_id' => 'required|integer|exists:attendance_logs,id',
            'correction_type' => 'required|in:time_in,time_out,status,both',
            'requested_time_in' => 'nullable|date|required_if:correction_type,time_in,both',
            'requested_time_out' => 'nullable|date|required_if:correction_type,time_out,both',
            'requested_remarks' => 'nullable|string|max:500',
            'reason' => 'required|string|min:10|max:1000',
            'supporting_document_path' => 'nullable|string|max:500',
        ]);

        $correctionRequest = $this->service->create($employee, $validated);

        return (new CorrectionRequestResource($correctionRequest->load('attendanceLog', 'employee')))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Show a single correction request.
     */
    public function show(string $ulid): CorrectionRequestResource
    {
        $request = AttendanceCorrectionRequest::where('ulid', $ulid)
            ->with('attendanceLog', 'employee', 'reviewer')
            ->firstOrFail();

        return new CorrectionRequestResource($request);
    }

    /**
     * Submit a draft correction request for HR review.
     */
    public function submit(string $ulid): JsonResponse
    {
        $request = AttendanceCorrectionRequest::where('ulid', $ulid)->firstOrFail();

        $updated = $this->service->submit($request);

        return (new CorrectionRequestResource($updated->load('attendanceLog', 'employee')))
            ->response();
    }

    /**
     * HR approves a correction request.
     */
    public function approve(Request $httpRequest, string $ulid): JsonResponse
    {
        abort_unless($httpRequest->user()->can('attendance.corrections.review'), 403);

        $request = AttendanceCorrectionRequest::where('ulid', $ulid)->firstOrFail();

        $remarks = $httpRequest->input('remarks');

        $updated = $this->service->approve($request, $httpRequest->user(), $remarks);

        return (new CorrectionRequestResource($updated->load('attendanceLog', 'employee', 'reviewer')))
            ->response();
    }

    /**
     * HR rejects a correction request.
     */
    public function reject(Request $httpRequest, string $ulid): JsonResponse
    {
        abort_unless($httpRequest->user()->can('attendance.corrections.review'), 403);

        $httpRequest->validate([
            'remarks' => 'required|string|min:5|max:1000',
        ]);

        $request = AttendanceCorrectionRequest::where('ulid', $ulid)->firstOrFail();

        $updated = $this->service->reject(
            $request,
            $httpRequest->user(),
            $httpRequest->input('remarks'),
        );

        return (new CorrectionRequestResource($updated->load('attendanceLog', 'employee', 'reviewer')))
            ->response();
    }
}
