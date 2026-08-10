<?php

namespace App\Services;

use App\Enums\AcademicStatus;
use App\Enums\AssessmentAttemptStatus;
use App\Enums\StudentCompetencyStatus;
use App\Models\Enrollment;
use App\Models\LearningClass;
use App\Models\LearningClassAssessment;

class ProgressReportQueryService
{
    public function __construct(
        private readonly LearningProgressQueryService $learningProgress,
        private readonly MasteryProgressQueryService $masteryProgress,
    ) {}

    /**
     * @param  array{program_id: int, course_id: int, learning_class_id: int, student_id: int, mastery_status: string}  $filters
     * @return array{rows: array<int, array<string, mixed>>, summary: array<string, int|float>}
     */
    public function overview(array $filters): array
    {
        $query = Enrollment::query()
            ->with([
                'student:id,name,email',
                'learningClass.course.program:id,name',
            ]);

        if ($filters['program_id'] > 0) {
            $query->whereHas(
                'learningClass.course',
                fn ($query) => $query->where('program_id', $filters['program_id']),
            );
        }

        if ($filters['course_id'] > 0) {
            $query->whereHas(
                'learningClass',
                fn ($query) => $query->where('course_id', $filters['course_id']),
            );
        }

        if ($filters['learning_class_id'] > 0) {
            $query->where('learning_class_id', $filters['learning_class_id']);
        }

        if ($filters['student_id'] > 0) {
            $query->where('student_id', $filters['student_id']);
        }

        $enrollments = $query
            ->orderBy('learning_class_id')
            ->orderBy('student_id')
            ->get();
        $lessonSummaries = $this->learningProgress->summariesForEnrollments($enrollments);
        $masteryByEnrollment = $this->masteryProgress->competenciesForEnrollments($enrollments);
        $rows = [];
        $allBestScores = collect();

        foreach ($enrollments as $enrollment) {
            $cells = $masteryByEnrollment[$enrollment->id] ?? [];

            if ($filters['mastery_status'] !== ''
                && ! collect($cells)->contains(
                    fn (array $cell): bool => $cell['status'] === $filters['mastery_status'],
                )) {
                continue;
            }

            $bestScores = collect($cells)
                ->pluck('best_score')
                ->filter(fn (mixed $score): bool => $score !== null)
                ->map(fn (mixed $score): float => (float) $score);
            $allBestScores->push(...$bestScores->all());
            $learningClass = $enrollment->learningClass;
            $lessonSummary = $lessonSummaries[$enrollment->id];

            $rows[] = [
                'enrollment_id' => $enrollment->id,
                'student_id' => $enrollment->student_id,
                'student' => $enrollment->student->name,
                'email' => $enrollment->student->email,
                'program' => $learningClass->course->program->name,
                'course' => $learningClass->course->name,
                'class_id' => $learningClass->id,
                'class' => $learningClass->name,
                'enrollment_status' => $enrollment->status->value,
                'completed_lessons' => $lessonSummary['completed_lessons'],
                'total_lessons' => $lessonSummary['total_lessons'],
                'lesson_percentage' => $lessonSummary['percentage'],
                'competencies_mastered' => collect($cells)->where(
                    'status',
                    StudentCompetencyStatus::Mastered->value,
                )->count(),
                'competencies_total' => count($cells),
                'needs_remedial' => collect($cells)->where(
                    'status',
                    StudentCompetencyStatus::NeedsRemedial->value,
                )->count(),
                'average_best_score' => $bestScores->isEmpty()
                    ? null
                    : round($bestScores->average(), 2),
                'url' => route('admin.reports.classes.show', $learningClass),
            ];
        }

        return [
            'rows' => $rows,
            'summary' => [
                'students' => collect($rows)->pluck('student_id')->unique()->count(),
                'classes' => collect($rows)->pluck('class_id')->unique()->count(),
                'enrollments' => count($rows),
                'competencies_mastered' => collect($rows)->sum('competencies_mastered'),
                'needs_remedial' => collect($rows)->sum('needs_remedial'),
                'average_best_score' => $allBestScores->isEmpty()
                    ? 0
                    : round($allBestScores->average(), 2),
            ],
        ];
    }

