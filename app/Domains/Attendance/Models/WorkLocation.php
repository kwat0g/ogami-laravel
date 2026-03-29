<?php

declare(strict_types=1);

namespace App\Domains\Attendance\Models;

use App\Shared\Traits\HasPublicUlid;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * @property int $id
 * @property string $ulid
 * @property string $name
 * @property string $code
 * @property string $address
 * @property string|null $city
 * @property string $latitude
 * @property string $longitude
 * @property int $radius_meters
 * @property int $allowed_variance_meters
 * @property bool $is_remote_allowed
 * @property bool $is_active
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Collection<int, EmployeeWorkLocation> $employeeAssignments
 * @property-read Collection<int, AttendanceLog> $attendanceLogs
 */
final class WorkLocation extends Model implements Auditable
{
    use AuditableTrait, HasPublicUlid, SoftDeletes;

    protected $table = 'work_locations';

    /** @var list<string> */
    protected $fillable = [
        'name',
        'code',
        'address',
        'city',
        'latitude',
        'longitude',
        'radius_meters',
        'allowed_variance_meters',
        'is_remote_allowed',
        'is_active',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'radius_meters' => 'integer',
            'allowed_variance_meters' => 'integer',
            'is_remote_allowed' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /** Effective geofence radius including GPS drift tolerance. */
    public function effectiveRadius(): int
    {
        return $this->radius_meters + $this->allowed_variance_meters;
    }

    // ── Relations ─────────────────────────────────────────────────────────────

    public function employeeAssignments(): HasMany
    {
        return $this->hasMany(EmployeeWorkLocation::class);
    }

    public function attendanceLogs(): HasMany
    {
        return $this->hasMany(AttendanceLog::class);
    }
}
