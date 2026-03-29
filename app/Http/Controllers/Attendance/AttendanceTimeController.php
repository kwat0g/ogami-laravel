<?php

declare(strict_types=1);

namespace App\Http\Controllers\Attendance;

use App\Domains\Attendance\Models\AttendanceLog;
use App\Domains\Attendance\Services\AttendanceTimeService;
use App\Domains\Attendance\Services\GeoFenceService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Attendance\TimeInRequest;
use App\Http\Requests\Attendance\TimeOutRequest;
use App\Http\Resources\Attendance\AttendanceLogResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class AttendanceTimeController extends Controller
{
    public function __construct(
        private readonly AttendanceTimeService $service,
    ) {}

    /**
     * Record time-in for the authenticated employee.
     */
    public function timeIn(TimeInRequest $request): JsonResponse
    {
        $employee = $request->user()->employee;

        abort_unless($employee, 403, 'No linked employee record found.');

        $log = $this->service->timeIn(
            employee: $employee,
            latitude: (float) $request->validated('latitude'),
            longitude: (float) $request->validated('longitude'),
            accuracyMeters: (float) $request->validated('accuracy_meters'),
            deviceInfo: $request->validated('device_info') ?? [],
            overrideReason: $request->validated('override_reason'),
        );

        return (new AttendanceLogResource($log->load('employee', 'workLocation')))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Record time-out for the authenticated employee.
     */
    public function timeOut(TimeOutRequest $request): JsonResponse
    {
        $employee = $request->user()->employee;

        abort_unless($employee, 403, 'No linked employee record found.');

        $log = $this->service->timeOut(
            employee: $employee,
            latitude: (float) $request->validated('latitude'),
            longitude: (float) $request->validated('longitude'),
            accuracyMeters: (float) $request->validated('accuracy_meters'),
            deviceInfo: $request->validated('device_info') ?? [],
            overrideReason: $request->validated('override_reason'),
        );

        return (new AttendanceLogResource($log->load('employee', 'workLocation')))
            ->response()
            ->setStatusCode(200);
    }

    /**
     * Get today's attendance log for the authenticated employee.
     */
    public function today(Request $request): JsonResponse
    {
        $employee = $request->user()->employee;

        abort_unless($employee, 403, 'No linked employee record found.');

        $log = AttendanceLog::where('employee_id', $employee->id)
            ->where('work_date', today()->toDateString())
            ->with('workLocation')
            ->first();

        if (! $log) {
            return response()->json(['data' => null]);
        }

        return response()->json(['data' => new AttendanceLogResource($log)]);
    }

    /**
     * Get the authenticated employee's attendance log history.
     */
    public function myLogs(Request $request): JsonResponse
    {
        $employee = $request->user()->employee;

        abort_unless($employee, 403, 'No linked employee record found.');

        $logs = AttendanceLog::where('employee_id', $employee->id)
            ->with('workLocation')
            ->when($request->query('from'), fn ($q, $from) => $q->where('work_date', '>=', $from))
            ->when($request->query('to'), fn ($q, $to) => $q->where('work_date', '<=', $to))
            ->orderByDesc('work_date')
            ->paginate((int) $request->query('per_page', '15'));

        return AttendanceLogResource::collection($logs)->response();
    }

    /**
     * Get current geofence settings (for frontend to know enforcement mode).
     */
    public function geofenceSettings(): JsonResponse
    {
        $geoFence = app(GeoFenceService::class);

        return response()->json([
            'data' => [
                'enabled' => $geoFence->isGeofenceEnabled(),
                'mode' => $geoFence->getGeofenceMode(),
            ],
        ]);
    }

    /**
     * Admin: Toggle geofence enforcement on/off.
     */
    public function toggleGeofence(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('attendance.work_locations.manage'), 403);

        $validated = $request->validate([
            'enabled' => 'required|boolean',
        ]);

        DB::table('system_settings')
            ->where('key', 'attendance.geofence_enabled')
            ->update(['value' => json_encode((bool) $validated['enabled'])]);

        return response()->json([
            'data' => [
                'enabled' => (bool) $validated['enabled'],
                'mode' => app(GeoFenceService::class)->getGeofenceMode(),
            ],
            'message' => $validated['enabled']
                ? 'Geofence enforcement enabled.'
                : 'Geofence enforcement temporarily disabled.',
        ]);
    }

    /**
     * Admin: Update geofence mode (strict/override/disabled).
     */
    public function updateGeofenceMode(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('attendance.work_locations.manage'), 403);

        $validated = $request->validate([
            'mode' => 'required|in:strict,override,disabled',
        ]);

        DB::table('system_settings')
            ->where('key', 'attendance.geofence_mode')
            ->update(['value' => json_encode($validated['mode'])]);

        return response()->json([
            'data' => [
                'enabled' => app(GeoFenceService::class)->isGeofenceEnabled(),
                'mode' => $validated['mode'],
            ],
            'message' => "Geofence mode set to '{$validated['mode']}'.",
        ]);
    }
}
