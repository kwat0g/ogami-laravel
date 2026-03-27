<?php

declare(strict_types=1);

namespace App\Http\Controllers\CRM;

use App\Domains\CRM\Models\Lead;
use App\Domains\CRM\Services\LeadService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class LeadController extends Controller
{
    public function __construct(private readonly LeadService $service) {}

    public function index(Request $request): JsonResponse
    {
        $page = $this->service->paginate($request->only([
            'status', 'source', 'assigned_to_id', 'search', 'per_page',
        ]));

        return response()->json($page);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'company_name' => ['required', 'string', 'max:255'],
            'contact_name' => ['required', 'string', 'max:255'],
            'email' => ['sometimes', 'email'],
            'phone' => ['sometimes', 'string', 'max:30'],
            'source' => ['sometimes', 'string'],
            'assigned_to_id' => ['sometimes', 'integer', 'exists:users,id'],
            'notes' => ['sometimes', 'string'],
        ]);

        $lead = $this->service->store($data, $request->user());

        return response()->json(['data' => $lead], 201);
    }

    public function show(Lead $lead): JsonResponse
    {
        return response()->json([
            'data' => $lead->load(['assignedTo', 'createdBy', 'activities', 'convertedCustomer']),
        ]);
    }

    public function update(Request $request, Lead $lead): JsonResponse
    {
        $data = $request->validate([
            'company_name' => ['sometimes', 'string', 'max:255'],
            'contact_name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'email'],
            'phone' => ['sometimes', 'string', 'max:30'],
            'source' => ['sometimes', 'string'],
            'status' => ['sometimes', 'string'],
            'assigned_to_id' => ['sometimes', 'integer', 'exists:users,id'],
            'notes' => ['sometimes', 'string'],
        ]);

        return response()->json(['data' => $this->service->update($lead, $data)]);
    }

    public function convert(Request $request, Lead $lead): JsonResponse
    {
        $opportunityData = null;
        if ($request->has('create_opportunity') && $request->boolean('create_opportunity')) {
            $opportunityData = $request->validate([
                'opportunity_title' => ['sometimes', 'string'],
                'expected_value_centavos' => ['sometimes', 'integer', 'min:0'],
                'probability_pct' => ['sometimes', 'integer', 'min:0', 'max:100'],
                'expected_close_date' => ['sometimes', 'date'],
            ]);
            $opportunityData['title'] = $opportunityData['opportunity_title'] ?? null;
        }

        $result = $this->service->convert($lead, $request->user(), $opportunityData);

        return response()->json(['data' => $result], 201);
    }

    public function disqualify(Request $request, Lead $lead): JsonResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string'],
        ]);

        return response()->json([
            'data' => $this->service->disqualify($lead, $data['reason']),
        ]);
    }
}
