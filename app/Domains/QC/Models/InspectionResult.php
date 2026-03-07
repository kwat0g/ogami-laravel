<?php

declare(strict_types=1);

namespace App\Domains\QC\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class InspectionResult extends Model
{
    use SoftDeletes;

    public $timestamps = false;

    const CREATED_AT = 'created_at';

    protected $table = 'inspection_results';

    protected $fillable = [
        'inspection_id',
        'inspection_template_item_id',
        'criterion',
        'actual_value',
        'is_conforming',
        'remarks',
    ];

    protected $casts = [
        'is_conforming' => 'boolean',
    ];

    /** @return BelongsTo<Inspection, $this> */
    public function inspection(): BelongsTo
    {
        return $this->belongsTo(Inspection::class, 'inspection_id');
    }

    /** @return BelongsTo<InspectionTemplateItem, $this> */
    public function templateItem(): BelongsTo
    {
        return $this->belongsTo(InspectionTemplateItem::class, 'inspection_template_item_id');
    }
}
