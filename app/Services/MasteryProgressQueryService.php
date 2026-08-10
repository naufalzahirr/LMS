<?php

namespace App\Services;

use App\Enums\AcademicStatus;
use App\Enums\MasteryRuleStatus;
use App\Enums\RemedialAssignmentStatus;
use App\Enums\StudentCompetencyStatus;
use App\Models\AssessmentAttempt;
use App\Models\Competency;
use App\Models\Enrollment;
use App\Models\LearningClass;
use App\Models\MasteryRule;
use App\Models\RemedialAssignment;
use App\Models\StudentCompetencyProgress;
use Illuminate\Database\Eloquent\Collection;

class MasteryProgressQueryService
{
    /** @return array<string, mixed> */
    public function heatmap(LearningClass $learningClass): array
    {
        $enrollments = Enrollment::query()
            ->where('learning_class_id', $learningClass->id)
            ->with(['student:id,name,email', 'learningClass:id,course_id'])
            ->orderBy('id')
            ->get();
        $byEnrollment = $this->competenciesForEnrollments($enrollments);
        $competencies = $this->activeCompetencies([$learningClass->course_id]);

        return [
            'competencies' => $competencies->map(fn (Competency $competency): array => [
                'id' => $competency->id,
                'name' => $competency->name,
                'prerequisites' => $competency->prerequisites->pluck('name')->values()->all(),
            ])->all(),
            'students' => $enrollments->map(fn (Enrollment $enrollment): array => [
                'enrollment_id' => $enrollment->id,
                'student' => $enrollment->student->name,
                'email' => $enrollment->student->email,
                'competencies' => array_map(
                    fn (array $cell): array => [
                        ...$cell,
                        'competency_id' => $cell['id'],
                    ],
                    $byEnrollment[$enrollment->id] ?? [],
                ),
            ])->all(),
        ];
    }

    /** @return array<int, array<string, mixed>> */
    public function studentCompetencies(LearningClass $learningClass, Enrollment $enrollment): array
    {
        $enrollment->setRelation('learningClass', $learningClass);
        $mapped = $this->competenciesForEnrollments(new Collection([$enrollment]));

        return $mapped[$enrollment->id] ?? [];
    }

    /**
     * @param  Collection<int, Enrollment>  $enrollments
     * @return array<int, array<int, array<string, mixed>>>
     */
    public function competenciesForEnrollments(Collection $enrollments): array
    {
        if ($enrollments->isEmpty()) {
            return [];
        }

        $enrollments->loadMissing('learningClass:id,course_id');
        $enrollmentIds = $enrollments->modelKeys();
        $classIds = $enrollments->pluck('learning_class_id')->unique()->values();
        $competenciesByCourse = $this->activeCompetencies(
            $enrollments->pluck('learningClass.course_id')->unique()->values()->all(),
        )->groupBy('course_id');
        $progress = StudentCompetencyProgress::query()
            ->whereIn('enrollment_id', $enrollmentIds)
            ->get();
        $progressByKey = $progress->keyBy(
            fn (StudentCompetencyProgress $item): string => "{$item->enrollment_id}:{$item->competency_id}",
        );
        $masteredByEnrollment = $progress
            ->filter(fn (StudentCompetencyProgress $item): bool => $item->status === StudentCompetencyStatus::Mastered)
            ->groupBy('enrollment_id')
            ->map(fn (Collection $items) => $items->pluck('competency_id'));
        $rules = MasteryRule::query()
            ->whereIn('learning_class_id', $classIds)
            ->where('status', MasteryRuleStatus::Active->value)
            ->with('classAssessment:id,max_attempts')
            ->get()
            ->keyBy(fn (MasteryRule $rule): string => "{$rule->learning_class_id}:{$rule->competency_id}");
        $remedials = RemedialAssignment::query()
            ->whereIn('enrollment_id', $enrollmentIds)
            ->where('status', RemedialAssignmentStatus::Assigned->value)
            ->with('lessons.lesson:id,title')
            ->get()
            ->keyBy(fn (RemedialAssignment $item): string => "{$item->enrollment_id}:{$item->competency_id}");
        $attemptCounts = AssessmentAttempt::query()
            ->whereIn('enrollment_id', $enrollmentIds)
            ->get(['id', 'enrollment_id', 'learning_class_assessment_id'])
            ->groupBy(fn (AssessmentAttempt $attempt): string => "{$attempt->enrollment_id}:{$attempt->learning_class_assessment_id}")
            ->map(fn (Collection $items): int => $items->count());
        $mapped = [];

        foreach ($enrollments as $enrollment) {
            $courseCompetencies = $competenciesByCourse->get(
                $enrollment->learningClass->course_id,
                new Collection,
            );
            $masteredIds = $masteredByEnrollment->get($enrollment->id, collect());
            $cells = [];

            foreach ($courseCompetencies as $competency) {
                $key = "{$enrollment->id}:{$competency->id}";
                $record = $progressByKey->get($key);
                $missing = $competency->prerequisites->whereNotIn('id', $masteredIds);
                $unlocked = $missing->isEmpty();
                $rule = $rules->get("{$enrollment->learning_class_id}:{$competency->id}");
                $remedial = $remedials->get($key);
                $attemptCount = $rule instanceof MasteryRule
                    ? ($attemptCounts->get("{$enrollment->id}:{$rule->learning_class_assessment_id}", 0))
                    : 0;
                $status = $unlocked
                    ? ($record?->status->value ?? StudentCompetencyStatus::Learning->value)
                    : 'locked';

                $cells[] = [
                    'id' => $competency->id,
                    'name' => $competency->name,
                    'unlocked' => $unlocked,
                    'status' => $status,
                    'prerequisites' => $competency->prerequisites->pluck('name')->values()->all(),
                    'missing_prerequisites' => $missing->pluck('name')->values()->all(),
                    'latest_score' => $record?->latest_score,
                    'best_score' => $record?->best_score,
                    'required_score' => $rule?->mastery_score,
                    'mastered_at' => $record?->mastered_at?->toDateTimeString(),
                    'total_mastery_attempts' => $attemptCount,
                    'attempts_exhausted' => $rule instanceof MasteryRule
                        && $status !== StudentCompetencyStatus::Mastered->value
                        && $attemptCount >= $rule->classAssessment->max_attempts,
                    'mastery_assignment_id' => $rule?->learning_class_assessment_id,
                    'remedial_assignment_id' => $remedial?->id,
                    'remedial_lessons' => $remedial?->lessons
                        ->map(fn ($item): string => $item->lesson->title)
                        ->values()
                        ->all() ?? [],
                ];
            }

            $mapped[$enrollment->id] = $cells;
        }

        return $mapped;
    }

    /**
     * @param  array<int, int>  $courseIds
     * @return Collection<int, Competency>
     */
    private function activeCompetencies(array $courseIds): Collection
    {
        if ($courseIds === []) {
            return new Collection;
        }

        return Competency::query()
            ->whereIn('course_id', $courseIds)
            ->where('status', AcademicStatus::Active->value)
            ->with('prerequisites:id,name,sort_order')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }
}
