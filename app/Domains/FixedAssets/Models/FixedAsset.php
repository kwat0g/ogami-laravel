<?php

declare(strict_types=1);

namespace App\Domains\FixedAssets\Models;

use App\Domains\HR\Models\Department;
use App\Models\User;
use App\Shared\Traits\HasPublicUlid;
use Database\Factories\FixedAssetFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * Fixed Asset — individual capitalised item in the asset register.
 *
 * @property int $id
 * @property string $ulid
 * @property string $asset_code Auto-generated: {PREFIX}-YYYY-NNNN
 * @property int $category_id
 * @property int|null $department_id
 * @property string $name
 * @property string|null $description
 * @property string|null $serial_number
 * @property string|null $location
 * @property Carbon $acquisition_date
 * @property int $acquisition_cost_centavos
 * @property int $residual_value_centavos
 * @property int $useful_life_years
 * @property string $depreciation_method straight_line|double_declining|units_of_production
 * @property int $accumulated_depreciation_centavos
 * @property string $status active|fully_depreciated|disposed|impaired
 * @property string|null $purchase_invoice_ref
 * @property string|null $purchased_from
 * @property Carbon|null $disposal_date
 * @property int $created_by_id
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at
 */
final class FixedAsset extends Model implements Auditable
{
    use AuditableTrait, HasFactory, HasPublicUlid, SoftDeletes;

    protected static function newFactory(): FixedAssetFactory
    {
        return FixedAssetFactory::new();
    }

    protected $table = 'fixed_assets';

    protected $fillable = [
        'asset_code',
        'category_id',
        'department_id',
        'name',
        'description',
        'serial_number',
        'location',
        'acquisition_date',
        'acquisition_cost_centavos',
        'residual_value_centavos',
        'useful_life_years',
        'depreciation_method',
        'accumulated_depreciation_centavos',
        'status',
        'purchase_invoice_ref',
        'purchased_from',
        'disposal_date',
        'created_by_id',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'acquisition_date' => 'date',
        'disposal_date' => 'date',
    ];

    /**
     * Current net book value in centavos.
     */
    public function bookValueCentavos(): int
    {
        return max(0, $this->acquisition_cost_centavos - $this->accumulated_depreciation_centavos);
    }

    /**
     * Depreciable amount (cost − residual value).
     */
    public function depreciableAmountCentavos(): int
    {
        return max(0, $this->acquisition_cost_centavos - $this->residual_value_centavos - $this->accumulated_depreciation_centavos);
    }

    /** @return BelongsTo<FixedAssetCategory, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(FixedAssetCategory::class, 'category_id');
    }

    /** @return BelongsTo<Department, $this> */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    /** @return HasMany<AssetDepreciationEntry, $this> */
    public function depreciationEntries(): HasMany
    {
        return $this->hasMany(AssetDepreciationEntry::class, 'fixed_asset_id');
    }

    /** @return HasOne<AssetDisposal, $this> */
    public function disposal(): HasOne
    {
        return $this->hasOne(AssetDisposal::class, 'fixed_asset_id');
    }

    /** @return BelongsTo<User, $this> */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }
}
