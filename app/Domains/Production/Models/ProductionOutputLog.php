<?php

declare(strict_types=1);

namespace App\Domains\Production\Models;

use App\Domains\HR\Models\Employee;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int         $id
 * @property int         $production_order_id
 * @property string      $shift
 * @property string      $log_date
 * @property string      $qty_produced
 * @property string      $qty_rejected
 * @property int         $operator_id
 * @property int         $recorded_by_id
 * @property string|null $remarks
 * @property \Carbon\Carbon $created_at
 */
final class ProductionOutputLog extends Model
{
    use SoftDeletes;

    public $timestamps = false;

    protected $table = 'production_output_logs';

    const CREATED_AT = 'created_at';

    protected $fillable = [
        'production_order_id',
        'shift',
        'log_date',
        'qty_produced',
        'qty_rejected',
        'operator_id',
        'recorded_by_id',
        'remarks',
    ];

    protected $casts = [
        'qty_produced' => 'decimal:4',
        'qty_rejected' => 'decimal:4',
        'log_date'     => 'date',
        'created_at'   => 'datetime',
    ];

    public function productionOrder(): BelongsTo
    {
        return $this->belongsTo(ProductionOrder::class, 'production_order_id');
    }

    public function operator(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'operator_id');
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by_id');
    }
}
