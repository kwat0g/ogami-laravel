<?php

declare(strict_types=1);

namespace App\Http\Controllers\CRM;

use App\Domains\CRM\Models\Opportunity;
use App\Domains\CRM\Services\OpportunityService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class OpportunityController extends Controller
{
    public function __construct(private readonly OpportunityService $service) {}

    public function index(Request $request): JsonResponse
    {
        $page = $this->service->paginate($request->only([
            'stage', 'customer_id', 'assigned_to_id', 'per_page',
        ]));

        return response()->json($page);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'contact_id' => ['sometimes', 'integer', 'exists:crm_contacts,id'],
            'title' => ['required', 'string', 'max:255'],
            'expected_value_centavos' => ['sometimes', 'integer', 'min:0'],
            'probability_pct' => ['sometimes', 'integer', 'min:0', 'max:100'],
            'expected_close_date' => ['sometimes', 'date'],
            'assigned_to_id' => ['sometimes', 'integer', 'exists:users,id'],
            'notes' => ['sometimes', 'string'],
        ]);

        $opportunity = $this->service->store($data, $request->user());

        return response()->json(['data' => $opportunity], 201);
    }

    public function show(Opportunity $opportunity): JsonResponse
    {
        return response()->json([
            'data' => $opportunity->load(['customer', 'contact', 'assignedTo', 'activities']),
        ]);
    }

    public function update(Request $request, Opportunity $opportunity): JsonResponse
    {
        $data = $request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'contact_id' => ['sometimes', 'integer', 'exists:crm_contacts,id'],
            'expected_value_centavos' => ['sometimes', 'integer', 'min:0'],
            'probability_pct' => ['sometimes', 'integer', 'min:0', 'max:100'],
            'expected_close_date' => ['sometimes', 'date'],
            'stage' => ['sometimes', 'string'],
            'assigned_to_id' => ['sometimes', 'integer', 'exists:users,id'],
            'notes' => ['sometimes', 'string'],
        ]);

        return response()->json(['data' => $this->service->update($opportunity, $data)]);
    }

    public function closeWon(Opportunity $opportunity): JsonResponse
    {
        return response()->json(['data' => $this->service->closeWon($opportunity)]);
    }

    public function closeLost(Request $request, Opportunity $opportunity): JsonResponse
    {
        $data = $request->validate(['reason' => ['required', 'string']]);

        return response()->json(['data' => $this->service->closeLost($opportunity, $data['reason'])]);
    }

    public function pipeline(): JsonResponse
    {
        return response()->json(['data' => $this->service->pipelineSummary()]);
    }
}
