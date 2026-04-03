<?php

declare(strict_types=1);

namespace App\Http\Controllers\HR\Recruitment;

use App\Domains\HR\Recruitment\Models\Hiring;
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
        abort_unless(
            $request->user()?->can('recruitment.hiring.execute'),
            403,
            'You do not have permission to hire candidates.',
        );

        $hiring = $this->service->submitForApproval($application, $request->validated(), $request->user());

        return response()->json([
            'data' => [
                'ulid' => $hiring->ulid,
                'status' => $hiring->status->value,
                'submitted_at' => $hiring->submitted_at?->toIso8601String(),
                'hired_at' => $hiring->hired_at?->toIso8601String(),
                'start_date' => (string) $hiring->start_date,
                'employee_id' => $hiring->employee_id,
            ],
        ], 201);
    }

    public function vpApprove(Request $request, Hiring $hiring): JsonResponse
    {
        abort_unless(
            $request->user()?->can('recruitment.hiring.approve'),
            403,
            'Only VP can approve hiring requests.',
        );

        $validated = $request->validate([
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $approved = $this->service->vpApprove($hiring, $request->user(), $validated['notes'] ?? null);

        return response()->json([
            'data' => [
                'ulid' => $approved->ulid,
                'status' => $approved->status->value,
                'employee_id' => $approved->employee_id,
                'hired_at' => $approved->hired_at?->toIso8601String(),
                'vp_approved_at' => $approved->vp_approved_at?->toIso8601String(),
            ],
        ]);
    }

    public function vpReject(Request $request, Hiring $hiring): JsonResponse
    {
        abort_unless(
            $request->user()?->can('recruitment.hiring.approve'),
            403,
            'Only VP can reject hiring requests.',
        );

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
