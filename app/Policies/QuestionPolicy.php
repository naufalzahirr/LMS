<?php

namespace App\Policies;

use App\Models\Competency;
use App\Models\Question;
use App\Models\QuestionBank;
use App\Models\User;
use App\Services\TutorCourseAccessService;

class QuestionPolicy
{
    public function __construct(private readonly TutorCourseAccessService $access) {}

    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['Admin', 'Tutor']);
    }

    public function view(User $user, Question $question): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user, ?QuestionBank $bank = null, ?Competency $competency = null): bool
    {
        if ($this->admin($user)) {
            return true;
        }

        if ($bank === null && $competency === null) {
            return $this->access->hasAnyActiveCourse($user);
        }

        if ($bank === null || $competency === null || $bank->course_id !== $competency->course_id) {
            return false;
        }

        return $this->access->canManageCourse($user, $bank->course_id);
    }

    public function update(User $user, Question $question): bool
    {
        return $this->admin($user) || $this->access->canManageQuestion($user, $question);
    }

    public function delete(User $user, Question $question): bool
    {
        return $this->update($user, $question);
    }

    private function admin(User $user): bool
    {
        return $user->hasRole('Admin') && $user->hasPermissionTo('manage-assessments');
    }
}
