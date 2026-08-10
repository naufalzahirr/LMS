<?php

namespace App\Policies;

use App\Enums\EnrollmentStatus;
use App\Models\LearningClass;
use App\Models\LearningClassAssessment;
use App\Models\User;

class LearningClassAssessmentPolicy
{
    public function view(User $user, LearningClassAssessment $assignment): bool
    {
        return $user->hasRole('Student') && $user->enrollments()
            ->where('learning_class_id', $assignment->learning_class_id)
            ->whereIn('status', [EnrollmentStatus::Active->value, EnrollmentStatus::Completed->value])
            ->exists();
    }

    public function reviewAttempts(User $user, LearningClassAssessment $assignment): bool
    {
        return $this->admin($user)
            || ($user->hasRole('Tutor')
                && $user->hasPermissionTo('view-class-progress')
                && $user->teachingClasses()->whereKey($assignment->learning_class_id)->exists());
    }

    public function start(User $user, LearningClassAssessment $assignment): bool
    {
        return $user->hasRole('Student') && $user->enrollments()
            ->where('learning_class_id', $assignment->learning_class_id)
            ->exists();
    }

    public function create(User $user, LearningClass $learningClass): bool
    {
        return $this->admin($user);
    }

    public function update(User $user, LearningClassAssessment $assignment): bool
    {
        return $this->admin($user);
    }

    public function delete(User $user, LearningClassAssessment $assignment): bool
    {
        return $this->admin($user);
    }

    private function admin(User $user): bool
    {
        return $user->hasRole('Admin')
            && $user->hasPermissionTo('manage-classes')
            && $user->hasPermissionTo('manage-assessments');
    }
}
