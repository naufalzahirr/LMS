<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AssignTutorRequest;
use App\Models\LearningClass;
use App\Models\User;
use App\Services\TutorAssignmentService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class TutorAssignmentController extends Controller
{
    public function __construct(private readonly TutorAssignmentService $tutorAssignmentService) {}

    public function store(AssignTutorRequest $request, LearningClass $learningClass): RedirectResponse
    {
        $this->tutorAssignmentService->assign($learningClass, $request->tutor());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Tutor assigned.')]);

        return to_route('admin.classes.show', $learningClass);
    }

    public function destroy(LearningClass $learningClass, User $tutor): RedirectResponse
    {
        $this->authorize('manageTutors', $learningClass);
        $this->tutorAssignmentService->unassign($learningClass, $tutor);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Tutor unassigned.')]);

        return to_route('admin.classes.show', $learningClass);
    }
}
