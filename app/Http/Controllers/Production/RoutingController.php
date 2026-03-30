<?php

declare(strict_types=1);

namespace App\Http\Controllers\Production;

use App\Domains\Production\Models\Routing;
use App\Domains\Production\Services\RoutingService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class RoutingController extends Controller
{
    public function __construct(private readonly RoutingService $service) {}

    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()?->hasPermissionTo('production.orders.view'), 403, 'Unauthorized');

        return response()->json(
            $this->service->paginate($request->only(['bom_id', 'work_center_id', 'per_page']))
        );
    }

    public function forBom(int $bomId): JsonResponse
    {
        return response()->json(['data' => $this->service->forBom($bomId)]);
    }

    public function store(Request $request): JsonResponse
    {
        abort_unless($request->user()?->hasPermissionTo('production.orders.create'), 403, 'Unauthorized');

        $validated = $request->validate([
            'bom_id' => ['required', 'integer', 'exists:bill_of_materials,id'],
            'work_center_id' => ['required', 'integer', 'exists:work_centers,id'],
            'sequence' => ['sometimes', 'integer', 'min:1'],
            'operation_name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'setup_time_hours' => ['sometimes', 'numeric', 'min:0'],
            'run_time_hours_per_unit' => ['sometimes', 'numeric', 'min:0'],
        ]);

        return response()->json(['data' => $this->service->store($validated)], 201);
    }

    public function update(Request $request, Routing $routing): JsonResponse
    {
        abort_unless($request->user()?->hasPermissionTo('production.orders.update'), 403, 'Unauthorized');

        $validated = $request->validate([
            'work_center_id' => ['sometimes', 'integer', 'exists:work_centers,id'],
            'sequence' => ['sometimes', 'integer', 'min:1'],
            'operation_name' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'setup_time_hours' => ['sometimes', 'numeric', 'min:0'],
            'run_time_hours_per_unit' => ['sometimes', 'numeric', 'min:0'],
        ]);

        return response()->json(['data' => $this->service->update($routing, $validated)]);
    }

    public function destroy(Request $request, Routing $routing): JsonResponse
    {
        abort_unless($request->user()?->hasPermissionTo('production.orders.update'), 403, 'Unauthorized');

        $this->service->destroy($routing);

        return response()->json(['message' => 'Routing step deleted.']);
    }

    public function reorder(Request $request, int $bomId): JsonResponse
    {
        abort_unless($request->user()?->hasPermissionTo('production.orders.update'), 403, 'Unauthorized');

        $validated = $request->validate([
            'order' => ['required', 'array'],
            'order.*' => ['required', 'integer', 'min:1'],
        ]);

        $steps = $this->service->reorder($bomId, $validated['order']);

        return response()->json(['data' => $steps]);
    }
}
