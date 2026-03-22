<?php

declare(strict_types=1);

namespace App\Domains\HR\Models;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Employee Clearance - Tracks exit clearance checklist items.
 *
 * @property int $id
 * @property int $employee_id
 * @property string $department_code IT|HR|FINANCE|DEPT
 * @property string $item_description Description of clearance item
 * @property string $status pending|in_progress|cleared|blocked
 * @property string|null $notes Additional notes
 * @property int|null $cleared_by_id User who cleared this item
 * @property Carbon|null $cleared_at When item was cleared
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
final class EmployeeClearance extends Model
{
    protected $table = 'employee_clearances';

    protected $fillable = [
        'employee_id',
        'department_code',
        'item_description',
        'status',
        'notes',
        'cleared_by_id',
        'cleared_at',
    ];

    protected $casts = [
        'cleared_at' => 'datetime',
    ];

    /** @return BelongsTo<Employee, EmployeeClearance> */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /** @return BelongsTo<User, EmployeeClearance> */
    public function clearedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cleared_by_id');
    }

    /**
     * Mark this clearance item as cleared.
     */
    public function markAsCleared(User $user, ?string $notes = null): void
    {
        $this->update([
            'status' => 'cleared',
            'cleared_by_id' => $user->id,
            'cleared_at' => now(),
            'notes' => $notes ?? $this->notes,
        ]);
    }

    /**
     * Mark this clearance item as blocked.
     */
    public function markAsBlocked(?string $reason = null): void
    {
        $this->update([
            'status' => 'blocked',
            'notes' => $reason ?? $this->notes,
        ]);
    }
}
