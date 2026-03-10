<?php

declare(strict_types=1);

namespace App\Http\Controllers\CRM;

use App\Domains\CRM\Models\Ticket;
use App\Domains\CRM\Policies\TicketPolicy;
use App\Domains\CRM\Services\TicketService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * TicketController — thin HTTP layer for CRM ticket management.
 *
 * Delegates all business logic to TicketService.
 * Authorization is handled by TicketPolicy.
 */
final class TicketController extends Controller
{
    public function __construct(private readonly TicketService $service) {}

    // ── List ─────────────────────────────────────────────────────────────────

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Ticket::class);

        $filters = $request->only(['status', 'priority', 'type', 'assigned_to_id', 'search', 'per_page']);

        $paginated = $this->service->list($filters, $request->user());

        return response()->json($paginated);
    }

    // ── Show ─────────────────────────────────────────────────────────────────

    public function show(Ticket $ticket): JsonResponse
    {
        $this->authorize('view', $ticket);

        $ticket->load(['customer', 'assignedTo', 'clientUser', 'messages.author']);

        return response()->json(['data' => $ticket]);
    }

    // ── Create ───────────────────────────────────────────────────────────────

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Ticket::class);

        $validated = $request->validate([
            'subject'         => ['required', 'string', 'max:200'],
            'description'     => ['required', 'string', 'min:10'],
            'type'            => ['required', 'in:complaint,inquiry,request'],
            'priority'        => ['in:low,normal,high,critical'],
            'customer_id'     => ['nullable', 'integer', 'exists:customers,id'],
            'client_user_id'  => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $ticket = $this->service->open($validated, $request->user());

        return response()->json(['data' => $ticket], 201);
    }

    // ── Reply ────────────────────────────────────────────────────────────────

    public function reply(Request $request, Ticket $ticket): JsonResponse
    {
        $this->authorize('reply', $ticket);

        $validated = $request->validate([
            'body'        => ['required', 'string', 'min:1'],
            'is_internal' => ['boolean'],
        ]);

        // Clients cannot post internal notes
        $isInternal = $request->user()->hasRole('client') ? false : (bool) ($validated['is_internal'] ?? false);

        $message = $this->service->reply($ticket, $request->user(), $validated['body'], $isInternal);

        return response()->json(['data' => $message], 201);
    }

    // ── Assign ───────────────────────────────────────────────────────────────

    public function assign(Request $request, Ticket $ticket): JsonResponse
    {
        $this->authorize('assign', Ticket::class);

        $validated = $request->validate([
            'assigned_to_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        $ticket = $this->service->assign($ticket, $request->user(), $validated['assigned_to_id']);

        return response()->json(['data' => $ticket]);
    }

    // ── Resolve ──────────────────────────────────────────────────────────────

    public function resolve(Request $request, Ticket $ticket): JsonResponse
    {
        $this->authorize('resolve', $ticket);

        $validated = $request->validate([
            'resolution_note' => ['nullable', 'string', 'max:2000'],
        ]);

        $ticket = $this->service->resolve($ticket, $request->user(), $validated['resolution_note'] ?? '');

        return response()->json(['data' => $ticket]);
    }

    // ── Close ────────────────────────────────────────────────────────────────

    public function close(Request $request, Ticket $ticket): JsonResponse
    {
        $this->authorize('close', $ticket);

        $ticket = $this->service->close($ticket, $request->user());

        return response()->json(['data' => $ticket]);
    }

    // ── Reopen ───────────────────────────────────────────────────────────────

    public function reopen(Request $request, Ticket $ticket): JsonResponse
    {
        $this->authorize('reopen', $ticket);

        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $ticket = $this->service->reopen($ticket, $request->user(), $validated['reason'] ?? '');

        return response()->json(['data' => $ticket]);
    }
}
