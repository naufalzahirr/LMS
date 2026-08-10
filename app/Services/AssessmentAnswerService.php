<?php

namespace App\Services;

use App\Enums\QuestionType;
use App\Models\AssessmentAnswer;
use App\Models\AssessmentAttempt;
use App\Models\AssessmentAttemptOption;
use App\Models\AssessmentAttemptQuestion;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AssessmentAnswerService
{
    public function __construct(private readonly AssessmentAttemptService $attemptService) {}

    /**
     * @param  array{answer_text: string|null, answer_boolean: bool|null, selected_option_ids: array<int, int>}  $data
     */
    public function save(
        User $student,
        AssessmentAttempt $attempt,
        AssessmentAttemptQuestion $attemptQuestion,
        array $data,
    ): AssessmentAnswer {
        return DB::transaction(function () use ($student, $attempt, $attemptQuestion, $data): AssessmentAnswer {
            $lockedAttempt = AssessmentAttempt::query()->whereKey($attempt->id)->lockForUpdate()->firstOrFail();
            $this->attemptService->ensureAttemptMayBeModified($student, $lockedAttempt);

            if ($attemptQuestion->assessment_attempt_id !== $lockedAttempt->id) {
                throw ValidationException::withMessages([
                    'question' => __('The question does not belong to this assessment attempt.'),
                ]);
            }

            $selectedIds = array_values(array_unique($data['selected_option_ids']));
            $this->validateShape($attemptQuestion, $selectedIds);

            if ($selectedIds !== []) {
                $matchingOptions = AssessmentAttemptOption::query()
                    ->where('assessment_attempt_question_id', $attemptQuestion->id)
                    ->whereIn('id', $selectedIds)
                    ->count();

                if ($matchingOptions !== count($selectedIds)) {
                    throw ValidationException::withMessages([
                        'selected_option_ids' => __('Every selected option must belong to this question.'),
                    ]);
                }
            }

            $answer = AssessmentAnswer::query()->updateOrCreate(
                [
                    'assessment_attempt_id' => $lockedAttempt->id,
                    'assessment_attempt_question_id' => $attemptQuestion->id,
                ],
                [
                    'answer_text' => in_array($attemptQuestion->question_type, [QuestionType::ShortAnswer, QuestionType::Essay], true)
                        ? $data['answer_text']
                        : null,
                    'answer_boolean' => $attemptQuestion->question_type === QuestionType::TrueFalse
                        ? $data['answer_boolean']
                        : null,
                    'auto_score' => null,
                    'manual_score' => null,
                    'is_correct' => null,
                    'feedback' => null,
                    'graded_by' => null,
                    'graded_at' => null,
                ],
            );
            $answer->selectedOptions()->sync($selectedIds);

            return $answer->refresh()->load('selectedOptions');
        });
    }

    /** @param array<int, int> $selectedIds */
    private function validateShape(AssessmentAttemptQuestion $question, array $selectedIds): void
    {
        if ($question->question_type === QuestionType::MultipleChoice && count($selectedIds) > 1) {
            throw ValidationException::withMessages([
                'selected_option_ids' => __('Multiple choice accepts at most one selected option.'),
            ]);
        }

        if (! in_array($question->question_type, [QuestionType::MultipleChoice, QuestionType::MultipleSelect], true)
            && $selectedIds !== []) {
            throw ValidationException::withMessages([
                'selected_option_ids' => __('This question type does not accept option selections.'),
            ]);
        }
    }
}
