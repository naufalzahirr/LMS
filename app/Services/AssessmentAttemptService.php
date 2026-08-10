<?php

namespace App\Services;

use App\Enums\AcademicStatus;
use App\Enums\AssessmentAttemptStatus;
use App\Enums\AssessmentStatus;
use App\Enums\ClassAssessmentStatus;
use App\Enums\EnrollmentStatus;
use App\Enums\LearningClassStatus;
use App\Enums\QuestionType;
use App\Models\AssessmentAnswer;
use App\Models\AssessmentAttempt;
use App\Models\AssessmentAttemptQuestion;
use App\Models\AssessmentQuestion;
use App\Models\Enrollment;
use App\Models\LearningClassAssessment;
use App\Models\Question;
use App\Models\QuestionAcceptedAnswer;
use App\Models\QuestionOption;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AssessmentAttemptService
{
    public function __construct(
        private readonly QuestionService $questionService,
        private readonly CompetencyAccessService $competencyAccess,
        private readonly MasteryEvaluationService $masteryEvaluation,
    ) {}

    public function startAttempt(
        User $student,
        Enrollment $enrollment,
        LearningClassAssessment $assignment,
    ): AssessmentAttempt {
        try {
            return DB::transaction(function () use ($student, $enrollment, $assignment): AssessmentAttempt {
                $lockedEnrollment = Enrollment::query()->whereKey($enrollment->id)->lockForUpdate()->firstOrFail();
                $lockedAssignment = LearningClassAssessment::query()->whereKey($assignment->id)->lockForUpdate()->firstOrFail();
                $this->ensureStudentOwnsEnrollment($student, $lockedEnrollment);
                $this->ensureActiveDelivery($lockedEnrollment, $lockedAssignment);

                $existing = AssessmentAttempt::query()
                    ->where('enrollment_id', $lockedEnrollment->id)
                    ->where('learning_class_assessment_id', $lockedAssignment->id)
                    ->where('status', AssessmentAttemptStatus::InProgress->value)
                    ->lockForUpdate()
                    ->first();

                if ($existing instanceof AssessmentAttempt) {
                    return $existing;
                }

                $this->ensureWindowIsOpen($lockedAssignment, true);
                $lockedAssignment->loadMissing('assessment:id,competency_id,status,shuffle_questions');

                if ($lockedAssignment->assessment->status !== AssessmentStatus::Published) {
                    throw ValidationException::withMessages([
                        'assessment' => __('This assessment is not available for new attempts.'),
                    ]);
                }

                $this->competencyAccess->ensureMasteryAssessmentMayStart($lockedEnrollment, $lockedAssignment);

                $usedAttempts = AssessmentAttempt::query()
                    ->where('enrollment_id', $lockedEnrollment->id)
                    ->where('learning_class_assessment_id', $lockedAssignment->id)
                    ->count();

                if ($usedAttempts >= $lockedAssignment->max_attempts) {
                    throw ValidationException::withMessages([
                        'assessment' => __('You have used all available attempts for this assessment.'),
                    ]);
                }

                $composition = AssessmentQuestion::query()
                    ->where('assessment_id', $lockedAssignment->assessment_id)
                    ->orderBy('sort_order')
                    ->orderBy('id')
                    ->get();

                if ($composition->isEmpty()) {
                    throw ValidationException::withMessages([
                        'assessment' => __('This assessment has no questions available.'),
                    ]);
                }

                $questions = Question::query()
                    ->with(['options', 'acceptedAnswers'])
                    ->whereIn('id', $composition->pluck('question_id'))
                    ->get()
                    ->keyBy('id');
                $validComposition = $composition->every(function (AssessmentQuestion $item) use ($lockedAssignment, $questions): bool {
                    $question = $questions->get($item->question_id);

                    return $question instanceof Question
                        && $question->status === AcademicStatus::Active
                        && $question->competency_id === $lockedAssignment->assessment->competency_id
                        && (float) $item->points > 0
                        && $this->questionService->hasValidAnswerKey($question);
                });

                if (! $validComposition) {
                    throw ValidationException::withMessages([
                        'assessment' => __('This assessment is temporarily unavailable because its question configuration is no longer valid.'),
                    ]);
                }

                if ($lockedAssignment->assessment->shuffle_questions) {
                    $composition = $composition->shuffle()->values();
                }

                $attempt = AssessmentAttempt::query()->create([
                    'learning_class_assessment_id' => $lockedAssignment->id,
                    'enrollment_id' => $lockedEnrollment->id,
                    'attempt_number' => $usedAttempts + 1,
                    'status' => AssessmentAttemptStatus::InProgress,
                    'started_at' => now(),
                    'max_points' => $composition->sum(fn (AssessmentQuestion $item): float => (float) $item->points),
                ]);

                foreach ($composition->values() as $index => $item) {
                    $question = $questions->get($item->question_id);

                    if (! $question instanceof Question) {
                        throw ValidationException::withMessages([
                            'assessment' => __('This assessment is temporarily unavailable because its question configuration is no longer valid.'),
                        ]);
                    }

                    $snapshot = $attempt->attemptQuestions()->create([
                        'source_question_id' => $question->id,
                        'question_type' => $question->question_type,
                        'prompt' => $question->prompt,
                        'explanation' => $question->explanation,
                        'points' => $item->points,
                        'sort_order' => $index,
                        'correct_boolean' => $question->correct_boolean,
                    ]);
                    $snapshot->options()->createMany($question->options->map(
                        fn (QuestionOption $option): array => [
                            'option_text' => $option->option_text,
                            'is_correct' => $option->is_correct,
                            'sort_order' => $option->sort_order,
                        ],
                    )->all());
                    $snapshot->acceptedAnswers()->createMany($question->acceptedAnswers->map(
                        fn (QuestionAcceptedAnswer $answer): array => [
                            'answer_text' => $answer->answer_text,
                            'case_sensitive' => $answer->case_sensitive,
                        ],
                    )->all());
                }

                return $attempt->refresh();
            });
        } catch (QueryException $exception) {
            $existing = AssessmentAttempt::query()
                ->where('enrollment_id', $enrollment->id)
                ->where('learning_class_assessment_id', $assignment->id)
                ->where('status', AssessmentAttemptStatus::InProgress->value)
                ->first();

            if ($existing instanceof AssessmentAttempt) {
                return $existing;
            }

            throw $exception;
        }
    }

    public function submit(User $student, AssessmentAttempt $attempt): AssessmentAttempt
    {
        return DB::transaction(function () use ($student, $attempt): AssessmentAttempt {
            $lockedAttempt = AssessmentAttempt::query()->whereKey($attempt->id)->lockForUpdate()->firstOrFail();
            $this->ensureAttemptMayBeModified($student, $lockedAttempt);
            $lockedAttempt->load([
                'attemptQuestions.options',
                'attemptQuestions.acceptedAnswers',
                'attemptQuestions.answer.selectedOptions',
            ]);

            $autoPoints = 0.0;
            $hasEssay = false;

            foreach ($lockedAttempt->attemptQuestions as $question) {
                $answer = $question->answer ?? AssessmentAnswer::query()->create([
                    'assessment_attempt_id' => $lockedAttempt->id,
                    'assessment_attempt_question_id' => $question->id,
                ]);

                if ($question->question_type === QuestionType::Essay) {
                    $hasEssay = true;
                    $answer->update(['auto_score' => null, 'is_correct' => null]);

                    continue;
                }

                $correct = $this->isObjectiveAnswerCorrect($question, $answer);
                $score = $correct ? (float) $question->points : 0.0;
                $autoPoints += $score;
                $answer->update([
                    'auto_score' => $this->decimal($score),
                    'manual_score' => null,
                    'is_correct' => $correct,
                ]);
            }

            $attributes = [
                'submitted_at' => now(),
                'auto_points' => $this->decimal($autoPoints),
            ];

            if ($hasEssay) {
                $attributes += [
                    'status' => AssessmentAttemptStatus::PendingGrading,
                    'manual_points' => null,
                    'earned_points' => null,
                    'percentage' => null,
                    'graded_at' => null,
                ];
            } else {
                $attributes += [
                    'status' => AssessmentAttemptStatus::Graded,
                    'manual_points' => '0.00',
                    'earned_points' => $this->decimal($autoPoints),
                    'percentage' => $this->percentage($autoPoints, (float) $lockedAttempt->max_points),
                    'graded_at' => now(),
                ];
            }

            $lockedAttempt->update($attributes);

            if ($lockedAttempt->status === AssessmentAttemptStatus::Graded) {
                $this->masteryEvaluation->evaluate($lockedAttempt->refresh());
            }

            return $lockedAttempt->refresh();
        });
    }

    public function ensureAttemptMayBeModified(User $student, AssessmentAttempt $attempt): void
    {
        $attempt->loadMissing(['enrollment.learningClass', 'classAssessment']);
        $this->ensureStudentOwnsEnrollment($student, $attempt->enrollment);

        if ($attempt->status !== AssessmentAttemptStatus::InProgress) {
            throw ValidationException::withMessages([
                'attempt' => __('Submitted assessment answers cannot be changed.'),
            ]);
        }

        $this->ensureActiveDelivery($attempt->enrollment, $attempt->classAssessment);
        $this->ensureWindowIsOpen($attempt->classAssessment, false);
    }

    private function ensureStudentOwnsEnrollment(User $student, Enrollment $enrollment): void
    {
        if (! $student->hasRole('Student') || $enrollment->student_id !== $student->id) {
            throw ValidationException::withMessages([
                'assessment' => __('This assessment attempt does not belong to you.'),
            ]);
        }
    }

    private function ensureActiveDelivery(Enrollment $enrollment, LearningClassAssessment $assignment): void
    {
        $enrollment->loadMissing('learningClass:id,status');

        if ($assignment->learning_class_id !== $enrollment->learning_class_id) {
            throw ValidationException::withMessages([
                'assessment' => __('This assessment is not assigned to your enrollment.'),
            ]);
        }

        if ($enrollment->status !== EnrollmentStatus::Active) {
            throw ValidationException::withMessages([
                'assessment' => __('Only an active enrollment may start or modify an assessment attempt.'),
            ]);
        }

        if ($enrollment->learningClass->status !== LearningClassStatus::Active) {
            throw ValidationException::withMessages([
                'assessment' => __('This class is not active.'),
            ]);
        }

        if ($assignment->status !== ClassAssessmentStatus::Active) {
            throw ValidationException::withMessages([
                'assessment' => __('This assessment assignment is not active.'),
            ]);
        }
    }

    private function ensureWindowIsOpen(LearningClassAssessment $assignment, bool $checkOpening): void
    {
        if ($checkOpening && $assignment->opens_at !== null && now()->lt($assignment->opens_at)) {
            throw ValidationException::withMessages([
                'assessment' => __('This assessment is not open yet.'),
            ]);
        }

        if ($assignment->closes_at !== null && now()->gt($assignment->closes_at)) {
            throw ValidationException::withMessages([
                'assessment' => __('The assessment submission period has ended.'),
            ]);
        }
    }

    private function isObjectiveAnswerCorrect(AssessmentAttemptQuestion $question, AssessmentAnswer $answer): bool
    {
        return match ($question->question_type) {
            QuestionType::MultipleChoice, QuestionType::MultipleSelect => $answer->selectedOptions
                ->pluck('id')->sort()->values()->all() === $question->options
                ->where('is_correct', true)->pluck('id')->sort()->values()->all(),
            QuestionType::TrueFalse => $answer->answer_boolean !== null
                && $answer->answer_boolean === $question->correct_boolean,
            QuestionType::ShortAnswer => $this->matchesAcceptedAnswer($question, $answer->answer_text),
            QuestionType::Essay => false,
        };
    }

    private function matchesAcceptedAnswer(AssessmentAttemptQuestion $question, ?string $studentAnswer): bool
    {
        if ($studentAnswer === null) {
            return false;
        }

        $studentAnswer = trim($studentAnswer);

        return $question->acceptedAnswers->contains(function ($accepted) use ($studentAnswer): bool {
            $expected = trim($accepted->answer_text);

            return $accepted->case_sensitive
                ? $studentAnswer === $expected
                : mb_strtolower($studentAnswer) === mb_strtolower($expected);
        });
    }

    private function percentage(float $earned, float $maximum): string
    {
        return $maximum <= 0 ? '0.00' : $this->decimal(($earned / $maximum) * 100);
    }

    private function decimal(float $value): string
    {
        return number_format($value, 2, '.', '');
    }
}
