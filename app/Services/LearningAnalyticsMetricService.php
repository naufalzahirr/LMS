<?php

namespace App\Services;

use App\Enums\EnrollmentStatus;
use App\Enums\StudentCompetencyStatus;
use App\Models\Enrollment;
use App\Models\LearningClass;
use Illuminate\Database\Eloquent\Collection;

class LearningAnalyticsMetricService
{
    public function __construct(
        private readonly LearningProgressQueryService $learningProgress,
        private readonly MasteryProgressQueryService $masteryProgress,
        private readonly AssessmentAnalyticsQueryService $assessmentAnalytics,
    ) {}

    /**
     * @param  Collection<int, LearningClass>  $classes
     * @return array{overview: array<string, int|float|null>, class_rows: array<int, array<string, mixed>>, student_rows: array<int, array<string, mixed>>, competencies: array<int, array<string, mixed>>, assessments: array<int, array<string, mixed>>}
     */
    public function forClasses(Collection $classes): array
    {
        if ($classes->isEmpty()) {
            return $this->emptyPayload();
        }

        $classes->loadMissing([
            'course.program:id,name',
            'tutors:id,name',
        ]);
        $enrollments = Enrollment::query()
            ->whereIn('learning_class_id', $classes->modelKeys())
            ->where('status', EnrollmentStatus::Active->value)
            ->with([
                'student:id,name,email',
                'learningClass:id,course_id,name',
            ])
            ->orderBy('learning_class_id')
            ->orderBy('student_id')
            ->get();
        $lessons = $this->learningProgress->summariesForEnrollments($enrollments);
        $mastery = $this->masteryProgress->competenciesForEnrollments($enrollments);
        $assessment = $this->assessmentAnalytics->summariesForClasses($classes, $enrollments);
        $studentRows = [];
        $studentRowsByClass = [];
        $competencies = [];

        foreach ($enrollments as $enrollment) {
            $cells = $mastery[$enrollment->id] ?? [];
            $lesson = $lessons[$enrollment->id];
            $mastered = collect($cells)->where('status', StudentCompetencyStatus::Mastered->value)->count();
            $remedialCases = collect($cells)->where('status', StudentCompetencyStatus::NeedsRemedial->value)->count();
            $assessmentSummary = $assessment['by_enrollment'][$enrollment->id] ?? $this->emptyAssessmentSummary();
            $learningClass = $classes->firstWhere('id', $enrollment->learning_class_id);

            if (! $learningClass instanceof LearningClass) {
                continue;
            }

            $row = [
                'enrollment_id' => $enrollment->id,
                'student_id' => $enrollment->student_id,
                'student' => $enrollment->student->name,
                'email' => $enrollment->student->email,
                'class_id' => $learningClass->id,
                'class' => $learningClass->name,
                'course' => $learningClass->course->name,
                'completed_lessons' => $lesson['completed_lessons'],
                'total_lessons' => $lesson['total_lessons'],
                'lesson_percentage' => $lesson['total_lessons'] === 0 ? null : $lesson['percentage'],
                'competencies_mastered' => $mastered,
                'competencies_total' => count($cells),
                'mastery_percentage' => $cells === [] ? null : (int) round(($mastered / count($cells)) * 100),
                'remedial_cases' => $remedialCases,
                'needs_remedial' => $remedialCases > 0,
                'assessment_submitted' => $assessmentSummary['submitted'],
                'assessment_eligible' => $assessmentSummary['eligible'],
                'assessment_graded' => $assessmentSummary['graded'],
                'assessment_pending_grading' => $assessmentSummary['pending_grading'],
                'assessment_average' => $assessmentSummary['average_score'],
            ];
            $studentRows[] = $row;
            $studentRowsByClass[$learningClass->id][] = $row;

            foreach ($cells as $cell) {
                $competency = $competencies[$cell['id']] ?? [
                    'id' => $cell['id'],
                    'competency' => $cell['name'],
                    'course' => $learningClass->course->name,
                    'eligible_student_contexts' => 0,
                    'mastered' => 0,
                    'learning' => 0,
                    'needs_remedial_cases' => 0,
                    'remedial_student_ids' => [],
                ];
                $competency['eligible_student_contexts']++;
                $competency['mastered'] += $cell['status'] === StudentCompetencyStatus::Mastered->value ? 1 : 0;
                $competency['learning'] += in_array($cell['status'], [
                    StudentCompetencyStatus::Learning->value,
                    StudentCompetencyStatus::ReadyForAssessment->value,
                    'locked',
                ], true) ? 1 : 0;
                $competency['needs_remedial_cases'] += $cell['status'] === StudentCompetencyStatus::NeedsRemedial->value ? 1 : 0;

                if ($cell['status'] === StudentCompetencyStatus::NeedsRemedial->value) {
                    $competency['remedial_student_ids'][$enrollment->student_id] = true;
                }

                $competencies[$cell['id']] = $competency;
            }
        }

        $classRows = $classes->map(function (LearningClass $learningClass) use ($studentRowsByClass, $assessment): array {
            $rows = collect($studentRowsByClass[$learningClass->id] ?? []);
            $completedLessons = $rows->sum('completed_lessons');
            $totalLessons = $rows->sum('total_lessons');
            $mastered = $rows->sum('competencies_mastered');
            $totalCompetencies = $rows->sum('competencies_total');
            $assessmentSummary = $assessment['by_class'][$learningClass->id] ?? $this->emptyAssessmentSummary();

            return [
                'id' => $learningClass->id,
                'name' => $learningClass->name,
                'code' => $learningClass->code,
                'program' => $learningClass->course->program->name,
                'course' => $learningClass->course->name,
                'tutors' => $learningClass->tutors->pluck('name')->values()->all(),
                'active_students' => $rows->pluck('student_id')->unique()->count(),
                'completed_lessons' => $completedLessons,
                'total_lessons' => $totalLessons,
                'lesson_percentage' => $totalLessons === 0
                    ? null
                    : (int) round(($completedLessons / $totalLessons) * 100),
                'competencies_mastered' => $mastered,
                'competencies_total' => $totalCompetencies,
                'mastery_percentage' => $totalCompetencies === 0
                    ? null
                    : (int) round(($mastered / $totalCompetencies) * 100),
                'students_needing_remedial' => $rows
                    ->where('needs_remedial', true)
                    ->pluck('student_id')
                    ->unique()
                    ->count(),
                'remedial_cases' => $rows->sum('remedial_cases'),
                'assessment_submitted' => $assessmentSummary['submitted'],
                'assessment_eligible' => $assessmentSummary['eligible'],
                'assessment_participation_percentage' => $assessmentSummary['participation_percentage'],
                'assessment_graded' => $assessmentSummary['graded'],
                'assessment_pending_grading' => $assessmentSummary['pending_grading'],
                'assessment_average' => $assessmentSummary['average_score'],
            ];
        })->values()->all();

        $competencyRows = collect($competencies)
            ->map(function (array $competency): array {
                $eligible = $competency['eligible_student_contexts'];

                return [
                    'id' => $competency['id'],
                    'competency' => $competency['competency'],
                    'course' => $competency['course'],
                    'eligible_student_contexts' => $eligible,
                    'mastered' => $competency['mastered'],
                    'mastery_percentage' => $eligible === 0
                        ? null
                        : (int) round(($competency['mastered'] / $eligible) * 100),
                    'learning' => $competency['learning'],
                    'students_needing_remedial' => count($competency['remedial_student_ids']),
                    'remedial_cases' => $competency['needs_remedial_cases'],
                ];
            })
            ->sort(function (array $left, array $right): int {
                $leftRate = $left['mastery_percentage'] ?? PHP_INT_MAX;
                $rightRate = $right['mastery_percentage'] ?? PHP_INT_MAX;

                return [$leftRate, -$left['remedial_cases'], $left['competency']]
                    <=> [$rightRate, -$right['remedial_cases'], $right['competency']];
            })
            ->values()
            ->all();

        return [
            'overview' => $this->overview($classes, $studentRows, $classRows),
            'class_rows' => $classRows,
            'student_rows' => $studentRows,
            'competencies' => $competencyRows,
            'assessments' => $assessment['assignments'],
        ];
    }

