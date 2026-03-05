<?php

declare(strict_types=1);

namespace App\Http\Controllers\Attendance;

use App\Domains\Attendance\Models\AttendanceLog;
use App\Domains\Attendance\Models\OvertimeRequest;
use App\Http\Controllers\Controller;
use App\Http\Resources\Attendance\AttendanceLogResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class AttendanceLogController extends Controller
{
    /**
     * GET /api/v1/attendance/logs
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', AttendanceLog::class);

        $logs = AttendanceLog::with('employee')
            ->when($request->query('employee_id'), fn ($q, $id) => $q->where('employee_id', $id))
            ->when($request->query('search'), function ($q, $search) {
                $q->whereHas('employee', fn ($e) => $e
                    ->where('last_name', 'ilike', "%{$search}%")
                    ->orWhere('first_name', 'ilike', "%{$search}%"));
            })
            ->when($request->query('date_from'), fn ($q, $d) => $q->where('work_date', '>=', $d))
            ->when($request->query('date_to'), fn ($q, $d) => $q->where('work_date', '<=', $d))
            ->when($request->query('import_batch_id'), fn ($q, $b) => $q->where('import_batch_id', $b))
            ->orderByDesc('work_date')
            ->orderBy('employee_id')
            ->paginate((int) $request->query('per_page', '50'));

        return AttendanceLogResource::collection($logs);
    }

    /**
     * GET /api/v1/attendance/logs/team
     * Department-scoped attendance logs for team managers.
     */
    public function team(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewTeam', AttendanceLog::class);

        $user = $request->user();
        $departmentIds = $user->departments()->pluck('departments.id')->toArray();

        $logs = AttendanceLog::with('employee')
            ->when($request->query('employee_id'), fn ($q, $id) => $q->where('employee_id', $id))
            ->when($request->query('date_from'), fn ($q, $d) => $q->where('work_date', '>=', $d))
            ->when($request->query('date_to'), fn ($q, $d) => $q->where('work_date', '<=', $d))
            ->when($request->query('search'), function ($q, $search) {
                $q->whereHas('employee', fn ($e) => $e
                    ->where('last_name', 'ilike', "%{$search}%")
                    ->orWhere('first_name', 'ilike', "%{$search}%"));
            })
            ->whereHas('employee', fn ($q) => $q->whereIn('department_id', $departmentIds))
            ->orderByDesc('work_date')
            ->orderBy('employee_id')
            ->paginate((int) $request->query('per_page', '50'));

        return AttendanceLogResource::collection($logs);
    }

    /**
     * GET /api/v1/attendance/logs/{attendanceLog}
     */
    public function show(AttendanceLog $attendanceLog): AttendanceLogResource
    {
        $this->authorize('view', $attendanceLog);

        return new AttendanceLogResource($attendanceLog->load('employee'));
    }

    /**
     * POST /api/v1/attendance/logs
     * Manual entry for time-in/out (HR admin override).
     */
    public function store(Request $request): AttendanceLogResource
    {
        $this->authorize('create', AttendanceLog::class);

        $validated = $request->validate([
            'employee_id' => 'required|integer|exists:employees,id',
            'work_date' => 'required|date',
            'time_in' => 'nullable|date_format:H:i',
            'time_out' => 'nullable|date_format:H:i|after_or_equal:time_in',
            'remarks' => 'nullable|string|max:500',
        ]);

        // Check if log already exists for this employee + date
        $existing = AttendanceLog::where('employee_id', $validated['employee_id'])
            ->where('work_date', $validated['work_date'])
            ->first();

        if ($existing) {
            abort(422, 'Attendance log already exists for this date. Use update instead.');
        }

        // Calculate worked minutes and other metrics
        $workedMinutes = 0;
        $lateMinutes = 0;
        $undertimeMinutes = 0;
        $isPresent = false;

        if ($validated['time_in'] && $validated['time_out']) {
            $timeIn = \DateTime::createFromFormat('H:i', $validated['time_in']);
            $timeOut = \DateTime::createFromFormat('H:i', $validated['time_out']);

            $workedMinutes = (int) (($timeOut->getTimestamp() - $timeIn->getTimestamp()) / 60);
            // Deduct break (assuming 60 min break for 8+ hour shifts)
            $breakMinutes = $workedMinutes >= 480 ? 60 : 0;
            $workedMinutes = max(0, $workedMinutes - $breakMinutes);

            // Simple late calculation (assuming 9:00 AM start)
            $scheduledStart = \DateTime::createFromFormat('H:i', '09:00');
            if ($timeIn > $scheduledStart) {
                $lateMinutes = (int) (($timeIn->getTimestamp() - $scheduledStart->getTimestamp()) / 60);
            }

            // Simple undertime calculation (assuming 8 hours = 480 minutes)
            if ($workedMinutes < 480) {
                $undertimeMinutes = 480 - $workedMinutes;
            }

            $isPresent = true;
        }

        $log = AttendanceLog::create([
            'employee_id' => $validated['employee_id'],
            'work_date' => $validated['work_date'],
            'source' => 'manual',
            'time_in' => $validated['time_in'] ?? null,
            'time_out' => $validated['time_out'] ?? null,
            'worked_minutes' => $workedMinutes,
            'worked_hours' => round($workedMinutes / 60, 2),
            'late_minutes' => $lateMinutes,
            'undertime_minutes' => $undertimeMinutes,
            'overtime_minutes' => 0,
            'is_present' => $isPresent,
            'is_absent' => ! $isPresent,
            'remarks' => $validated['remarks'] ?? null,
            'processed_by' => $request->user()->id,
        ]);

        return new AttendanceLogResource($log->load('employee'));
    }

    /**
     * PATCH /api/v1/attendance/logs/{attendanceLog}
     * Update existing attendance log (HR admin override).
     */
    public function update(Request $request, AttendanceLog $attendanceLog): AttendanceLogResource
    {
        $this->authorize('update', $attendanceLog);

        $validated = $request->validate([
            'time_in' => 'nullable|date_format:H:i,H:i:s',
            'time_out' => 'nullable|date_format:H:i,H:i:s|after_or_equal:time_in',
            'remarks' => 'nullable|string|max:500',
        ]);

        // Calculate worked minutes and other metrics
        $workedMinutes = 0;
        $lateMinutes = 0;
        $undertimeMinutes = 0;
        $isPresent = false;

        $timeIn = $validated['time_in'] ?? $attendanceLog->time_in;
        $timeOut = $validated['time_out'] ?? $attendanceLog->time_out;

        if ($timeIn && $timeOut) {
            // Handle both H:i and H:i:s formats
            $timeInFormat = strlen($timeIn) > 5 ? 'H:i:s' : 'H:i';
            $timeOutFormat = strlen($timeOut) > 5 ? 'H:i:s' : 'H:i';
            $timeInObj = \DateTime::createFromFormat($timeInFormat, $timeIn);
            $timeOutObj = \DateTime::createFromFormat($timeOutFormat, $timeOut);

            $workedMinutes = (int) (($timeOutObj->getTimestamp() - $timeInObj->getTimestamp()) / 60);
            $breakMinutes = $workedMinutes >= 480 ? 60 : 0;
            $workedMinutes = max(0, $workedMinutes - $breakMinutes);

            $scheduledStart = \DateTime::createFromFormat('H:i', '09:00');
            if ($timeInObj > $scheduledStart) {
                $lateMinutes = (int) (($timeInObj->getTimestamp() - $scheduledStart->getTimestamp()) / 60);
            }

            if ($workedMinutes < 480) {
                $undertimeMinutes = 480 - $workedMinutes;
            }

            $isPresent = true;
        }

        $attendanceLog->update([
            'time_in' => $timeIn,
            'time_out' => $timeOut,
            'worked_minutes' => $workedMinutes,
            'worked_hours' => round($workedMinutes / 60, 2),
            'late_minutes' => $lateMinutes,
            'undertime_minutes' => $undertimeMinutes,
            'is_present' => $isPresent,
            'is_absent' => ! $isPresent,
            'remarks' => $validated['remarks'] ?? $attendanceLog->remarks,
            'processed_by' => $request->user()->id,
        ]);

        return new AttendanceLogResource($attendanceLog->load('employee'));
    }

    /**
     * GET /api/v1/attendance/dashboard
     *
     * Returns an anomaly feed (unapproved tardiness / absences flagged for review)
     * and the pending OT approval queue, both scoped to the user's department.
     */
    public function dashboard(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('attendance.view'), 403);

        $departmentId = $request->query('department_id');

        // ── Anomaly feed: unresolved logs (tardy / absent) within last 14 days ─
        $anomalies = AttendanceLog::with('employee')
            ->when($departmentId, fn ($q) => $q->whereHas('employee', fn ($e) => $e->where('department_id', $departmentId)))
            ->where('work_date', '>=', now()->subDays(14)->toDateString())
            ->where(fn ($q) => $q
                ->where('is_present', false)
                ->orWhere('tardiness_minutes', '>', 0)
                ->orWhere('undertime_minutes', '>', 0)
            )
            ->orderByDesc('work_date')
            ->limit(50)
            ->get();

        // ── OT approval queue: pending overtime requests ───────────────────────
        $overtimeQueue = OvertimeRequest::with('employee')
            ->when($departmentId, fn ($q) => $q->whereHas('employee', fn ($e) => $e->where('department_id', $departmentId)))
            ->where('status', 'pending')
            ->orderBy('work_date')
            ->paginate((int) $request->query('per_page', '25'));

        // ── Period summary stats ──────────────────────────────────────────────
        $today = now()->toDateString();
        $monthStart = now()->startOfMonth()->toDateString();

        $stats = AttendanceLog::when($departmentId, fn ($q) => $q->whereHas('employee', fn ($e) => $e->where('department_id', $departmentId)))
            ->where('work_date', '>=', $monthStart)
            ->where('work_date', '<=', $today)
            ->selectRaw('
                COUNT(*) FILTER (WHERE is_present = false)            AS absent_count,
                COUNT(*) FILTER (WHERE tardiness_minutes > 0)         AS tardy_count,
                COALESCE(SUM(overtime_minutes), 0)                    AS total_ot_minutes,
                COUNT(DISTINCT employee_id)                           AS employee_count
            ')
            ->first();

        return response()->json([
            'data' => [
                'anomaly_feed' => $anomalies,
                'overtime_queue' => $overtimeQueue,
                'stats' => [
                    'absent_count' => (int) ($stats->absent_count ?? 0),
                    'tardy_count' => (int) ($stats->tardy_count ?? 0),
                    'total_ot_minutes' => (int) ($stats->total_ot_minutes ?? 0),
                    'employee_count' => (int) ($stats->employee_count ?? 0),
                    'period_start' => $monthStart,
                    'period_end' => $today,
                ],
            ],
        ]);
    }
}
