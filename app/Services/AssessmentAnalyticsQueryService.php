<?php

namespace App\Services;

use App\Enums\AcademicStatus;
use App\Enums\AssessmentAttemptStatus;
use App\Enums\AssessmentStatus;
use App\Enums\ClassAssessmentStatus;
use App\Models\AssessmentAttempt;
use App\Models\Enrollment;
use App\Models\LearningClass;
use App\Models\LearningClassAssessment;
use Illuminate\Database\Eloquent\Collection;

class AssessmentAnalyticsQueryService
{
    /**
     * Assessment participation is one submitted Student-assignment cell per active
     * enrollment. Performance selects the latest graded attempt in that cell, so
     * retries never enlarge the population and pending work is never scored as zero.
     *
     * @param  Collection<int, LearningClass>  $classes
     * @param  Collection<int, Enrollment>  $enrollments
     * @return array{assignments: array<int, array<string, mixed>>, by_class: array<int, array<string, int|float|null>>, by_enrollment: array<int, array<string, int|float|null>>}
     */
    public function summariesForClasses(Collection $classes, Collection $enrollments): array
    {
        $empty = ['assignments' => [], 'by_class' => [], 'by_enrollment' => []];

        if ($classes->isEmpty()) {
            return $empty;
        }

        $assignments = LearningClassAssessment::query()
            ->whereIn('learning_class_id', $classes->modelKeys())
            ->where('status', ClassAssessmentStatus::Active->value)
            ->whereHas('assessment', fn ($query) => $query
                ->where('status', AssessmentStatus::Published->value)
                ->whereHas('competency', fn ($query) => $query->where('status', AcademicStatus::Active->value)))
            ->with([
                'assessment:id,competency_id,title,purpose',
                'learningClass:id,name',
            ])
            ->orderBy('learning_class_id')
            ->orderBy('id')
            ->get();

        if ($assignments->isEmpty()) {
            return $empty;
        }

        $enrollmentsByClass = $enrollments->groupBy('learning_class_id');
        $attempts = AssessmentAttempt::query()
            ->whereIn('learning_class_assessment_id', $assignments->modelKeys())
            ->whereIn('enrollment_id', $enrollments->modelKeys())
            ->orderBy('learning_class_assessment_id')
            ->orderBy('enrollment_id')
            ->orderByDesc('attempt_number')
            ->orderByDesc('id')
            ->get()
            ->groupBy(fn (AssessmentAttempt $attempt): string => $this->attemptKey(
                $attempt->learning_class_assessment_id,
                $attempt->enrollment_id,
            ));
        $rows = [];
        $byClass = [];
        $byEnrollment = [];

        foreach ($assignments as $assignment) {
            $eligible = $enrollmentsByClass->get($assignment->learning_class_id, new Collection);
            $submitted = 0;
            $pending = 0;
            $inProgress = 0;
            $gradedScores = [];

            foreach ($eligible as $enrollment) {
                $cellAttempts = $attempts->get(
                    $this->attemptKey($assignment->id, $enrollment->id),
                    new Collection,
                );
                $latestAttempt = $cellAttempts->first();
                $latestSubmitted = $cellAttempts->first(
                    fn (AssessmentAttempt $attempt): bool => in_array($attempt->status, [
                        AssessmentAttemptStatus::PendingGrading,
                        AssessmentAttemptStatus::Graded,
                    ], true),
                );
                $latestGraded = $cellAttempts->first(
                    fn (AssessmentAttempt $attempt): bool => $attempt->status === AssessmentAttemptStatus::Graded
                        && $attempt->percentage !== null,
                );

                $submitted += $latestSubmitted instanceof AssessmentAttempt ? 1 : 0;
                $pending += $latestSubmitted?->status === AssessmentAttemptStatus::PendingGrading ? 1 : 0;
                $inProgress += $latestAttempt?->status === AssessmentAttemptStatus::InProgress ? 1 : 0;

                if ($latestGraded instanceof AssessmentAttempt) {
                    $gradedScores[] = (float) $latestGraded->percentage;
                }

                $enrollmentSummary = $byEnrollment[$enrollment->id] ?? $this->emptySummary();
                $enrollmentSummary['eligible']++;
                $enrollmentSummary['submitted'] += $latestSubmitted instanceof AssessmentAttempt ? 1 : 0;
                $enrollmentSummary['pending_grading'] += $latestSubmitted?->status === AssessmentAttemptStatus::PendingGrading ? 1 : 0;
                $enrollmentSummary['in_progress'] += $latestAttempt?->status === AssessmentAttemptStatus::InProgress ? 1 : 0;

                if ($latestGraded instanceof AssessmentAttempt) {
                    $enrollmentSummary['graded']++;
                    $enrollmentSummary['score_total'] += (float) $latestGraded->percentage;
                }

                $byEnrollment[$enrollment->id] = $enrollmentSummary;
            }

            $row = [
                'assignment_id' => $assignment->id,
                'class_id' => $assignment->learning_class_id,
                'class' => $assignment->learningClass->name,
                'assessment' => $assignment->assessment->title,
                'purpose' => $assignment->assessment->purpose->value,
                'eligible_students' => $eligible->pluck('student_id')->unique()->count(),
                'submitted_students' => $submitted,
                'participation_percentage' => $eligible->isEmpty()
                    ? null
                    : (int) round(($submitted / $eligible->count()) * 100),
                'graded_students' => count($gradedScores),
                'pending_grading_students' => $pending,
                'in_progress_students' => $inProgress,
                'average_score' => $gradedScores === []
                    ? null
                    : round(array_sum($gradedScores) / count($gradedScores), 2),
            ];
            $rows[] = $row;

            $classSummary = $byClass[$assignment->learning_class_id] ?? $this->emptySummary();
            $classSummary['eligible'] += $eligible->count();
            $classSummary['submitted'] += $submitted;
            $classSummary['graded'] += count($gradedScores);
            $classSummary['pending_grading'] += $pending;
            $classSummary['in_progress'] += $inProgress;
            $classSummary['score_total'] += array_sum($gradedScores);
            $byClass[$assignment->learning_class_id] = $classSummary;
        }

        foreach ($byClass as &$summary) {
            $summary = $this->finishSummary($summary);
        }
        unset($summary);

        foreach ($byEnrollment as &$summary) {
            $summary = $this->finishSummary($summary);
        }
        unset($summary);

        return [
            'assignments' => $rows,
            'by_class' => $byClass,
            'by_enrollment' => $byEnrollment,
        ];
    }

