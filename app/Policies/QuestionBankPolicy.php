<?php

namespace App\Policies;

use App\Models\Course;
use App\Models\QuestionBank;
use App\Models\User;
use App\Services\TutorCourseAccessService;

class QuestionBankPolicy
{
    public function __construct(private readonly TutorCourseAccessService $access) {}

    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['Admin', 'Tutor']);
    }

    public function view(User $user, QuestionBank $questionBank): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user, ?Course $course = null): bool
    {
        return $this->admin($user) || ($course === null ? $this->access->hasAnyActiveCourse($user) : $this->access->canManageCourse($user, $course));
    }

    public function update(User $user, QuestionBank $questionBank): bool
    {
        return $this->admin($user) || $this->access->canManageQuestionBank($user, $questionBank);
    }

    public function delete(User $user, QuestionBank $questionBank): bool
    {
        return $this->update($user, $questionBank);
    }

    private function admin(User $user): bool
    {
        return $user->hasRole('Admin') && $user->hasPermissionTo('manage-assessments');
    }
}
