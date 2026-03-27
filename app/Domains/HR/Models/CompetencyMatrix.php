<?php

declare(strict_types=1);

namespace App\Domains\HR\Models;

use App\Models\User;
use App\Shared\Traits\HasPublicUlid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property string $ulid
 * @property int $employee_id
 * @property string $skill_name
 * @property string|null $category
 * @property int $current_level 1-5
 * @property int $required_level 1-5
 * @property string|null $assessed_at
 * @property int|null $assessed_by_id
 * @property string|null $notes
 */
final class CompetencyMatrix extends Model
{
    use HasPublicUlid, SoftDeletes;

    protected $table = 'competency_matrices';

    protected $fillable = [
        'employee_id', 'skill_name', 'category',
        'current_level', 'required_level',
        'assessed_at', 'assessed_by_id', 'notes',
    ];

    protected $casts = [
        'current_level' => 'integer',
        'required_level' => 'integer',
        'assessed_at' => 'date',
    ];

    public function assessedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assessed_by_id');
    }

    public function hasGap(): bool
    {
        return $this->current_level < $this->required_level;
    }
}
