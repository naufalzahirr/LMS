<?php

namespace App\Services;

use App\Enums\AcademicStatus;
use App\Enums\EnrollmentStatus;
use App\Enums\LearningClassStatus;
use App\Models\Enrollment;
use App\Models\LearningClass;
use App\Models\Lesson;
use App\Models\User;

class StudentLearningAccessService
{
    public function enrollmentForViewing(User $user, LearningClass $learningClass): ?Enrollment
    {
        if (! $this->mayViewClass($user, $learningClass)) {
            return null;
        }

        return Enrollment::query()
            ->where('learning_class_id', $learningClass->id)
            ->where('student_id', $user->id)
            ->whereIn('status', [EnrollmentStatus::Active->value, EnrollmentStatus::Completed->value])
            ->first();
    }

    public function mayViewClass(User $user, LearningClass $learningClass): bool
    {
        if (! $user->hasRole('Student')) {
            return false;
        }

        if (! in_array($learningClass->status, [LearningClassStatus::Active, LearningClassStatus::Completed], true)) {
            return false;
        }

        return $learningClass->course()->where('status', AcademicStatus::Active->value)
            ->whereHas('program', fn ($query) => $query->where('status', AcademicStatus::Active->value))
            ->exists();
    }

    public function lessonBelongsToActiveCourse(Lesson $lesson, LearningClass $learningClass): bool
    {
        return Lesson::query()
            ->whereKey($lesson->id)
            ->where('status', AcademicStatus::Active->value)
            ->whereHas('module', function ($query) use ($learningClass): void {
                $query->where('status', AcademicStatus::Active->value)
                    ->whereHas('competency', function ($query) use ($learningClass): void {
                        $query->where('status', AcademicStatus::Active->value)
                            ->where('course_id', $learningClass->course_id)
                            ->whereHas('course', function ($query): void {
                                $query->where('status', AcademicStatus::Active->value)
                                    ->whereHas('program', fn ($query) => $query->where('status', AcademicStatus::Active->value));
                            });
                    });
            })
            ->exists();
    }

    public function mayMutateProgress(
        User $user,
        Enrollment $enrollment,
        Lesson $lesson,
    ): bool {
        $learningClass = $enrollment->learningClass;

        return $enrollment->student_id === $user->id
            && $enrollment->status === EnrollmentStatus::Active
            && $learningClass->status === LearningClassStatus::Active
            && $this->mayViewClass($user, $learningClass)
            && $this->lessonBelongsToActiveCourse($lesson, $learningClass);
    }
}
