<?php

declare(strict_types=1);

namespace App\Domains\HR\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * @property int $id
 * @property int $employee_id
 * @property string $category government_id|contract|certificate|medical|tax|bank|training|other
 * @property string $document_name
 * @property string|null $file_path
 * @property string|null $notes
 * @property Carbon|null $expires_at
 * @property bool $is_verified
 * @property int|null $verified_by FK users.id
 * @property Carbon|null $verified_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Employee $employee
 */
final class EmployeeDocument extends Model implements Auditable
{
    use AuditableTrait, SoftDeletes, SoftDeletes;

    protected $table = 'employee_documents';

    /** @var list<string> */
    protected $fillable = [
        'employee_id',
        'category',
        'document_name',
        'file_path',
        'notes',
        'expires_at',
        'is_verified',
        'verified_by',
        'verified_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'expires_at' => 'date',
            'verified_at' => 'datetime',
            'is_verified' => 'boolean',
        ];
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    // ── Relations ─────────────────────────────────────────────────────────────

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
