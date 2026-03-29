<?php

declare(strict_types=1);

namespace App\Http\Controllers\HR\Recruitment;

use App\Domains\HR\Recruitment\Models\Application;
use App\Domains\HR\Recruitment\Models\PreEmploymentChecklist;
use App\Domains\HR\Recruitment\Models\PreEmploymentRequirement;
use App\Domains\HR\Recruitment\Services\PreEmploymentService;
use App\Http\Controllers\Controller;
use App\Http\Requests\HR\Recruitment\SubmitDocumentRequest;
use App\Http\Resources\HR\Recruitment\PreEmploymentChecklistResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class PreEmploymentController extends Controller
{
    public function __construct(
        private readonly PreEmploymentService $service,
    ) {}

    public function show(Request $request, Application $application): JsonResponse
    {
        abort_unless($request->user()->can('recruitment.preemployment.view'), 403); // PreEmployment uses checklist-level policy

        $checklist = $this->service->show($application);

        if (! $checklist) {
            return response()->json(['data' => null]);
        }

        return response()->json(['data' => new PreEmploymentChecklistResource($checklist)]);
    }

    public function init(Request $request, Application $application): JsonResponse
    {
        abort_unless($request->user()->can('recruitment.preemployment.view'), 403); // PreEmployment uses checklist-level policy

        $checklist = $this->service->initChecklist($application);

        return response()->json(['data' => new PreEmploymentChecklistResource($checklist)], 201);
    }

    public function submitDocument(SubmitDocumentRequest $request, PreEmploymentRequirement $requirement): JsonResponse
    {
        $this->service->submitDocument($requirement, $request->file('document'), $request->user());

        return response()->json(['message' => 'Document submitted successfully.']);
    }

    public function verify(Request $request, PreEmploymentRequirement $requirement): JsonResponse
    {
        abort_unless($request->user()->can('recruitment.preemployment.verify'), 403); // PreEmployment verify permission

        $this->service->verifyDocument($requirement, $request->user());

        return response()->json(['message' => 'Document verified.']);
    }

    public function rejectDocument(Request $request, PreEmploymentRequirement $requirement): JsonResponse
    {
        abort_unless($request->user()->can('recruitment.preemployment.verify'), 403); // PreEmployment verify permission

        $request->validate(['remarks' => ['required', 'string', 'max:2000']]);

        $this->service->rejectDocument($requirement, $request->input('remarks'), $request->user());

        return response()->json(['message' => 'Document rejected.']);
    }

    public function waive(Request $request, PreEmploymentRequirement $requirement): JsonResponse
    {
        abort_unless($request->user()->can('recruitment.preemployment.verify'), 403); // PreEmployment verify permission

        $this->service->waiveRequirement($requirement, $request->user());

        return response()->json(['message' => 'Requirement waived.']);
    }

    public function complete(Request $request, PreEmploymentChecklist $checklist): JsonResponse
    {
        abort_unless($request->user()->can('recruitment.preemployment.verify'), 403); // PreEmployment verify permission

        $this->service->markComplete($checklist, $request->user());

        return response()->json(['message' => 'Pre-employment checklist completed.']);
    }
}
