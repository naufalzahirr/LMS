<?php

namespace Database\Factories;

use App\Enums\QuestionType;
use App\Models\AssessmentAttempt;
use App\Models\AssessmentAttemptQuestion;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<AssessmentAttemptQuestion> */
class AssessmentAttemptQuestionFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'assessment_attempt_id' => AssessmentAttempt::factory(),
            'source_question_id' => null,
            'question_type' => QuestionType::Essay,
            'prompt' => fake()->sentence().'?',
            'explanation' => fake()->optional()->sentence(),
            'points' => '1.00',
            'sort_order' => 0,
            'correct_boolean' => null,
        ];
    }
}
