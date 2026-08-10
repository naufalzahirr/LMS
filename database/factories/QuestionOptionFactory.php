<?php

namespace Database\Factories;

use App\Models\Question;
use App\Models\QuestionOption;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<QuestionOption> */
class QuestionOptionFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'question_id' => Question::factory()->multipleChoice(),
            'option_text' => fake()->sentence(),
            'is_correct' => false,
            'sort_order' => fake()->numberBetween(0, 10),
        ];
    }
}
