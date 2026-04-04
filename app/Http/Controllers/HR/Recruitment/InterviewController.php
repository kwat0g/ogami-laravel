<?php

declare(strict_types=1);

namespace App\Http\Controllers\HR\Recruitment;

use App\Domains\HR\Recruitment\Models\Application;
use App\Domains\HR\Recruitment\Models\InterviewSchedule;
use App\Domains\HR\Recruitment\Services\InterviewService;
use App\Http\Controllers\Controller;
use App\Http\Requests\HR\Recruitment\ScheduleInterviewRequest;
use App\Http\Requests\HR\Recruitment\SubmitEvaluationRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class InterviewController extends Controller
{
    public function __construct(
        private readonly InterviewService $service,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', InterviewSchedule::class);

        $result = $this->service->list(
            $request->only(['application_id', 'interviewer_id', 'interviewer_department_id', 'status', 'from_date', 'to_date']),
            (int) $request->query('per_page', '25'),
        );

        return response()->json($result);
    }

    public function store(ScheduleInterviewRequest $request): JsonResponse
    {
        $this->authorize('create', InterviewSchedule::class);

        $application = Application::findOrFail($request->validated('application_id'));
        $interview = $this->service->schedule($application, $request->validated(), $request->user());

        return response()->json($interview->load(['interviewer', 'interviewerDepartment', 'application.candidate']), 201);
    }

    public function show(Request $request, InterviewSchedule $interview): JsonResponse
    {
        $this->authorize('viewAny', InterviewSchedule::class);

        return response()->json($this->service->show($interview));
    }

    public function update(Request $request, InterviewSchedule $interview): JsonResponse
    {
        $this->authorize('update', $interview);

        $data = $request->validate([
            'scheduled_at' => ['sometimes', 'date', 'after:now'],
            'duration_minutes' => ['sometimes', 'integer', 'min:15'],
            'location' => ['nullable', 'string', 'max:500'],
            'interviewer_id' => ['sometimes', 'integer', 'exists:users,id'],
            'interviewer_department_id' => ['sometimes', 'integer', 'exists:departments,id'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $interview = $this->service->reschedule($interview, $data, $request->user());

        return response()->json($interview);
    }

    public function cancel(Request $request, InterviewSchedule $interview): JsonResponse
    {
        $this->authorize('update', $interview);

        $request->validate(['reason' => ['nullable', 'string', 'max:2000']]);

        $interview = $this->service->cancel($interview, $request->user(), $request->input('reason'));

        return response()->json($interview);
    }

    public function markNoShow(Request $request, InterviewSchedule $interview): JsonResponse
    {
        $this->authorize('update', $interview);

        return response()->json($this->service->markNoShow($interview, $request->user()));
    }

    public function complete(Request $request, InterviewSchedule $interview): JsonResponse
    {
        $this->authorize('update', $interview);

        return response()->json($this->service->complete($interview, $request->user()));
    }

    public function submitEvaluation(SubmitEvaluationRequest $request, InterviewSchedule $interview): JsonResponse
    {
        $this->authorize('evaluate', $interview);

        $evaluation = $this->service->submitEvaluation($interview, $request->validated(), $request->user());

        return response()->json($evaluation, 201);
    }
}
