<?php

namespace App\Services;

use App\Enums\AcademicStatus;
use App\Enums\EnrollmentStatus;
use App\Enums\LearningClassStatus;
use App\Enums\RemedialAssignmentStatus;
use App\Enums\StudentCompetencyStatus;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\RemedialAssignment;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class StudentDashboardQueryService
{
    private const MAX_CONTINUE_LEARNING = 3;

    private const MAX_REMEDIAL_ITEMS = 5;

    private const MAX_AVAILABLE_ASSESSMENT_ITEMS = 3;

    private const MAX_ASSESSMENTS = 5;

    /** @var array<int, string> */
    private const ACTIONABLE_ASSESSMENT_AVAILABILITY = ['In Progress', 'Available', 'Submitted / Pending Grading'];

    public function __construct(
        private readonly LearningProgressQueryService $learningProgress,
        private readonly MasteryProgressQueryService $masteryProgress,
        private readonly StudentAssessmentPayloadService $assessmentPayloads,
        private readonly CompetencyAccessService $competencyAccess,
    ) {}

    /** @return array<string, mixed> */
    public function forStudent(User $user): array
    {
        $activeEnrollments = $this->activeEnrollments($user);

        if ($activeEnrollments->isEmpty()) {
            return [
                'has_any_enrollment_history' => Enrollment::query()->where('student_id', $user->id)->exists(),
                'continue_learning' => [],
                'needs_attention' => [
                    'remedial' => [],
                    'assessments_available' => ['count' => 0, 'items' => []],
                ],
                'progress' => [
                    'completed_lessons' => 0,
                    'total_lessons' => 0,
                    'competencies_mastered' => 0,
                    'competencies_total' => 0,
                ],
                'assessments' => [],
            ];
        }

        $summaries = $this->learningProgress->summariesForEnrollments($activeEnrollments);
        $assessments = $this->assessmentCards($activeEnrollments);
        $availableAssessments = collect($assessments)
            ->filter(fn (array $card): bool => $card['availability'] === 'Available')
            ->values();

        return [
            'has_any_enrollment_history' => true,
            'continue_learning' => $this->continueLearningCards($activeEnrollments, $summaries),
            'needs_attention' => [
                'remedial' => $this->remedialItems($activeEnrollments),
                'assessments_available' => [
                    'count' => $availableAssessments->count(),
                    'items' => $availableAssessments->take(self::MAX_AVAILABLE_ASSESSMENT_ITEMS)
                        ->map(fn (array $card): array => [
                            'title' => $card['title'],
                            'class_name' => $card['class_name'],
                            'start_url' => $card['action']['url'],
                            'method' => $card['action']['method'],
                        ])->values()->all(),
                ],
            ],
            'progress' => $this->progressSummary($activeEnrollments, $summaries),
            'assessments' => array_slice($assessments, 0, self::MAX_ASSESSMENTS),
        ];
    }

    /** @return Collection<int, Enrollment> */
    private function activeEnrollments(User $user): Collection
    {
        return Enrollment::query()
            ->where('student_id', $user->id)
            ->where('status', EnrollmentStatus::Active->value)
            ->whereHas('learningClass', function ($query): void {
                $query->where('status', LearningClassStatus::Active->value)
                    ->whereHas('course', function ($query): void {
                        $query->where('status', AcademicStatus::Active->value)
                            ->whereHas('program', fn ($query) => $query->where('status', AcademicStatus::Active->value));
                    });
            })
            ->with(['learningClass.course.program:id,name'])
            ->orderByDesc('enrolled_at')
            ->get();
    }

    /**
     * @param  Collection<int, Enrollment>  $activeEnrollments
     * @param  array<int, array{completed_lessons: int, total_lessons: int, percentage: int, continue_lesson_id: int|null}>  $summaries
     * @return array<int, array<string, mixed>>
     */
    private function continueLearningCards(Collection $activeEnrollments, array $summaries): array
    {
        return $activeEnrollments->take(self::MAX_CONTINUE_LEARNING)
            ->map(function (Enrollment $enrollment) use ($summaries): array {
                $learningClass = $enrollment->learningClass;
                $summary = $summaries[$enrollment->id];
                $continueLessonId = $summary['continue_lesson_id'];
                $continueLesson = $continueLessonId === null ? null : Lesson::query()->find($continueLessonId);
                $mayContinue = $continueLesson instanceof Lesson
                    && $this->competencyAccess->mayOpenLesson($enrollment, $continueLesson);

                return [
                    'enrollment_id' => $enrollment->id,
                    'learning_class_id' => $learningClass->id,
                    'name' => $learningClass->name,
                    'course' => $learningClass->course->name,
                    'program' => $learningClass->course->program->name,
                    'completed_lessons' => $summary['completed_lessons'],
                    'total_lessons' => $summary['total_lessons'],
                    'percentage' => $summary['percentage'],
                    'continue_lesson_title' => $mayContinue ? $continueLesson->title : null,
                    'continue_url' => $mayContinue
                        ? route('student.lessons.show', [$learningClass, $continueLesson])
                        : route('student.classes.show', $learningClass),
                    'class_url' => route('student.classes.show', $learningClass),
                ];
            })->values()->all();
    }

    /**
     * @param  Collection<int, Enrollment>  $activeEnrollments
     * @return array<int, array<string, mixed>>
     */
    private function remedialItems(Collection $activeEnrollments): array
    {
        $enrollmentsById = $activeEnrollments->keyBy('id');

        return RemedialAssignment::query()
            ->where('status', RemedialAssignmentStatus::Assigned->value)
            ->whereIn('enrollment_id', $enrollmentsById->keys())
            ->with('competency:id,name')
            ->orderBy('assigned_at')
            ->limit(self::MAX_REMEDIAL_ITEMS)
            ->get()
            ->map(fn (RemedialAssignment $item): array => [
                'enrollment_id' => $item->enrollment_id,
                'competency_name' => $item->competency->name,
                'class_name' => $enrollmentsById->get($item->enrollment_id)?->learningClass->name,
                'remedial_url' => route('student.remedials.show', $item),
            ])->values()->all();
    }

    /**
     * @param  Collection<int, Enrollment>  $activeEnrollments
     * @return array<int, array<string, mixed>>
     */
    private function assessmentCards(Collection $activeEnrollments): array
    {
        $cards = [];

        foreach ($activeEnrollments as $enrollment) {
            foreach ($this->assessmentPayloads->assignmentsForEnrollment($enrollment) as $card) {
                if (! in_array($card['availability'], self::ACTIONABLE_ASSESSMENT_AVAILABILITY, true)) {
                    continue;
                }

                $cards[] = [
                    ...$card,
                    'class_name' => $enrollment->learningClass->name,
                    'action' => $this->assessmentAction($card),
                ];
            }
        }

        usort(
            $cards,
            fn (array $a, array $b): int => $this->availabilityRank($a['availability']) <=> $this->availabilityRank($b['availability']),
        );

        return $cards;
    }

    private function availabilityRank(string $availability): int
    {
        return match ($availability) {
            'In Progress' => 0,
            'Available' => 1,
            default => 2,
        };
    }

    /**
     * Map an assignmentCard() payload to the single CTA the dashboard should show.
     *
     * `can_start` alone is not a safe signal: it stays true whenever attempts remain,
     * even while the latest attempt is awaiting manual grading, so a submission
     * pending review must never fall through to a "start a new attempt" action.
     *
     * @param  array<string, mixed>  $card
     * @return array{label: string|null, url: string|null, method: 'get'|'post'|null}
     */
    private function assessmentAction(array $card): array
    {
        if ($card['in_progress_url'] !== null) {
            return ['label' => 'Continue Assessment', 'url' => $card['in_progress_url'], 'method' => 'get'];
        }

        if ($card['availability'] === 'Submitted / Pending Grading') {
            return $card['latest_attempt_result_url'] !== null
                ? ['label' => 'View submission', 'url' => $card['latest_attempt_result_url'], 'method' => 'get']
                : ['label' => 'Awaiting grading', 'url' => null, 'method' => null];
        }

        if ($card['can_start']) {
            return ['label' => $card['start_label'], 'url' => $card['start_url'], 'method' => 'post'];
        }

        if ($card['latest_attempt_result_url'] !== null) {
            return ['label' => 'View result', 'url' => $card['latest_attempt_result_url'], 'method' => 'get'];
        }

        return ['label' => null, 'url' => null, 'method' => null];
    }

    /**
     * @param  Collection<int, Enrollment>  $activeEnrollments
     * @param  array<int, array{completed_lessons: int, total_lessons: int, percentage: int, continue_lesson_id: int|null}>  $summaries
     * @return array<string, int>
     */
    private function progressSummary(Collection $activeEnrollments, array $summaries): array
    {
        $completedLessons = 0;
        $totalLessons = 0;

        foreach ($activeEnrollments->modelKeys() as $id) {
            $completedLessons += $summaries[$id]['completed_lessons'];
            $totalLessons += $summaries[$id]['total_lessons'];
        }

        // The denominator is every active competency in the student's active enrolled
        // courses, not just ones with an existing progress row (a student who hasn't
        // started a competency yet still has 0 rows for it). competenciesForEnrollments()
        // already maps that missing-row case to the default "learning" status, and
        // de-duplicating by competency id keeps a competency shared across two active
        // enrollments (e.g. the same course) from being counted twice.
        $competencyCells = collect($this->masteryProgress->competenciesForEnrollments($activeEnrollments))
            ->flatten(1)
            ->unique(fn (array $cell): int => $cell['id']);

        return [
            'completed_lessons' => $completedLessons,
            'total_lessons' => $totalLessons,
            'competencies_mastered' => $competencyCells
                ->where('status', StudentCompetencyStatus::Mastered->value)
                ->count(),
            'competencies_total' => $competencyCells->count(),
        ];
    }
}
