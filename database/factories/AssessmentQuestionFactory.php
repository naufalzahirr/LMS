<?php

namespace Database\Factories;

use App\Models\Assessment;
use App\Models\AssessmentQuestion;
use App\Models\Question;
use Illuminate\Database\Eloquent\Factories\Factory;
use LogicException;

/** @extends Factory<AssessmentQuestion> */
class AssessmentQuestionFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'assessment_id' => Assessment::factory(),
            'question_id' => function (array $attributes): int {
                $assessmentId = $attributes['assessment_id'];

                if (! is_int($assessmentId)) {
                    throw new LogicException('A persisted assessment is required.');
                }

                $assessment = Assessment::query()->whereKey($assessmentId)->firstOrFail();
                $bank = QuestionBankFactory::new()->for($assessment->competency->course)->createOne();

                return Question::factory()
                    ->for($bank, 'questionBank')
                    ->for($assessment->competency, 'competency')
                    ->essay()
                    ->create()
                    ->id;
            },
            'points' => 1,
            'sort_order' => fake()->numberBetween(0, 20),
        ];
    }
}
