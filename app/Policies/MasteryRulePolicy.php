<?php

namespace App\Policies;

use App\Models\LearningClass;
use App\Models\MasteryRule;
use App\Models\User;

class MasteryRulePolicy
{
    public function view(User $user, MasteryRule $rule): bool
    {
        return $user->hasRole('Admin')
            || ($user->hasRole('Tutor') && $user->teachingClasses()->whereKey($rule->learning_class_id)->exists());
    }

    public function manage(User $user, LearningClass $learningClass): bool
    {
        return $user->hasRole('Admin')
            && $user->hasPermissionTo('manage-classes')
            && $user->hasPermissionTo('manage-assessments');
    }
}
