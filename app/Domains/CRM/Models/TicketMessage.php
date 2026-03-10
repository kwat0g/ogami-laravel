<?php

declare(strict_types=1);

namespace App\Domains\CRM\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * TicketMessage — a message in a CRM ticket thread.
 *
 * @property int    $id
 * @property int    $ticket_id
 * @property int    $author_id
 * @property string $body
 * @property bool   $is_internal  true = staff-only note, hidden from client view
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
final class TicketMessage extends Model
{
    protected $table = 'crm_ticket_messages';

    protected $fillable = [
        'ticket_id',
        'author_id',
        'body',
        'is_internal',
    ];

    protected $casts = [
        'is_internal' => 'boolean',
    ];

    /** @return BelongsTo<Ticket, TicketMessage> */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    /** @return BelongsTo<User, TicketMessage> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }
}
