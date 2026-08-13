<?php

namespace App\Http\Controllers\Tutor;

use App\Enums\AssessmentAttemptStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\GradeEssayAnswersRequest;
use App\Models\AssessmentAttempt;
use App\Models\LearningClass;
use App\Models\LearningClassAssessment;
use App\Models\User;
use App\Services\AssessmentAttemptReviewQueryService;
use App\Services\AssessmentGradingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AssessmentAttemptController extends Controller
{
    public function __construct(
        private readonly AssessmentAttemptReviewQueryService $queries,
        private readonly AssessmentGradingService $grading,
    ) {}

    public function index(
        Request $request,
        LearningClass $learningClass,
        LearningClassAssessment $assignment,
    ): Response {
        $this->ensureAssignment($learningClass, $assignment);
        $this->authorize('view', $learningClass);
        $this->authorize('reviewAttempts', $assignment);
        $status = $this->reviewStatus($request);
        $search = $this->reviewSearch($request);

        return Inertia::render('assessment-attempts/Index', [
            ...$this->queries->listPayload($learningClass, $assignment, $status, 'tutor.class-assessment-attempts.edit', $search),
            'backUrl' => route('tutor.classes.show', $learningClass),
        ]);
    }

    public function edit(
        Request $request,
        LearningClass $learningClass,
        LearningClassAssessment $assignment,
        AssessmentAttempt $attempt,
    ): Response {
        $this->ensureAttempt($learningClass, $assignment, $attempt);
        $this->authorize('grade', $attempt);
        $status = $this->reviewStatus($request);
        $search = $this->reviewSearch($request);
        $adjacent = $this->queries->adjacentAttemptIds($assignment, $status, $search, $attempt->id);
        $filterQuery = ['status' => $status?->value, 'search' => $search];

        return Inertia::render('assessment-attempts/Grade', [
            ...$this->queries->gradingPayload($attempt),
            'previousUrl' => $adjacent['previous_id'] !== null
                ? route('tutor.class-assessment-attempts.edit', [$learningClass, $assignment, $adjacent['previous_id'], ...$filterQuery])
                : null,
            'nextUrl' => $adjacent['next_id'] !== null
                ? route('tutor.class-assessment-attempts.edit', [$learningClass, $assignment, $adjacent['next_id'], ...$filterQuery])
                : null,
            'submitUrl' => route('tutor.class-assessment-attempts.update', [$learningClass, $assignment, $attempt]),
            'backUrl' => route('tutor.class-assessment-attempts.index', [$learningClass, $assignment, ...$filterQuery]),
        ]);
    }

    public function update(
        GradeEssayAnswersRequest $request,
        LearningClass $learningClass,
        LearningClassAssessment $assignment,
        AssessmentAttempt $attempt,
    ): RedirectResponse {
        $this->ensureAttempt($learningClass, $assignment, $attempt);
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $this->grading->grade($attempt, $user, $request->grades());
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Essay grades saved.')]);

        return back();
    }

    private function ensureAssignment(LearningClass $class, LearningClassAssessment $assignment): void
    {
        abort_unless($assignment->learning_class_id === $class->id, 404);
    }

    private function ensureAttempt(LearningClass $class, LearningClassAssessment $assignment, AssessmentAttempt $attempt): void
    {
        $this->ensureAssignment($class, $assignment);
        abort_unless($attempt->learning_class_assessment_id === $assignment->id, 404);
    }

    private function reviewStatus(Request $request): ?AssessmentAttemptStatus
    {
        $status = AssessmentAttemptStatus::tryFrom($request->string('status')->toString());

        return in_array($status, [AssessmentAttemptStatus::PendingGrading, AssessmentAttemptStatus::Graded], true)
            ? $status
            : null;
    }

    private function reviewSearch(Request $request): ?string
    {
        $search = $request->string('search')->trim()->toString();

        return $search === '' ? null : $search;
    }
}
