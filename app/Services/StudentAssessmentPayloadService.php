<?php

namespace App\Services;

use App\Enums\AssessmentAttemptStatus;
use App\Enums\AssessmentFeedbackMode;
use App\Enums\AssessmentStatus;
use App\Enums\ClassAssessmentStatus;
use App\Enums\EnrollmentStatus;
use App\Enums\LearningClassStatus;
use App\Enums\QuestionType;
use App\Models\AssessmentAnswer;
use App\Models\AssessmentAttempt;
use App\Models\AssessmentAttemptOption;
use App\Models\AssessmentAttemptQuestion;
use App\Models\Enrollment;
use App\Models\LearningClassAssessment;

class StudentAssessmentPayloadService
{
    /** @return array<int, array<string, mixed>> */
    public function assignmentsForEnrollment(Enrollment $enrollment): array
    {
        $assignments = LearningClassAssessment::query()
            ->where('learning_class_id', $enrollment->learning_class_id)
            ->with([
                'assessment' => fn ($query) => $query
                    ->with('competency:id,course_id,name')
                    ->withCount('assessmentQuestions')
                    ->withSum('assessmentQuestions as total_points', 'points'),
                'attempts' => fn ($query) => $query
                    ->where('enrollment_id', $enrollment->id)
                    ->orderByDesc('attempt_number'),
            ])
            ->orderBy('created_at')
            ->get();

        return $assignments->map(
            fn (LearningClassAssessment $assignment): array => $this->assignmentCard($enrollment, $assignment),
        )->all();
    }

    /** @return array<string, mixed> */
    public function assignmentIntro(Enrollment $enrollment, LearningClassAssessment $assignment): array
    {
        $assignment->loadMissing([
            'assessment.competency:id,course_id,name',
            'attempts' => fn ($query) => $query
                ->where('enrollment_id', $enrollment->id)
                ->orderByDesc('attempt_number'),
        ]);
        $assignment->assessment->loadCount('assessmentQuestions');
        $assignment->assessment->loadSum('assessmentQuestions as total_points', 'points');
        $card = $this->assignmentCard($enrollment, $assignment);

        return [
            ...$card,
            'description' => $assignment->assessment->description,
            'instructions' => $assignment->assessment->instructions,
            'attempts' => $assignment->attempts->map(fn (AssessmentAttempt $attempt): array => [
                ...$this->attemptSummary($attempt),
                'result_url' => $attempt->status === AssessmentAttemptStatus::InProgress
                    ? null
                    : route('student.assessment-attempts.result', $attempt),
            ])->values()->all(),
        ];
    }

    /** @return array<string, mixed> */
    public function attemptPlayer(AssessmentAttempt $attempt): array
    {
        $attempt->loadMissing([
            'classAssessment.assessment:id,title',
            'attemptQuestions.options',
            'attemptQuestions.answer.selectedOptions',
        ]);

        return [
            'id' => $attempt->id,
            'assessment_title' => $attempt->classAssessment->assessment->title,
            'attempt_number' => $attempt->attempt_number,
            'status' => $attempt->status->value,
            'started_at' => $attempt->started_at->toDateTimeString(),
            'questions' => $attempt->attemptQuestions->map(
                fn (AssessmentAttemptQuestion $question): array => [
                    'id' => $question->id,
                    'question_type' => $question->question_type->value,
                    'prompt' => $question->prompt,
                    'points' => $question->points,
                    'sort_order' => $question->sort_order,
                    'options' => $question->options->map(fn (AssessmentAttemptOption $option): array => [
                        'id' => $option->id,
                        'option_text' => $option->option_text,
                        'sort_order' => $option->sort_order,
                    ])->all(),
                    'answer' => $this->studentAnswer($question->answer),
                    'answer_url' => route('student.assessment-answers.update', [$attempt, $question]),
                ],
            )->values()->all(),
            'submit_url' => route('student.assessment-attempts.submit', $attempt),
            'back_url' => route('student.assessments.show', [
                $attempt->classAssessment->learning_class_id,
                $attempt->classAssessment,
            ]),
        ];
    }

