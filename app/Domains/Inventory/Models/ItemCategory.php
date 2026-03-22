<?php

declare(strict_types=1);

namespace App\Domains\Inventory\Models;

use Database\Factories\ItemCategoryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property string $code
 * @property string $name
 * @property string|null $description
 * @property bool $is_active
 */
final class ItemCategory extends Model
{
    use HasFactory, SoftDeletes;

    protected static function newFactory(): ItemCategoryFactory
    {
        return ItemCategoryFactory::new();
    }

    protected $table = 'item_categories';

    protected $fillable = ['code', 'name', 'description', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    /** @return HasMany<ItemMaster, ItemCategory> */
    public function items(): HasMany
    {
        return $this->hasMany(ItemMaster::class, 'category_id');
    }
}
