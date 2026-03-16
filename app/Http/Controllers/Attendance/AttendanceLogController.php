<?php

declare(strict_types=1);

namespace App\Http\Controllers\Attendance;

use App\Domains\Attendance\Models\AttendanceLog;
use App\Domains\Attendance\Models\EmployeeShiftAssignment;
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
            'work_date' => 'required|date|before_or_equal:today',
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

        // Resolve the employee's active shift for the given work date
        $shift = EmployeeShiftAssignment::with('shiftSchedule')
            ->where('employee_id', $validated['employee_id'])
            ->where('effective_from', '<=', $validated['work_date'])
            ->where(fn ($q) => $q->whereNull('effective_to')
                ->orWhere('effective_to', '>=', $validated['work_date']))
            ->latest('effective_from')
            ->first()
            ?->shiftSchedule;

        // Validate time_in / time_out against the shift window (shift ± 4 hours)
        if ($shift && $validated['time_in']) {
            [$sh, $sm] = array_pad(explode(':', $shift->start_time), 2, '00');
            [$eh, $em] = array_pad(explode(':', $shift->end_time), 2, '00');
            $shiftStartMins = (int) $sh * 60 + (int) $sm;
            $shiftEndMins = (int) $eh * 60 + (int) $em;
            if ($shiftEndMins <= $shiftStartMins) {
                $shiftEndMins += 1440; // overnight shift
            }
            $windowOpen = $shiftStartMins - 240; // 4 h before shift start
            $windowClose = $shiftEndMins + 240; // 4 h after shift end

            [$tih, $tim] = explode(':', $validated['time_in']);
            $timeInMins = (int) $tih * 60 + (int) $tim;

            if ($timeInMins < $windowOpen || $timeInMins > $windowClose) {
                abort(422, sprintf(
                    'Time-in (%s) is outside the expected window for the assigned shift (%s – %s).',
                    $validated['time_in'],
                    substr($shift->start_time, 0, 5),
                    substr($shift->end_time, 0, 5)
                ));
            }

            if ($validated['time_out']) {
                [$toh, $tom] = explode(':', $validated['time_out']);
                $timeOutMins = (int) $toh * 60 + (int) $tom;
                if ($timeOutMins < $timeInMins) {
                    $timeOutMins += 1440; // past midnight
                }
                if ($timeOutMins > $windowClose + 1440) {
                    abort(422, sprintf(
                        'Time-out (%s) is outside the expected window for the assigned shift (%s – %s).',
                        $validated['time_out'],
                        substr($shift->start_time, 0, 5),
                        substr($shift->end_time, 0, 5)
                    ));
                }
            }
        }

        // Build full timestamps by combining work_date + H:i time strings
        $workDate = $validated['work_date'];
        $timeInTs = $validated['time_in'] ? $workDate.' '.$validated['time_in'].':00' : null;
        $timeOutTs = $validated['time_out'] ? $workDate.' '.$validated['time_out'].':00' : null;

        // Calculate worked / late / undertime using actual shift data where available
        $workedMinutes = 0;
        $lateMinutes = 0;
        $undertimeMinutes = 0;
        $isPresent = false;

        if ($timeInTs && $timeOutTs) {
            $timeInObj = new \DateTime($timeInTs);
            $timeOutObj = new \DateTime($timeOutTs);

            // Handle overnight: time_out < time_in means it crossed midnight
            if ($timeOutObj < $timeInObj) {
                $timeOutObj->modify('+1 day');
            }

            $grossMinutes = (int) (($timeOutObj->getTimestamp() - $timeInObj->getTimestamp()) / 60);
            $breakMinutes = $shift ? $shift->break_minutes : ($grossMinutes >= 480 ? 60 : 0);
            $workedMinutes = max(0, $grossMinutes - $breakMinutes);

            // Late = time_in exceeds shift start + grace period
            $scheduledStartStr = $shift ? substr($shift->start_time, 0, 5) : '09:00';
            $graceMins = (int) ($shift ? $shift->grace_period_minutes : 0);
            $scheduledStart = new \DateTime($workDate.' '.$scheduledStartStr.':00');
            if ($graceMins > 0) {
                $scheduledStart->modify("+{$graceMins} minutes");
            }
            if ($timeInObj > $scheduledStart) {
                $lateMinutes = (int) (($timeInObj->getTimestamp() - $scheduledStart->getTimestamp()) / 60);
            }

            // Undertime = worked less than the shift's net working minutes
            $requiredMinutes = $shift ? $shift->netWorkingMinutes() : 480;
            if ($workedMinutes < $requiredMinutes) {
                $undertimeMinutes = $requiredMinutes - $workedMinutes;
            }

            $isPresent = true;
        }

        $log = AttendanceLog::create([
            'employee_id' => $validated['employee_id'],
            'work_date' => $workDate,
            'source' => 'manual',
            'time_in' => $timeInTs,
            'time_out' => $timeOutTs,
            'worked_minutes' => $workedMinutes,
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
            'time_in' => 'nullable|date_format:H:i',
            'time_out' => 'nullable|date_format:H:i|after_or_equal:time_in',
            'remarks' => 'nullable|string|max:500',
        ]);

        // Resolve the employee's active shift for this log's work date
        $workDate = $attendanceLog->work_date->toDateString();

        $shift = EmployeeShiftAssignment::with('shiftSchedule')
            ->where('employee_id', $attendanceLog->employee_id)
            ->where('effective_from', '<=', $workDate)
            ->where(fn ($q) => $q->whereNull('effective_to')
                ->orWhere('effective_to', '>=', $workDate))
            ->latest('effective_from')
            ->first()
            ?->shiftSchedule;

        // Build timestamps: new H:i input takes precedence, otherwise keep existing value
        $timeInTs = array_key_exists('time_in', $validated)
            ? ($validated['time_in'] ? $workDate.' '.$validated['time_in'].':00' : null)
            : ($attendanceLog->time_in !== null ? (string) $attendanceLog->time_in : null);
        $timeOutTs = array_key_exists('time_out', $validated)
            ? ($validated['time_out'] ? $workDate.' '.$validated['time_out'].':00' : null)
            : ($attendanceLog->time_out !== null ? (string) $attendanceLog->time_out : null);

        // Recalculate worked / late / undertime
        $workedMinutes = 0;
        $lateMinutes = 0;
        $undertimeMinutes = 0;
        $isPresent = false;

        if ($timeInTs && $timeOutTs) {
            $timeInObj = new \DateTime($timeInTs);
            $timeOutObj = new \DateTime($timeOutTs);

            if ($timeOutObj < $timeInObj) {
                $timeOutObj->modify('+1 day');
            }

            $grossMinutes = (int) (($timeOutObj->getTimestamp() - $timeInObj->getTimestamp()) / 60);
            $breakMinutes = $shift ? $shift->break_minutes : ($grossMinutes >= 480 ? 60 : 0);
            $workedMinutes = max(0, $grossMinutes - $breakMinutes);

            $scheduledStartStr = $shift ? substr($shift->start_time, 0, 5) : '09:00';
            $graceMins = (int) ($shift ? $shift->grace_period_minutes : 0);
            $scheduledStart = new \DateTime($workDate.' '.$scheduledStartStr.':00');
            if ($graceMins > 0) {
                $scheduledStart->modify("+{$graceMins} minutes");
            }
            if ($timeInObj > $scheduledStart) {
                $lateMinutes = (int) (($timeInObj->getTimestamp() - $scheduledStart->getTimestamp()) / 60);
            }

            $requiredMinutes = $shift ? $shift->netWorkingMinutes() : 480;
            if ($workedMinutes < $requiredMinutes) {
                $undertimeMinutes = $requiredMinutes - $workedMinutes;
            }

            $isPresent = true;
        }

        $attendanceLog->update([
            'time_in' => $timeInTs,
            'time_out' => $timeOutTs,
            'worked_minutes' => $workedMinutes,
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
