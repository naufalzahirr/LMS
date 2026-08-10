<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AddAssessmentQuestionRequest;
use App\Http\Requests\Admin\UpdateAssessmentQuestionRequest;
use App\Models\Assessment;
use App\Models\AssessmentQuestion;
use App\Services\AssessmentService;
use Illuminate\Http\RedirectResponse;

class AssessmentQuestionController extends Controller
{
    public function __construct(private readonly AssessmentService $service) {}

    public function store(AddAssessmentQuestionRequest $request, Assessment $assessment): RedirectResponse
    {
        $this->service->addQuestion($assessment, $request->question(), $request->points());

        return back();
    }

    public function update(
        UpdateAssessmentQuestionRequest $request,
        Assessment $assessment,
        AssessmentQuestion $assessmentQuestion,
    ): RedirectResponse {
        $this->service->updateQuestionPoints($assessment, $assessmentQuestion, $request->points());

        return back();
    }

    public function move(Assessment $assessment, AssessmentQuestion $assessmentQuestion, string $direction): RedirectResponse
    {
        $this->authorize('update', $assessment);
        abort_unless(in_array($direction, ['up', 'down'], true), 404);
        $this->service->moveQuestion($assessment, $assessmentQuestion, $direction);

        return back();
    }

    public function destroy(Assessment $assessment, AssessmentQuestion $assessmentQuestion): RedirectResponse
    {
        $this->authorize('update', $assessment);
        $this->service->removeQuestion($assessment, $assessmentQuestion);

        return back();
    }
}
