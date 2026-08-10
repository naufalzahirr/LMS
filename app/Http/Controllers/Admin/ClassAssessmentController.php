<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreClassAssessmentRequest;
use App\Http\Requests\Admin\UpdateClassAssessmentRequest;
use App\Models\LearningClass;
use App\Models\LearningClassAssessment;
use App\Services\ClassAssessmentService;
use Illuminate\Http\RedirectResponse;

class ClassAssessmentController extends Controller
{
    public function __construct(private readonly ClassAssessmentService $service) {}

    public function store(StoreClassAssessmentRequest $request, LearningClass $learningClass): RedirectResponse
    {
        $this->service->assign($learningClass, $request->assessment(), $request->payload());

        return back();
    }

    public function update(
        UpdateClassAssessmentRequest $request,
        LearningClass $learningClass,
        LearningClassAssessment $assignment,
    ): RedirectResponse {
        abort_unless($assignment->learning_class_id === $learningClass->id, 404);
        $this->service->update($assignment, $request->payload());

        return back();
    }

    public function destroy(LearningClass $learningClass, LearningClassAssessment $assignment): RedirectResponse
    {
        abort_unless($assignment->learning_class_id === $learningClass->id, 404);
        $this->authorize('delete', $assignment);
        $this->service->unassign($assignment);

        return back();
    }
}
