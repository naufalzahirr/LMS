<?php

namespace Database\Factories;

use App\Models\Lesson;
use App\Models\Module;
use App\Models\RemedialAssignment;
use App\Models\RemedialAssignmentLesson;
use Illuminate\Database\Eloquent\Factories\Factory;
use LogicException;

/** @extends Factory<RemedialAssignmentLesson> */
class RemedialAssignmentLessonFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'remedial_assignment_id' => RemedialAssignment::factory(),
            'lesson_id' => function (array $attributes): int {
                $assignmentId = $attributes['remedial_assignment_id'];

                if (! is_int($assignmentId)) {
                    throw new LogicException('A persisted remedial assignment is required.');
                }

                $assignment = RemedialAssignment::query()->findOrFail($assignmentId);
                $module = Module::factory()->for($assignment->competency)->createOne();

                return Lesson::factory()->for($module)->createOne()->id;
            },
            'sort_order' => 0,
            'completed_at' => null,
        ];
    }

    public function completed(): static
    {
        return $this->state(fn (): array => ['completed_at' => now()]);
    }
}
