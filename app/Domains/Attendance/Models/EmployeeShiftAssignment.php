<?php

declare(strict_types=1);

namespace App\Domains\Attendance\Models;

use App\Domains\HR\Models\Employee;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * Employee-to-shift assignment with non-overlapping guarantee enforced at DB
 * via EXCLUDE USING gist constraint.
 *
 * @property int $id
 * @property int $employee_id
 * @property int $shift_schedule_id
 * @property Carbon $effective_from
 * @property Carbon|null $effective_to null = still active
 * @property string|null $notes
 * @property int $created_by FK users.id
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Employee $employee
 * @property-read ShiftSchedule $shiftSchedule
 */
final class EmployeeShiftAssignment extends Model implements Auditable
{
    use AuditableTrait;

    protected $table = 'employee_shift_assignments';

    /** @var list<string> */
    protected $fillable = [
        'employee_id',
        'shift_schedule_id',
        'effective_from',
        'effective_to',
        'notes',
        'assigned_by',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'effective_from' => 'date',
            'effective_to' => 'date',
        ];
    }

    /** Whether this assignment is currently active. */
    public function isCurrentlyActive(): bool
    {
        $today = now()->toDateString();

        return $this->effective_from->toDateString() <= $today
            && ($this->effective_to === null || $this->effective_to->toDateString() >= $today);
    }

    // ── Relations ─────────────────────────────────────────────────────────────

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function shiftSchedule(): BelongsTo
    {
        return $this->belongsTo(ShiftSchedule::class);
    }
}
