<?php

declare(strict_types=1);

namespace App\Http\Controllers\Mold;

use App\Domains\Mold\Models\MoldMaster;
use App\Domains\Mold\Services\MoldService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Mold\LogMoldShotsRequest;
use App\Http\Requests\Mold\StoreMoldMasterRequest;
use App\Http\Resources\Mold\MoldMasterResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class MoldController extends Controller
{
    public function __construct(private readonly MoldService $service) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', MoldMaster::class);

        return MoldMasterResource::collection(
            $this->service->paginate($request->only(['search', 'status', 'per_page', 'with_archived']))
        );
    }

    public function store(StoreMoldMasterRequest $request): JsonResponse
    {
        $this->authorize('create', MoldMaster::class);
        $mold = $this->service->store($request->validated(), $request->user()->id);

        return (new MoldMasterResource($mold))->response()->setStatusCode(201);
    }

    public function show(MoldMaster $moldMaster): MoldMasterResource
    {
        $this->authorize('view', $moldMaster);

        return new MoldMasterResource($moldMaster->load('shotLogs'));
    }

    public function update(StoreMoldMasterRequest $request, MoldMaster $moldMaster): MoldMasterResource
    {
        $this->authorize('update', $moldMaster);

        return new MoldMasterResource($this->service->update($moldMaster, $request->validated()));
    }

    public function logShots(LogMoldShotsRequest $request, MoldMaster $moldMaster): JsonResponse
    {
        $this->authorize('logShots', $moldMaster);
        $log = $this->service->logShots($moldMaster, $request->validated(), $request->user()->id);

        return response()->json(['data' => $log], 201);
    }

    public function retire(Request $request, MoldMaster $moldMaster): JsonResponse
    {
        $this->authorize('update', $moldMaster);

        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $mold = $this->service->retire($moldMaster, $validated['reason'] ?? '');

        return response()->json(['data' => $mold]);
    }

    /** List archived (soft-deleted) molds. */
    public function archived(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', MoldMaster::class);

        return MoldMasterResource::collection(
            $this->service->listArchived(
                perPage: $request->integer('per_page', 20),
                search: $request->input('search'),
            )
        );
    }

    /** Restore a soft-deleted mold from the archive. */
    public function restore(Request $request, int $moldMaster): MoldMasterResource
    {
        $mold = $this->service->restoreMold($moldMaster, $request->user());

        return new MoldMasterResource($mold);
    }

    /** Permanently delete a mold — superadmin only. */
    public function forceDelete(Request $request, int $moldMaster): JsonResponse
    {
        abort_unless($request->user()->hasRole('super_admin'), 403, 'Only super admins can permanently delete records.');

        $this->service->forceDelete($moldMaster, $request->user());

        return response()->json(['message' => 'Mold permanently deleted.']);
    }
}
