<?php

declare(strict_types=1);

namespace App\Http\Controllers\HR\Recruitment;

use App\Domains\HR\Recruitment\Models\Application;
use App\Domains\HR\Recruitment\Models\JobOffer;
use App\Domains\HR\Recruitment\Services\OfferService;
use App\Http\Controllers\Controller;
use App\Http\Requests\HR\Recruitment\PrepareOfferRequest;
use App\Http\Resources\HR\Recruitment\JobOfferResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class OfferController extends Controller
{
    public function __construct(
        private readonly OfferService $service,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', JobOffer::class);

        $result = $this->service->list(
            $request->only(['status', 'search']),
            (int) $request->query('per_page', '25'),
        );

        return JobOfferResource::collection($result);
    }

    public function store(PrepareOfferRequest $request): JsonResponse
    {
        $application = Application::findOrFail($request->validated('application_id'));
        $offer = $this->service->prepareOffer($application, $request->validated(), $request->user());

        return (new JobOfferResource($offer->load(['offeredPosition', 'offeredDepartment', 'preparer'])))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, JobOffer $offer): JobOfferResource
    {
        $this->authorize('viewAny', JobOffer::class);

        return new JobOfferResource($this->service->show($offer));
    }

    public function update(Request $request, JobOffer $offer): JobOfferResource
    {
        $this->authorize('update', $offer);

        $data = $request->validate([
            'offered_salary' => ['sometimes', 'integer', 'min:1'],
            'start_date' => ['sometimes', 'date', 'after:today'],
            'employment_type' => ['sometimes', 'string'],
            'expires_at' => ['nullable', 'date', 'after:today'],
        ]);

        return new JobOfferResource($this->service->update($offer, $data));
    }

    public function send(Request $request, JobOffer $offer): JobOfferResource
    {
        $this->authorize('send', $offer);

        return new JobOfferResource($this->service->sendOffer($offer, $request->user()));
    }

    public function accept(Request $request, JobOffer $offer): JobOfferResource
    {
        return new JobOfferResource($this->service->acceptOffer($offer));
    }

    public function reject(Request $request, JobOffer $offer): JobOfferResource
    {
        $request->validate(['reason' => ['required', 'string', 'max:2000']]);

        return new JobOfferResource($this->service->rejectOffer($offer, $request->input('reason')));
    }

    public function withdraw(Request $request, JobOffer $offer): JobOfferResource
    {
        $this->authorize('update', $offer);

        return new JobOfferResource($this->service->withdrawOffer($offer, $request->user()));
    }
}
