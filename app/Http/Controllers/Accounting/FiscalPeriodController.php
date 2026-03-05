<?php

declare(strict_types=1);

namespace App\Http\Controllers\Accounting;

use App\Domains\Accounting\Models\FiscalPeriod;
use App\Domains\Accounting\Services\FiscalPeriodService;
use App\Http\Controllers\Controller;
use App\Http\Resources\Accounting\FiscalPeriodResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class FiscalPeriodController extends Controller
{
    public function __construct(
        private readonly FiscalPeriodService $service,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', FiscalPeriod::class);

        $periods = FiscalPeriod::orderByDesc('date_from')
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->paginate(24);

        return FiscalPeriodResource::collection($periods);
    }

    public function store(Request $request): FiscalPeriodResource
    {
        $this->authorize('create', FiscalPeriod::class);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:50'],
            'date_from' => ['required', 'date'],
            'date_to' => ['required', 'date', 'after_or_equal:date_from'],
        ]);

        $period = $this->service->create($validated);

        return new FiscalPeriodResource($period);
    }

    public function show(FiscalPeriod $fiscalPeriod): FiscalPeriodResource
    {
        $this->authorize('view', $fiscalPeriod);

        return new FiscalPeriodResource($fiscalPeriod);
    }

    /**
     * Re-open a closed fiscal period.
     */
    public function open(FiscalPeriod $fiscalPeriod): FiscalPeriodResource
    {
        $this->authorize('open', $fiscalPeriod);

        $updated = $this->service->open($fiscalPeriod);

        return new FiscalPeriodResource($updated);
    }

    /**
     * Close a fiscal period. Rejects if open JE drafts remain.
     */
    public function close(FiscalPeriod $fiscalPeriod): FiscalPeriodResource
    {
        $this->authorize('close', $fiscalPeriod);

        $updated = $this->service->close($fiscalPeriod);

        return new FiscalPeriodResource($updated);
    }
}
