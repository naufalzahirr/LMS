<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewChildDashboard(User $user): bool
    {
        return $user->hasRole('Parent') && $user->hasPermissionTo('view-child-progress');
    }

    public function viewChildProgress(User $user, User $student): bool
    {
        return $this->viewChildDashboard($user)
            && $student->hasRole('Student')
            && $user->children()->whereKey($student->id)->exists();
    }

    public function viewAny(User $user): bool
    {
        return $this->canManage($user);
    }

    public function view(User $user, User $model): bool
    {
        return $this->canManage($user);
    }

    public function create(User $user): bool
    {
        return $this->canManage($user);
    }

    public function update(User $user, User $model): bool
    {
        return $this->canManage($user);
    }

    public function delete(User $user, User $model): bool
    {
        return $this->canManage($user) && $user->isNot($model);
    }

    private function canManage(User $user): bool
    {
        return $user->hasRole('Admin') && $user->hasPermissionTo('manage-users');
    }
}
