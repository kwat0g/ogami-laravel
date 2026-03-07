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
}
