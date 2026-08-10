<?php

namespace App\Services;

use App\Enums\AcademicStatus;
use App\Enums\AssessmentAttemptStatus;
use App\Enums\RemedialAssignmentStatus;
use App\Enums\StudentCompetencyStatus;
use App\Models\AssessmentAttempt;
use App\Models\Lesson;
use App\Models\MasteryRule;
use App\Models\RemedialAssignment;
use App\Models\RemedialAssignmentLesson;
use App\Models\StudentCompetencyProgress;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RemedialAssignmentService
{
    public function createForFailure(
        MasteryRule $rule,
        AssessmentAttempt $attempt,
    ): RemedialAssignment {
        return DB::transaction(function () use ($rule, $attempt): RemedialAssignment {
            $existing = RemedialAssignment::query()
                ->where('enrollment_id', $attempt->enrollment_id)
                ->where('competency_id', $rule->competency_id)
                ->where('status', RemedialAssignmentStatus::Assigned->value)
                ->lockForUpdate()
                ->first();

            if ($existing instanceof RemedialAssignment) {
                return $existing;
            }

            $assignment = RemedialAssignment::query()->create([
                'enrollment_id' => $attempt->enrollment_id,
                'competency_id' => $rule->competency_id,
                'mastery_rule_id' => $rule->id,
                'source_assessment_attempt_id' => $attempt->id,
                'status' => RemedialAssignmentStatus::Assigned,
                'open_slot' => true,
                'assigned_at' => now(),
            ]);
            $rule->loadMissing('remedialLessons');

            foreach ($rule->remedialLessons as $index => $lesson) {
                $assignment->lessons()->create([
                    'lesson_id' => $lesson->id,
                    'sort_order' => $index,
                ]);
            }

            return $assignment->refresh()->load('lessons.lesson');
        });
    }

    public function completeLesson(
        User $student,
        RemedialAssignment $assignment,
        RemedialAssignmentLesson $item,
    ): RemedialAssignment {
        if (! $student->hasRole('Student') || $assignment->enrollment->student_id !== $student->id) {
            throw new AuthorizationException;
        }

        return DB::transaction(function () use ($assignment, $item): RemedialAssignment {
            $locked = RemedialAssignment::query()->whereKey($assignment->id)->lockForUpdate()->firstOrFail();
            $this->ensureAssigned($locked);

            if ($item->remedial_assignment_id !== $locked->id) {
                throw ValidationException::withMessages(['remedial' => __('This lesson is not part of the remedial plan.')]);
            }

            $item->update(['completed_at' => now()]);
            $remaining = $locked->lessons()->whereNull('completed_at')->count();

            if ($locked->lessons()->exists() && $remaining === 0) {
                $this->complete($locked);
            }

            return $locked->refresh()->load('lessons.lesson');
        });
    }

    public function addLesson(User $manager, RemedialAssignment $assignment, Lesson $lesson): RemedialAssignmentLesson
    {
        $this->authorizeManager($manager, $assignment);

        return DB::transaction(function () use ($assignment, $lesson): RemedialAssignmentLesson {
            $this->ensureAssigned($assignment);
            $lesson->loadMissing('module:id,competency_id,status');

            if ($lesson->status !== AcademicStatus::Active
                || $lesson->module->status !== AcademicStatus::Active
                || $lesson->module->competency_id !== $assignment->competency_id) {
                throw ValidationException::withMessages([
                    'lesson_id' => __('Remedial lessons must belong to the same competency.'),
                ]);
            }

            if ($assignment->lessons()->where('lesson_id', $lesson->id)->exists()) {
                throw ValidationException::withMessages(['lesson_id' => __('This lesson is already assigned.')]);
            }

            return $assignment->lessons()->create([
                'lesson_id' => $lesson->id,
                'sort_order' => ((int) $assignment->lessons()->max('sort_order')) + 1,
            ]);
        });
    }

    public function removeLesson(
        User $manager,
        RemedialAssignment $assignment,
        RemedialAssignmentLesson $item,
    ): void {
        $this->authorizeManager($manager, $assignment);

        DB::transaction(function () use ($assignment, $item): void {
            $this->ensureAssigned($assignment);

            if ($item->remedial_assignment_id !== $assignment->id) {
                throw ValidationException::withMessages(['remedial' => __('This lesson is not part of the remedial plan.')]);
            }

            $item->delete();
        });
    }

    public function updateNotes(User $manager, RemedialAssignment $assignment, ?string $notes): RemedialAssignment
    {
        $this->authorizeManager($manager, $assignment);
        $assignment->update(['notes' => $notes]);

        return $assignment->refresh();
    }

    public function completeIntervention(User $manager, RemedialAssignment $assignment): RemedialAssignment
    {
        $this->authorizeManager($manager, $assignment);

        return DB::transaction(function () use ($assignment): RemedialAssignment {
            $this->ensureAssigned($assignment);

            if ($assignment->lessons()->whereNull('completed_at')->exists()) {
                throw ValidationException::withMessages([
                    'remedial' => __('Complete every remedial lesson before closing this intervention.'),
                ]);
            }

            return $this->complete($assignment);
        });
    }

    public function refreshCompletedForClassAssessment(int $classAssessmentId): void
    {
        $rule = MasteryRule::query()->where('learning_class_assessment_id', $classAssessmentId)->first();

        if (! $rule instanceof MasteryRule) {
            return;
        }

        $completed = RemedialAssignment::query()
            ->where('mastery_rule_id', $rule->id)
            ->where('status', RemedialAssignmentStatus::Completed->value)
            ->get();

        foreach ($completed as $assignment) {
            $this->synchronizeProgressAfterCompletion($assignment);
        }
    }

    private function complete(RemedialAssignment $assignment): RemedialAssignment
    {
        $assignment->update([
            'status' => RemedialAssignmentStatus::Completed,
            'open_slot' => null,
            'completed_at' => now(),
        ]);
        $this->synchronizeProgressAfterCompletion($assignment);

        return $assignment->refresh();
    }

    private function synchronizeProgressAfterCompletion(RemedialAssignment $assignment): void
    {
        $assignment->loadMissing('masteryRule.classAssessment');
        $attemptCount = AssessmentAttempt::query()
            ->where('enrollment_id', $assignment->enrollment_id)
            ->where('learning_class_assessment_id', $assignment->masteryRule->learning_class_assessment_id)
            ->whereIn('status', array_map(
                fn (AssessmentAttemptStatus $status): string => $status->value,
                AssessmentAttemptStatus::cases(),
            ))
            ->count();
        $hasAttemptRemaining = $attemptCount < $assignment->masteryRule->classAssessment->max_attempts;
        StudentCompetencyProgress::query()->updateOrCreate(
            [
                'enrollment_id' => $assignment->enrollment_id,
                'competency_id' => $assignment->competency_id,
            ],
            [
                'status' => $hasAttemptRemaining
                    ? StudentCompetencyStatus::ReadyForAssessment
                    : StudentCompetencyStatus::NeedsRemedial,
                'started_at' => now(),
            ],
        );
    }

    private function ensureAssigned(RemedialAssignment $assignment): void
    {
        if ($assignment->status !== RemedialAssignmentStatus::Assigned) {
            throw ValidationException::withMessages(['remedial' => __('Only an open remedial plan can be changed.')]);
        }
    }

    private function authorizeManager(User $user, RemedialAssignment $assignment): void
    {
        if (! $user->can('manage', $assignment)) {
            throw new AuthorizationException;
        }
    }
}
