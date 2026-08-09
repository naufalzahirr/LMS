<?php

namespace App\Policies;

use App\Models\Competency;
use App\Models\User;

class CompetencyPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['Admin', 'Tutor']);
    }

    public function view(User $user, Competency $competency): bool
    {
        return $this->viewAny($user);
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