    /**
     * @param  Collection<int, Enrollment>  $enrollments
     * @return array<int, array<string, mixed>>
     */
    public function recentForEnrollments(Collection $enrollments, int $limit = 10): array
    {
        if ($enrollments->isEmpty()) {
            return [];
        }

        $enrollments->loadMissing('learningClass:id,name');
        $assignments = LearningClassAssessment::query()
            ->whereIn('learning_class_id', $enrollments->pluck('learning_class_id')->unique())
            ->where('status', ClassAssessmentStatus::Active->value)
            ->whereHas('assessment', fn ($query) => $query->where('status', AssessmentStatus::Published->value))
            ->with('assessment:id,title,purpose')
            ->get()
            ->keyBy('id');

        if ($assignments->isEmpty()) {
            return [];
        }

        $enrollmentsById = $enrollments->keyBy('id');

        return AssessmentAttempt::query()
            ->whereIn('learning_class_assessment_id', $assignments->keys())
            ->whereIn('enrollment_id', $enrollments->modelKeys())
            ->orderByDesc('attempt_number')
            ->orderByDesc('id')
            ->get()
            ->groupBy('learning_class_assessment_id')
            ->map(fn (Collection $attempts): ?AssessmentAttempt => $attempts->first())
            ->filter(fn (?AssessmentAttempt $attempt): bool => $attempt instanceof AssessmentAttempt)
            ->sortByDesc(fn (AssessmentAttempt $attempt) => $attempt->graded_at
                ?? $attempt->submitted_at
                ?? $attempt->started_at)
            ->take($limit)
            ->map(function (AssessmentAttempt $attempt) use ($assignments, $enrollmentsById): array {
                $assignment = $assignments->get($attempt->learning_class_assessment_id);
                $enrollment = $enrollmentsById->get($attempt->enrollment_id);

                return [
                    'attempt_id' => $attempt->id,
                    'assignment_id' => $attempt->learning_class_assessment_id,
                    'assessment' => $assignment?->assessment?->title,
                    'purpose' => $assignment?->assessment?->purpose->value,
                    'class' => $enrollment?->learningClass?->name,
                    'class_id' => $enrollment?->learning_class_id,
                    'attempt_number' => $attempt->attempt_number,
                    'status' => $attempt->status->value,
                    'percentage' => $attempt->status === AssessmentAttemptStatus::Graded
                        ? $attempt->percentage
                        : null,
                    'date' => ($attempt->graded_at ?? $attempt->submitted_at ?? $attempt->started_at)->toDateTimeString(),
                    'url' => $attempt->status === AssessmentAttemptStatus::InProgress
                        ? route('student.assessment-attempts.show', $attempt)
                        : route('student.assessment-attempts.result', $attempt),
                ];
            })
            ->values()
            ->all();
    }

    private function attemptKey(int $assignmentId, int $enrollmentId): string
    {
        return "{$assignmentId}:{$enrollmentId}";
    }

    /** @return array{eligible: int, submitted: int, graded: int, pending_grading: int, in_progress: int, score_total: float} */
    private function emptySummary(): array
    {
        return [
            'eligible' => 0,
            'submitted' => 0,
            'graded' => 0,
            'pending_grading' => 0,
            'in_progress' => 0,
            'score_total' => 0.0,
        ];
    }

    /**
     * @param  array{eligible: int, submitted: int, graded: int, pending_grading: int, in_progress: int, score_total: float}  $summary
     * @return array<string, int|float|null>
     */
    private function finishSummary(array $summary): array
    {
        return [
            ...$summary,
            'participation_percentage' => $summary['eligible'] === 0
                ? null
                : (int) round(($summary['submitted'] / $summary['eligible']) * 100),
            'average_score' => $summary['graded'] === 0
                ? null
                : round($summary['score_total'] / $summary['graded'], 2),
        ];
    }
}
