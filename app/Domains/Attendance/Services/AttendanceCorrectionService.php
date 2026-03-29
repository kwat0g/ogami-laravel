<?php

declare(strict_types=1);

namespace App\Domains\Attendance\Services;

use App\Domains\Attendance\Enums\AttendanceStatus;
use App\Domains\Attendance\Enums\CorrectionRequestStatus;
use App\Domains\Attendance\Models\AttendanceCorrectionRequest;
use App\Domains\Attendance\Models\AttendanceLog;
use App\Domains\Attendance\StateMachines\CorrectionRequestStateMachine;
use App\Domains\HR\Models\Employee;
use App\Models\User;
use App\Shared\Contracts\ServiceContract;
use App\Shared\Exceptions\DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Handles the attendance correction request workflow:
 * employee submits → HR reviews → approved corrections applied.
 */
final class AttendanceCorrectionService implements ServiceContract
{
    public function __construct(
        private readonly CorrectionRequestStateMachine $stateMachine,
        private readonly TimeComputationService $timeComputation,
    ) {}

    /**
     * Create a correction request (status = draft).
     */
    public function create(Employee $employee, array $data): AttendanceCorrectionRequest
    {
        $log = AttendanceLog::where('employee_id', $employee->id)
            ->findOrFail($data['attendance_log_id']);

        return AttendanceCorrectionRequest::create([
            'attendance_log_id' => $log->id,
            'employee_id' => $employee->id,
            'correction_type' => $data['correction_type'],
            'requested_time_in' => $data['requested_time_in'] ?? null,
            'requested_time_out' => $data['requested_time_out'] ?? null,
            'requested_remarks' => $data['requested_remarks'] ?? null,
            'reason' => $data['reason'],
            'supporting_document_path' => $data['supporting_document_path'] ?? null,
            'status' => CorrectionRequestStatus::Draft->value,
        ]);
    }

    /**
     * Submit a draft correction request for review.
     */
    public function submit(AttendanceCorrectionRequest $request): AttendanceCorrectionRequest
    {
        $this->stateMachine->transition($request, 'submitted');

        return $request->fresh();
    }

    /**
     * HR approves a correction request and applies changes to the attendance log.
     */
    public function approve(
        AttendanceCorrectionRequest $request,
        User $reviewer,
        ?string $remarks = null,
    ): AttendanceCorrectionRequest {
        return DB::transaction(function () use ($request, $reviewer, $remarks) {
            $this->stateMachine->transition($request, 'approved');

            $request->update([
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => now(),
                'review_remarks' => $remarks,
            ]);

            // Apply the correction to the attendance log
            $log = $request->attendanceLog()->lockForUpdate()->firstOrFail();

            $updates = [
                'correction_note' => $request->reason,
                'corrected_by' => $reviewer->id,
                'corrected_at' => now(),
            ];

            if ($request->requested_time_in) {
                $updates['time_in'] = $request->requested_time_in;
            }
            if ($request->requested_time_out) {
                $updates['time_out'] = $request->requested_time_out;
            }
            if ($request->requested_remarks) {
                $updates['remarks'] = $request->requested_remarks;
            }

            $log->update($updates);

            // Recompute derived fields if times changed
            if ($request->requested_time_in || $request->requested_time_out) {
                $refreshed = $log->fresh();
                if ($refreshed->time_in && $refreshed->time_out) {
                    $computed = $this->timeComputation->compute($refreshed);
                    // Override status to 'corrected' since this was a manual correction
                    $computed['attendance_status'] = AttendanceStatus::Corrected->value;
                    $refreshed->update($computed);
                }
            } else {
                // Status-only correction
                $log->update(['attendance_status' => AttendanceStatus::Corrected->value]);
            }

            return $request->fresh();
        });
    }

    /**
     * HR rejects a correction request.
     */
    public function reject(
        AttendanceCorrectionRequest $request,
        User $reviewer,
        string $remarks,
    ): AttendanceCorrectionRequest {
        if (! $remarks) {
            throw new DomainException(
                'Rejection remarks are required.',
                'REJECTION_REMARKS_REQUIRED',
                422,
            );
        }

        $this->stateMachine->transition($request, 'rejected');

        $request->update([
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => now(),
            'review_remarks' => $remarks,
        ]);

        return $request->fresh();
    }
}
