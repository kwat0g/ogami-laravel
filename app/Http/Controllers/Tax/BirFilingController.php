<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tax;

use App\Domains\Tax\Models\BirFiling;
use App\Domains\Tax\Services\BirFilingService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tax\CalendarBirFilingRequest;
use App\Http\Requests\Tax\MarkAmendedRequest;
use App\Http\Requests\Tax\MarkFiledRequest;
use App\Http\Requests\Tax\ScheduleBirFilingRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class BirFilingController extends Controller
{
    public function __construct(private readonly BirFilingService $service) {}

    /**
     * List all BIR filings, optionally filtered by fiscal year or status.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', BirFiling::class);

        $query = BirFiling::with('fiscalPeriod', 'filedBy')->orderBy('due_date');

        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->value());
        }

        if ($request->filled('form_type')) {
            $query->where('form_type', $request->string('form_type')->value());
        }

        if ($request->filled('fiscal_year')) {
            $year = $request->integer('fiscal_year');
            $query->whereHas('fiscalPeriod', fn ($q) => $q->whereYear('date_from', $year));
        }

        return response()->json(['data' => $query->get()]);
    }

    /**
     * Schedule a new BIR filing (idempotent per form_type + fiscal_period_id).
     */
    public function schedule(ScheduleBirFilingRequest $request): JsonResponse
    {
        $this->authorize('create', BirFiling::class);

        $data = $request->validated();

        $filing = $this->service->schedule($data, $request->user());

        return response()->json(['data' => $filing->load('fiscalPeriod')], 201);
    }

    /**
     * Mark a filing as filed with confirmation details.
     */
    public function markFiled(MarkFiledRequest $request, BirFiling $birFiling): JsonResponse
    {
        $this->authorize('update', $birFiling);

        $data = $request->validated();

        $updated = $this->service->markFiled($birFiling, $data, $request->user());

        return response()->json(['data' => $updated->load('fiscalPeriod', 'filedBy')]);
    }

    /**
     * Mark a previously filed return as amended.
     */
    public function markAmended(MarkAmendedRequest $request, BirFiling $birFiling): JsonResponse
    {
        $this->authorize('update', $birFiling);

        $data = $request->validated();

        $updated = $this->service->markAmended($birFiling, $data, $request->user());

        return response()->json(['data' => $updated]);
    }

    /**
     * Return all overdue pending filings.
     */
    public function overdue(): JsonResponse
    {
        $this->authorize('viewAny', BirFiling::class);

        return response()->json(['data' => $this->service->getOverdue()]);
    }

    /**
     * Return filing calendar grouped by form type for a fiscal year.
     */
    public function calendar(CalendarBirFilingRequest $request): JsonResponse
    {
        $this->authorize('viewAny', BirFiling::class);

        $data = $request->validated();

        return response()->json(['data' => $this->service->getCalendar($data['fiscal_year'])]);
    }
}
