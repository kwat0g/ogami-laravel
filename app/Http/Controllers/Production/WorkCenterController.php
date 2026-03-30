<?php

declare(strict_types=1);

namespace App\Http\Controllers\Production;

use App\Domains\Production\Models\WorkCenter;
use App\Domains\Production\Services\WorkCenterService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class WorkCenterController extends Controller
{
    public function __construct(private readonly WorkCenterService $service) {}

    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()?->hasPermissionTo('production.orders.view'), 403, 'Unauthorized');

        return response()->json(
            $this->service->paginate($request->only(['search', 'is_active', 'per_page']))
        );
    }

    public function store(Request $request): JsonResponse
    {
        abort_unless($request->user()?->hasPermissionTo('production.orders.create'), 403, 'Unauthorized');

        $validated = $request->validate([
            'code' => ['required', 'string', 'max:30', 'unique:work_centers,code'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'hourly_rate_centavos' => ['sometimes', 'integer', 'min:0'],
            'overhead_rate_centavos' => ['sometimes', 'integer', 'min:0'],
            'capacity_hours_per_day' => ['sometimes', 'integer', 'min:1', 'max:24'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        return response()->json(['data' => $this->service->store($validated)], 201);
    }

    public function show(WorkCenter $workCenter): JsonResponse
    {
        return response()->json([
            'data' => $workCenter->load('routings.bom.productItem'),
        ]);
    }

    public function update(Request $request, WorkCenter $workCenter): JsonResponse
    {
        abort_unless($request->user()?->hasPermissionTo('production.orders.update'), 403, 'Unauthorized');

        $validated = $request->validate([
            'code' => ['sometimes', 'string', 'max:30', 'unique:work_centers,code,' . $workCenter->id],
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'hourly_rate_centavos' => ['sometimes', 'integer', 'min:0'],
            'overhead_rate_centavos' => ['sometimes', 'integer', 'min:0'],
            'capacity_hours_per_day' => ['sometimes', 'integer', 'min:1', 'max:24'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        return response()->json(['data' => $this->service->update($workCenter, $validated)]);
    }

    public function destroy(Request $request, WorkCenter $workCenter): JsonResponse
    {
        abort_unless($request->user()?->hasPermissionTo('production.orders.update'), 403, 'Unauthorized');

        $this->service->archive($workCenter);

        return response()->json(['message' => 'Work center archived.']);
    }
}
