<?php

namespace App\Services;

use App\Enums\AcademicStatus;
use App\Enums\QuestionType;
use App\Models\Competency;
use App\Models\Question;
use App\Models\QuestionBank;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class QuestionService
{
    /**
     * @param array{
     *   question_bank_id: int, competency_id: int, question_type: QuestionType,
     *   prompt: string, explanation: string|null, default_points: string,
     *   correct_boolean: bool|null, status: AcademicStatus, sort_order: int,
     *   options: array<int, array{option_text: string, is_correct: bool, sort_order: int}>,
     *   accepted_answers: array<int, array{answer_text: string, case_sensitive: bool}>
     * } $data
     */
    public function create(array $data): Question
    {
        return DB::transaction(function () use ($data): Question {
            $this->validateData($data);
            $question = Question::query()->create($this->questionAttributes($data));
            $this->syncAnswerKey($question, $data);

            return $question->refresh()->load(['options', 'acceptedAnswers']);
        });
    }

    /**
     * @param array{
     *   question_bank_id: int, competency_id: int, question_type: QuestionType,
     *   prompt: string, explanation: string|null, default_points: string,
     *   correct_boolean: bool|null, status: AcademicStatus, sort_order: int,
     *   options: array<int, array{option_text: string, is_correct: bool, sort_order: int}>,
     *   accepted_answers: array<int, array{answer_text: string, case_sensitive: bool}>
     * } $data
     */
    public function update(Question $question, array $data): Question
    {
        return DB::transaction(function () use ($question, $data): Question {
            $this->validateData($data);

            if ($question->assessmentQuestions()->exists() && $question->competency_id !== $data['competency_id']) {
                throw ValidationException::withMessages([
                    'competency_id' => __('A question attached to an assessment cannot move to another competency.'),
                ]);
            }

            $question->update($this->questionAttributes($data));
            $this->syncAnswerKey($question, $data);

            return $question->refresh()->load(['options', 'acceptedAnswers']);
        });
    }

    public function delete(Question $question): void
    {
        DB::transaction(function () use ($question): void {
            if ($question->assessmentQuestions()->exists()) {
                throw ValidationException::withMessages([
                    'question' => __('This question cannot be deleted while it is attached to an assessment.'),
                ]);
            }

            $question->delete();
        });
    }

    public function hasValidAnswerKey(Question $question): bool
    {
        $question->loadMissing(['options', 'acceptedAnswers']);

        return match ($question->question_type) {
            QuestionType::MultipleChoice => $question->options->count() >= 2
                && $question->options->where('is_correct', true)->count() === 1
                && $question->acceptedAnswers->isEmpty()
                && $question->correct_boolean === null,
            QuestionType::MultipleSelect => $question->options->count() >= 2
                && $question->options->where('is_correct', true)->isNotEmpty()
                && $question->acceptedAnswers->isEmpty()
                && $question->correct_boolean === null,
            QuestionType::TrueFalse => $question->correct_boolean !== null
                && $question->options->isEmpty()
                && $question->acceptedAnswers->isEmpty(),
            QuestionType::ShortAnswer => $question->acceptedAnswers->isNotEmpty()
                && $question->options->isEmpty()
                && $question->correct_boolean === null,
            QuestionType::Essay => $question->options->isEmpty()
                && $question->acceptedAnswers->isEmpty()
                && $question->correct_boolean === null,
        };
    }

    /**
     * @param array{
     *   question_bank_id: int, competency_id: int, question_type: QuestionType,
     *   prompt: string, explanation: string|null, default_points: string,
     *   correct_boolean: bool|null, status: AcademicStatus, sort_order: int,
     *   options: array<int, array{option_text: string, is_correct: bool, sort_order: int}>,
     *   accepted_answers: array<int, array{answer_text: string, case_sensitive: bool}>
     * } $data
     */
    private function validateData(array $data): void
    {
        $bank = QuestionBank::query()->find($data['question_bank_id']);
        $competency = Competency::query()->find($data['competency_id']);

        if (! $bank instanceof QuestionBank || ! $competency instanceof Competency
            || $bank->course_id !== $competency->course_id) {
            throw ValidationException::withMessages([
                'competency_id' => __('The question bank and competency must belong to the same course.'),
            ]);
        }

        $type = $data['question_type'];
        $options = collect($data['options'])->filter(
            fn (array $option): bool => trim($option['option_text']) !== '',
        );
        $correctOptions = $options->where('is_correct', true)->count();
        $answers = collect($data['accepted_answers'])->filter(
            fn (array $answer): bool => trim($answer['answer_text']) !== '',
        );

        if (in_array($type, [QuestionType::MultipleChoice, QuestionType::MultipleSelect], true)
            && $options->count() < 2) {
            throw ValidationException::withMessages(['options' => __('At least two answer options are required.')]);
        }

        if ($type === QuestionType::MultipleChoice && $correctOptions !== 1) {
            throw ValidationException::withMessages(['options' => __('Multiple choice requires exactly one correct option.')]);
        }

        if ($type === QuestionType::MultipleSelect && $correctOptions < 1) {
            throw ValidationException::withMessages(['options' => __('Multiple select requires at least one correct option.')]);
        }

        if ($type === QuestionType::TrueFalse && $data['correct_boolean'] === null) {
            throw ValidationException::withMessages(['correct_boolean' => __('Select the correct true/false answer.')]);
        }

        if ($type === QuestionType::ShortAnswer && $answers->isEmpty()) {
            throw ValidationException::withMessages(['accepted_answers' => __('At least one accepted answer is required.')]);
        }
    }

    /**
     * @param array{
     *   question_bank_id: int, competency_id: int, question_type: QuestionType,
     *   prompt: string, explanation: string|null, default_points: string,
     *   correct_boolean: bool|null, status: AcademicStatus, sort_order: int,
     *   options: array<int, array{option_text: string, is_correct: bool, sort_order: int}>,
     *   accepted_answers: array<int, array{answer_text: string, case_sensitive: bool}>
     * } $data
     * @return array{question_bank_id: int, competency_id: int, question_type: QuestionType, prompt: string, explanation: string|null, default_points: string, correct_boolean: bool|null, status: AcademicStatus, sort_order: int}
     */
    private function questionAttributes(array $data): array
    {
        return [
            'question_bank_id' => $data['question_bank_id'],
            'competency_id' => $data['competency_id'],
            'question_type' => $data['question_type'],
            'prompt' => trim($data['prompt']),
            'explanation' => $data['explanation'],
            'default_points' => $data['default_points'],
            'correct_boolean' => $data['question_type'] === QuestionType::TrueFalse
                ? $data['correct_boolean']
                : null,
            'status' => $data['status'],
            'sort_order' => $data['sort_order'],
        ];
    }

    /**
     * @param array{
     *   question_bank_id: int, competency_id: int, question_type: QuestionType,
     *   prompt: string, explanation: string|null, default_points: string,
     *   correct_boolean: bool|null, status: AcademicStatus, sort_order: int,
     *   options: array<int, array{option_text: string, is_correct: bool, sort_order: int}>,
     *   accepted_answers: array<int, array{answer_text: string, case_sensitive: bool}>
     * } $data
     */
    private function syncAnswerKey(Question $question, array $data): void
    {
        $question->options()->delete();
        $question->acceptedAnswers()->delete();

        if (in_array($data['question_type'], [QuestionType::MultipleChoice, QuestionType::MultipleSelect], true)) {
            $options = collect($data['options'])
                ->filter(fn (array $option): bool => trim($option['option_text']) !== '')
                ->values()
                ->map(fn (array $option): array => [
                    'option_text' => trim($option['option_text']),
                    'is_correct' => $option['is_correct'],
                    'sort_order' => $option['sort_order'],
                ])->all();
            $question->options()->createMany($options);
        }

        if ($data['question_type'] === QuestionType::ShortAnswer) {
            $seen = [];
            $answers = [];

            foreach ($data['accepted_answers'] as $answer) {
                $text = trim($answer['answer_text']);
                $key = mb_strtolower($text);

                if ($text === '' || isset($seen[$key])) {
                    continue;
                }

                $seen[$key] = true;
                $answers[] = ['answer_text' => $text, 'case_sensitive' => $answer['case_sensitive']];
            }

            $question->acceptedAnswers()->createMany($answers);
        }
    }
}
