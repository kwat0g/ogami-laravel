<?php

declare(strict_types=1);

namespace App\Domains\CRM\Models;

use App\Models\User;
use App\Shared\Traits\HasPublicUlid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * Ticket — CRM support ticket raised by a client or on behalf of a customer.
 *
 * @property int         $id
 * @property string      $ulid
 * @property int|null    $customer_id
 * @property int|null    $client_user_id
 * @property string      $ticket_number      TKT-YYYY-NNNNN
 * @property string      $subject
 * @property string      $description
 * @property string      $type               complaint|inquiry|request
 * @property string      $priority           low|normal|high|critical
 * @property string      $status             open|in_progress|pending_client|resolved|closed
 * @property int|null    $assigned_to_id
 * @property \Carbon\Carbon|null $resolved_at
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
final class Ticket extends Model implements Auditable
{
    use AuditableTrait, HasPublicUlid, SoftDeletes;

    protected $table = 'crm_tickets';

    protected $fillable = [
        'customer_id',
        'client_user_id',
        'ticket_number',
        'subject',
        'description',
        'type',
        'priority',
        'status',
        'assigned_to_id',
        'resolved_at',
    ];

    protected $casts = [
        'resolved_at' => 'datetime',
    ];

    // ── Relations ────────────────────────────────────────────────────────────

    /** @return BelongsTo<\App\Domains\AR\Models\Customer, Ticket> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(\App\Domains\AR\Models\Customer::class);
    }

    /** @return BelongsTo<User, Ticket> */
    public function clientUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_user_id');
    }

    /** @return BelongsTo<User, Ticket> */
    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_id');
    }

    /** @return HasMany<TicketMessage, Ticket> */
    public function messages(): HasMany
    {
        return $this->hasMany(TicketMessage::class)->orderBy('created_at');
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    public function isOpen(): bool
    {
        return in_array($this->status, ['open', 'in_progress', 'pending_client'], true);
    }

    public function isResolved(): bool
    {
        return in_array($this->status, ['resolved', 'closed'], true);
    }
}
