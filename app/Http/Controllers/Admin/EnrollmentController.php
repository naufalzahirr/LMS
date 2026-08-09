<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\EnrollStudentRequest;
use App\Models\Enrollment;
use App\Models\LearningClass;
use App\Services\EnrollmentService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class EnrollmentController extends Controller
{
    public function __construct(private readonly EnrollmentService $enrollmentService) {}

    public function store(EnrollStudentRequest $request, LearningClass $learningClass): RedirectResponse
    {
        $this->enrollmentService->enroll($learningClass, $request->student());

        return $this->success($learningClass, __('Student enrolled.'));
    }

    public function withdraw(LearningClass $learningClass, Enrollment $enrollment): RedirectResponse
    {
        $this->authorize('manageEnrollments', $learningClass);
        $this->enrollmentService->withdraw($learningClass, $enrollment);

        return $this->success($learningClass, __('Enrollment withdrawn.'));
    }

    public function reactivate(LearningClass $learningClass, Enrollment $enrollment): RedirectResponse
    {
        $this->authorize('manageEnrollments', $learningClass);
        $this->enrollmentService->reactivate($learningClass, $enrollment);

        return $this->success($learningClass, __('Enrollment reactivated.'));
    }

    public function complete(LearningClass $learningClass, Enrollment $enrollment): RedirectResponse
    {
        $this->authorize('manageEnrollments', $learningClass);
        $this->enrollmentService->complete($learningClass, $enrollment);

        return $this->success($learningClass, __('Enrollment completed.'));
    }

    private function success(LearningClass $learningClass, string $message): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return to_route('admin.classes.show', $learningClass);
    }
}
