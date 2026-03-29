<?php

declare(strict_types=1);

namespace App\Domains\Accounting\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Configurable GL account mapping — replaces hardcoded account lookups (F-002).
 *
 * @property int $id
 * @property string $module
 * @property string $event
 * @property string|null $sub_key
 * @property string $side  debit|credit
 * @property int $account_id
 * @property string|null $description
 * @property bool $is_active
 * @property-read ChartOfAccount $account
 */
final class AccountMapping extends Model
{
    protected $table = 'account_mappings';

    protected $fillable = [
        'module',
        'event',
        'sub_key',
        'side',
        'account_id',
        'description',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /** @return BelongsTo<ChartOfAccount, $this> */
    public function account(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'account_id');
    }

    /**
     * Look up a GL account ID by module, event, side, and optional sub_key.
     *
     * @throws \App\Shared\Exceptions\DomainException if mapping not found
     */
    public static function resolve(string $module, string $event, string $side, ?string $subKey = null): int
    {
        $query = self::where('module', $module)
            ->where('event', $event)
            ->where('side', $side)
            ->where('is_active', true);

        if ($subKey !== null) {
            $query->where('sub_key', $subKey);
        } else {
            $query->whereNull('sub_key');
        }

        $mapping = $query->first();

        if ($mapping === null) {
            throw new \App\Shared\Exceptions\DomainException(
                "GL account mapping not found for module={$module}, event={$event}, side={$side}, sub_key={$subKey}. "
                .'Configure this mapping in the Account Mappings admin page.',
                'GL_MAPPING_NOT_FOUND',
                422,
                ['module' => $module, 'event' => $event, 'side' => $side, 'sub_key' => $subKey],
            );
        }

        return $mapping->account_id;
    }
}
