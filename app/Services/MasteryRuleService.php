<?php

namespace App\Services;

use App\Enums\AcademicStatus;
use App\Enums\AssessmentPurpose;
use App\Enums\AssessmentStatus;
use App\Enums\MasteryRuleStatus;
use App\Models\Competency;
use App\Models\LearningClass;
use App\Models\LearningClassAssessment;
use App\Models\Lesson;
use App\Models\MasteryRule;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MasteryRuleService
{
    /**
     * @param array{
     *   learning_class_assessment_id: int, mastery_score: string,
     *   require_remedial: bool, status: MasteryRuleStatus,
     *   remedial_lesson_ids: array<int, int>
     * } $data
     */
    public function save(LearningClass $learningClass, Competency $competency, array $data): MasteryRule
    {
        return DB::transaction(function () use ($learningClass, $competency, $data): MasteryRule {
            if ($competency->course_id !== $learningClass->course_id) {
                throw ValidationException::withMessages([
                    'competency' => __('The competency must belong to the class course.'),
                ]);
            }

            $assignment = LearningClassAssessment::query()
                ->with('assessment')
                ->whereKey($data['learning_class_assessment_id'])
                ->first();

            if (! $assignment instanceof LearningClassAssessment
                || $assignment->learning_class_id !== $learningClass->id
                || $assignment->assessment->competency_id !== $competency->id
                || $assignment->assessment->purpose !== AssessmentPurpose::Mastery
                || $assignment->assessment->status !== AssessmentStatus::Published) {
                throw ValidationException::withMessages([
                    'learning_class_assessment_id' => __('Select a published mastery assessment assigned to this class and competency.'),
                ]);
            }

            $score = (float) $data['mastery_score'];

            if ($score <= 0 || $score > 100) {
                throw ValidationException::withMessages([
                    'mastery_score' => __('The mastery score must be greater than zero and at most 100.'),
                ]);
            }

            $rule = MasteryRule::query()
                ->where('learning_class_id', $learningClass->id)
                ->where('competency_id', $competency->id)
                ->lockForUpdate()
                ->first();

            if ($rule instanceof MasteryRule && $rule->classAssessment->attempts()->exists()) {
                if ($rule->learning_class_assessment_id !== $assignment->id) {
                    throw ValidationException::withMessages([
                        'learning_class_assessment_id' => __('The mastery assessment cannot change after student attempts exist.'),
                    ]);
                }

                if ((float) $rule->mastery_score !== $score) {
                    throw ValidationException::withMessages([
                        'mastery_score' => __('The mastery score cannot change after student attempts exist.'),
                    ]);
                }
            }

            $lessonIds = array_values(array_unique($data['remedial_lesson_ids']));
            $validLessonCount = $lessonIds === [] ? 0 : Lesson::query()
                ->whereIn('id', $lessonIds)
                ->where('status', AcademicStatus::Active->value)
                ->whereHas('module', fn ($query) => $query
                    ->where('competency_id', $competency->id)
                    ->where('status', AcademicStatus::Active->value))
                ->count();

            if ($validLessonCount !== count($lessonIds)) {
                throw ValidationException::withMessages([
                    'remedial_lesson_ids' => __('Every remedial lesson must belong to this competency.'),
                ]);
            }

            $attributes = [
                'learning_class_assessment_id' => $assignment->id,
                'mastery_score' => $data['mastery_score'],
                'require_remedial' => $data['require_remedial'],
                'status' => $data['status'],
            ];

            if ($rule instanceof MasteryRule) {
                $rule->update($attributes);
            } else {
                $rule = MasteryRule::query()->create([
                    'learning_class_id' => $learningClass->id,
                    'competency_id' => $competency->id,
                    ...$attributes,
                ]);
            }

            $sync = [];

            foreach ($lessonIds as $sortOrder => $lessonId) {
                $sync[$lessonId] = ['sort_order' => $sortOrder];
            }

            $rule->remedialLessons()->sync($sync);

            return $rule->refresh()->load(['classAssessment.assessment', 'remedialLessons']);
        });
    }
}
