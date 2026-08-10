<?php

namespace App\Services;

use App\Enums\AcademicStatus;
use App\Models\Course;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CourseService
{
    /**
     * @param  array{program_id: int, name: string, slug: string, code: string|null, description: string|null, status: AcademicStatus, sort_order: int}  $data
     */
    public function create(array $data): Course
    {
        return DB::transaction(fn (): Course => Course::query()->create($data));
    }

    /**
     * @param  array{program_id: int, name: string, slug: string, code: string|null, description: string|null, status: AcademicStatus, sort_order: int}  $data
     */
    public function update(Course $course, array $data): Course
    {
        return DB::transaction(function () use ($course, $data): Course {
            if ($course->program_id !== $data['program_id']
                && ($course->competencies()->exists() || $course->learningClasses()->exists() || $course->questionBanks()->exists())) {
                throw ValidationException::withMessages([
                    'program_id' => __('A course with academic or delivery records cannot be moved to another program.'),
                ]);
            }

            $course->update($data);

            return $course->refresh();
        });
    }

    /**
     * @throws ValidationException
     */
    public function delete(Course $course): void
    {
        DB::transaction(function () use ($course): void {
            if ($course->competencies()->exists() || $course->learningClasses()->exists() || $course->questionBanks()->exists()) {
                throw ValidationException::withMessages([
                    'course' => __('This course cannot be deleted while it still has competencies, classes, or question banks.'),
                ]);
            }

            $course->delete();
        });
    }
}
