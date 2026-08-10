<?php

namespace Database\Factories;

use App\Enums\AcademicStatus;
use App\Enums\QuestionType;
use App\Models\Competency;
use App\Models\Question;
use App\Models\QuestionAcceptedAnswer;
use App\Models\QuestionBank;
use App\Models\QuestionOption;
use Illuminate\Database\Eloquent\Factories\Factory;
use LogicException;

/** @extends Factory<Question> */
class QuestionFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'question_bank_id' => QuestionBank::factory(),
            'competency_id' => function (array $attributes): int {
                $bankId = $attributes['question_bank_id'];

                if (! is_int($bankId)) {
                    throw new LogicException('A persisted question bank is required.');
                }

                $bank = QuestionBank::query()->whereKey($bankId)->firstOrFail();

                return Competency::factory()->for($bank->course)->create()->id;
            },
            'question_type' => QuestionType::Essay,
            'prompt' => fake()->sentence().'?',
            'explanation' => fake()->optional()->sentence(),
            'default_points' => 1,
            'correct_boolean' => null,
            'status' => AcademicStatus::Active,
            'sort_order' => fake()->numberBetween(0, 20),
        ];
    }

    public function multipleChoice(): static
    {
        return $this->state(fn (): array => [
            'question_type' => QuestionType::MultipleChoice,
            'correct_boolean' => null,
        ])->afterCreating(function (Question $question): void {
            QuestionOption::factory()->for($question)->create([
                'option_text' => 'Correct option',
                'is_correct' => true,
                'sort_order' => 0,
            ]);
            QuestionOption::factory()->for($question)->create([
                'option_text' => 'Incorrect option',
                'is_correct' => false,
                'sort_order' => 1,
            ]);
        });
    }

    public function multipleSelect(): static
    {
        return $this->state(fn (): array => [
            'question_type' => QuestionType::MultipleSelect,
            'correct_boolean' => null,
        ])->afterCreating(function (Question $question): void {
            foreach ([true, true, false] as $index => $correct) {
                QuestionOption::factory()->for($question)->create([
                    'option_text' => "Option {$index}",
                    'is_correct' => $correct,
                    'sort_order' => $index,
                ]);
            }
        });
    }

    public function trueFalse(): static
    {
        return $this->state(fn (): array => [
            'question_type' => QuestionType::TrueFalse,
            'correct_boolean' => true,
        ]);
    }

    public function shortAnswer(): static
    {
        return $this->state(fn (): array => [
            'question_type' => QuestionType::ShortAnswer,
            'correct_boolean' => null,
        ])->afterCreating(function (Question $question): void {
            QuestionAcceptedAnswer::factory()->for($question)->create([
                'answer_text' => 'Accepted answer',
            ]);
        });
    }

    public function essay(): static
    {
        return $this->state(fn (): array => [
            'question_type' => QuestionType::Essay,
            'correct_boolean' => null,
        ]);
    }
}
