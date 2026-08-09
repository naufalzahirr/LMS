<?php

namespace App\Policies;

use App\Models\Competency;
use App\Models\Module;
use App\Models\User;
use App\Services\TutorCourseAccessService;

class ModulePolicy
{
    public function __construct(private readonly TutorCourseAccessService $tutorCourseAccess) {}

    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['Admin', 'Tutor']);
    }

    public function view(User $user, Module $module): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user, ?Competency $competency = null): bool
    {
        if ($this->canAdminManage($user)) {
            return true;
        }

        return $competency === null
            ? $this->tutorCourseAccess->hasAnyActiveCourse($user)
            : $this->tutorCourseAccess->canManageCompetency($user, $competency);
    }

    public function update(User $user, Module $module): bool
    {
        return $this->canAdminManage($user) || $this->tutorCourseAccess->canManageModule($user, $module);
    }

    public function delete(User $user, Module $module): bool
    {
        return $this->canAdminManage($user) || $this->tutorCourseAccess->canManageModule($user, $module);
    }

    private function canAdminManage(User $user): bool
    {
        return $user->hasRole('Admin') && $user->hasPermissionTo('manage-modules');
    }
}
