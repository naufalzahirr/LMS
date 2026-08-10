<?php

namespace Database\Factories;

use App\Models\AssessmentAnswer;
use App\Models\AssessmentAttempt;
use App\Models\AssessmentAttemptQuestion;
use Illuminate\Database\Eloquent\Factories\Factory;
use LogicException;

/** @extends Factory<AssessmentAnswer> */
class AssessmentAnswerFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'assessment_attempt_id' => AssessmentAttempt::factory(),
            'assessment_attempt_question_id' => function (array $attributes): int {
                $attemptId = $attributes['assessment_attempt_id'];

                if (! is_int($attemptId)) {
                    throw new LogicException('A persisted assessment attempt is required.');
                }

                return AssessmentAttemptQuestion::factory()->createOne([
                    'assessment_attempt_id' => $attemptId,
                ])->id;
            },
            'answer_text' => fake()->optional()->sentence(),
            'answer_boolean' => null,
            'auto_score' => null,
            'manual_score' => null,
            'is_correct' => null,
            'feedback' => null,
            'graded_by' => null,
            'graded_at' => null,
        ];
    }
}
