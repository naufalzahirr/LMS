<?php

namespace Database\Factories;

use App\Enums\QuestionType;
use App\Models\AssessmentAttemptOption;
use App\Models\AssessmentAttemptQuestion;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<AssessmentAttemptOption> */
class AssessmentAttemptOptionFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'assessment_attempt_question_id' => AssessmentAttemptQuestion::factory()->state([
                'question_type' => QuestionType::MultipleChoice,
            ]),
            'option_text' => fake()->sentence(),
            'is_correct' => false,
            'sort_order' => 0,
        ];
    }
}
