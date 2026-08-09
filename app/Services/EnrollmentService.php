<?php

namespace App\Services;

use App\Enums\EnrollmentStatus;
use App\Models\Enrollment;
use App\Models\LearningClass;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EnrollmentService
{
    public function enroll(LearningClass $learningClass, User $student): Enrollment
    {
        $this->ensureStudent($student);

        return DB::transaction(function () use ($learningClass, $student): Enrollment {
            $enrollment = Enrollment::query()->firstOrNew([
                'learning_class_id' => $learningClass->id,
                'student_id' => $student->id,
            ]);

            if ($enrollment->exists && $enrollment->status === EnrollmentStatus::Active) {
                throw ValidationException::withMessages([
                    'student_id' => __('This student is already actively enrolled in the class.'),
                ]);
            }

            $enrollment->fill([
                'status' => EnrollmentStatus::Active,
                'enrolled_at' => now(),
                'completed_at' => null,
            ])->save();

            return $enrollment->refresh();
        });
    }

    public function withdraw(LearningClass $learningClass, Enrollment $enrollment): Enrollment
    {
        return $this->changeStatus($learningClass, $enrollment, EnrollmentStatus::Withdrawn);
    }

    public function reactivate(LearningClass $learningClass, Enrollment $enrollment): Enrollment
    {
        return DB::transaction(function () use ($learningClass, $enrollment): Enrollment {
            $this->ensureEnrollmentBelongsToClass($learningClass, $enrollment);

            if ($enrollment->status === EnrollmentStatus::Active) {
                throw ValidationException::withMessages([
                    'enrollment' => __('This enrollment is already active.'),
                ]);
            }

            $enrollment->update([
                'status' => EnrollmentStatus::Active,
                'enrolled_at' => now(),
                'completed_at' => null,
            ]);

            return $enrollment->refresh();
        });
    }

    public function complete(LearningClass $learningClass, Enrollment $enrollment): Enrollment
    {
        return $this->changeStatus($learningClass, $enrollment, EnrollmentStatus::Completed);
    }

    private function changeStatus(
        LearningClass $learningClass,
        Enrollment $enrollment,
        EnrollmentStatus $status,
    ): Enrollment {
        return DB::transaction(function () use ($learningClass, $enrollment, $status): Enrollment {
            $this->ensureEnrollmentBelongsToClass($learningClass, $enrollment);

            if ($enrollment->status === $status) {
                throw ValidationException::withMessages([
                    'enrollment' => __('This enrollment already has the requested status.'),
                ]);
            }

            $enrollment->update([
                'status' => $status,
                'completed_at' => $status === EnrollmentStatus::Completed ? now() : null,
            ]);

            return $enrollment->refresh();
        });
    }

    private function ensureStudent(User $user): void
    {
        if (! $user->hasRole('Student')) {
            throw ValidationException::withMessages([
                'student_id' => __('Only users with the Student role can be enrolled.'),
            ]);
        }
    }

    private function ensureEnrollmentBelongsToClass(LearningClass $learningClass, Enrollment $enrollment): void
    {
        if ($enrollment->learning_class_id !== $learningClass->id) {
            throw ValidationException::withMessages([
                'enrollment' => __('The enrollment does not belong to this class.'),
            ]);
        }
    }
}
