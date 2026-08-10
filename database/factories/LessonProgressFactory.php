<?php

namespace Database\Factories;

use App\Enums\LessonProgressStatus;
use App\Models\Competency;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\Module;
use Illuminate\Database\Eloquent\Factories\Factory;
use LogicException;

/**
 * @extends Factory<LessonProgress>
 */
class LessonProgressFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'enrollment_id' => Enrollment::factory(),
            'lesson_id' => function (array $attributes): int {
                $enrollmentId = $attributes['enrollment_id'];

                if (! is_int($enrollmentId)) {
                    throw new LogicException('Lesson progress requires a persisted enrollment.');
                }

                $enrollment = Enrollment::query()->whereKey($enrollmentId)->firstOrFail();

                return Lesson::factory()
                    ->for(Module::factory()->for(Competency::factory()->for($enrollment->learningClass->course)))
                    ->create()
                    ->id;
            },
            'status' => LessonProgressStatus::InProgress,
            'started_at' => now()->subMinutes(10),
            'completed_at' => null,
            'last_viewed_at' => now(),
        ];
    }

    public function inProgress(): static
    {
        return $this->state(fn (): array => [
            'status' => LessonProgressStatus::InProgress,
            'completed_at' => null,
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn (): array => [
            'status' => LessonProgressStatus::Completed,
            'completed_at' => now(),
        ]);
    }
}
