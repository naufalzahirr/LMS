<?php

namespace App\Policies;

use App\Models\Lesson;
use App\Models\Module;
use App\Models\User;
use App\Services\TutorCourseAccessService;

class LessonPolicy
{
    public function __construct(private readonly TutorCourseAccessService $tutorCourseAccess) {}

    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['Admin', 'Tutor']);
    }

    public function view(User $user, Lesson $lesson): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user, ?Module $module = null): bool
    {
        if ($this->canAdminManage($user)) {
            return true;
        }

        return $module === null
            ? $this->tutorCourseAccess->hasAnyActiveCourse($user)
            : $this->tutorCourseAccess->canManageModule($user, $module);
    }

    public function update(User $user, Lesson $lesson): bool
    {
        return $this->canAdminManage($user) || $this->tutorCourseAccess->canManageLesson($user, $lesson);
    }

    public function delete(User $user, Lesson $lesson): bool
    {
        return $this->canAdminManage($user) || $this->tutorCourseAccess->canManageLesson($user, $lesson);
    }

    private function canAdminManage(User $user): bool
    {
        return $user->hasRole('Admin') && $user->hasPermissionTo('manage-lessons');
    }
}
