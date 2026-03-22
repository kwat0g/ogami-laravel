<?php

declare(strict_types=1);

namespace App\Domains\CRM\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Client Order Activity - Audit trail for all actions on client orders
 */
class ClientOrderActivity extends Model
{
    use HasFactory;

    public const ACTION_SUBMITTED = 'submitted';
    public const ACTION_APPROVED = 'approved';
    public const ACTION_REJECTED = 'rejected';
    public const ACTION_NEGOTIATED = 'negotiated';
    public const ACTION_CLIENT_RESPONDED = 'client_responded';
    public const ACTION_SALES_RESPONDED = 'sales_responded'; // Sales response to client counter
    public const ACTION_CANCELLED = 'cancelled';
    public const ACTION_NOTE_ADDED = 'note_added';

    protected $fillable = [
        'client_order_id',
        'user_id',
        'user_type',
        'action',
        'from_status',
        'to_status',
        'comment',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    // Relationships
    public function clientOrder(): BelongsTo
    {
        return $this->belongsTo(ClientOrder::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // Helper methods
    public function isClientAction(): bool
    {
        return $this->user_type === 'client';
    }

    public function getMetadataValue(string $key, mixed $default = null): mixed
    {
        return $this->metadata[$key] ?? $default;
    }
}
