<?php

declare(strict_types=1);

namespace App\Domains\Attendance\Services;

use App\Domains\Attendance\Models\EmployeeWorkLocation;
use App\Domains\Attendance\Models\WorkLocation;
use App\Domains\HR\Models\Employee;
use App\Shared\Contracts\ServiceContract;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Geofence validation service.
 *
 * Uses the Haversine formula (pure PHP, no PostGIS dependency) to compute
 * the distance between a GPS coordinate and the employee's assigned
 * work location. Returns whether the coordinate is within the geofence.
 */
final class GeoFenceService implements ServiceContract
{
    /** Mean Earth radius in meters. */
    private const EARTH_RADIUS_METERS = 6_371_000;

    /**
     * Compute distance in meters between two GPS coordinates
     * using the Haversine formula.
     */
    public function distanceMeters(
        float $lat1,
        float $lon1,
        float $lat2,
        float $lon2,
    ): float {
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return self::EARTH_RADIUS_METERS * $c;
    }

    /**
     * Validate whether a GPS coordinate is within the employee's assigned
     * work location geofence.
     *
     * @return array{within: bool, distance_meters: float, location: WorkLocation|null}
     */
    public function validateLocation(
        Employee $employee,
        float $latitude,
        float $longitude,
        Carbon $at,
    ): array {
        $workLocation = $this->resolveWorkLocation($employee, $at);

        if (! $workLocation) {
            // No work location assigned — skip geofence check
            return ['within' => true, 'distance_meters' => 0.0, 'location' => null];
        }

        if ($workLocation->is_remote_allowed) {
            // Remote-allowed locations skip geofence
            return ['within' => true, 'distance_meters' => 0.0, 'location' => $workLocation];
        }

        $distance = $this->distanceMeters(
            (float) $workLocation->latitude,
            (float) $workLocation->longitude,
            $latitude,
            $longitude,
        );

        $effectiveRadius = $workLocation->effectiveRadius();

        return [
            'within' => $distance <= $effectiveRadius,
            'distance_meters' => round($distance, 2),
            'location' => $workLocation,
        ];
    }

    /**
     * Is geofence enforcement enabled globally?
     * Admin can temporarily disable via system_settings.
     */
    public function isGeofenceEnabled(): bool
    {
        $raw = DB::table('system_settings')
            ->where('key', 'attendance.geofence_enabled')
            ->value('value');

        return $raw !== null ? (bool) json_decode($raw) : true;
    }

    /**
     * Get the geofence enforcement mode.
     *
     * @return string 'strict' | 'override' | 'disabled'
     *   strict   = block clock-in outside geofence (no override allowed)
     *   override = allow clock-in with a reason (flagged for HR review)
     *   disabled = no geofence check at all
     */
    public function getGeofenceMode(): string
    {
        if (! $this->isGeofenceEnabled()) {
            return 'disabled';
        }

        $raw = DB::table('system_settings')
            ->where('key', 'attendance.geofence_mode')
            ->value('value');

        $mode = $raw !== null ? (string) json_decode($raw) : 'strict';

        return in_array($mode, ['strict', 'override', 'disabled'], true) ? $mode : 'strict';
    }

    /**
     * Admin: Toggle geofence enforcement on/off.
     */
    public function setGeofenceEnabled(bool $enabled): void
    {
        DB::table('system_settings')
            ->updateOrInsert(
                ['key' => 'attendance.geofence_enabled'],
                [
                    'label' => 'Attendance Geofence Enabled',
                    'value' => json_encode($enabled),
                    'data_type' => 'boolean',
                    'editable_by_role' => 'admin',
                    'group' => 'general',
                ],
            );
    }

    /**
     * Admin: Update geofence mode (strict/override/disabled).
     */
    public function setGeofenceMode(string $mode): void
    {
        DB::table('system_settings')
            ->updateOrInsert(
                ['key' => 'attendance.geofence_mode'],
                [
                    'label' => 'Attendance Geofence Mode',
                    'value' => json_encode($mode),
                    'data_type' => 'string',
                    'editable_by_role' => 'admin',
                    'group' => 'general',
                ],
            );
    }

    /**
     * Resolve the primary active work location for an employee on a given date.
     */
    public function resolveWorkLocation(Employee $employee, Carbon $at): ?WorkLocation
    {
        return EmployeeWorkLocation::where('employee_id', $employee->id)
            ->activeOn($at->toDateString())
            ->where('is_primary', true)
            ->with('workLocation')
            ->orderByDesc('effective_date')
            ->first()
            ?->workLocation;
    }
}