    /**
     * @param  Collection<int, LearningClass>  $classes
     * @param  array<int, array<string, mixed>>  $studentRows
     * @param  array<int, array<string, mixed>>  $classRows
     * @return array<string, int|float|null>
     */
    private function overview(Collection $classes, array $studentRows, array $classRows): array
    {
        $studentRows = collect($studentRows);
        $classRows = collect($classRows);
        $completedLessons = $classRows->sum('completed_lessons');
        $totalLessons = $classRows->sum('total_lessons');
        $mastered = $classRows->sum('competencies_mastered');
        $competencies = $classRows->sum('competencies_total');
        $eligibleAssessments = $classRows->sum('assessment_eligible');
        $submittedAssessments = $classRows->sum('assessment_submitted');
        $gradedAssessments = $classRows->sum('assessment_graded');
        $assessmentScoreTotal = $classRows->sum(
            fn (array $row): float => $row['assessment_average'] === null
                ? 0.0
                : ((float) $row['assessment_average'] * $row['assessment_graded']),
        );

        return [
            'active_classes' => $classes->count(),
            'active_students' => $studentRows->pluck('student_id')->unique()->count(),
            'completed_lessons' => $completedLessons,
            'total_lessons' => $totalLessons,
            'lesson_percentage' => $totalLessons === 0
                ? null
                : (int) round(($completedLessons / $totalLessons) * 100),
            'competencies_mastered' => $mastered,
            'competencies_total' => $competencies,
            'mastery_percentage' => $competencies === 0
                ? null
                : (int) round(($mastered / $competencies) * 100),
            'students_needing_remedial' => $studentRows
                ->where('needs_remedial', true)
                ->pluck('student_id')
                ->unique()
                ->count(),
            'remedial_cases' => $studentRows->sum('remedial_cases'),
            'assessment_submitted' => $submittedAssessments,
            'assessment_eligible' => $eligibleAssessments,
            'assessment_participation_percentage' => $eligibleAssessments === 0
                ? null
                : (int) round(($submittedAssessments / $eligibleAssessments) * 100),
            'assessment_graded' => $gradedAssessments,
            'assessment_pending_grading' => $classRows->sum('assessment_pending_grading'),
            'assessment_average' => $gradedAssessments === 0
                ? null
                : round($assessmentScoreTotal / $gradedAssessments, 2),
        ];
    }

