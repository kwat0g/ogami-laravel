<?php

declare(strict_types=1);

namespace App\Domains\HR\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Individual KPI/criteria within a performance appraisal.
 *
 * @property int $id
 * @property int $appraisal_id
 * @property string $criteria_name
 * @property string|null $description
 * @property int $weight_pct Weight as percentage (0-100), all criteria weights should sum to 100
 * @property int|null $rating_pct Rating as percentage (0-100)
 * @property string|null $comments
 * @property-read PerformanceAppraisal $appraisal
 */
final class PerformanceAppraisalCriteria extends Model
{
    public $timestamps = false;

    protected $table = 'performance_appraisal_criteria';

    protected $fillable = [
        'appraisal_id',
        'criteria_name',
        'description',
        'weight_pct',
        'rating_pct',
        'comments',
    ];

    protected $casts = [
        'weight_pct' => 'integer',
        'rating_pct' => 'integer',
    ];

    public function appraisal(): BelongsTo
    {
        return $this->belongsTo(PerformanceAppraisal::class, 'appraisal_id');
    }
}
