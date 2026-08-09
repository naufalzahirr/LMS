<?php

namespace Database\Factories;

use App\Enums\LearningClassStatus;
use App\Models\Course;
use App\Models\LearningClass;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LearningClass>
 */
class LearningClassFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'course_id' => Course::factory(),
            'name' => str(fake()->unique()->sentence(3))->trim('.')->title()->toString(),
            'code' => fake()->unique()->bothify('CLS-####'),
            'description' => fake()->sentence(),
            'start_date' => now()->startOfDay(),
            'end_date' => now()->addMonths(3)->startOfDay(),
            'status' => LearningClassStatus::Active,
        ];
    }
}
