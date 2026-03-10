<?php

declare(strict_types=1);

namespace App\Domains\FixedAssets\Models;

use App\Domains\Accounting\Models\FiscalPeriod;
use App\Domains\Accounting\Models\JournalEntry;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int                        $id
 * @property int                        $fixed_asset_id
 * @property int                        $fiscal_period_id
 * @property int                        $depreciation_amount_centavos
 * @property string                     $method
 * @property int|null                   $journal_entry_id
 * @property int                        $computed_by_id
 * @property \Illuminate\Support\Carbon $created_at
 */
final class AssetDepreciationEntry extends Model
{
    public $timestamps = false;

    protected $table = 'fixed_asset_depreciation_entries';

    protected $fillable = [
        'fixed_asset_id',
        'fiscal_period_id',
        'depreciation_amount_centavos',
        'method',
        'journal_entry_id',
        'computed_by_id',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'created_at' => 'datetime',
    ];

    /** @return BelongsTo<FixedAsset, $this> */
    public function fixedAsset(): BelongsTo
    {
        return $this->belongsTo(FixedAsset::class, 'fixed_asset_id');
    }

    /** @return BelongsTo<FiscalPeriod, $this> */
    public function fiscalPeriod(): BelongsTo
    {
        return $this->belongsTo(FiscalPeriod::class, 'fiscal_period_id');
    }

    /** @return BelongsTo<JournalEntry, $this> */
    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'journal_entry_id');
    }

    /** @return BelongsTo<User, $this> */
    public function computedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'computed_by_id');
    }
}
