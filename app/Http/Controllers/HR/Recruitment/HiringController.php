<?php

declare(strict_types=1);

namespace App\Http\Controllers\HR\Recruitment;

use App\Domains\HR\Recruitment\Models\Application;
use App\Domains\HR\Recruitment\Services\HiringService;
use App\Http\Controllers\Controller;
use App\Http\Requests\HR\Recruitment\HireRequest;
use Illuminate\Http\JsonResponse;

final class HiringController extends Controller
{
    public function __construct(
        private readonly HiringService $service,
    ) {}

    public function hire(HireRequest $request, Application $application): JsonResponse
    {
        $hiring = $this->service->hire($application, $request->validated(), $request->user());

        return response()->json([
            'data' => [
                'ulid' => $hiring->ulid,
                'status' => $hiring->status->value,
                'hired_at' => $hiring->hired_at?->toIso8601String(),
                'start_date' => (string) $hiring->start_date,
                'employee_id' => $hiring->employee_id,
            ],
        ], 201);
    }
}
