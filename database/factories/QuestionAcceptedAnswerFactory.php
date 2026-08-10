<?php

namespace Database\Factories;

use App\Models\Question;
use App\Models\QuestionAcceptedAnswer;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<QuestionAcceptedAnswer> */
class QuestionAcceptedAnswerFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'question_id' => Question::factory()->shortAnswer(),
            'answer_text' => fake()->word(),
            'case_sensitive' => false,
        ];
    }
}
