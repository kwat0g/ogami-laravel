<?php

declare(strict_types=1);

namespace App\Domains\Accounting\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * Chart of Accounts — one node in the GL account hierarchy.
 *
 * @property int $id
 * @property string $code Permanent unique identifier (COA-001)
 * @property string $name
 * @property string $account_type ASSET|LIABILITY|EQUITY|REVENUE|COGS|OPEX|TAX
 * @property int|null $parent_id
 * @property string $normal_balance DEBIT|CREDIT
 * @property bool $is_active
 * @property bool $is_system Protected from archiving/renaming (COA-003)
 * @property bool $is_current PFRS current vs non-current classification
 * @property string|null $bs_classification current_asset|non_current_asset|current_liability|non_current_liability|equity|none
 * @property string|null $cf_classification operating|investing|financing|cash_equivalent|none
 * @property string|null $description
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at Soft-delete = archive
 * @property-read ChartOfAccount|null             $parent
 * @property-read Collection<int, ChartOfAccount> $children
 * @property-read Collection<int, JournalEntryLine> $journalEntryLines
 */
final class ChartOfAccount extends Model implements Auditable
{
    use AuditableTrait, SoftDeletes;

    protected $table = 'chart_of_accounts';

    protected $fillable = [
        'code',
        'name',
        'account_type',
        'parent_id',
        'normal_balance',
        'is_active',
        'is_system',
        'is_current',
        'bs_classification',
        'cf_classification',
        'description',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_system' => 'boolean',
        'is_current' => 'boolean',
    ];

    // ── Relationships ────────────────────────────────────────────────────────

    public function parent(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(ChartOfAccount::class, 'parent_id');
    }

    public function journalEntryLines(): HasMany
    {
        return $this->hasMany(JournalEntryLine::class, 'account_id');
    }

    // ── Business helpers ─────────────────────────────────────────────────────

    /**
     * COA-002: Returns true if this account has children.
     * If true, it cannot be posted to.
     */
    public function isParentNode(): bool
    {
        return $this->children()->whereNull('deleted_at')->exists();
    }

    /**
     * COA-004 / COA-005: Returns true if any posted JE lines exist against this account.
     */
    public function hasPostedLines(): bool
    {
        return $this->journalEntryLines()
            ->whereHas('journalEntry', fn ($q) => $q->where('status', 'posted'))
            ->exists();
    }

    /**
     * Count the depth of this node in the hierarchy (root = 1).
     * COA-006: max depth = 5.
     */
    public function depth(): int
    {
        $depth = 1;
        $parentId = $this->parent_id;

        while ($parentId !== null) {
            $depth++;
            $parentId = self::whereKey($parentId)->value('parent_id');
        }

        return $depth;
    }
}
