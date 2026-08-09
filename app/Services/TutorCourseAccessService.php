<?php

namespace App\Services;

use App\Enums\LearningClassStatus;
use App\Models\Competency;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\User;

class TutorCourseAccessService
{
    /**
     * @return array<int, int>
     */
    public function manageableCourseIds(User $user): array
    {
        if (! $user->hasRole('Tutor')) {
            return [];
        }

        return $user->teachingClasses()
            ->where('learning_classes.status', LearningClassStatus::Active->value)
            ->pluck('learning_classes.course_id')
            ->unique()
            ->values()
            ->map(fn (mixed $courseId): int => (int) $courseId)
            ->all();
    }

    public function hasAnyActiveCourse(User $user): bool
    {
        return $this->manageableCourseIds($user) !== [];
    }

    public function canManageCourse(User $user, Course|int $course): bool
    {
        $courseId = $course instanceof Course ? $course->id : $course;

        return $user->hasRole('Tutor')
            && $user->teachingClasses()
                ->where('learning_classes.course_id', $courseId)
                ->where('learning_classes.status', LearningClassStatus::Active->value)
                ->exists();
    }

    public function canManageCompetency(User $user, Competency $competency): bool
    {
        return $this->canManageCourse($user, $competency->course_id);
    }

    public function canManageModule(User $user, Module $module): bool
    {
        $module->loadMissing('competency:id,course_id');

        return $this->canManageCourse($user, $module->competency->course_id);
    }

    public function canManageLesson(User $user, Lesson $lesson): bool
    {
        $lesson->loadMissing('module.competency:id,course_id');

        return $this->canManageCourse($user, $lesson->module->competency->course_id);
    }
}
