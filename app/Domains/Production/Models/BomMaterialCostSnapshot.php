<?php

declare(strict_types=1);

namespace App\Domains\Production\Models;

use App\Shared\Traits\HasPublicUlid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

final class BomMaterialCostSnapshot extends Model
{
    use HasPublicUlid, SoftDeletes;

    protected $table = 'bom_material_cost_snapshots';

    protected $fillable = [
        'bom_id',
        'bom_version',
        'material_cost_centavos',
        'component_lines',
        'source',
        'created_by_id',
    ];

    protected $casts = [
        'material_cost_centavos' => 'integer',
        'component_lines' => 'array',
    ];

    public function bom(): BelongsTo
    {
        return $this->belongsTo(BillOfMaterials::class, 'bom_id');
    }
}
