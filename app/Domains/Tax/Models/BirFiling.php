<?php

declare(strict_types=1);

namespace App\Domains\Tax\Models;

use App\Shared\Traits\HasPublicUlid;
use App\Domains\Accounting\Models\FiscalPeriod;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * BIR Filing Tracker — records each required BIR form per period.
 *
 * PH BIR forms tracked:
 *   1601C     — Monthly WHT on Compensation
 *   0619E     — Monthly Creditable Withholding Tax (EWT)
 *   1601EQ    — Quarterly EWT return
 *   2550M     — Monthly VAT declaration
 *   2550Q     — Quarterly VAT return
 *   0605      — Payment form (registration fees etc.)
 *   1702Q     — Quarterly Income Tax Return
 *   1702RT    — Annual Income Tax Return (RCIT)
 *   2307_alpha — Annual alpha list of EWT-subject payees
 *
 * @property int $id
 * @property string $ulid
 * @property string $form_type
 * @property int $fiscal_period_id
 * @property \Illuminate\Support\Carbon $due_date
 * @property int $total_tax_due_centavos
 * @property \Illuminate\Support\Carbon|null $filed_date
 * @property string|null $confirmation_number
 * @property string $status  pending|filed|late|amended|cancelled
 * @property string|null $notes
 * @property int $created_by_id
 * @property int|null $filed_by_id
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @property-read User $createdBy
 * @property-read User|null $filedBy
 * @property-read FiscalPeriod $fiscalPeriod
 */
final class BirFiling extends Model
{
    use HasPublicUlid;
    use SoftDeletes;

    protected $table = 'bir_filings';

    protected $fillable = [
        'form_type',
        'fiscal_period_id',
        'due_date',
        'total_tax_due_centavos',
        'filed_date',
        'confirmation_number',
        'status',
        'notes',
        'created_by_id',
        'filed_by_id',
    ];

    protected $casts = [
        'due_date'                  => 'date',
        'filed_date'                => 'date',
        'total_tax_due_centavos'    => 'integer',
    ];

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function filedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'filed_by_id');
    }

    public function fiscalPeriod(): BelongsTo
    {
        return $this->belongsTo(FiscalPeriod::class, 'fiscal_period_id');
    }

    // ── Accessors / helpers ───────────────────────────────────────────────────

    public function isOverdue(): bool
    {
        return $this->status === 'pending' && now()->isAfter($this->due_date);
    }

    public function isLate(): bool
    {
        return $this->filed_date !== null
            && $this->filed_date->isAfter($this->due_date);
    }
}
