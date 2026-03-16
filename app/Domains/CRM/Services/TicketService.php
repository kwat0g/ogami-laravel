<?php

declare(strict_types=1);

namespace App\Domains\CRM\Services;

use App\Domains\CRM\Models\Ticket;
use App\Domains\CRM\Models\TicketMessage;
use App\Models\User;
use App\Shared\Contracts\ServiceContract;
use App\Shared\Exceptions\DomainException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * TicketService — CRM ticket lifecycle management.
 *
 * Status transitions:
 *   open → in_progress (when assigned or replied to by staff)
 *   in_progress → pending_client (waiting for client response)
 *   * → resolved (staff resolves)
 *   resolved → closed (staff closes or auto-close)
 *   resolved|closed → open (reopen by client or staff)
 */
final class TicketService implements ServiceContract
{
    // ── Query ────────────────────────────────────────────────────────────────

    /**
     * @param  array<string, mixed>  $filters
     */
    public function list(array $filters = [], ?User $actor = null): LengthAwarePaginator
    {
        $query = Ticket::with(['customer', 'assignedTo', 'clientUser'])
            ->orderByRaw("CASE priority WHEN 'critical' THEN 0 WHEN 'high' THEN 1 WHEN 'normal' THEN 2 ELSE 3 END")
            ->orderBy('created_at', 'desc');

        // Client users only see their own tickets
        if ($actor && $actor->hasRole('client')) {
            $query->where('client_user_id', $actor->id);
        }

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (isset($filters['priority'])) {
            $query->where('priority', $filters['priority']);
        }
        if (isset($filters['type'])) {
            $query->where('type', $filters['type']);
        }
        if (isset($filters['assigned_to_id'])) {
            $query->where('assigned_to_id', $filters['assigned_to_id']);
        }
        if (isset($filters['search'])) {
            $search = $filters['search'];
            $query->where(function (Builder $q) use ($search): void {
                $q->where('subject', 'ilike', "%{$search}%")
                    ->orWhere('ticket_number', 'ilike', "%{$search}%");
            });
        }

        return $query->paginate((int) ($filters['per_page'] ?? 20));
    }

    // ── Mutations ────────────────────────────────────────────────────────────

    /**
     * Open a new ticket.
     *
     * @param  array<string, mixed>  $data
     */
    public function open(array $data, User $actor): Ticket
    {
        return DB::transaction(function () use ($data, $actor): Ticket {
            $priority = $data['priority'] ?? 'normal';

            $ticket = Ticket::create([
                'customer_id' => $data['customer_id'] ?? null,
                'client_user_id' => $actor->hasRole('client') ? $actor->id : ($data['client_user_id'] ?? null),
                'ticket_number' => $this->generateTicketNumber(),
                'subject' => $data['subject'],
                'description' => $data['description'],
                'type' => $data['type'],
                'priority' => $priority,
                'status' => 'open',
                'assigned_to_id' => null,
                'sla_due_at' => now()->addHours(Ticket::slaHoursForPriority($priority)),
            ]);

            // Auto-post opening description as first message
            TicketMessage::create([
                'ticket_id' => $ticket->id,
                'author_id' => $actor->id,
                'body' => $data['description'],
                'is_internal' => false,
            ]);

            return $ticket;
        });
    }

    /**
     * Add a reply to a ticket thread.
     */
    public function reply(Ticket $ticket, User $actor, string $body, bool $isInternal = false): TicketMessage
    {
        if ($ticket->status === 'closed') {
            throw new DomainException(
                message: 'Cannot reply to a closed ticket. Reopen it first.',
                errorCode: 'TICKET_CLOSED',
                httpStatus: 422,
            );
        }

        return DB::transaction(function () use ($ticket, $actor, $body, $isInternal): TicketMessage {
            $message = TicketMessage::create([
                'ticket_id' => $ticket->id,
                'author_id' => $actor->id,
                'body' => $body,
                'is_internal' => $isInternal,
            ]);

            // Transition status based on who replied
            if ($actor->hasRole('client')) {
                if ($ticket->status === 'pending_client') {
                    $ticket->update(['status' => 'in_progress']);
                }
            } else {
                if ($ticket->status === 'open') {
                    $ticket->update(['status' => 'in_progress']);
                }
                // Record first staff response for SLA tracking
                if (! $isInternal && $ticket->first_response_at === null) {
                    $ticket->update(['first_response_at' => now()]);
                }
            }

            return $message;
        });
    }

