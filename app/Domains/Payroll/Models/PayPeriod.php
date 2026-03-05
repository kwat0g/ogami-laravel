<?php

declare(strict_types=1);

namespace App\Domains\Payroll\Models;

use Database\Factories\PayPeriodFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * @property int $id
 * @property string $label
 * @property string $cutoff_start
 * @property string $cutoff_end
 * @property string $pay_date
 * @property string $frequency 'semi_monthly' | 'monthly' | 'weekly'
 * @property string $status 'open' | 'closed'
 */
class PayPeriod extends Model implements Auditable
{
    use AuditableTrait, HasFactory, SoftDeletes;

    protected static function newFactory(): PayPeriodFactory
    {
        return PayPeriodFactory::new();
    }

    protected $table = 'pay_periods';

    protected $fillable = [
        'label',
        'cutoff_start',
        'cutoff_end',
        'pay_date',
        'frequency',
        'status',
    ];

    protected $casts = [
        'cutoff_start' => 'date:Y-m-d',
        'cutoff_end' => 'date:Y-m-d',
        'pay_date' => 'date:Y-m-d',
    ];

    // ── Relations ──────────────────────────────────────────────────────────

    public function payrollRuns(): HasMany
    {
        return $this->hasMany(PayrollRun::class, 'pay_period_id');
    }

    // ── Helpers ────────────────────────────────────────────────────────────

    public function isOpen(): bool
    {
        return $this->status === 'open';
    }

    public function isClosed(): bool
    {
        return $this->status === 'closed';
    }
}
