<?php

namespace Database\Factories;

use App\Enums\AssessmentPurpose;
use App\Enums\AssessmentStatus;
use App\Models\Assessment;
use App\Models\Competency;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Assessment> */
class AssessmentFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'competency_id' => Competency::factory(),
            'title' => str(fake()->unique()->sentence(3))->trim('.')->title()->toString(),
            'code' => fake()->unique()->bothify('ASM-###'),
            'description' => fake()->sentence(),
            'purpose' => AssessmentPurpose::Formative,
            'status' => AssessmentStatus::Draft,
            'instructions' => fake()->sentence(),
            'shuffle_questions' => false,
        ];
    }

    public function published(): static
    {
        return $this->state(fn (): array => ['status' => AssessmentStatus::Published]);
    }

    public function archived(): static
    {
        return $this->state(fn (): array => ['status' => AssessmentStatus::Archived]);
    }
}
