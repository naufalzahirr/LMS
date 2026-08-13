<?php

namespace App\Listeners\Concerns;

use App\Models\LearningClass;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

trait FiltersAuthorizedTutors
{
    /**
     * Tutors assigned to the class who are actually authorized to review it —
     * the same predicate AssessmentAttemptPolicy::grade() / LearningClassAssessmentPolicy::reviewAttempts() use.
     *
     * @return Collection<int, User>
     */
    private function authorizedTutors(LearningClass $learningClass): Collection
    {
        return $learningClass->tutors->filter(
            fn (User $tutor): bool => $tutor->hasRole('Tutor') && $tutor->hasPermissionTo('view-class-progress'),
        );
    }
}
