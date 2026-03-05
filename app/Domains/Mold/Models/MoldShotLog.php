<?php

declare(strict_types=1);

namespace App\Domains\Mold\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class MoldShotLog extends Model
{
    public $timestamps = false;

    const CREATED_AT = 'created_at';

    protected $table = 'mold_shot_logs';

    protected $fillable = [
        'mold_id',
        'production_order_id',
        'shot_count',
        'operator_id',
        'log_date',
        'remarks',
    ];

    protected $casts = [
        'log_date'   => 'date',
        'shot_count' => 'integer',
    ];

    /** @return BelongsTo<MoldMaster, $this> */
    public function mold(): BelongsTo
    {
        return $this->belongsTo(MoldMaster::class, 'mold_id');
    }

    /** @return BelongsTo<\App\Models\User, $this> */
    public function operator(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'operator_id');
    }
}
