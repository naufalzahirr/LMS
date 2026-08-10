<?php

namespace App\Services;

use App\Enums\AssessmentAttemptStatus;
use App\Enums\MasteryRuleStatus;
use App\Enums\RemedialAssignmentStatus;
use App\Enums\StudentCompetencyStatus;
use App\Models\AssessmentAttempt;
use App\Models\MasteryRule;
use App\Models\RemedialAssignment;
use App\Models\StudentCompetencyProgress;
use Illuminate\Support\Facades\DB;

class MasteryEvaluationService
{
    public function __construct(private readonly RemedialAssignmentService $remedials) {}

    public function evaluate(AssessmentAttempt $attempt): ?StudentCompetencyProgress
    {
        if ($attempt->status !== AssessmentAttemptStatus::Graded || $attempt->percentage === null) {
            return null;
        }

        $rule = MasteryRule::query()
            ->where('learning_class_assessment_id', $attempt->learning_class_assessment_id)
            ->where('status', MasteryRuleStatus::Active->value)
            ->first();

        if (! $rule instanceof MasteryRule) {
            return null;
        }

        return $this->recalculate($rule, $attempt->enrollment_id);
    }

    public function recalculate(MasteryRule $rule, int $enrollmentId): StudentCompetencyProgress
    {
        return DB::transaction(function () use ($rule, $enrollmentId): StudentCompetencyProgress {
            $attempts = AssessmentAttempt::query()
                ->where('enrollment_id', $enrollmentId)
                ->where('learning_class_assessment_id', $rule->learning_class_assessment_id)
                ->orderByDesc('attempt_number')
                ->get();
            $graded = $attempts->where('status', AssessmentAttemptStatus::Graded)
                ->filter(fn (AssessmentAttempt $attempt): bool => $attempt->percentage !== null);
            $latest = $graded->first();
            $bestScore = $graded->max(fn (AssessmentAttempt $attempt): float => (float) $attempt->percentage);
            $progress = StudentCompetencyProgress::query()->firstOrNew([
                'enrollment_id' => $enrollmentId,
                'competency_id' => $rule->competency_id,
            ]);
            $progress->started_at ??= now();
            $progress->latest_score = $latest?->percentage;
            $progress->best_score = $bestScore === null ? null : $this->decimal((float) $bestScore);
            $progress->total_mastery_attempts = $attempts->count();
            $progress->last_evaluated_at = now();
            $mastered = $bestScore !== null && (float) $bestScore >= (float) $rule->mastery_score;

            if ($mastered) {
                $progress->status = StudentCompetencyStatus::Mastered;
                $progress->mastered_at ??= now();
                $progress->save();
                RemedialAssignment::query()
                    ->where('enrollment_id', $enrollmentId)
                    ->where('competency_id', $rule->competency_id)
                    ->where('status', RemedialAssignmentStatus::Assigned->value)
                    ->update([
                        'status' => RemedialAssignmentStatus::Superseded,
                        'open_slot' => null,
                    ]);

                return $progress->refresh();
            }

            $progress->mastered_at = null;
            $attemptsRemain = $attempts->count() < $rule->classAssessment->max_attempts;

            if ($rule->require_remedial) {
                $latestRemedial = $latest === null ? null : RemedialAssignment::query()
                    ->where('source_assessment_attempt_id', $latest->id)
                    ->latest('id')
                    ->first();

                if ($latestRemedial?->status === RemedialAssignmentStatus::Completed) {
                    $progress->status = $attemptsRemain
                        ? StudentCompetencyStatus::ReadyForAssessment
                        : StudentCompetencyStatus::NeedsRemedial;
                    $progress->save();

                    return $progress->refresh();
                }

                $progress->status = StudentCompetencyStatus::NeedsRemedial;
                $progress->save();

                if ($latest instanceof AssessmentAttempt) {
                    $this->remedials->createForFailure($rule, $latest);
                }
            } else {
                $progress->status = $attemptsRemain
                    ? StudentCompetencyStatus::ReadyForAssessment
                    : StudentCompetencyStatus::NeedsRemedial;
                $progress->save();
            }

            return $progress->refresh();
        });
    }

    private function decimal(float $value): string
    {
        return number_format($value, 2, '.', '');
    }
}
