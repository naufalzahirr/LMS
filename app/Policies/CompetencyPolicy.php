<?php

namespace App\Policies;

use App\Models\Competency;
use App\Models\User;
use App\Services\TutorCourseAccessService;

class CompetencyPolicy
{
    public function __construct(private readonly TutorCourseAccessService $access) {}

    public function viewAny(User $user): bool
    {
        return $this->canManage($user) || $user->hasRole('Tutor');
    }

    public function view(User $user, Competency $competency): bool
    {
        return $this->canManage($user) || $this->access->canManageCompetency($user, $competency);
    }

    public function create(User $user): bool
    {
        return $this->canManage($user);
    }

    public function update(User $user, Competency $competency): bool
    {
        return $this->canManage($user);
    }

    public function delete(User $user, Competency $competency): bool
    {
        return $this->canManage($user);
    }

    private function canManage(User $user): bool
    {
        return $user->hasRole('Admin') && $user->hasPermissionTo('manage-competencies');
    }
}
