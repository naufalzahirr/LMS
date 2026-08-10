<?php

namespace Database\Factories;

use App\Enums\QuestionType;
use App\Models\AssessmentAttemptAcceptedAnswer;
use App\Models\AssessmentAttemptQuestion;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<AssessmentAttemptAcceptedAnswer> */
class AssessmentAttemptAcceptedAnswerFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'assessment_attempt_question_id' => AssessmentAttemptQuestion::factory()->state([
                'question_type' => QuestionType::ShortAnswer,
            ]),
            'answer_text' => fake()->word(),
            'case_sensitive' => false,
        ];
    }
}
