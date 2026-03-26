<?php

declare(strict_types=1);

namespace App\Http\Controllers\Accounting;

use App\Domains\Accounting\Models\JournalEntry;
use App\Domains\Accounting\Models\JournalEntryTemplate;
use App\Domains\Accounting\Services\JournalEntryService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Accounting\CreateJournalEntryRequest;
use App\Http\Resources\Accounting\JournalEntryResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class JournalEntryController extends Controller
{
    public function __construct(
        private readonly JournalEntryService $service,
    ) {}

    /**
     * List journal entries with optional filters:
     *   ?status=draft|submitted|posted|cancelled|stale
     *   ?fiscal_period_id=X
     *   ?date_from=YYYY-MM-DD&date_to=YYYY-MM-DD
     *   ?source_type=manual|payroll|ap|ar
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', JournalEntry::class);

        $query = JournalEntry::with(['fiscalPeriod', 'creator'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->when($request->filled('fiscal_period_id'), fn ($q) => $q->where('fiscal_period_id', $request->integer('fiscal_period_id')))
            ->when($request->filled('source_type'), fn ($q) => $q->where('source_type', $request->input('source_type')))
            ->when($request->filled('date_from'), fn ($q) => $q->whereDate('date', '>=', $request->input('date_from')))
            ->when($request->filled('date_to'), fn ($q) => $q->whereDate('date', '<=', $request->input('date_to')))
            ->orderByDesc('date')
            ->orderByDesc('id');

        return JournalEntryResource::collection($query->paginate(30));
    }

    /**
     * Create a new DRAFT journal entry.
     */
    public function store(CreateJournalEntryRequest $request): JournalEntryResource
    {
        $this->authorize('create', JournalEntry::class);

        $je = $this->service->create($request->validated());

        return new JournalEntryResource($je->load('lines.account', 'fiscalPeriod'));
    }

    public function show(JournalEntry $journalEntry): JournalEntryResource
    {
        $this->authorize('view', $journalEntry);

        return new JournalEntryResource($journalEntry->load('lines.account', 'fiscalPeriod', 'creator', 'submitter', 'poster', 'reversalOf'));
    }

    /**
     * Submit a draft JE for posting.
     */
    public function submit(JournalEntry $journalEntry): JournalEntryResource
    {
        $this->authorize('submit', $journalEntry);

        $je = $this->service->submit($journalEntry);

        return new JournalEntryResource($je->load('lines.account', 'fiscalPeriod'));
    }

    /**
     * Post a submitted JE (enforces SoD — poster ≠ drafter).
     */
    public function post(JournalEntry $journalEntry): JournalEntryResource
    {
        $this->authorize('post', $journalEntry);

        $je = $this->service->post($journalEntry);

        return new JournalEntryResource($je->load('lines.account', 'fiscalPeriod'));
    }

    /**
     * Create a reversing JE (JE-007).
     */
    public function reverse(Request $request, JournalEntry $journalEntry): JournalEntryResource
    {
        $this->authorize('reverse', $journalEntry);

        $validated = $request->validate([
            'description' => ['sometimes', 'nullable', 'string', 'max:500'],
        ]);

        $reversalJe = $this->service->reverse($journalEntry, $validated['description'] ?? '');

        return new JournalEntryResource($reversalJe->load('lines.account', 'fiscalPeriod'));
    }

    /**
     * Cancel a draft/submitted JE.
     */
    public function cancel(JournalEntry $journalEntry): JsonResponse
    {
        $this->authorize('delete', $journalEntry);

        $this->service->cancel($journalEntry);

        return response()->json(['message' => 'Journal entry has been cancelled.']);
    }

    // ── Template Methods ─────────────────────────────────────────────────────

    /**
     * List available journal entry templates
     */
    public function templates(Request $request): JsonResponse
    {
        $templates = $this->service->getTemplates($request->user()?->id);

        return response()->json(['data' => $templates]);
    }

    /**
     * Apply a template to get pre-filled lines
     */
    public function applyTemplate(Request $request, int $templateId): JsonResponse
    {
        $result = $this->service->applyTemplate($templateId);

        return response()->json($result);
    }

    /**
     * Create a new custom template
     */
    public function storeTemplate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'lines' => 'required|array|min:2',
            'lines.*.account_id' => 'required|integer|exists:chart_of_accounts,id',
            'lines.*.debit_or_credit' => 'required|in:debit,credit',
            'lines.*.description' => 'nullable|string',
        ]);

        $template = $this->service->createTemplate($validated, $request->user()->id);

        return response()->json([
            'message' => 'Template created successfully',
            'template' => [
                'id' => $template->id,
                'name' => $template->name,
                'description' => $template->description,
            ],
        ], 201);
    }

    /**
     * Delete a user-created template
     */
    public function deleteTemplate(Request $request, int $templateId): JsonResponse
    {
        $template = JournalEntryTemplate::findOrFail($templateId);

        $this->service->deleteTemplate($template, $request->user()->id);

        return response()->json(['message' => 'Template deleted successfully']);
    }
}
