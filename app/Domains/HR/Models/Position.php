<?php

declare(strict_types=1);

namespace App\Domains\HR\Models;

use Database\Factories\PositionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * @property int $id
 * @property string $code
 * @property string $title
 * @property int $department_id
 * @property string|null $pay_grade
 * @property bool $is_active
 */
final class Position extends Model implements Auditable
{
    /** @use HasFactory<PositionFactory> */
    use AuditableTrait, HasFactory, SoftDeletes;

    protected $table = 'positions';

    protected $fillable = [
        'code',
        'title',
        'department_id',
        'pay_grade',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    protected static function newFactory(): PositionFactory
    {
        return PositionFactory::new();
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }
}