    /** @return array<string, mixed> */
    public function classReport(LearningClass $learningClass): array
    {
        $learningClass->load([
            'course.program:id,name',
            'course.competencies' => fn ($query) => $query
                ->where('status', AcademicStatus::Active->value)
                ->with('prerequisites:id,name,sort_order'),
        ]);
        $enrollments = Enrollment::query()
            ->where('learning_class_id', $learningClass->id)
            ->with(['student:id,name,email', 'learningClass:id,course_id'])
            ->orderBy('student_id')
            ->get();
        $lessonSummaries = $this->learningProgress->summariesForEnrollments($enrollments);
        $masteryByEnrollment = $this->masteryProgress->competenciesForEnrollments($enrollments);
        $studentRows = [];
        $heatmapStudents = [];
        $attention = [];
        $masteredCount = 0;
        $needsRemedialCount = 0;
        $totalCells = 0;

        foreach ($enrollments as $enrollment) {
            $cells = $masteryByEnrollment[$enrollment->id] ?? [];
            $mastered = collect($cells)->where('status', StudentCompetencyStatus::Mastered->value)->count();
            $needsRemedial = collect($cells)->where('status', StudentCompetencyStatus::NeedsRemedial->value)->count();
            $lessonSummary = $lessonSummaries[$enrollment->id];
            $masteredCount += $mastered;
            $needsRemedialCount += $needsRemedial;
            $totalCells += count($cells);

            foreach ($cells as $cell) {
                $reasons = [];

                if ($cell['status'] === StudentCompetencyStatus::NeedsRemedial->value) {
                    $reasons[] = 'Needs remedial learning';
                }

                if ($cell['attempts_exhausted'] === true) {
                    $reasons[] = 'Maximum mastery attempts reached';
                }

                if ($reasons !== []) {
                    $attention[] = [
                        'student' => $enrollment->student->name,
                        'email' => $enrollment->student->email,
                        'competency' => $cell['name'],
                        'reasons' => $reasons,
                    ];
                }
            }

            $studentRows[] = [
                'enrollment_id' => $enrollment->id,
                'student' => $enrollment->student->name,
                'email' => $enrollment->student->email,
                'enrollment_status' => $enrollment->status->value,
                'completed_lessons' => $lessonSummary['completed_lessons'],
                'total_lessons' => $lessonSummary['total_lessons'],
                'lesson_percentage' => $lessonSummary['percentage'],
                'competencies_mastered' => $mastered,
                'competencies_total' => count($cells),
                'needs_remedial' => $needsRemedial,
            ];
            $heatmapStudents[] = [
                'enrollment_id' => $enrollment->id,
                'student' => $enrollment->student->name,
                'email' => $enrollment->student->email,
                'competencies' => array_map(
                    fn (array $cell): array => [
                        ...$cell,
                        'competency_id' => $cell['id'],
                        'remedial_url' => null,
                    ],
                    $cells,
                ),
            ];
        }

        $assignments = LearningClassAssessment::query()
            ->where('learning_class_id', $learningClass->id)
            ->with([
                'assessment:id,title,purpose,competency_id',
                'attempts:id,learning_class_assessment_id,status,percentage',
            ])
            ->orderBy('id')
            ->get();

        return [
            'learning_class' => [
                'id' => $learningClass->id,
                'name' => $learningClass->name,
                'code' => $learningClass->code,
                'program' => $learningClass->course->program->name,
                'course' => $learningClass->course->name,
                'status' => $learningClass->status->value,
            ],
            'summary' => [
                'students' => $enrollments->count(),
                'competencies' => $learningClass->course->competencies->count(),
                'mastery_rate' => $totalCells === 0
                    ? 0
                    : (int) round(($masteredCount / $totalCells) * 100),
                'needs_remedial' => $needsRemedialCount,
            ],
            'students' => $studentRows,
            'heatmap' => [
                'competencies' => $learningClass->course->competencies->map(
                    fn ($competency): array => [
                        'id' => $competency->id,
                        'name' => $competency->name,
                        'prerequisites' => $competency->prerequisites->pluck('name')->values()->all(),
                    ],
                )->values()->all(),
                'students' => $heatmapStudents,
            ],
            'assessments' => $assignments->map(function (LearningClassAssessment $assignment): array {
                $graded = $assignment->attempts->filter(
                    fn ($attempt): bool => $attempt->status === AssessmentAttemptStatus::Graded,
                );
                $percentages = $graded->pluck('percentage')
                    ->filter(fn (mixed $score): bool => $score !== null)
                    ->map(fn (mixed $score): float => (float) $score);

                return [
                    'assessment' => $assignment->assessment->title,
                    'purpose' => $assignment->assessment->purpose->value,
                    'attempts' => $assignment->attempts->count(),
                    'in_progress' => $assignment->attempts->where(
                        'status',
                        AssessmentAttemptStatus::InProgress,
                    )->count(),
                    'pending_grading' => $assignment->attempts->where(
                        'status',
                        AssessmentAttemptStatus::PendingGrading,
                    )->count(),
                    'graded' => $graded->count(),
                    'average_score' => $percentages->isEmpty()
                        ? null
                        : round($percentages->average(), 2),
                ];
            })->values()->all(),
            'attention' => $attention,
        ];
    }

    /** @return array<int, array<int, string|null>> */
    public function classCsvRows(LearningClass $learningClass): array
    {
        $learningClass->load('course.program:id,name');
        $enrollments = Enrollment::query()
            ->where('learning_class_id', $learningClass->id)
            ->with(['student:id,name,email', 'learningClass:id,course_id'])
            ->orderBy('student_id')
            ->get();
        $masteryByEnrollment = $this->masteryProgress->competenciesForEnrollments($enrollments);
        $rows = [];

        foreach ($enrollments as $enrollment) {
            foreach ($masteryByEnrollment[$enrollment->id] ?? [] as $cell) {
                $rows[] = [
                    $enrollment->student->name,
                    $enrollment->student->email,
                    $learningClass->name,
                    $cell['name'],
                    $cell['status'],
                    $cell['latest_score'],
                    $cell['best_score'],
                    $cell['required_score'],
                    $cell['mastered_at'],
                ];
            }
        }

        return $rows;
    }
}
