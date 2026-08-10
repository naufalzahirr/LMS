<?php

namespace Database\Factories;

use App\Enums\AssessmentAttemptStatus;
use App\Enums\EnrollmentStatus;
use App\Models\AssessmentAttempt;
use App\Models\Enrollment;
use App\Models\LearningClassAssessment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use LogicException;

/** @extends Factory<AssessmentAttempt> */
class AssessmentAttemptFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'learning_class_assessment_id' => LearningClassAssessment::factory(),
            'enrollment_id' => function (array $attributes): int {
                $assignmentId = $attributes['learning_class_assessment_id'];

                if (! is_int($assignmentId)) {
                    throw new LogicException('A persisted class assessment is required.');
                }

                $assignment = LearningClassAssessment::query()->findOrFail($assignmentId);
                $student = User::factory()->createOne();

                return Enrollment::factory()->for($assignment->learningClass)->for($student, 'student')->createOne([
                    'status' => EnrollmentStatus::Active,
                ])->id;
            },
            'attempt_number' => 1,
            'status' => AssessmentAttemptStatus::InProgress,
            'started_at' => now(),
            'submitted_at' => null,
            'graded_at' => null,
            'auto_points' => null,
            'manual_points' => null,
            'earned_points' => null,
            'max_points' => '1.00',
            'percentage' => null,
        ];
    }

    public function inProgress(): static
    {
        return $this->state(fn (): array => [
            'status' => AssessmentAttemptStatus::InProgress,
            'submitted_at' => null,
            'graded_at' => null,
        ]);
    }

    public function pendingGrading(): static
    {
        return $this->state(fn (): array => [
            'status' => AssessmentAttemptStatus::PendingGrading,
            'submitted_at' => now(),
            'graded_at' => null,
            'auto_points' => '0.00',
            'manual_points' => null,
            'earned_points' => null,
            'percentage' => null,
        ]);
    }

    public function graded(): static
    {
        return $this->state(fn (): array => [
            'status' => AssessmentAttemptStatus::Graded,
            'submitted_at' => now()->subMinute(),
            'graded_at' => now(),
            'auto_points' => '1.00',
            'manual_points' => '0.00',
            'earned_points' => '1.00',
            'max_points' => '1.00',
            'percentage' => '100.00',
        ]);
    }
}