    /**
     * Assign a ticket to a staff user.
     */
    public function assign(Ticket $ticket, User $actor, int $assigneeId): Ticket
    {
        return DB::transaction(function () use ($ticket, $actor, $assigneeId): Ticket {
            $ticket->update([
                'assigned_to_id' => $assigneeId,
                'status' => $ticket->status === 'open' ? 'in_progress' : $ticket->status,
            ]);

            TicketMessage::create([
                'ticket_id' => $ticket->id,
                'author_id' => $actor->id,
                'body' => "Ticket assigned to user #{$assigneeId}.",
                'is_internal' => true,
            ]);

            return $ticket->fresh();
        });
    }

    /**
     * Resolve a ticket (staff only).
     */
    public function resolve(Ticket $ticket, User $actor, string $resolutionNote = ''): Ticket
    {
        if ($ticket->isResolved()) {
            throw new DomainException(
                message: 'Ticket is already resolved or closed.',
                errorCode: 'TICKET_ALREADY_RESOLVED',
                httpStatus: 422,
            );
        }

        return DB::transaction(function () use ($ticket, $actor, $resolutionNote): Ticket {
            $ticket->update([
                'status' => 'resolved',
                'resolved_at' => now(),
            ]);

            if ($resolutionNote) {
                TicketMessage::create([
                    'ticket_id' => $ticket->id,
                    'author_id' => $actor->id,
                    'body' => $resolutionNote,
                    'is_internal' => false,
                ]);
            }

            return $ticket->fresh();
        });
    }

    /**
     * Close a resolved ticket permanently.
     */
    public function close(Ticket $ticket, User $actor): Ticket
    {
        if ($ticket->status === 'closed') {
            throw new DomainException(
                message: 'Ticket is already closed.',
                errorCode: 'TICKET_ALREADY_CLOSED',
                httpStatus: 422,
            );
        }

        return DB::transaction(function () use ($ticket, $actor): Ticket {
            $ticket->update(['status' => 'closed']);

            TicketMessage::create([
                'ticket_id' => $ticket->id,
                'author_id' => $actor->id,
                'body' => 'Ticket closed.',
                'is_internal' => true,
            ]);

            return $ticket->fresh();
        });
    }

    /**
     * Reopen a resolved or closed ticket.
     */
    public function reopen(Ticket $ticket, User $actor, string $reason = ''): Ticket
    {
        if (! $ticket->isResolved()) {
            throw new DomainException(
                message: 'Only resolved or closed tickets can be reopened.',
                errorCode: 'TICKET_NOT_RESOLVED',
                httpStatus: 422,
            );
        }

        return DB::transaction(function () use ($ticket, $actor, $reason): Ticket {
            $ticket->update([
                'status' => 'open',
                'resolved_at' => null,
            ]);

            TicketMessage::create([
                'ticket_id' => $ticket->id,
                'author_id' => $actor->id,
                'body' => $reason ?: 'Ticket reopened.',
                'is_internal' => false,
            ]);

            return $ticket->fresh();
        });
    }

    // ── Private ──────────────────────────────────────────────────────────────

    private function generateTicketNumber(): string
    {
        $year = now()->format('Y');
        $seq = (Ticket::whereYear('created_at', $year)->withTrashed()->count() + 1);

        return sprintf('TKT-%s-%05d', $year, $seq);
    }
}
