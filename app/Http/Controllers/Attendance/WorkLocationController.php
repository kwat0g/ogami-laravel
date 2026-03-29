<?php

declare(strict_types=1);

namespace App\Http\Controllers\Attendance;

use App\Domains\Attendance\Models\WorkLocation;
use App\Http\Controllers\Controller;
use App\Http\Resources\Attendance\WorkLocationResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class WorkLocationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('attendance.work_locations.manage'), 403);

        $locations = WorkLocation::query()
            ->when($request->boolean('active_only', false), fn ($q) => $q->where('is_active', true))
            ->orderBy('name')
            ->paginate((int) $request->query('per_page', '50'));

        return WorkLocationResource::collection($locations)->response();
    }

    public function store(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('attendance.work_locations.manage'), 403);

        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'code' => 'required|string|max:20|unique:work_locations,code',
            'address' => 'required|string',
            'city' => 'nullable|string|max:100',
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'radius_meters' => 'required|integer|min:10|max:5000',
            'allowed_variance_meters' => 'nullable|integer|min:0|max:200',
            'is_remote_allowed' => 'boolean',
            'is_active' => 'boolean',
        ]);

        $location = WorkLocation::create($validated);

        return (new WorkLocationResource($location))
            ->response()
            ->setStatusCode(201);
    }

    public function show(WorkLocation $workLocation): WorkLocationResource
    {
        return new WorkLocationResource($workLocation->load('employeeAssignments.employee'));
    }

    public function update(Request $request, WorkLocation $workLocation): WorkLocationResource
    {
        abort_unless($request->user()->can('attendance.work_locations.manage'), 403);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:100',
            'code' => 'sometimes|string|max:20|unique:work_locations,code,' . $workLocation->id,
            'address' => 'sometimes|string',
            'city' => 'nullable|string|max:100',
            'latitude' => 'sometimes|numeric|between:-90,90',
            'longitude' => 'sometimes|numeric|between:-180,180',
            'radius_meters' => 'sometimes|integer|min:10|max:5000',
            'allowed_variance_meters' => 'nullable|integer|min:0|max:200',
            'is_remote_allowed' => 'boolean',
            'is_active' => 'boolean',
        ]);

        $workLocation->update($validated);

        return new WorkLocationResource($workLocation->fresh());
    }

    public function destroy(Request $request, WorkLocation $workLocation): JsonResponse
    {
        abort_unless($request->user()->can('attendance.work_locations.manage'), 403);

        $workLocation->delete();

        return response()->json(null, 204);
    }
}
