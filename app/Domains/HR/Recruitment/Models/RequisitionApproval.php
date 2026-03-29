<?php

declare(strict_types=1);

namespace App\Domains\HR\Recruitment\Models;

use App\Models\User;
use Database\Factories\Recruitment\RequisitionApprovalFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $job_requisition_id
 * @property int $user_id
 * @property string $action
 * @property int $sequence
 * @property string|null $remarks
 * @property \Illuminate\Support\Carbon|null $acted_at
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 */
final class RequisitionApproval extends Model
{
    /** @use HasFactory<RequisitionApprovalFactory> */
    use HasFactory;
    protected $table = 'requisition_approvals';

    protected $fillable = [
        'job_requisition_id',
        'user_id',
        'action',
        'sequence',
        'remarks',
        'acted_at',
    ];

    protected function casts(): array
    {
        return [
            'sequence' => 'integer',
            'acted_at' => 'datetime',
        ];
    }

    protected static function newFactory(): RequisitionApprovalFactory
    {
        return RequisitionApprovalFactory::new();
    }

    public function requisition(): BelongsTo
    {
        return $this->belongsTo(JobRequisition::class, 'job_requisition_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
