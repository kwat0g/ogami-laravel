<?php

declare(strict_types=1);

namespace App\Http\Controllers\Accounting;

use App\Domains\Accounting\Models\RecurringJournalTemplate;
use App\Domains\Accounting\Services\RecurringJournalTemplateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

final class RecurringJournalTemplateController extends Controller
{
    public function __construct(
        private readonly RecurringJournalTemplateService $service,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', RecurringJournalTemplate::class);

        $templates = RecurringJournalTemplate::query()
            ->when(
                $request->boolean('active_only'),
                fn ($q) => $q->where('is_active', true),
            )
            ->orderBy('description')
            ->paginate((int) $request->input('per_page', 20));

        return response()->json($templates);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', RecurringJournalTemplate::class);

        $data = $request->validate([
            'description'         => 'required|string|max:500',
            'frequency'           => 'required|string|in:daily,weekly,monthly,semi_monthly,annual',
            'day_of_month'        => 'nullable|integer|min:1|max:28',
            'next_run_date'       => 'required|date',
            'lines'               => 'required|array|min:2',
            'lines.*.account_id'  => 'required|integer|exists:chart_of_accounts,id',
            'lines.*.debit'       => 'nullable|numeric|min:0',
            'lines.*.credit'      => 'nullable|numeric|min:0',
            'lines.*.description' => 'nullable|string|max:500',
        ]);

        $template = $this->service->store($data, $request->user());

        return response()->json(['data' => $template], 201);
    }

    public function show(RecurringJournalTemplate $recurringJournalTemplate): JsonResponse
    {
        $this->authorize('view', $recurringJournalTemplate);

        return response()->json(['data' => $recurringJournalTemplate]);
    }

    public function update(Request $request, RecurringJournalTemplate $recurringJournalTemplate): JsonResponse
    {
        $this->authorize('update', $recurringJournalTemplate);

        $data = $request->validate([
            'description'         => 'sometimes|string|max:500',
            'frequency'           => 'sometimes|string|in:daily,weekly,monthly,semi_monthly,annual',
            'day_of_month'        => 'nullable|integer|min:1|max:28',
            'next_run_date'       => 'sometimes|date',
            'lines'               => 'sometimes|array|min:2',
            'lines.*.account_id'  => 'required_with:lines|integer|exists:chart_of_accounts,id',
            'lines.*.debit'       => 'nullable|numeric|min:0',
            'lines.*.credit'      => 'nullable|numeric|min:0',
            'lines.*.description' => 'nullable|string|max:500',
        ]);

        $template = $this->service->update($recurringJournalTemplate, $data);

        return response()->json(['data' => $template]);
    }

    public function toggle(RecurringJournalTemplate $recurringJournalTemplate): JsonResponse
    {
        $this->authorize('update', $recurringJournalTemplate);

        $template = $this->service->toggle($recurringJournalTemplate);

        return response()->json(['data' => $template]);
    }

    public function destroy(RecurringJournalTemplate $recurringJournalTemplate): JsonResponse
    {
        $this->authorize('delete', $recurringJournalTemplate);

        $recurringJournalTemplate->delete();

        return response()->json(null, 204);
    }
}
