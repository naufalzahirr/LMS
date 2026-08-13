<?php

namespace App\Services;

use App\Enums\AssessmentAttemptStatus;
use App\Enums\QuestionType;
use App\Models\AssessmentAttempt;
use App\Models\AssessmentAttemptQuestion;
use App\Models\LearningClass;
use App\Models\LearningClassAssessment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

class AssessmentAttemptReviewQueryService
{
    /** @return LengthAwarePaginator<int, AssessmentAttempt> */
    public function attempts(LearningClassAssessment $assignment, ?AssessmentAttemptStatus $status, ?string $search = null): LengthAwarePaginator
    {
        return $this->orderedAttemptsQuery($assignment, $status, $search)
            ->with('enrollment.student:id,name,email')
            ->paginate(15)
            ->withQueryString();
    }

    /**
     * The neighboring attempt ids in the exact same order the Tutor sees on
     * the queue page — reused (not re-derived) so Previous/Next can never
     * disagree with what was actually filtered/sorted on screen.
     *
     * @return array{previous_id: int|null, next_id: int|null}
     */
    public function adjacentAttemptIds(LearningClassAssessment $assignment, ?AssessmentAttemptStatus $status, ?string $search, int $currentAttemptId): array
    {
        $ids = $this->orderedAttemptsQuery($assignment, $status, $search)->pluck('id')->values();
        $position = $ids->search($currentAttemptId);

        if ($position === false) {
            return ['previous_id' => null, 'next_id' => null];
        }

        return [
            'previous_id' => $ids->get($position - 1),
            'next_id' => $ids->get($position + 1),
        ];
    }

    /** @return Builder<AssessmentAttempt> */
    private function orderedAttemptsQuery(LearningClassAssessment $assignment, ?AssessmentAttemptStatus $status, ?string $search): Builder
    {
        return AssessmentAttempt::query()
            ->where('learning_class_assessment_id', $assignment->id)
            ->when($status, fn ($query) => $query->where('status', $status->value))
            ->when($search, fn ($query) => $query->whereHas('enrollment.student', fn ($studentQuery) => $studentQuery
                ->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")))
            ->orderByDesc('submitted_at')
            ->orderByDesc('attempt_number');
    }

    /** @return array<string, mixed> */
    public function listPayload(
        LearningClass $learningClass,
        LearningClassAssessment $assignment,
        ?AssessmentAttemptStatus $status,
        string $gradeRouteName,
        ?string $search = null,
    ): array {
        $assignment->loadMissing('assessment.competency:id,course_id,name');
        // The assessment relation is only ever missing for genuinely corrupt
        // data (e.g. an Assessment removed outside AssessmentService::delete()'s
        // guard) — never a state normal application flows can produce. Fail
        // with a controlled 404 rather than a null-property crash.
        abort_if($assignment->assessment === null, 404);
        $paginator = $this->attempts($assignment, $status, $search);

        return [
            'learningClass' => ['id' => $learningClass->id, 'name' => $learningClass->name],
            'assignment' => [
                'id' => $assignment->id,
                'title' => $assignment->assessment->title,
                'competency' => $assignment->assessment->competency->name,
            ],
            // Independent of the active status filter, so the Tutor always
            // knows the true amount of outstanding work for this assignment.
            'pending_count' => AssessmentAttempt::query()
                ->where('learning_class_assessment_id', $assignment->id)
                ->where('status', AssessmentAttemptStatus::PendingGrading->value)
                ->count(),
            'attempts' => [
                'data' => $paginator->getCollection()->map(fn (AssessmentAttempt $attempt): array => [
                    'id' => $attempt->id,
                    'student' => $attempt->enrollment->student->name,
                    'email' => $attempt->enrollment->student->email,
                    'attempt_number' => $attempt->attempt_number,
                    'submitted_at' => $attempt->submitted_at?->toDateTimeString(),
                    'status' => $attempt->status->value,
                    'auto_points' => $attempt->auto_points,
                    'earned_points' => $attempt->earned_points,
                    'max_points' => $attempt->max_points,
                    'percentage' => $attempt->percentage,
                    'grade_url' => in_array($attempt->status, [AssessmentAttemptStatus::PendingGrading, AssessmentAttemptStatus::Graded], true)
                        ? route($gradeRouteName, [$learningClass, $assignment, $attempt, 'status' => $status?->value, 'search' => $search])
                        : null,
                ])->all(),
                'links' => $paginator->linkCollection()->all(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
                'total' => $paginator->total(),
            ],
            'filters' => [
                'status' => $status === null ? '' : $status->value,
                'search' => $search ?? '',
            ],
            'statuses' => [
                ['value' => AssessmentAttemptStatus::PendingGrading->value, 'label' => AssessmentAttemptStatus::PendingGrading->label()],
                ['value' => AssessmentAttemptStatus::Graded->value, 'label' => AssessmentAttemptStatus::Graded->label()],
            ],
        ];
    }

    /** @return array<string, mixed> */
    public function gradingPayload(AssessmentAttempt $attempt): array
    {
        $attempt->loadMissing([
            'enrollment.student:id,name,email',
            'classAssessment.assessment:id,title',
            'attemptQuestions.answer.selectedOptions',
        ]);
        abort_if($attempt->classAssessment->assessment === null, 404);

        $essays = $attempt->attemptQuestions->where('question_type', QuestionType::Essay);
        $autoGraded = $attempt->attemptQuestions->where('question_type', '!=', QuestionType::Essay);

        return [
            'attempt' => [
                'id' => $attempt->id,
                'assessment_title' => $attempt->classAssessment->assessment->title,
                'student' => $attempt->enrollment->student->name,
                'email' => $attempt->enrollment->student->email,
                'attempt_number' => $attempt->attempt_number,
                'status' => $attempt->status->value,
                'submitted_at' => $attempt->submitted_at?->toDateTimeString(),
                'auto_points' => $attempt->auto_points,
                'earned_points' => $attempt->earned_points,
                'max_points' => $attempt->max_points,
                'percentage' => $attempt->percentage,
            ],
            'essays' => $essays->map(fn (AssessmentAttemptQuestion $question): array => [
                'id' => $question->id,
                'prompt' => $question->prompt,
                'answer_text' => $question->answer?->answer_text,
                'points' => $question->points,
                'manual_score' => $question->answer?->manual_score,
                'feedback' => $question->answer?->feedback,
            ])->values()->all(),
            // Read-only context so the Tutor can see the whole attempt while
            // grading essays — never editable here, auto-grading is untouched.
            'auto_graded' => $autoGraded->map(fn (AssessmentAttemptQuestion $question): array => [
                'id' => $question->id,
                'question_type' => $question->question_type->value,
                'prompt' => $question->prompt,
                'points' => $question->points,
                'earned' => $question->answer?->auto_score,
                'is_correct' => $question->answer?->is_correct,
                'student_answer' => match ($question->question_type) {
                    QuestionType::MultipleChoice, QuestionType::MultipleSelect => $question->answer?->selectedOptions
                        ->pluck('option_text')->values()->all() ?? [],
                    QuestionType::TrueFalse => $question->answer?->answer_boolean,
                    QuestionType::ShortAnswer => $question->answer?->answer_text,
                    QuestionType::Essay => null,
                },
            ])->values()->all(),
        ];
    }
}
