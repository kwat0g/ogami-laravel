<?php

declare(strict_types=1);

namespace App\Domains\FixedAssets\Models;

use App\Domains\Accounting\Models\JournalEntry;
use App\Models\User;
use App\Shared\Traits\HasPublicUlid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * @property int                             $id
 * @property string                          $ulid
 * @property int                             $fixed_asset_id
 * @property \Illuminate\Support\Carbon      $disposal_date
 * @property int                             $proceeds_centavos
 * @property string                          $disposal_method         sale|scrap|donation|write_off
 * @property int                             $gain_loss_centavos       positive = gain; negative = loss
 * @property int|null                        $journal_entry_id
 * @property string|null                     $notes
 * @property int|null                        $approved_by_id
 * @property int                             $created_by_id
 * @property \Illuminate\Support\Carbon      $created_at
 * @property \Illuminate\Support\Carbon      $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 */
final class AssetDisposal extends Model implements Auditable
{
    use AuditableTrait, HasPublicUlid, SoftDeletes;

    protected $table = 'fixed_asset_disposals';

    protected $fillable = [
        'fixed_asset_id',
        'disposal_date',
        'proceeds_centavos',
        'disposal_method',
        'gain_loss_centavos',
        'journal_entry_id',
        'notes',
        'approved_by_id',
        'created_by_id',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'disposal_date' => 'date',
    ];

    /** @return BelongsTo<FixedAsset, $this> */
    public function fixedAsset(): BelongsTo
    {
        return $this->belongsTo(FixedAsset::class, 'fixed_asset_id');
    }

    /** @return BelongsTo<JournalEntry, $this> */
    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'journal_entry_id');
    }

    /** @return BelongsTo<User, $this> */
    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_id');
    }

    /** @return BelongsTo<User, $this> */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }
}
