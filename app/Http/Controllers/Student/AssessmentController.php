<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Enrollment;
use App\Models\LearningClass;
use App\Models\LearningClassAssessment;
use App\Models\User;
use App\Services\AssessmentAttemptService;
use App\Services\StudentAssessmentPayloadService;
use App\Services\StudentLearningAccessService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AssessmentController extends Controller
{
    public function __construct(
        private readonly StudentLearningAccessService $access,
        private readonly StudentAssessmentPayloadService $payloads,
        private readonly AssessmentAttemptService $attempts,
    ) {}

    public function index(Request $request, LearningClass $learningClass): Response
    {
        [$user, $enrollment] = $this->context($request, $learningClass);

        return Inertia::render('student/assessments/Index', [
            'learningClass' => [
                'id' => $learningClass->id,
                'name' => $learningClass->name,
                'course' => $learningClass->course->name,
                'url' => route('student.classes.show', $learningClass),
            ],
            'assessments' => $this->payloads->assignmentsForEnrollment($enrollment),
        ]);
    }

    public function show(
        Request $request,
        LearningClass $learningClass,
        LearningClassAssessment $assignment,
    ): Response {
        [, $enrollment] = $this->context($request, $learningClass);
        abort_unless($assignment->learning_class_id === $learningClass->id, 404);
        $this->authorize('view', $assignment);

        return Inertia::render('student/assessments/Show', [
            'learningClass' => [
                'id' => $learningClass->id,
                'name' => $learningClass->name,
                'assessments_url' => route('student.assessments.index', $learningClass),
            ],
            'assessment' => $this->payloads->assignmentIntro($enrollment, $assignment),
        ]);
    }

    public function start(
        Request $request,
        LearningClass $learningClass,
        LearningClassAssessment $assignment,
    ): RedirectResponse {
        [$user, $enrollment] = $this->context($request, $learningClass);
        abort_unless($assignment->learning_class_id === $learningClass->id, 404);
        $this->authorize('start', $assignment);
        $attempt = $this->attempts->startAttempt($user, $enrollment, $assignment);

        return to_route('student.assessment-attempts.show', $attempt);
    }

    /** @return array{User, Enrollment} */
    private function context(Request $request, LearningClass $learningClass): array
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->hasRole('Student'), 403);
        $enrollment = $this->access->enrollmentForViewing($user, $learningClass);
        abort_unless($enrollment instanceof Enrollment, 403);
        $learningClass->loadMissing('course:id,name');

        return [$user, $enrollment];
    }
}
