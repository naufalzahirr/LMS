<?php

namespace App\Policies;

use App\Models\RemedialAssignment;
use App\Models\User;

class RemedialAssignmentPolicy
{
    public function view(User $user, RemedialAssignment $assignment): bool
    {
        return $user->hasRole('Student')
            && $assignment->enrollment()->where('student_id', $user->id)->exists();
    }

    public function manage(User $user, RemedialAssignment $assignment): bool
    {
        return ($user->hasRole('Admin')
                && $user->hasPermissionTo('manage-assessments')
                && $user->hasPermissionTo('manage-classes'))
            || $this->assignedTutor($user, $assignment);
    }

    public function completeLesson(User $user, RemedialAssignment $assignment): bool
    {
        return $user->hasRole('Student')
            && $assignment->enrollment()->where('student_id', $user->id)->exists();
    }

    private function assignedTutor(User $user, RemedialAssignment $assignment): bool
    {
        if (! $user->hasRole('Tutor') || ! $user->hasPermissionTo('view-class-progress')) {
            return false;
        }

        $assignment->loadMissing('masteryRule:id,learning_class_id');

        return $user->teachingClasses()->whereKey($assignment->masteryRule->learning_class_id)->exists();
    }
}
