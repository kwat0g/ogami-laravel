<?php

declare(strict_types=1);

namespace App\Domains\QC\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * Inspection result - tracks QC measurements and conformance.
 * HIGH-002: Audit trail enabled for quality control traceability.
 */
final class InspectionResult extends Model implements Auditable
{
    use AuditableTrait, SoftDeletes;

    public $timestamps = false;

    /**
     * Attributes to include in the audit trail.
     * QC measurements are audited for compliance tracking.
     *
     * @var list<string>
     */
    protected $auditInclude = [
        'inspection_id',
        'inspection_template_item_id',
        'criterion',
        'actual_value',
        'is_conforming',
        'remarks',
    ];

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
