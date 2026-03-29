<?php

declare(strict_types=1);

namespace App\Domains\HR\Recruitment\Models;

use App\Domains\HR\Models\Employee;
use App\Domains\HR\Recruitment\Enums\CandidateSource;
use Database\Factories\Recruitment\CandidateFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * @property int $id
 * @property string $first_name
 * @property string $last_name
 * @property string $email
 * @property string|null $phone
 * @property string|null $address
 * @property string $source
 * @property string|null $resume_path
 * @property string|null $linkedin_url
 * @property string|null $notes
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read string $full_name
 */
final class Candidate extends Model implements Auditable
{
    /** @use HasFactory<CandidateFactory> */
    use AuditableTrait, HasFactory, SoftDeletes;

    protected $table = 'candidates';

    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'phone',
        'address',
        'source',
        'resume_path',
        'linkedin_url',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'source' => CandidateSource::class,
        ];
    }

    protected static function newFactory(): CandidateFactory
    {
        return CandidateFactory::new();
    }

    // ── Accessors ─────────────────────────────────────────────────────────

    public function getFullNameAttribute(): string
    {
        return "{$this->first_name} {$this->last_name}";
    }

    // ── Relationships ─────────────────────────────────────────────────────

    public function applications(): HasMany
    {
        return $this->hasMany(Application::class);
    }

    public function employee(): HasOne
    {
        return $this->hasOne(Employee::class, 'id', 'id'); // linked via hiring
    }
}
