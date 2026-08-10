<?php

namespace App\Policies;

use App\Models\Course;
use App\Models\User;
use App\Services\TutorCourseAccessService;

class CoursePolicy
{
    public function __construct(private readonly TutorCourseAccessService $access) {}

    public function viewAny(User $user): bool
    {
        return $this->canManage($user) || $user->hasRole('Tutor');
    }

    public function view(User $user, Course $course): bool
    {
        return $this->canManage($user) || $this->access->canManageCourse($user, $course);
    }

    public function create(User $user): bool
    {
        return $this->canManage($user);
    }

    public function update(User $user, Course $course): bool
    {
        return $this->canManage($user);
    }

    public function delete(User $user, Course $course): bool
    {
        return $this->canManage($user);
    }

    private function canManage(User $user): bool
    {
        return $user->hasRole('Admin') && $user->hasPermissionTo('manage-courses');
    }
}