    /** @return array<string, mixed> */
    public function result(AssessmentAttempt $attempt): array
    {
        $attempt->loadMissing([
            'classAssessment.assessment:id,title',
            'attemptQuestions.options',
            'attemptQuestions.acceptedAnswers',
            'attemptQuestions.answer.selectedOptions',
        ]);
        $detailed = $this->detailedFeedbackAllowed($attempt);
        $payload = [
            'id' => $attempt->id,
            'assessment_title' => $attempt->classAssessment->assessment->title,
            ...$this->attemptSummary($attempt),
            'max_attempts' => $attempt->classAssessment->max_attempts,
            'detailed_feedback' => $detailed,
            'assessment_url' => route('student.assessments.show', [
                $attempt->classAssessment->learning_class_id,
                $attempt->classAssessment,
            ]),
        ];

        if (! $detailed) {
            return $payload;
        }

        return [
            ...$payload,
            'questions' => $attempt->attemptQuestions->map(
                fn (AssessmentAttemptQuestion $question): array => $this->resultQuestion($question),
            )->values()->all(),
        ];
    }

    public function detailedFeedbackAllowed(AssessmentAttempt $attempt): bool
    {
        if ($attempt->status !== AssessmentAttemptStatus::Graded) {
            return false;
        }

        $attempt->loadMissing('classAssessment');
        $assignment = $attempt->classAssessment;

        return match ($assignment->feedback_mode) {
            AssessmentFeedbackMode::ScoreOnly => false,
            AssessmentFeedbackMode::AfterEachAttempt => true,
            AssessmentFeedbackMode::AfterFinalAttempt => $assignment->closes_at?->isPast() === true
                || $assignment->attempts()
                    ->where('enrollment_id', $attempt->enrollment_id)
                    ->whereIn('status', [
                        AssessmentAttemptStatus::PendingGrading->value,
                        AssessmentAttemptStatus::Graded->value,
                    ])
                    ->count() >= $assignment->max_attempts,
        };
    }

    /** @return array<string, mixed> */
    private function assignmentCard(Enrollment $enrollment, LearningClassAssessment $assignment): array
    {
        $inProgress = $assignment->attempts->first(
            fn (AssessmentAttempt $attempt): bool => $attempt->status === AssessmentAttemptStatus::InProgress,
        );
        $usedAttempts = $assignment->attempts->count();
        $canStart = $this->canStart($enrollment, $assignment, $usedAttempts);
        $state = $this->availabilityState($enrollment, $assignment, $usedAttempts, $inProgress);

        return [
            'id' => $assignment->id,
            'title' => $assignment->assessment->title,
            'competency' => $assignment->assessment->competency->name,
            'purpose' => $assignment->assessment->purpose->value,
            'question_count' => $assignment->assessment->assessment_questions_count ?? 0,
            'total_points' => $assignment->assessment->getAttribute('total_points') ?? '0.00',
            'max_attempts' => $assignment->max_attempts,
            'attempts_used' => $usedAttempts,
            'opens_at' => $assignment->opens_at?->toDateTimeString(),
            'closes_at' => $assignment->closes_at?->toDateTimeString(),
            'availability' => $state,
            'can_start' => $canStart || $inProgress instanceof AssessmentAttempt,
            'start_label' => $inProgress instanceof AssessmentAttempt ? 'Resume Assessment' : 'Start Assessment',
            'start_url' => route('student.assessments.start', [$enrollment->learning_class_id, $assignment]),
            'intro_url' => route('student.assessments.show', [$enrollment->learning_class_id, $assignment]),
            'in_progress_url' => $inProgress instanceof AssessmentAttempt
                ? route('student.assessment-attempts.show', $inProgress)
                : null,
        ];
    }

