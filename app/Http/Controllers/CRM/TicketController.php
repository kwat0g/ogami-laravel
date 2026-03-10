<?php

declare(strict_types=1);

namespace App\Http\Controllers\CRM;

use App\Domains\CRM\Models\Ticket;
use App\Domains\CRM\Policies\TicketPolicy;
use App\Domains\CRM\Services\TicketService;
use App\Http\Controllers\Controller;
use App\Http\Requests\CRM\AssignTicketRequest;
use App\Http\Requests\CRM\ReopenTicketRequest;
use App\Http\Requests\CRM\ReplyTicketRequest;
use App\Http\Requests\CRM\ResolveTicketRequest;
use App\Http\Requests\CRM\StoreTicketRequest;
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

    public function show(Request $request, Ticket $ticket): JsonResponse
    {
        $this->authorize('view', $ticket);

        $isClientUser = $request->user()?->hasRole('client');

        // Internal notes must NEVER be visible to client portal users.
        $ticket->load([
            'customer',
            'assignedTo',
            'clientUser',
            'messages' => fn ($q) => $q
                ->when($isClientUser, fn ($q2) => $q2->where('is_internal', false))
                ->with('author')
                ->orderBy('created_at'),
        ]);

        return response()->json(['data' => $ticket]);
    }

    // ── Create ───────────────────────────────────────────────────────────────

    public function store(StoreTicketRequest $request): JsonResponse
    {
        $this->authorize('create', Ticket::class);

        $validated = $request->validated();

        $ticket = $this->service->open($validated, $request->user());

        return response()->json(['data' => $ticket], 201);
    }

    // ── Reply ────────────────────────────────────────────────────────────────

    public function reply(ReplyTicketRequest $request, Ticket $ticket): JsonResponse
    {
        $this->authorize('reply', $ticket);

        $validated = $request->validated();

        // Clients cannot post internal notes
        $isInternal = $request->user()->hasRole('client') ? false : (bool) ($validated['is_internal'] ?? false);

        $message = $this->service->reply($ticket, $request->user(), $validated['body'], $isInternal);

        return response()->json(['data' => $message], 201);
    }

    // ── Assign ───────────────────────────────────────────────────────────────

    public function assign(AssignTicketRequest $request, Ticket $ticket): JsonResponse
    {
        $this->authorize('assign', Ticket::class);

        $validated = $request->validated();

        $ticket = $this->service->assign($ticket, $request->user(), $validated['assigned_to_id']);

        return response()->json(['data' => $ticket]);
    }

    // ── Resolve ──────────────────────────────────────────────────────────────

    public function resolve(ResolveTicketRequest $request, Ticket $ticket): JsonResponse
    {
        $this->authorize('resolve', $ticket);

        $validated = $request->validated();

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

    public function reopen(ReopenTicketRequest $request, Ticket $ticket): JsonResponse
    {
        $this->authorize('reopen', $ticket);

        $validated = $request->validated();

        $ticket = $this->service->reopen($ticket, $request->user(), $validated['reason'] ?? '');

        return response()->json(['data' => $ticket]);
    }
}
