<?php

namespace App\Services;

use App\Enums\AcademicStatus;
use App\Enums\MasteryRuleStatus;
use App\Enums\RemedialAssignmentStatus;
use App\Enums\StudentCompetencyStatus;
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
        $competencies = $this->competencies($learningClass);
        $enrollments = Enrollment::query()
            ->where('learning_class_id', $learningClass->id)
            ->with('student:id,name,email')
            ->orderBy('id')
            ->get();
        $progress = StudentCompetencyProgress::query()
            ->whereIn('enrollment_id', $enrollments->pluck('id'))
            ->get()
            ->keyBy(fn (StudentCompetencyProgress $item): string => "{$item->enrollment_id}:{$item->competency_id}");
        $rules = MasteryRule::query()
            ->where('learning_class_id', $learningClass->id)
            ->get()
            ->keyBy('competency_id');
        $remedials = RemedialAssignment::query()
            ->whereIn('enrollment_id', $enrollments->pluck('id'))
            ->where('status', RemedialAssignmentStatus::Assigned->value)
            ->get()
            ->keyBy(fn (RemedialAssignment $item): string => "{$item->enrollment_id}:{$item->competency_id}");

        return [
            'competencies' => $competencies->map(fn (Competency $competency): array => [
                'id' => $competency->id,
                'name' => $competency->name,
                'prerequisites' => $competency->prerequisites->pluck('name')->values()->all(),
            ])->all(),
            'students' => $enrollments->map(function (Enrollment $enrollment) use ($competencies, $progress, $rules, $remedials): array {
                $masteredIds = $progress->filter(
                    fn (StudentCompetencyProgress $item): bool => $item->enrollment_id === $enrollment->id
                        && $item->status === StudentCompetencyStatus::Mastered,
                )->pluck('competency_id');

                return [
                    'enrollment_id' => $enrollment->id,
                    'student' => $enrollment->student->name,
                    'email' => $enrollment->student->email,
                    'competencies' => $competencies->map(function (Competency $competency) use ($enrollment, $progress, $rules, $remedials, $masteredIds): array {
                        $key = "{$enrollment->id}:{$competency->id}";
                        $record = $progress->get($key);
                        $unlocked = $competency->prerequisites->pluck('id')->diff($masteredIds)->isEmpty();
                        $rule = $rules->get($competency->id);
                        $remedial = $remedials->get($key);

                        return [
                            'competency_id' => $competency->id,
                            'status' => $unlocked
                                ? ($record?->status->value ?? StudentCompetencyStatus::Learning->value)
                                : 'locked',
                            'latest_score' => $record?->latest_score,
                            'best_score' => $record?->best_score,
                            'required_score' => $rule?->mastery_score,
                            'remedial_assignment_id' => $remedial?->id,
                        ];
                    })->all(),
                ];
            })->all(),
        ];
    }

    /** @return array<int, array<string, mixed>> */
    public function studentCompetencies(LearningClass $learningClass, Enrollment $enrollment): array
    {
        $competencies = $this->competencies($learningClass);
        $progress = StudentCompetencyProgress::query()
            ->where('enrollment_id', $enrollment->id)
            ->get()
            ->keyBy('competency_id');
        $masteredIds = $progress->where('status', StudentCompetencyStatus::Mastered)->pluck('competency_id');
        $rules = MasteryRule::query()
            ->where('learning_class_id', $learningClass->id)
            ->where('status', MasteryRuleStatus::Active->value)
            ->get()
            ->keyBy('competency_id');
        $remedials = RemedialAssignment::query()
            ->where('enrollment_id', $enrollment->id)
            ->where('status', RemedialAssignmentStatus::Assigned->value)
            ->get()
            ->keyBy('competency_id');

        return $competencies->map(function (Competency $competency) use ($progress, $masteredIds, $rules, $remedials): array {
            $record = $progress->get($competency->id);
            $missing = $competency->prerequisites->whereNotIn('id', $masteredIds);
            $unlocked = $missing->isEmpty();
            $rule = $rules->get($competency->id);
            $remedial = $remedials->get($competency->id);

            return [
                'id' => $competency->id,
                'name' => $competency->name,
                'unlocked' => $unlocked,
                'status' => $unlocked
                    ? ($record?->status->value ?? StudentCompetencyStatus::Learning->value)
                    : 'locked',
                'prerequisites' => $competency->prerequisites->pluck('name')->values()->all(),
                'missing_prerequisites' => $missing->pluck('name')->values()->all(),
                'latest_score' => $record?->latest_score,
                'best_score' => $record?->best_score,
                'required_score' => $rule?->mastery_score,
                'mastery_assignment_id' => $rule?->learning_class_assessment_id,
                'remedial_assignment_id' => $remedial?->id,
            ];
        })->all();
    }

    /** @return Collection<int, Competency> */
    private function competencies(LearningClass $learningClass): Collection
    {
        return Competency::query()
            ->where('course_id', $learningClass->course_id)
            ->where('status', AcademicStatus::Active->value)
            ->with('prerequisites:id,name,sort_order')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }
}
