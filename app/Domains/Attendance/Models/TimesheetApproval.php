<?php

declare(strict_types=1);

namespace App\Domains\Attendance\Models;

use App\Models\User;
use App\Shared\Traits\HasPublicUlid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $ulid
 * @property int $employee_id
 * @property string $period_start
 * @property string $period_end
 * @property string $total_regular_hours
 * @property string $total_overtime_hours
 * @property int $days_present
 * @property int $days_absent
 * @property string $status draft|submitted|approved|rejected
 * @property int|null $submitted_by_id
 * @property Carbon|null $submitted_at
 * @property int|null $approved_by_id
 * @property Carbon|null $approved_at
 * @property string|null $remarks
 */
final class TimesheetApproval extends Model
{
    use HasPublicUlid, SoftDeletes;

    protected $table = 'timesheet_approvals';

    protected $fillable = [
        'employee_id', 'period_start', 'period_end',
        'total_regular_hours', 'total_overtime_hours',
        'days_present', 'days_absent',
        'status', 'submitted_by_id', 'submitted_at',
        'approved_by_id', 'approved_at', 'remarks',
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'total_regular_hours' => 'decimal:2',
        'total_overtime_hours' => 'decimal:2',
        'days_present' => 'integer',
        'days_absent' => 'integer',
        'submitted_at' => 'datetime',
        'approved_at' => 'datetime',
    ];

    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by_id');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_id');
    }
}
