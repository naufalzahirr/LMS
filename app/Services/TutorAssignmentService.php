<?php

namespace App\Services;

use App\Models\LearningClass;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TutorAssignmentService
{
    public function assign(LearningClass $learningClass, User $tutor): void
    {
        if (! $tutor->hasRole('Tutor')) {
            throw ValidationException::withMessages([
                'tutor_id' => __('Only users with the Tutor role can be assigned.'),
            ]);
        }

        DB::transaction(function () use ($learningClass, $tutor): void {
            if ($learningClass->tutors()->whereKey($tutor->id)->exists()) {
                throw ValidationException::withMessages([
                    'tutor_id' => __('This tutor is already assigned to the class.'),
                ]);
            }

            $learningClass->tutors()->attach($tutor->id);
        });
    }

    public function unassign(LearningClass $learningClass, User $tutor): void
    {
        DB::transaction(function () use ($learningClass, $tutor): void {
            if (! $learningClass->tutors()->whereKey($tutor->id)->exists()) {
                throw ValidationException::withMessages([
                    'tutor_id' => __('This tutor is not assigned to the class.'),
                ]);
            }

            $learningClass->tutors()->detach($tutor->id);
        });
    }
}
