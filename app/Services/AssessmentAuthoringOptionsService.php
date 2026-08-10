<?php

namespace App\Services;

use App\Models\Competency;
use App\Models\Course;
use App\Models\Program;
use App\Models\QuestionBank;
use App\Models\User;

class AssessmentAuthoringOptionsService
{
    public function __construct(private readonly TutorCourseAccessService $tutorAccess) {}

    /** @return array<string, array<int, array<string, mixed>>> */
    public function forUser(User $user, bool $manageableOnly = true): array
    {
        $courseIds = $user->hasRole('Admin') || ! $manageableOnly
            ? null
            : $this->tutorAccess->manageableCourseIds($user);
        $programQuery = Program::query()->orderBy('name');
        $courseQuery = Course::query()->with('program:id,name')->orderBy('name');
        $competencyQuery = Competency::query()->orderBy('name');
        $bankQuery = QuestionBank::query()->orderBy('name');

        if ($courseIds !== null) {
            $programQuery->whereHas('courses', fn ($query) => $query->whereIn('id', $courseIds));
            $courseQuery->whereIn('id', $courseIds);
            $competencyQuery->whereIn('course_id', $courseIds);
            $bankQuery->whereIn('course_id', $courseIds);
        }

        return [
            'programs' => $programQuery->get(['id', 'name'])->map(
                fn (Program $program): array => ['id' => $program->id, 'name' => $program->name],
            )->all(),
            'courses' => $courseQuery->get(['id', 'program_id', 'name'])->map(
                fn (Course $course): array => [
                    'id' => $course->id,
                    'program_id' => $course->program_id,
                    'name' => $course->name,
                    'program' => $course->program->name,
                ],
            )->all(),
            'competencies' => $competencyQuery->get(['id', 'course_id', 'code', 'name'])->map(
                fn (Competency $competency): array => [
                    'id' => $competency->id,
                    'course_id' => $competency->course_id,
                    'code' => $competency->code,
                    'name' => $competency->name,
                ],
            )->all(),
            'questionBanks' => $bankQuery->get(['id', 'course_id', 'name'])->map(
                fn (QuestionBank $bank): array => [
                    'id' => $bank->id,
                    'course_id' => $bank->course_id,
                    'name' => $bank->name,
                ],
            )->all(),
        ];
    }
}
