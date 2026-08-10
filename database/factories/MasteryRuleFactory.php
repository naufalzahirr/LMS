<?php

namespace Database\Factories;

use App\Enums\AssessmentPurpose;
use App\Enums\AssessmentStatus;
use App\Enums\MasteryRuleStatus;
use App\Models\Assessment;
use App\Models\Competency;
use App\Models\LearningClass;
use App\Models\LearningClassAssessment;
use App\Models\MasteryRule;
use Illuminate\Database\Eloquent\Factories\Factory;
use LogicException;

/** @extends Factory<MasteryRule> */
class MasteryRuleFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'learning_class_id' => LearningClass::factory(),
            'competency_id' => function (array $attributes): int {
                $classId = $attributes['learning_class_id'];

                if (! is_int($classId)) {
                    throw new LogicException('A persisted learning class is required.');
                }

                $learningClass = LearningClass::query()->findOrFail($classId);

                return Competency::factory()->for($learningClass->course)->createOne()->id;
            },
            'learning_class_assessment_id' => function (array $attributes): int {
                $classId = $attributes['learning_class_id'];
                $competencyId = $attributes['competency_id'];

                if (! is_int($classId) || ! is_int($competencyId)) {
                    throw new LogicException('A persisted class and competency are required.');
                }

                $assessment = Assessment::factory()->createOne([
                    'competency_id' => $competencyId,
                    'purpose' => AssessmentPurpose::Mastery,
                    'status' => AssessmentStatus::Published,
                ]);

                return LearningClassAssessment::factory()->createOne([
                    'learning_class_id' => $classId,
                    'assessment_id' => $assessment->id,
                ])->id;
            },
            'mastery_score' => '80.00',
            'require_remedial' => true,
            'status' => MasteryRuleStatus::Active,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['status' => MasteryRuleStatus::Inactive]);
    }
}
