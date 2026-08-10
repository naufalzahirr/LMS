<?php

namespace App\Policies;

use App\Models\RemedialAssignment;
use App\Models\User;

class RemedialAssignmentPolicy
{
    public function view(User $user, RemedialAssignment $assignment): bool
    {
        if ($user->hasRole('Admin')) {
            return true;
        }

        if ($user->hasRole('Student')) {
            return $assignment->enrollment()->where('student_id', $user->id)->exists();
        }

        return $this->assignedTutor($user, $assignment);
    }

    public function manage(User $user, RemedialAssignment $assignment): bool
    {
        return $user->hasRole('Admin') || $this->assignedTutor($user, $assignment);
    }

    public function completeLesson(User $user, RemedialAssignment $assignment): bool
    {
        return $user->hasRole('Student')
            && $assignment->enrollment()->where('student_id', $user->id)->exists();
    }

    private function assignedTutor(User $user, RemedialAssignment $assignment): bool
    {
        if (! $user->hasRole('Tutor')) {
            return false;
        }

        $assignment->loadMissing('masteryRule:id,learning_class_id');

        return $user->teachingClasses()->whereKey($assignment->masteryRule->learning_class_id)->exists();
    }
}
