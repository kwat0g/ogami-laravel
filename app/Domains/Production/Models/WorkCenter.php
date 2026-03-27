<?php

declare(strict_types=1);

namespace App\Domains\Production\Models;

use App\Shared\Traits\HasPublicUlid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * @property int $id
 * @property string $ulid
 * @property string $code
 * @property string $name
 * @property string|null $description
 * @property int $hourly_rate_centavos
 * @property int $overhead_rate_centavos
 * @property int $capacity_hours_per_day
 * @property bool $is_active
 */
final class WorkCenter extends Model implements Auditable
{
    use AuditableTrait, HasPublicUlid, SoftDeletes;

    protected $table = 'work_centers';

    protected $fillable = [
        'code', 'name', 'description',
        'hourly_rate_centavos', 'overhead_rate_centavos',
        'capacity_hours_per_day', 'is_active',
    ];

    protected $casts = [
        'hourly_rate_centavos' => 'integer',
        'overhead_rate_centavos' => 'integer',
        'capacity_hours_per_day' => 'integer',
        'is_active' => 'boolean',
    ];

    public function routings(): HasMany
    {
        return $this->hasMany(Routing::class, 'work_center_id');
    }
}
