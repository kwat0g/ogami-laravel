<?php

declare(strict_types=1);

namespace App\Shared\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Polymorphic approval log — records who approved/rejected/returned what, when, and why.
 *
 * @property int $id
 * @property string $approvable_type
 * @property int $approvable_id
 * @property string $stage        The approval step name (e.g., 'head_noted', 'manager_checked')
 * @property string $action       approved|rejected|returned|noted|checked|reviewed|processed
 * @property int $user_id
 * @property string|null $remarks
 * @property array|null $metadata
 * @property \Illuminate\Support\Carbon $created_at
 * @property-read Model $approvable
 * @property-read User $user
 */
final class ApprovalLog extends Model
{
    public $timestamps = false;

    protected $table = 'approval_logs';

    protected $fillable = [
        'approvable_type',
        'approvable_id',
        'stage',
        'action',
        'user_id',
        'remarks',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
        'created_at' => 'datetime',
    ];

    // ── Boot ──────────────────────────────────────────────────────────────

    protected static function booted(): void
    {
        static::creating(function (self $log): void {
            $log->created_at ??= now();
        });
    }

    // ── Relationships ────────────────────────────────────────────────────

    public function approvable(): MorphTo
    {
        return $this->morphTo();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
