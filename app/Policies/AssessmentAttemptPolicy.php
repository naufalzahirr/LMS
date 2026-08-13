<?php

namespace App\Policies;

use App\Models\AssessmentAttempt;
use App\Models\User;

class AssessmentAttemptPolicy
{
    public function view(User $user, AssessmentAttempt $attempt): bool
    {
        return $user->hasRole('Student')
            && $attempt->enrollment()->where('student_id', $user->id)->exists();
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
        return ($user->hasRole('Admin') && $user->hasPermissionTo('manage-assessments'))
            || $this->tutorAssignedToAttemptClass($user, $attempt);
    }

    private function tutorAssignedToAttemptClass(User $user, AssessmentAttempt $attempt): bool
    {
        if (! $user->hasRole('Tutor') || ! $user->hasPermissionTo('view-class-progress')) {
            return false;
        }

        // Column-limited on purpose, but must include every column any later
        // loadMissing() on this same $attempt instance will need — once
        // Eloquent marks classAssessment as loaded, a later constrained
        // nested loadMissing (e.g. AssessmentAttemptReviewQueryService's
        // classAssessment.assessment) reuses this instance rather than
        // re-fetching it, so a missing column here silently breaks that
        // later relation instead of raising an error.
        $attempt->loadMissing('classAssessment:id,learning_class_id,assessment_id');

        return $user->teachingClasses()->whereKey($attempt->classAssessment->learning_class_id)->exists();
    }
}
