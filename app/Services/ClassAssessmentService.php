<?php

namespace App\Services;

use App\Enums\AssessmentFeedbackMode;
use App\Enums\AssessmentStatus;
use App\Enums\ClassAssessmentStatus;
use App\Models\Assessment;
use App\Models\LearningClass;
use App\Models\LearningClassAssessment;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ClassAssessmentService
{
    public function __construct(private readonly RemedialAssignmentService $remedials) {}

    /** @param array{assessment_id: int, opens_at: string|null, closes_at: string|null, max_attempts: int, status: ClassAssessmentStatus, feedback_mode: AssessmentFeedbackMode} $data */
    public function assign(LearningClass $learningClass, Assessment $assessment, array $data): LearningClassAssessment
    {
        return DB::transaction(function () use ($learningClass, $assessment, $data): LearningClassAssessment {
            $assessment->loadMissing('competency:id,course_id');

            if ($assessment->status !== AssessmentStatus::Published) {
                throw ValidationException::withMessages(['assessment_id' => __('Only published assessments may be assigned.')]);
            }

            if ($learningClass->course_id !== $assessment->competency->course_id) {
                throw ValidationException::withMessages(['assessment_id' => __('The assessment must belong to the class course.')]);
            }

            if ($learningClass->assessmentAssignments()->where('assessment_id', $assessment->id)->exists()) {
                throw ValidationException::withMessages(['assessment_id' => __('This assessment is already assigned to the class.')]);
            }

            return $learningClass->assessmentAssignments()->create($data);
        });
    }

    /** @param array{opens_at: string|null, closes_at: string|null, max_attempts: int, status: ClassAssessmentStatus, feedback_mode: AssessmentFeedbackMode} $data */
    public function update(LearningClassAssessment $assignment, array $data): LearningClassAssessment
    {
        return DB::transaction(function () use ($assignment, $data): LearningClassAssessment {
            $highestUsedAttempt = (int) $assignment->attempts()->max('attempt_number');

            if ($data['max_attempts'] < $highestUsedAttempt) {
                throw ValidationException::withMessages([
                    'max_attempts' => __('Maximum attempts cannot be lower than attempts already used by a student.'),
                ]);
            }

            $assignment->update($data);
            $this->remedials->refreshCompletedForClassAssessment($assignment->id);

            return $assignment->refresh();
        });
    }

    public function setStatus(LearningClassAssessment $assignment, ClassAssessmentStatus $status): LearningClassAssessment
    {
        return DB::transaction(function () use ($assignment, $status): LearningClassAssessment {
            $assignment->update(['status' => $status]);

            return $assignment->refresh();
        });
    }

    public function unassign(LearningClassAssessment $assignment): void
    {
        DB::transaction(function () use ($assignment): void {
            if ($assignment->attempts()->exists()) {
                throw ValidationException::withMessages([
                    'assessment_assignment' => __('This assessment assignment cannot be removed after students have started attempts. Set it inactive instead.'),
                ]);
            }

            $assignment->delete();
        });
    }
}
