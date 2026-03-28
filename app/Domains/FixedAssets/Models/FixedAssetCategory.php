<?php

declare(strict_types=1);

namespace App\Domains\FixedAssets\Models;

use App\Models\User;
use App\Shared\Traits\HasPublicUlid;
use Database\Factories\FixedAssetCategoryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * @property int $id
 * @property string $ulid
 * @property string $name
 * @property string $code_prefix
 * @property int $default_useful_life_years
 * @property string $default_depreciation_method
 * @property int|null $gl_asset_account_id
 * @property int|null $gl_depreciation_expense_account_id
 * @property int|null $gl_accumulated_depreciation_account_id
 * @property int $created_by_id
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at
 */
final class FixedAssetCategory extends Model implements Auditable
{
    use AuditableTrait, SoftDeletes, HasFactory, HasPublicUlid, SoftDeletes;

    protected static function newFactory(): FixedAssetCategoryFactory
    {
        return FixedAssetCategoryFactory::new();
    }

    protected $table = 'fixed_asset_categories';

    protected $fillable = [
        'name',
        'code_prefix',
        'default_useful_life_years',
        'default_depreciation_method',
        'gl_asset_account_id',
        'gl_depreciation_expense_account_id',
        'gl_accumulated_depreciation_account_id',
        'created_by_id',
    ];

    /** @return HasMany<FixedAsset, $this> */
    public function assets(): HasMany
    {
        return $this->hasMany(FixedAsset::class, 'category_id');
    }

    /** @return BelongsTo<User, $this> */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }
}