    /** @return array<string, int|float|null> */
    private function emptyAssessmentSummary(): array
    {
        return [
            'eligible' => 0,
            'submitted' => 0,
            'graded' => 0,
            'pending_grading' => 0,
            'in_progress' => 0,
            'score_total' => 0.0,
            'participation_percentage' => null,
            'average_score' => null,
        ];
    }

    /** @return array{overview: array<string, int|float|null>, class_rows: array<int, array<string, mixed>>, student_rows: array<int, array<string, mixed>>, competencies: array<int, array<string, mixed>>, assessments: array<int, array<string, mixed>>} */
    private function emptyPayload(): array
    {
        return [
            'overview' => [
                'active_classes' => 0,
                'active_students' => 0,
                'completed_lessons' => 0,
                'total_lessons' => 0,
                'lesson_percentage' => null,
                'competencies_mastered' => 0,
                'competencies_total' => 0,
                'mastery_percentage' => null,
                'students_needing_remedial' => 0,
                'remedial_cases' => 0,
                'assessment_submitted' => 0,
                'assessment_eligible' => 0,
                'assessment_participation_percentage' => null,
                'assessment_graded' => 0,
                'assessment_pending_grading' => 0,
                'assessment_average' => null,
            ],
            'class_rows' => [],
            'student_rows' => [],
            'competencies' => [],
            'assessments' => [],
        ];
    }
}
