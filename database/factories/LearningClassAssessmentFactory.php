<?php

namespace Database\Factories;

use App\Enums\AssessmentFeedbackMode;
use App\Enums\AssessmentStatus;
use App\Enums\ClassAssessmentStatus;
use App\Models\Assessment;
use App\Models\Competency;
use App\Models\LearningClass;
use App\Models\LearningClassAssessment;
use Illuminate\Database\Eloquent\Factories\Factory;
use LogicException;

/** @extends Factory<LearningClassAssessment> */
class LearningClassAssessmentFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'learning_class_id' => LearningClass::factory(),
            'assessment_id' => function (array $attributes): int {
                $classId = $attributes['learning_class_id'];

                if (! is_int($classId)) {
                    throw new LogicException('A persisted learning class is required.');
                }

                $learningClass = LearningClass::query()->whereKey($classId)->firstOrFail();

                return Assessment::factory()
                    ->for(Competency::factory()->for($learningClass->course))
                    ->create(['status' => AssessmentStatus::Published])
                    ->id;
            },
            'opens_at' => null,
            'closes_at' => null,
            'max_attempts' => 1,
            'status' => ClassAssessmentStatus::Active,
            'feedback_mode' => AssessmentFeedbackMode::AfterFinalAttempt,
        ];
    }
}
