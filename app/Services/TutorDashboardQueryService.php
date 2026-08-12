<?php

namespace App\Services;

use App\Enums\AssessmentAttemptStatus;
use App\Enums\EnrollmentStatus;
use App\Enums\LearningClassStatus;
use App\Enums\StudentCompetencyStatus;
use App\Models\AssessmentAttempt;
use App\Models\Enrollment;
use App\Models\LearningClass;
use App\Models\LearningClassAssessment;
use App\Models\StudentCompetencyProgress;
use App\Models\User;
use Illuminate\Support\Collection;

class TutorDashboardQueryService
{
    private const MAX_CLASSES = 5;

    private const MAX_GRADING_ITEMS = 5;

    public function __construct(private readonly TutorCourseAccessService $access) {}

    /** @return array<string, mixed> */
    public function forTutor(User $user): array
    {
        $activeClassIds = $user->teachingClasses()
            ->where('learning_classes.status', LearningClassStatus::Active->value)
            ->pluck('learning_classes.id');

        if ($activeClassIds->isEmpty()) {
            return [
                'my_classes' => [],
                'needs_attention' => ['needs_remedial_count' => 0, 'needs_remedial_url' => route('tutor.classes.index')],
                'grading_queue' => ['count' => 0, 'items' => []],
                'quick_actions' => $this->quickActions($user),
            ];
        }

        return [
            'my_classes' => $this->myClasses($user),
            'needs_attention' => $this->needsAttention($activeClassIds),
            'grading_queue' => $this->gradingQueue($activeClassIds),
            'quick_actions' => $this->quickActions($user),
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function myClasses(User $user): array
    {
        return $user->teachingClasses()
            ->where('learning_classes.status', LearningClassStatus::Active->value)
            ->with('course.program:id,name')
            ->withCount([
                'enrollments as active_students_count' => fn ($query) => $query->where('status', EnrollmentStatus::Active->value),
            ])
            ->orderByDesc('learning_classes.start_date')
            ->limit(self::MAX_CLASSES)
            ->get()
            ->map(fn (LearningClass $learningClass): array => [
                'id' => $learningClass->id,
                'name' => $learningClass->name,
                'course' => $learningClass->course->name,
                'program' => $learningClass->course->program->name,
                'active_students_count' => $learningClass->active_students_count ?? 0,
                'url' => route('tutor.classes.show', $learningClass),
            ])->values()->all();
    }

    /** @param Collection<int, int> $activeClassIds
     * @return array<string, mixed>
     */
    private function needsAttention(Collection $activeClassIds): array
    {
        $enrollmentIds = Enrollment::query()
            ->whereIn('learning_class_id', $activeClassIds)
            ->where('status', EnrollmentStatus::Active->value)
            ->pluck('id');
        // Distinct students, not competency-progress rows: a single student can have
        // several NeedsRemedial competencies and must still count once.
        $needsRemedialCount = $enrollmentIds->isEmpty() ? 0 : StudentCompetencyProgress::query()
            ->join('enrollments', 'enrollments.id', '=', 'student_competency_progress.enrollment_id')
            ->whereIn('student_competency_progress.enrollment_id', $enrollmentIds)
            ->where('student_competency_progress.status', StudentCompetencyStatus::NeedsRemedial->value)
            ->distinct('enrollments.student_id')
            ->count('enrollments.student_id');

        return [
            'needs_remedial_count' => $needsRemedialCount,
            'needs_remedial_url' => $activeClassIds->count() === 1
                ? route('tutor.classes.show', $activeClassIds->first())
                : route('tutor.classes.index'),
        ];
    }

    /** @param Collection<int, int> $activeClassIds
     * @return array<string, mixed>
     */
    private function gradingQueue(Collection $activeClassIds): array
    {
        $assignmentIds = LearningClassAssessment::query()
            ->whereIn('learning_class_id', $activeClassIds)
            ->pluck('id');

        if ($assignmentIds->isEmpty()) {
            return ['count' => 0, 'items' => []];
        }

        $pending = AssessmentAttempt::query()
            ->whereIn('learning_class_assessment_id', $assignmentIds)
            ->where('status', AssessmentAttemptStatus::PendingGrading->value);
        $count = (clone $pending)->count();
        $items = [];

        foreach ((clone $pending)
            ->with(['classAssessment.assessment:id,title', 'classAssessment.learningClass:id,name'])
            ->orderBy('submitted_at')
            ->limit(self::MAX_GRADING_ITEMS * 3)
            ->get()
            ->groupBy('learning_class_assessment_id')
            ->take(self::MAX_GRADING_ITEMS) as $assignmentId => $attempts) {
            $assignment = $attempts->first()->classAssessment;
            $items[] = [
                'assignment_id' => $assignmentId,
                'learning_class_id' => $assignment->learning_class_id,
                'title' => $assignment->assessment->title,
                'class_name' => $assignment->learningClass->name,
                'count' => $attempts->count(),
                'review_url' => route('tutor.class-assessment-attempts.index', [
                    $assignment->learning_class_id,
                    $assignmentId,
                ]).'?status=pending_grading',
            ];
        }

        return ['count' => $count, 'items' => $items];
    }

    /** @return array<int, array<string, mixed>> */
    private function quickActions(User $user): array
    {
        $actions = [
            ['label' => 'Open my classes', 'url' => route('tutor.classes.index')],
        ];

        if ($this->access->hasAnyActiveCourse($user)) {
            $actions[] = ['label' => 'Create lesson', 'url' => route('admin.lessons.create')];
            $actions[] = ['label' => 'Create assessment', 'url' => route('admin.assessments.create')];
        }

        return $actions;
    }
}
