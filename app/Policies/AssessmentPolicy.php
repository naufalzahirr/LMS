<?php

namespace App\Policies;

use App\Models\Assessment;
use App\Models\Competency;
use App\Models\User;
use App\Services\TutorCourseAccessService;

class AssessmentPolicy
{
    public function __construct(private readonly TutorCourseAccessService $access) {}

    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['Admin', 'Tutor']);
    }

    public function view(User $user, Assessment $assessment): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user, ?Competency $competency = null): bool
    {
        return $this->admin($user) || ($competency === null ? $this->access->hasAnyActiveCourse($user) : $this->access->canManageCompetency($user, $competency));
    }

    public function update(User $user, Assessment $assessment): bool
    {
        return $this->admin($user) || $this->access->canManageAssessment($user, $assessment);
    }

    public function delete(User $user, Assessment $assessment): bool
    {
        return $this->update($user, $assessment);
    }

    private function admin(User $user): bool
    {
        return $user->hasRole('Admin') && $user->hasPermissionTo('manage-assessments');
    }
}
