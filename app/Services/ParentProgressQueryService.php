<?php

namespace App\Services;

use App\Enums\AssessmentAttemptStatus;
use App\Enums\EnrollmentStatus;
use App\Enums\LearningClassStatus;
use App\Enums\StudentCompetencyStatus;
use App\Models\AssessmentAttempt;
use App\Models\Enrollment;
use App\Models\ParentStudentRelationship;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class ParentProgressQueryService
{
    public function __construct(
        private readonly LearningProgressQueryService $learningProgress,
        private readonly MasteryProgressQueryService $masteryProgress,
    ) {}

    /** @return array<int, array<string, mixed>> */
    public function dashboard(User $parent): array
    {
        $children = User::role('Student')
            ->whereIn('id', ParentStudentRelationship::query()
                ->where('parent_id', $parent->id)
                ->select('student_id'))
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        return $this->childrenPayload($children);
    }

    /** @return array<string, mixed> */
    public function child(User $student): array
    {
        $payload = $this->childrenPayload(new Collection([$student]));

        return $payload[0] ?? [
            'id' => $student->id,
            'name' => $student->name,
            'email' => $student->email,
            'summary' => $this->emptySummary(),
            'current_classes' => [],
            'history_classes' => [],
            'url' => route('parent.students.show', $student),
        ];
    }

    /**
     * @param  Collection<int, User>  $children
     * @return array<int, array<string, mixed>>
     */
    private function childrenPayload(Collection $children): array
    {
        if ($children->isEmpty()) {
            return [];
        }

        $enrollments = Enrollment::query()
            ->whereIn('student_id', $children->modelKeys())
            ->with([
                'learningClass.course.program:id,name',
                'learningClass.tutors:id,name',
            ])
            ->orderByDesc('enrolled_at')
            ->get();
        $lessonSummaries = $this->learningProgress->summariesForEnrollments($enrollments);
        $mastery = $this->masteryProgress->competenciesForEnrollments($enrollments);
        $attempts = AssessmentAttempt::query()
            ->whereIn('enrollment_id', $enrollments->modelKeys())
            ->with([
                'classAssessment:id,assessment_id',
                'classAssessment.assessment:id,title,purpose',
            ])
            ->orderByDesc('submitted_at')
            ->orderByDesc('attempt_number')
            ->get()
            ->groupBy('enrollment_id');
        $enrollmentsByStudent = $enrollments->groupBy('student_id');

        return $children->map(function (User $child) use ($enrollmentsByStudent, $lessonSummaries, $mastery, $attempts): array {
            $childEnrollments = $enrollmentsByStudent->get($child->id, new Collection);
            $current = [];
            $history = [];

            foreach ($childEnrollments as $enrollment) {
                $isCurrent = $enrollment->status === EnrollmentStatus::Active
                    && $enrollment->learningClass->status === LearningClassStatus::Active;
                $class = $this->classPayload(
                    $enrollment,
                    $lessonSummaries[$enrollment->id],
                    $mastery[$enrollment->id] ?? [],
                    $attempts->get($enrollment->id, new Collection),
                );

                if ($isCurrent) {
                    $current[] = $class;
                } else {
                    $history[] = $class;
                }
            }

            return [
                'id' => $child->id,
                'name' => $child->name,
                'email' => $child->email,
                'summary' => $this->summary($current),
                'current_classes' => $current,
                'history_classes' => $history,
                'url' => route('parent.students.show', $child),
            ];
        })->values()->all();
    }

    /**
     * @param  array{completed_lessons: int, total_lessons: int, percentage: int, continue_lesson_id: int|null}  $lessons
     * @param  array<int, array<string, mixed>>  $mastery
     * @param  Collection<int, AssessmentAttempt>  $attempts
     * @return array<string, mixed>
     */
    private function classPayload(
        Enrollment $enrollment,
        array $lessons,
        array $mastery,
        Collection $attempts,
    ): array {
        $learningClass = $enrollment->learningClass;

        return [
            'id' => $learningClass->id,
            'name' => $learningClass->name,
            'program' => $learningClass->course->program->name,
            'course' => $learningClass->course->name,
            'class_status' => $learningClass->status->value,
            'enrollment_status' => $enrollment->status->value,
            'tutors' => $learningClass->tutors->pluck('name')->values()->all(),
            'lesson_progress' => [
                'completed' => $lessons['completed_lessons'],
                'total' => $lessons['total_lessons'],
                'percentage' => $lessons['percentage'],
            ],
            'mastery' => $mastery,
            'assessments' => $attempts->map(fn (AssessmentAttempt $attempt): array => [
                'assessment' => $attempt->classAssessment->assessment->title,
                'purpose' => $attempt->classAssessment->assessment->purpose->value,
                'attempt' => $attempt->attempt_number,
                'status' => $attempt->status->value,
                'score' => $attempt->status === AssessmentAttemptStatus::Graded
                    ? "{$attempt->earned_points} / {$attempt->max_points}"
                    : null,
                'percentage' => $attempt->status === AssessmentAttemptStatus::Graded
                    ? $attempt->percentage
                    : null,
                'submitted_at' => $attempt->submitted_at?->toDateTimeString(),
            ])->values()->all(),
        ];
    }

    /** @param array<int, array<string, mixed>> $classes
     * @return array<string, int>
     */
    private function summary(array $classes): array
    {
        $completed = 0;
        $lessons = 0;
        $mastered = 0;
        $competencies = 0;
        $needsRemedial = 0;

        foreach ($classes as $class) {
            $completed += $class['lesson_progress']['completed'];
            $lessons += $class['lesson_progress']['total'];

            foreach ($class['mastery'] as $cell) {
                $competencies++;
                $mastered += $cell['status'] === StudentCompetencyStatus::Mastered->value ? 1 : 0;
                $needsRemedial += $cell['status'] === StudentCompetencyStatus::NeedsRemedial->value ? 1 : 0;
            }
        }

        return [
            'active_classes' => count($classes),
            'lesson_percentage' => $lessons === 0 ? 0 : (int) round(($completed / $lessons) * 100),
            'competencies_mastered' => $mastered,
            'competencies_total' => $competencies,
            'needs_remedial' => $needsRemedial,
        ];
    }

    /** @return array<string, int> */
    private function emptySummary(): array
    {
        return [
            'active_classes' => 0,
            'lesson_percentage' => 0,
            'competencies_mastered' => 0,
            'competencies_total' => 0,
            'needs_remedial' => 0,
        ];
    }
}
