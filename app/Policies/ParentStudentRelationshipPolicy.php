<?php

namespace App\Policies;

use App\Models\ParentStudentRelationship;
use App\Models\User;

class ParentStudentRelationshipPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->canManage($user);
    }

    public function create(User $user): bool
    {
        return $this->canManage($user);
    }

    public function delete(User $user, ParentStudentRelationship $relationship): bool
    {
        return $this->canManage($user);
    }

    private function canManage(User $user): bool
    {
        return $user->hasRole('Admin') && $user->hasPermissionTo('manage-parent-relationships');
    }
}
