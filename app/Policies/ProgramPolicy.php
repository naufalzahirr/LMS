<?php

namespace App\Policies;

use App\Models\Program;
use App\Models\User;
use App\Services\TutorCourseAccessService;

class ProgramPolicy
{
    public function __construct(private readonly TutorCourseAccessService $access) {}

    public function viewAny(User $user): bool
    {
        return $this->canManage($user) || $user->hasRole('Tutor');
    }

    public function view(User $user, Program $program): bool
    {
        return $this->canManage($user)
            || ($user->hasRole('Tutor') && $program->courses()->whereIn('id', $this->access->manageableCourseIds($user))->exists());
    }

    public function create(User $user): bool
    {
        return $this->canManage($user);
    }

    public function update(User $user, Program $program): bool
    {
        return $this->canManage($user);
    }

    public function delete(User $user, Program $program): bool
    {
        return $this->canManage($user);
    }

    private function canManage(User $user): bool
    {
        return $user->hasRole('Admin') && $user->hasPermissionTo('manage-programs');
    }
}
