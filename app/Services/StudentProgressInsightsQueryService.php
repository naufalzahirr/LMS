<?php

namespace App\Services;

use App\Enums\AcademicStatus;
use App\Enums\EnrollmentStatus;
use App\Enums\LearningClassStatus;
use App\Enums\StudentCompetencyStatus;
use App\Models\Enrollment;
use App\Models\LearningClass;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class StudentProgressInsightsQueryService
{
    public function __construct(
        private readonly LearningProgressQueryService $learningProgress,
        private readonly MasteryProgressQueryService $masteryProgress,
        private readonly AssessmentAnalyticsQueryService $assessmentAnalytics,
    ) {}

    /** @return array<string, mixed> */
    public function forStudent(User $student): array
    {
        $enrollments = $this->activeEnrollments($student);

        if ($enrollments->isEmpty()) {
            return $this->emptyPayload();
        }

        $classes = LearningClass::query()
            ->whereIn('id', $enrollments->pluck('learning_class_id')->unique())
            ->with('course.program:id,name')
            ->get();
        $lessonSummaries = $this->learningProgress->summariesForEnrollments($enrollments);
        $masteryByEnrollment = $this->masteryProgress->competenciesForEnrollments($enrollments);
        $assessment = $this->assessmentAnalytics->summariesForClasses($classes, $enrollments);
        $completedLessons = 0;
        $totalLessons = 0;
        $classRows = [];
        $competencyCells = collect();

        foreach ($enrollments as $enrollment) {
            $learningClass = $enrollment->learningClass;
            $lesson = $lessonSummaries[$enrollment->id];
            $cells = collect($masteryByEnrollment[$enrollment->id] ?? []);
            $mastered = $cells->where('status', StudentCompetencyStatus::Mastered->value)->count();
            $remedial = $cells->where('status', StudentCompetencyStatus::NeedsRemedial->value)->count();
            $assessmentSummary = $assessment['by_enrollment'][$enrollment->id] ?? $this->emptyAssessmentSummary();
            $completedLessons += $lesson['completed_lessons'];
            $totalLessons += $lesson['total_lessons'];

            $classRows[] = [
                'id' => $learningClass->id,
                'name' => $learningClass->name,
                'course' => $learningClass->course->name,
                'program' => $learningClass->course->program->name,
                'completed_lessons' => $lesson['completed_lessons'],
                'total_lessons' => $lesson['total_lessons'],
                'lesson_percentage' => $lesson['total_lessons'] === 0 ? null : $lesson['percentage'],
                'competencies_mastered' => $mastered,
                'competencies_total' => $cells->count(),
                'needs_attention' => $remedial,
                'assessment_submitted' => $assessmentSummary['submitted'],
                'assessment_eligible' => $assessmentSummary['eligible'],
                'assessment_pending_grading' => $assessmentSummary['pending_grading'],
                'class_url' => route('student.classes.show', $learningClass),
                'continue_url' => $lesson['continue_lesson_id'] === null
                    ? route('student.classes.show', $learningClass)
                    : route('student.lessons.show', [$learningClass, $lesson['continue_lesson_id']]),
            ];

            foreach ($cells as $cell) {
                $competencyCells->push([
                    ...$cell,
                    'class_id' => $learningClass->id,
                    'class' => $learningClass->name,
                    'course' => $learningClass->course->name,
                    'action_url' => $cell['remedial_assignment_id'] === null
                        ? route('student.classes.show', $learningClass)
                        : route('student.remedials.show', $cell['remedial_assignment_id']),
                ]);
            }
        }

        // Match the established Student dashboard: the same active competency in two
        // concurrent enrollments is one competency in the Student-level summary.
        $uniqueCompetencies = $competencyCells->unique('id')->values();
        $mastered = $uniqueCompetencies->where('status', StudentCompetencyStatus::Mastered->value)->count();
        $needsAttention = $uniqueCompetencies->where('status', StudentCompetencyStatus::NeedsRemedial->value)->count();
        $assessmentSummary = $this->assessmentSummary($assessment['by_enrollment']);

        return [
            'summary' => [
                'completed_lessons' => $completedLessons,
                'total_lessons' => $totalLessons,
                'lesson_percentage' => $totalLessons === 0
                    ? null
                    : (int) round(($completedLessons / $totalLessons) * 100),
                'competencies_mastered' => $mastered,
                'competencies_total' => $uniqueCompetencies->count(),
                'mastery_percentage' => $uniqueCompetencies->isEmpty()
                    ? null
                    : (int) round(($mastered / $uniqueCompetencies->count()) * 100),
                'needs_attention' => $needsAttention,
                ...$assessmentSummary,
            ],
            'observations' => [
                "You have mastered {$mastered} of {$uniqueCompetencies->count()} active competencies.",
                "You completed {$completedLessons} of {$totalLessons} accessible lessons.",
                $needsAttention === 1
                    ? '1 competency currently needs remedial attention.'
                    : "{$needsAttention} competencies currently need remedial attention.",
            ],
            'classes' => $classRows,
            'competencies' => [
                'mastered' => $uniqueCompetencies
                    ->where('status', StudentCompetencyStatus::Mastered->value)
                    ->values()
                    ->all(),
                'learning' => $uniqueCompetencies
                    ->filter(fn (array $cell): bool => in_array($cell['status'], [
                        StudentCompetencyStatus::Learning->value,
                        StudentCompetencyStatus::ReadyForAssessment->value,
                        'locked',
                    ], true))
                    ->values()
                    ->all(),
                'needs_attention' => $uniqueCompetencies
                    ->where('status', StudentCompetencyStatus::NeedsRemedial->value)
                    ->values()
                    ->all(),
            ],
            'current_focus' => $uniqueCompetencies
                ->reject(fn (array $cell): bool => $cell['status'] === StudentCompetencyStatus::Mastered->value)
                ->sortBy(fn (array $cell): int => match ($cell['status']) {
                    StudentCompetencyStatus::NeedsRemedial->value => 0,
                    StudentCompetencyStatus::ReadyForAssessment->value => 1,
                    StudentCompetencyStatus::Learning->value => 2,
                    'locked' => 4,
                    default => 3,
                })
                ->take(5)
                ->values()
                ->all(),
            'recent_assessments' => $this->assessmentAnalytics->recentForEnrollments($enrollments),
        ];
    }

    /** @return Collection<int, Enrollment> */
    private function activeEnrollments(User $student): Collection
    {
        return Enrollment::query()
            ->where('student_id', $student->id)
            ->where('status', EnrollmentStatus::Active->value)
            ->whereHas('learningClass', fn ($query) => $query
                ->where('status', LearningClassStatus::Active->value)
                ->whereHas('course', fn ($query) => $query
                    ->where('status', AcademicStatus::Active->value)
                    ->whereHas('program', fn ($query) => $query->where('status', AcademicStatus::Active->value))))
            ->with('learningClass.course.program:id,name')
            ->orderByDesc('enrolled_at')
            ->get();
    }

    /**
     * @param  array<int, array<string, int|float|null>>  $summaries
     * @return array<string, int|float|null>
     */
    private function assessmentSummary(array $summaries): array
    {
        $eligible = collect($summaries)->sum('eligible');
        $submitted = collect($summaries)->sum('submitted');
        $graded = collect($summaries)->sum('graded');
        $scoreTotal = collect($summaries)->sum(
            fn (array $summary): float => $summary['average_score'] === null
                ? 0.0
                : ((float) $summary['average_score'] * $summary['graded']),
        );

        return [
            'assessment_eligible' => $eligible,
            'assessment_submitted' => $submitted,
            'assessment_graded' => $graded,
            'assessment_pending_grading' => collect($summaries)->sum('pending_grading'),
            'assessment_average' => $graded === 0 ? null : round($scoreTotal / $graded, 2),
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

    /** @return array<string, mixed> */
    private function emptyPayload(): array
    {
        return [
            'summary' => [
                'completed_lessons' => 0,
                'total_lessons' => 0,
                'lesson_percentage' => null,
                'competencies_mastered' => 0,
                'competencies_total' => 0,
                'mastery_percentage' => null,
                'needs_attention' => 0,
                'assessment_eligible' => 0,
                'assessment_submitted' => 0,
                'assessment_graded' => 0,
                'assessment_pending_grading' => 0,
                'assessment_average' => null,
            ],
            'observations' => [
                'You are not currently enrolled in an active learning class.',
            ],
            'classes' => [],
            'competencies' => [
                'mastered' => [],
                'learning' => [],
                'needs_attention' => [],
            ],
            'current_focus' => [],
            'recent_assessments' => [],
        ];
    }
}
