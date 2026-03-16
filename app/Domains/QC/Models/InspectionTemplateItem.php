<?php

declare(strict_types=1);

namespace App\Domains\QC\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

final class InspectionTemplateItem extends Model
{
    use SoftDeletes;

    public $timestamps = false;

    protected $table = 'inspection_template_items';

    protected $fillable = [
        'inspection_template_id',
        'criterion',
        'method',
        'acceptable_range',
        'sort_order',
    ];

    /** @return BelongsTo<InspectionTemplate, $this> */
    public function template(): BelongsTo
    {
        return $this->belongsTo(InspectionTemplate::class, 'inspection_template_id');
    }
}
