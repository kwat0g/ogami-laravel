<?php

declare(strict_types=1);

namespace App\Domains\HR\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $training_id
 * @property int $employee_id
 * @property string $status enrolled|attended|completed|no_show|cancelled
 * @property int|null $score
 * @property bool|null $passed
 * @property string|null $remarks
 * @property-read Training $training
 */
final class TrainingAttendee extends Model
{
    protected $table = 'training_attendees';

    protected $fillable = [
        'training_id', 'employee_id', 'status',
        'score', 'passed', 'remarks',
    ];

    protected $casts = [
        'score' => 'integer',
        'passed' => 'boolean',
    ];

    public function training(): BelongsTo
    {
        return $this->belongsTo(Training::class, 'training_id');
    }
}
