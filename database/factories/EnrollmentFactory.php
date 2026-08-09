<?php

namespace Database\Factories;

use App\Enums\EnrollmentStatus;
use App\Models\Enrollment;
use App\Models\LearningClass;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Enrollment>
 */
class EnrollmentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'learning_class_id' => LearningClass::factory(),
            'student_id' => User::factory(),
            'status' => EnrollmentStatus::Active,
            'enrolled_at' => now(),
            'completed_at' => null,
        ];
    }

    public function completed(): static
    {
        return $this->state(fn (): array => [
            'status' => EnrollmentStatus::Completed,
            'completed_at' => now(),
        ]);
    }

    public function withdrawn(): static
    {
        return $this->state(fn (): array => [
            'status' => EnrollmentStatus::Withdrawn,
            'completed_at' => null,
        ]);
    }
}
