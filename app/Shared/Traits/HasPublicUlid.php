<?php

declare(strict_types=1);

namespace App\Shared\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

/**
 * Adds a secondary ULID column (`ulid`) that is used for URL routing.
 *
 * The integer primary key is preserved for all FK relationships and DB
 * performance. The ULID is exposed in URLs so route IDs are opaque and
 * non-enumerable — e.g. /hr/employees/01jnk7b3vf000000z2p4hxq1kg
 *
 * Auto-generated on model creation; cannot be overwritten after creation.
 *
 * @property string $ulid
 */
trait HasPublicUlid
{
    public static function bootHasPublicUlid(): void
    {
        static::creating(function (self $model): void {
            if (empty($model->attributes['ulid'])) {
                $model->attributes['ulid'] = (string) Str::ulid();
            }
        });
    }

    /**
     * Use `ulid` as the route key instead of the integer primary key.
     */
    public function getRouteKeyName(): string
    {
        return 'ulid';
    }

    /**
     * Explicit resolver so the Sanctum / route-model-binding pipeline
     * always queries by `ulid` regardless of any $field override.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function resolveRouteBindingQuery($query, $value, $field = null): Builder
    {
        return $query->where('ulid', $value);
    }
}
