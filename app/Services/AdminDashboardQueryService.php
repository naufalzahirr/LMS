<?php

namespace App\Services;

use App\Enums\AcademicStatus;
use App\Enums\AssessmentStatus;
use App\Enums\EnrollmentStatus;
use App\Enums\LearningClassStatus;
use App\Enums\StudentCompetencyStatus;
use App\Models\Assessment;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\LearningClass;
use App\Models\Lesson;
use App\Models\Program;
use App\Models\StudentCompetencyProgress;
use App\Models\User;

class AdminDashboardQueryService
{
    private const MAX_ATTENTION_ITEMS = 5;

    /** @return array<string, mixed> */
    public function forAdmin(): array
    {
        return [
            'overview' => $this->overview(),
            'needs_attention' => [
                'classes_without_tutor' => $this->classesMissing('tutors'),
                'classes_without_students' => $this->classesMissing('enrollments'),
            ],
            'content' => $this->content(),
            'learning_status' => $this->learningStatus(),
            'quick_actions' => $this->quickActions(),
        ];
    }

    /** @return array<string, int> */
    private function overview(): array
    {
        return [
            'active_classes' => LearningClass::query()->where('status', LearningClassStatus::Active->value)->count(),
            'active_students' => $this->distinctActiveStudentCount(),
            'tutors_with_assignments' => User::role('Tutor')
                ->whereHas('teachingClasses', fn ($query) => $query->where('learning_classes.status', LearningClassStatus::Active->value))
                ->count(),
            'active_courses' => Course::query()->where('status', AcademicStatus::Active->value)->count(),
            'active_programs' => Program::query()->where('status', AcademicStatus::Active->value)->count(),
        ];
    }

    /**
     * Distinct students with an active enrollment in an active Learning Class.
     * A student enrolled in multiple active classes counts once.
     */
    private function distinctActiveStudentCount(): int
    {
        return Enrollment::query()
            ->where('status', EnrollmentStatus::Active->value)
            ->whereHas('learningClass', fn ($query) => $query->where('status', LearningClassStatus::Active->value))
            ->distinct('student_id')
            ->count('student_id');
    }

    /** @return array<string, mixed> */
    private function classesMissing(string $relation): array
    {
        $query = LearningClass::query()
            ->where('status', LearningClassStatus::Active->value)
            ->whereDoesntHave($relation);

        return [
            'items' => (clone $query)
                ->orderBy('name')
                ->limit(self::MAX_ATTENTION_ITEMS)
                ->get(['id', 'name'])
                ->map(fn (LearningClass $learningClass): array => [
                    'id' => $learningClass->id,
                    'name' => $learningClass->name,
                    'url' => route('admin.classes.show', $learningClass),
                ])->values()->all(),
            'total' => (clone $query)->count(),
        ];
    }

    /** @return array<string, int> */
    private function content(): array
    {
        return [
            'programs' => Program::query()->where('status', AcademicStatus::Active->value)->count(),
            'courses' => Course::query()->where('status', AcademicStatus::Active->value)->count(),
            'lessons' => Lesson::query()->where('status', AcademicStatus::Active->value)->count(),
            'assessments' => Assessment::query()->where('status', AssessmentStatus::Published->value)->count(),
        ];
    }

    /** @return array<string, int> */
    private function learningStatus(): array
    {
        return [
            'students_currently_learning' => $this->distinctActiveStudentCount(),
            'competencies_mastered' => StudentCompetencyProgress::query()
                ->where('status', StudentCompetencyStatus::Mastered->value)
                ->count(),
            'students_needing_remedial' => StudentCompetencyProgress::query()
                ->join('enrollments', 'enrollments.id', '=', 'student_competency_progress.enrollment_id')
                ->where('student_competency_progress.status', StudentCompetencyStatus::NeedsRemedial->value)
                ->distinct('enrollments.student_id')
                ->count('enrollments.student_id'),
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function quickActions(): array
    {
        return [
            ['label' => 'Create learning class', 'url' => route('admin.classes.create')],
            ['label' => 'Manage users', 'url' => route('admin.users.index')],
            ['label' => 'Create course', 'url' => route('admin.courses.create')],
            ['label' => 'View progress reports', 'url' => route('admin.reports.progress.index')],
        ];
    }
}
