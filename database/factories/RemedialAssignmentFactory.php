<?php

namespace Database\Factories;

use App\Enums\EnrollmentStatus;
use App\Enums\RemedialAssignmentStatus;
use App\Models\AssessmentAttempt;
use App\Models\Enrollment;
use App\Models\MasteryRule;
use App\Models\RemedialAssignment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use LogicException;

/** @extends Factory<RemedialAssignment> */
class RemedialAssignmentFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'mastery_rule_id' => MasteryRule::factory(),
            'enrollment_id' => function (array $attributes): int {
                $ruleId = $attributes['mastery_rule_id'];

                if (! is_int($ruleId)) {
                    throw new LogicException('A persisted mastery rule is required.');
                }

                $rule = MasteryRule::query()->findOrFail($ruleId);

                return Enrollment::factory()
                    ->for($rule->learningClass)
                    ->for(User::factory(), 'student')
                    ->createOne(['status' => EnrollmentStatus::Active])
                    ->id;
            },
            'competency_id' => function (array $attributes): int {
                $ruleId = $attributes['mastery_rule_id'];

                if (! is_int($ruleId)) {
                    throw new LogicException('A persisted mastery rule is required.');
                }

                return MasteryRule::query()->findOrFail($ruleId)->competency_id;
            },
            'source_assessment_attempt_id' => function (array $attributes): int {
                $ruleId = $attributes['mastery_rule_id'];

                if (! is_int($ruleId)) {
                    throw new LogicException('A persisted mastery rule is required.');
                }

                $rule = MasteryRule::query()->findOrFail($ruleId);

                return AssessmentAttempt::factory()->graded()->createOne([
                    'learning_class_assessment_id' => $rule->learning_class_assessment_id,
                    'enrollment_id' => $attributes['enrollment_id'],
                    'auto_points' => '0.50',
                    'earned_points' => '0.50',
                    'percentage' => '50.00',
                ])->id;
            },
            'status' => RemedialAssignmentStatus::Assigned,
            'open_slot' => true,
            'assigned_at' => now(),
            'completed_at' => null,
            'notes' => null,
        ];
    }

    public function completed(): static
    {
        return $this->state(fn (): array => [
            'status' => RemedialAssignmentStatus::Completed,
            'open_slot' => null,
            'completed_at' => now(),
        ]);
    }

    public function superseded(): static
    {
        return $this->state(fn (): array => [
            'status' => RemedialAssignmentStatus::Superseded,
            'open_slot' => null,
            'completed_at' => null,
        ]);
    }
}