    private function canStart(Enrollment $enrollment, LearningClassAssessment $assignment, int $usedAttempts): bool
    {
        $enrollment->loadMissing('learningClass:id,status');

        return $enrollment->status === EnrollmentStatus::Active
            && $enrollment->learningClass->status === LearningClassStatus::Active
            && $assignment->status === ClassAssessmentStatus::Active
            && $assignment->assessment->status === AssessmentStatus::Published
            && ($assignment->opens_at === null || $assignment->opens_at->isPast())
            && ($assignment->closes_at === null || $assignment->closes_at->isFuture())
            && $usedAttempts < $assignment->max_attempts;
    }

    private function availabilityState(
        Enrollment $enrollment,
        LearningClassAssessment $assignment,
        int $usedAttempts,
        ?AssessmentAttempt $inProgress,
    ): string {
        if ($inProgress instanceof AssessmentAttempt) {
            return 'In Progress';
        }

        if ($assignment->opens_at !== null && $assignment->opens_at->isFuture()) {
            return 'Not Open Yet';
        }

        if ($assignment->closes_at !== null && $assignment->closes_at->isPast()) {
            return 'Closed';
        }

        if ($usedAttempts >= $assignment->max_attempts) {
            return 'Attempts Exhausted';
        }

        $latest = $assignment->attempts->first();

        if ($latest?->status === AssessmentAttemptStatus::PendingGrading) {
            return 'Submitted / Pending Grading';
        }

        if ($latest?->status === AssessmentAttemptStatus::Graded) {
            return 'Graded';
        }

        return $this->canStart($enrollment, $assignment, $usedAttempts) ? 'Available' : 'Closed';
    }

    /** @return array<string, mixed> */
    private function attemptSummary(AssessmentAttempt $attempt): array
    {
        return [
            'attempt_number' => $attempt->attempt_number,
            'status' => $attempt->status->value,
            'started_at' => $attempt->started_at->toDateTimeString(),
            'submitted_at' => $attempt->submitted_at?->toDateTimeString(),
            'graded_at' => $attempt->graded_at?->toDateTimeString(),
            'earned_points' => $attempt->earned_points,
            'max_points' => $attempt->max_points,
            'percentage' => $attempt->percentage,
        ];
    }

    /** @return array{answer_text: string|null, answer_boolean: bool|null, selected_option_ids: array<int, int>} */
    private function studentAnswer(?AssessmentAnswer $answer): array
    {
        return [
            'answer_text' => $answer?->answer_text,
            'answer_boolean' => $answer?->answer_boolean,
            'selected_option_ids' => $answer === null
                ? []
                : $answer->selectedOptions->pluck('id')->values()->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function resultQuestion(AssessmentAttemptQuestion $question): array
    {
        $answer = $question->answer;

        return [
            'id' => $question->id,
            'question_type' => $question->question_type->value,
            'prompt' => $question->prompt,
            'question_points' => $question->points,
            'points_earned' => $question->question_type === QuestionType::Essay
                ? $answer?->manual_score
                : $answer?->auto_score,
            'correct' => $answer?->is_correct,
            'student_answer' => $this->resultStudentAnswer($question, $answer),
            'correct_answer' => $this->correctAnswer($question),
            'explanation' => $question->explanation,
            'feedback' => $answer?->feedback,
        ];
    }

    private function resultStudentAnswer(AssessmentAttemptQuestion $question, ?AssessmentAnswer $answer): mixed
    {
        if ($answer === null) {
            return null;
        }

        return match ($question->question_type) {
            QuestionType::MultipleChoice, QuestionType::MultipleSelect => $answer->selectedOptions
                ->pluck('option_text')->values()->all(),
            QuestionType::TrueFalse => $answer->answer_boolean,
            QuestionType::ShortAnswer, QuestionType::Essay => $answer->answer_text,
        };
    }

    private function correctAnswer(AssessmentAttemptQuestion $question): mixed
    {
        return match ($question->question_type) {
            QuestionType::MultipleChoice, QuestionType::MultipleSelect => $question->options
                ->where('is_correct', true)->pluck('option_text')->values()->all(),
            QuestionType::TrueFalse => $question->correct_boolean,
            QuestionType::ShortAnswer => $question->acceptedAnswers->pluck('answer_text')->values()->all(),
            QuestionType::Essay => null,
        };
    }
}
