<?php

declare(strict_types=1);

namespace App\Domains\Production\Models;

use App\Shared\Traits\HasPublicUlid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property string $ulid
 * @property int $bom_id
 * @property int $work_center_id
 * @property int $sequence
 * @property string $operation_name
 * @property string|null $description
 * @property string $setup_time_hours
 * @property string $run_time_hours_per_unit
 * @property-read BillOfMaterials $bom
 * @property-read WorkCenter $workCenter
 */
final class Routing extends Model
{
    use HasPublicUlid, SoftDeletes;

    protected $table = 'routings';

    protected $fillable = [
        'bom_id', 'work_center_id', 'sequence',
        'operation_name', 'description',
        'setup_time_hours', 'run_time_hours_per_unit',
    ];

    protected $casts = [
        'sequence' => 'integer',
        'setup_time_hours' => 'decimal:2',
        'run_time_hours_per_unit' => 'decimal:4',
    ];

    public function bom(): BelongsTo
    {
        return $this->belongsTo(BillOfMaterials::class, 'bom_id');
    }

    public function workCenter(): BelongsTo
    {
        return $this->belongsTo(WorkCenter::class, 'work_center_id');
    }
}
