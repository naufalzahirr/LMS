<?php

namespace App\Services;

use App\Enums\AssessmentAttemptStatus;
use App\Enums\QuestionType;
use App\Models\AssessmentAnswer;
use App\Models\AssessmentAttempt;
use App\Models\AssessmentAttemptQuestion;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AssessmentGradingService
{
    public function __construct(private readonly MasteryEvaluationService $masteryEvaluation) {}

    /**
     * @param  array<int, array{attempt_question_id: int, manual_score: string, feedback: string|null}>  $grades
     */
    public function grade(AssessmentAttempt $attempt, User $grader, array $grades): AssessmentAttempt
    {
        if (! $grader->can('grade', $attempt)) {
            throw new AuthorizationException;
        }

        return DB::transaction(function () use ($attempt, $grader, $grades): AssessmentAttempt {
            $lockedAttempt = AssessmentAttempt::query()->whereKey($attempt->id)->lockForUpdate()->firstOrFail();

            if (! in_array($lockedAttempt->status, [AssessmentAttemptStatus::PendingGrading, AssessmentAttemptStatus::Graded], true)) {
                throw ValidationException::withMessages([
                    'attempt' => __('Only submitted Essay attempts may be graded.'),
                ]);
            }

            foreach ($grades as $grade) {
                $question = AssessmentAttemptQuestion::query()
                    ->whereKey($grade['attempt_question_id'])
                    ->where('assessment_attempt_id', $lockedAttempt->id)
                    ->where('question_type', QuestionType::Essay->value)
                    ->first();

                if (! $question instanceof AssessmentAttemptQuestion) {
                    throw ValidationException::withMessages([
                        'grades' => __('Every grade must reference an Essay question from this attempt.'),
                    ]);
                }

                $score = (float) $grade['manual_score'];

                if ($score < 0 || $score > (float) $question->points) {
                    throw ValidationException::withMessages([
                        'grades' => __('Essay scores must be between zero and the question maximum points.'),
                    ]);
                }

                AssessmentAnswer::query()->updateOrCreate(
                    [
                        'assessment_attempt_id' => $lockedAttempt->id,
                        'assessment_attempt_question_id' => $question->id,
                    ],
                    [
                        'manual_score' => $this->decimal($score),
                        'feedback' => $grade['feedback'],
                        'graded_by' => $grader->id,
                        'graded_at' => now(),
                    ],
                );
            }

            $essayQuestions = AssessmentAttemptQuestion::query()
                ->where('assessment_attempt_id', $lockedAttempt->id)
                ->where('question_type', QuestionType::Essay->value)
                ->with('answer')
                ->get();
            $fullyGraded = $essayQuestions->isNotEmpty()
                && $essayQuestions->every(fn (AssessmentAttemptQuestion $question): bool => $question->answer?->manual_score !== null);

            if (! $fullyGraded) {
                $lockedAttempt->update([
                    'status' => AssessmentAttemptStatus::PendingGrading,
                    'manual_points' => null,
                    'earned_points' => null,
                    'percentage' => null,
                    'graded_at' => null,
                ]);

                return $lockedAttempt->refresh();
            }

            $manualPoints = $essayQuestions->sum(
                fn (AssessmentAttemptQuestion $question): float => (float) $question->answer?->manual_score,
            );
            $autoPoints = (float) ($lockedAttempt->auto_points ?? 0);
            $earnedPoints = $autoPoints + $manualPoints;
            $lockedAttempt->update([
                'status' => AssessmentAttemptStatus::Graded,
                'manual_points' => $this->decimal($manualPoints),
                'earned_points' => $this->decimal($earnedPoints),
                'percentage' => (float) $lockedAttempt->max_points <= 0
                    ? '0.00'
                    : $this->decimal(($earnedPoints / (float) $lockedAttempt->max_points) * 100),
                'graded_at' => now(),
            ]);
            $this->masteryEvaluation->evaluate($lockedAttempt->refresh());

            return $lockedAttempt->refresh();
        });
    }

    private function decimal(float $value): string
    {
        return number_format($value, 2, '.', '');
    }
}
