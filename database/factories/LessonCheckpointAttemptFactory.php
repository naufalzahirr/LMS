<?php

namespace Database\Factories;

use App\Models\Enrollment;
use App\Models\LessonCheckpoint;
use App\Models\LessonCheckpointAttempt;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<LessonCheckpointAttempt> */
class LessonCheckpointAttemptFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'lesson_checkpoint_id' => LessonCheckpoint::factory(),
            'enrollment_id' => Enrollment::factory(),
            'submitted_answer' => ['option_id' => fake()->uuid()],
            'is_correct' => false,
            'attempt_number' => 1,
        ];
    }
}
