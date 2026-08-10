<?php

namespace App\Services;

use App\Enums\MasteryRuleStatus;
use App\Enums\RemedialAssignmentStatus;
use App\Enums\StudentCompetencyStatus;
use App\Models\Competency;
use App\Models\Enrollment;
use App\Models\LearningClassAssessment;
use App\Models\Lesson;
use App\Models\MasteryRule;
use App\Models\StudentCompetencyProgress;
use Illuminate\Validation\ValidationException;

class CompetencyAccessService
{
    /** @return array<string, mixed>|null */
    public function masteryAssessmentState(
        Enrollment $enrollment,
        LearningClassAssessment $assignment,
    ): ?array {
        $rule = MasteryRule::query()
            ->where('learning_class_assessment_id', $assignment->id)
            ->where('status', MasteryRuleStatus::Active->value)
            ->with('competency:id,course_id,name')
            ->first();

        if (! $rule instanceof MasteryRule) {
            return null;
        }

        $unlocked = $this->isUnlocked($enrollment, $rule->competency);
        $progress = StudentCompetencyProgress::query()
            ->where('enrollment_id', $enrollment->id)
            ->where('competency_id', $rule->competency_id)
            ->first();
        $remedial = $enrollment->remedialAssignments()
            ->where('competency_id', $rule->competency_id)
            ->where('status', RemedialAssignmentStatus::Assigned->value)
            ->first();
        $status = $unlocked
            ? ($progress?->status->value ?? StudentCompetencyStatus::Learning->value)
            : 'locked';

        $message = match (true) {
            ! $unlocked => __('Complete the prerequisite competencies before starting this mastery assessment.'),
            $remedial !== null => __('Complete your remedial learning before retaking the mastery assessment.'),
            $progress?->status === StudentCompetencyStatus::Mastered => __('This competency is already mastered.'),
            $progress?->status !== StudentCompetencyStatus::ReadyForAssessment => __('Complete this competency learning before starting its mastery assessment.'),
            default => null,
        };

        return [
            'can_start' => $message === null,
            'status' => $status,
            'required_score' => $rule->mastery_score,
            'latest_score' => $progress?->latest_score,
            'best_score' => $progress?->best_score,
            'message' => $message,
            'remedial_url' => $remedial === null ? null : route('student.remedials.show', $remedial),
        ];
    }

    public function isUnlocked(Enrollment $enrollment, Competency $competency): bool
    {
        $prerequisiteIds = $competency->prerequisites()->pluck('competencies.id');

        if ($prerequisiteIds->isEmpty()) {
            return true;
        }

        return StudentCompetencyProgress::query()
            ->where('enrollment_id', $enrollment->id)
            ->whereIn('competency_id', $prerequisiteIds)
            ->where('status', StudentCompetencyStatus::Mastered->value)
            ->count() === $prerequisiteIds->count();
    }

    public function mayOpenLesson(Enrollment $enrollment, Lesson $lesson): bool
    {
        $lesson->loadMissing('module.competency');

        return $lesson->module->competency->course_id === $enrollment->learningClass->course_id
            && $this->isUnlocked($enrollment, $lesson->module->competency);
    }

    public function ensureMasteryAssessmentMayStart(
        Enrollment $enrollment,
        LearningClassAssessment $assignment,
    ): void {
        $state = $this->masteryAssessmentState($enrollment, $assignment);

        if ($state === null || $state['can_start'] === true) {
            return;
        }

        throw ValidationException::withMessages(['assessment' => $state['message']]);
    }
}
