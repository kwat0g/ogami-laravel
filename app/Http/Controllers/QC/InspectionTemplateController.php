<?php

declare(strict_types=1);

namespace App\Http\Controllers\QC;

use App\Domains\QC\Models\InspectionTemplate;
use App\Domains\QC\Services\InspectionTemplateService;
use App\Http\Controllers\Controller;
use App\Http\Requests\QC\StoreInspectionTemplateRequest;
use App\Http\Resources\QC\InspectionTemplateResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class InspectionTemplateController extends Controller
{
    public function __construct(private readonly InspectionTemplateService $service) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', InspectionTemplate::class);
        return InspectionTemplateResource::collection(
            $this->service->paginate($request->only(['stage', 'is_active', 'per_page']))
        );
    }

    public function store(StoreInspectionTemplateRequest $request): JsonResponse
    {
        $this->authorize('create', InspectionTemplate::class);
        $template = $this->service->store($request->validated(), $request->user()->id);
        return (new InspectionTemplateResource($template))->response()->setStatusCode(201);
    }

    public function show(InspectionTemplate $inspectionTemplate): InspectionTemplateResource
    {
        $this->authorize('view', $inspectionTemplate);
        return new InspectionTemplateResource($inspectionTemplate->load('items'));
    }

    public function update(StoreInspectionTemplateRequest $request, InspectionTemplate $inspectionTemplate): InspectionTemplateResource
    {
        $this->authorize('update', $inspectionTemplate);
        return new InspectionTemplateResource(
            $this->service->update($inspectionTemplate, $request->validated())
        );
    }
}
