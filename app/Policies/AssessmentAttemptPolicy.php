<?php

namespace App\Policies;

use App\Models\AssessmentAttempt;
use App\Models\User;

class AssessmentAttemptPolicy
{
    public function view(User $user, AssessmentAttempt $attempt): bool
    {
        if ($user->hasRole('Admin')) {
            return true;
        }

        if ($user->hasRole('Student')) {
            return $attempt->enrollment()->where('student_id', $user->id)->exists();
        }

        return $this->tutorAssignedToAttemptClass($user, $attempt);
    }

    public function update(User $user, AssessmentAttempt $attempt): bool
    {
        return $user->hasRole('Student')
            && $attempt->enrollment()->where('student_id', $user->id)->exists();
    }

    public function submit(User $user, AssessmentAttempt $attempt): bool
    {
        return $this->update($user, $attempt);
    }

    public function grade(User $user, AssessmentAttempt $attempt): bool
    {
        return $user->hasRole('Admin') || $this->tutorAssignedToAttemptClass($user, $attempt);
    }

    private function tutorAssignedToAttemptClass(User $user, AssessmentAttempt $attempt): bool
    {
        if (! $user->hasRole('Tutor')) {
            return false;
        }

        $attempt->loadMissing('classAssessment:id,learning_class_id');

        return $user->teachingClasses()->whereKey($attempt->classAssessment->learning_class_id)->exists();
    }
}
