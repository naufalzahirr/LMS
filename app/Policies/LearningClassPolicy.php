<?php

namespace App\Policies;

use App\Models\LearningClass;
use App\Models\User;

class LearningClassPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['Admin', 'Tutor']);
    }

    public function view(User $user, LearningClass $learningClass): bool
    {
        return $this->isAdmin($user)
            || ($user->hasRole('Tutor') && $user->teachingClasses()->whereKey($learningClass->id)->exists());
    }

    public function manage(User $user, ?LearningClass $learningClass = null): bool
    {
        return $this->isAdmin($user) && $user->hasPermissionTo('manage-classes');
    }

    public function create(User $user): bool
    {
        return $this->manage($user);
    }

    public function update(User $user, LearningClass $learningClass): bool
    {
        return $this->manage($user);
    }

    public function delete(User $user, LearningClass $learningClass): bool
    {
        return $this->manage($user);
    }

    public function manageEnrollments(User $user, LearningClass $learningClass): bool
    {
        return $this->isAdmin($user) && $user->hasPermissionTo('manage-enrollments');
    }

    public function manageTutors(User $user, LearningClass $learningClass): bool
    {
        return $this->isAdmin($user) && $user->hasPermissionTo('manage-tutor-assignments');
    }

    private function isAdmin(User $user): bool
    {
        return $user->hasRole('Admin');
    }
}
