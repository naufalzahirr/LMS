<?php

namespace App\Services;

use App\Enums\AssessmentAttemptStatus;
use App\Enums\QuestionType;
use App\Models\AssessmentAttempt;
use App\Models\AssessmentAttemptQuestion;
use App\Models\LearningClass;
use App\Models\LearningClassAssessment;
use Illuminate\Pagination\LengthAwarePaginator;

class AssessmentAttemptReviewQueryService
{
    /** @return LengthAwarePaginator<int, AssessmentAttempt> */
    public function attempts(LearningClassAssessment $assignment, ?AssessmentAttemptStatus $status): LengthAwarePaginator
    {
        return AssessmentAttempt::query()
            ->where('learning_class_assessment_id', $assignment->id)
            ->when($status, fn ($query) => $query->where('status', $status->value))
            ->with('enrollment.student:id,name,email')
            ->orderByDesc('submitted_at')
            ->orderByDesc('attempt_number')
            ->paginate(15)
            ->withQueryString();
    }

    /** @return array<string, mixed> */
    public function listPayload(
        LearningClass $learningClass,
        LearningClassAssessment $assignment,
        ?AssessmentAttemptStatus $status,
        string $gradeRouteName,
    ): array {
        $assignment->loadMissing('assessment.competency:id,course_id,name');
        // The assessment relation is only ever missing for genuinely corrupt
        // data (e.g. an Assessment removed outside AssessmentService::delete()'s
        // guard) — never a state normal application flows can produce. Fail
        // with a controlled 404 rather than a null-property crash.
        abort_if($assignment->assessment === null, 404);
        $paginator = $this->attempts($assignment, $status);

        return [
            'learningClass' => ['id' => $learningClass->id, 'name' => $learningClass->name],
            'assignment' => [
                'id' => $assignment->id,
                'title' => $assignment->assessment->title,
                'competency' => $assignment->assessment->competency->name,
            ],
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
                        ? route($gradeRouteName, [$learningClass, $assignment, $attempt])
                        : null,
                ])->all(),
                'links' => $paginator->linkCollection()->all(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
                'total' => $paginator->total(),
            ],
            'filters' => ['status' => $status === null ? '' : $status->value],
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
            'attemptQuestions' => fn ($query) => $query
                ->where('question_type', QuestionType::Essay->value)
                ->with('answer'),
        ]);
        abort_if($attempt->classAssessment->assessment === null, 404);

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
            'essays' => $attempt->attemptQuestions->map(fn (AssessmentAttemptQuestion $question): array => [
                'id' => $question->id,
                'prompt' => $question->prompt,
                'answer_text' => $question->answer?->answer_text,
                'points' => $question->points,
                'manual_score' => $question->answer?->manual_score,
                'feedback' => $question->answer?->feedback,
            ])->values()->all(),
        ];
    }
}
