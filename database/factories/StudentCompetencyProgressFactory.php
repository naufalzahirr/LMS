<?php

namespace Database\Factories;

use App\Enums\StudentCompetencyStatus;
use App\Models\Competency;
use App\Models\Enrollment;
use App\Models\StudentCompetencyProgress;
use Illuminate\Database\Eloquent\Factories\Factory;
use LogicException;

/** @extends Factory<StudentCompetencyProgress> */
class StudentCompetencyProgressFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'enrollment_id' => Enrollment::factory(),
            'competency_id' => function (array $attributes): int {
                $enrollmentId = $attributes['enrollment_id'];

                if (! is_int($enrollmentId)) {
                    throw new LogicException('A persisted enrollment is required.');
                }

                $enrollment = Enrollment::query()->findOrFail($enrollmentId);

                return Competency::factory()->for($enrollment->learningClass->course)->createOne()->id;
            },
            'status' => StudentCompetencyStatus::Learning,
            'latest_score' => null,
            'best_score' => null,
            'total_mastery_attempts' => 0,
            'started_at' => now(),
            'mastered_at' => null,
            'last_evaluated_at' => null,
        ];
    }

    public function learning(): static
    {
        return $this->state(fn (): array => ['status' => StudentCompetencyStatus::Learning]);
    }

    public function ready(): static
    {
        return $this->state(fn (): array => ['status' => StudentCompetencyStatus::ReadyForAssessment]);
    }

    public function needsRemedial(): static
    {
        return $this->state(fn (): array => ['status' => StudentCompetencyStatus::NeedsRemedial]);
    }

    public function mastered(): static
    {
        return $this->state(fn (): array => [
            'status' => StudentCompetencyStatus::Mastered,
            'latest_score' => '90.00',
            'best_score' => '90.00',
            'mastered_at' => now(),
            'last_evaluated_at' => now(),
        ]);
    }
}
