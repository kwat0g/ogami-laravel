<?php

declare(strict_types=1);

namespace App\Domains\HR\Recruitment\Models;

use App\Domains\HR\Recruitment\Enums\RequirementStatus;
use App\Domains\HR\Recruitment\Enums\RequirementType;
use Database\Factories\Recruitment\PreEmploymentRequirementFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $pre_employment_checklist_id
 * @property string $requirement_type
 * @property string $label
 * @property bool $is_required
 * @property string $status
 * @property string|null $document_path
 * @property \Illuminate\Support\Carbon|null $submitted_at
 * @property \Illuminate\Support\Carbon|null $verified_at
 * @property string|null $remarks
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 */
final class PreEmploymentRequirement extends Model
{
    /** @use HasFactory<PreEmploymentRequirementFactory> */
    use HasFactory;
    protected $table = 'pre_employment_requirements';

    protected $fillable = [
        'pre_employment_checklist_id',
        'requirement_type',
        'label',
        'is_required',
        'status',
        'document_path',
        'submitted_at',
        'verified_at',
        'remarks',
    ];

    protected function casts(): array
    {
        return [
            'requirement_type' => RequirementType::class,
            'status' => RequirementStatus::class,
            'is_required' => 'boolean',
            'submitted_at' => 'datetime',
            'verified_at' => 'datetime',
        ];
    }

    protected static function newFactory(): PreEmploymentRequirementFactory
    {
        return PreEmploymentRequirementFactory::new();
    }

    public function checklist(): BelongsTo
    {
        return $this->belongsTo(PreEmploymentChecklist::class, 'pre_employment_checklist_id');
    }
}
