<?php

namespace App\Policies;

use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\User;
use App\Services\StudentLearningAccessService;

class LessonProgressPolicy
{
    public function __construct(private readonly StudentLearningAccessService $access) {}

    public function viewAny(User $user): bool
    {
        return ($user->hasRole('Admin') && $user->hasPermissionTo('view-all-progress'))
            || ($user->hasRole('Tutor') && $user->hasPermissionTo('view-class-progress'));
    }

    public function view(User $user, LessonProgress $progress): bool
    {
        if ($user->hasRole('Admin') && $user->hasPermissionTo('view-all-progress')) {
            return true;
        }

        $enrollment = $progress->enrollment;

        if ($user->hasRole('Tutor') && $user->hasPermissionTo('view-class-progress')) {
            return $user->teachingClasses()->whereKey($enrollment->learning_class_id)->exists();
        }

        return $enrollment->student_id === $user->id
            && $this->access->enrollmentForViewing($user, $enrollment->learningClass)?->is($enrollment) === true;
    }

    public function mutate(User $user, Enrollment $enrollment, Lesson $lesson): bool
    {
        return $this->access->mayMutateProgress($user, $enrollment, $lesson);
    }
}
