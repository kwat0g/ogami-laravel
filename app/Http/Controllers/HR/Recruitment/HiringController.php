<?php

declare(strict_types=1);

namespace App\Http\Controllers\HR\Recruitment;

use App\Domains\HR\Recruitment\Models\Hiring;
use App\Domains\HR\Recruitment\Models\Application;
use App\Domains\HR\Recruitment\Enums\HiringStatus;
use App\Domains\HR\Recruitment\Services\HiringService;
use App\Http\Controllers\Controller;
use App\Http\Requests\HR\Recruitment\HireRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class HiringController extends Controller
{
    public function __construct(
        private readonly HiringService $service,
    ) {}

    public function hire(HireRequest $request, Application $application): JsonResponse
    {
        $this->authorize('execute', Hiring::class);

        $application->loadMissing('hiring.employee');

        if ($application->hiring !== null) {
            if ($application->hiring->status === HiringStatus::Hired) {
                return response()->json([
                    'data' => [
                        'ulid' => $application->hiring->ulid,
                        'status' => $application->hiring->status->value,
                        'submitted_at' => $application->hiring->submitted_at?->toIso8601String(),
                        'hired_at' => $application->hiring->hired_at?->toIso8601String(),
                        'start_date' => (string) $application->hiring->start_date,
                        'employee_id' => $application->hiring->employee_id,
                        'employee_ulid' => $application->hiring->employee?->ulid,
                    ],
                ]);
            }

            if ($application->hiring->status === HiringStatus::PendingVpApproval) {
                $approved = $this->service->vpApprove($application->hiring, $request->user());
                $approved->load('employee');

                return response()->json([
                    'data' => [
                        'ulid' => $approved->ulid,
                        'status' => $approved->status->value,
                        'submitted_at' => $approved->submitted_at?->toIso8601String(),
                        'hired_at' => $approved->hired_at?->toIso8601String(),
                        'start_date' => (string) $approved->start_date,
                        'employee_id' => $approved->employee_id,
                        'employee_ulid' => $approved->employee?->ulid,
                    ],
                ]);
            }
        }

        $submitted = $this->service->submitForApproval($application, $request->validated(), $request->user());
        $hiring = $this->service->vpApprove($submitted, $request->user());
        $hiring->load('employee');

        return response()->json([
            'data' => [
                'ulid' => $hiring->ulid,
                'status' => $hiring->status->value,
                'submitted_at' => $hiring->submitted_at?->toIso8601String(),
                'hired_at' => $hiring->hired_at?->toIso8601String(),
                'start_date' => (string) $hiring->start_date,
                'employee_id' => $hiring->employee_id,
                'employee_ulid' => $hiring->employee?->ulid,
            ],
        ], 201);
    }

    public function vpApprove(Request $request, Hiring $hiring): JsonResponse
    {
        $this->authorize('approve', Hiring::class);

        $validated = $request->validate([
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $approved = $this->service->vpApprove($hiring, $request->user(), $validated['notes'] ?? null);
        $approved->load('employee');

        return response()->json([
            'data' => [
                'ulid' => $approved->ulid,
                'status' => $approved->status->value,
                'employee_id' => $approved->employee_id,
                'employee_ulid' => $approved->employee?->ulid,
                'hired_at' => $approved->hired_at?->toIso8601String(),
                'vp_approved_at' => $approved->vp_approved_at?->toIso8601String(),
            ],
        ]);
    }

    public function vpReject(Request $request, Hiring $hiring): JsonResponse
    {
        $this->authorize('approve', Hiring::class);

        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:2000'],
        ]);

        $rejected = $this->service->vpReject($hiring, $request->user(), $validated['reason']);

        return response()->json([
            'data' => [
                'ulid' => $rejected->ulid,
                'status' => $rejected->status->value,
                'vp_rejected_at' => $rejected->vp_rejected_at?->toIso8601String(),
                'vp_rejection_reason' => $rejected->vp_rejection_reason,
            ],
        ]);
    }
}
