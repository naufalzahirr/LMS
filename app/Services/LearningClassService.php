<?php

namespace App\Services;

use App\Enums\LearningClassStatus;
use App\Models\LearningClass;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LearningClassService
{
    /**
     * @param  array{course_id: int, name: string, code: string, description: string|null, start_date: string|null, end_date: string|null, status: LearningClassStatus}  $data
     */
    public function create(array $data): LearningClass
    {
        return DB::transaction(fn (): LearningClass => LearningClass::query()->create($data));
    }

    /**
     * @param  array{course_id: int, name: string, code: string, description: string|null, start_date: string|null, end_date: string|null, status: LearningClassStatus}  $data
     */
    public function update(LearningClass $learningClass, array $data): LearningClass
    {
        return DB::transaction(function () use ($learningClass, $data): LearningClass {
            $learningClass->update($data);

            return $learningClass->refresh();
        });
    }

    public function delete(LearningClass $learningClass): void
    {
        DB::transaction(function () use ($learningClass): void {
            if ($learningClass->enrollments()->exists()) {
                throw ValidationException::withMessages([
                    'learning_class' => __('This class cannot be deleted because it has enrollment history.'),
                ]);
            }

            if ($learningClass->tutors()->exists()) {
                throw ValidationException::withMessages([
                    'learning_class' => __('This class cannot be deleted while tutors are assigned.'),
                ]);
            }

            if ($learningClass->assessmentAssignments()->exists()) {
                throw ValidationException::withMessages([
                    'learning_class' => __('This class cannot be deleted while assessments are assigned.'),
                ]);
            }

            $learningClass->delete();
        });
    }
}
