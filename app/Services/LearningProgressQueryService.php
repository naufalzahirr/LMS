<?php

namespace App\Services;

use App\Enums\AcademicStatus;
use App\Enums\LessonProgressStatus;
use App\Models\Enrollment;
use App\Models\LearningClass;
use App\Models\Lesson;
use App\Models\LessonProgress;
use Illuminate\Database\Eloquent\Collection;

class LearningProgressQueryService
{
    public function loadActiveHierarchy(LearningClass $learningClass): LearningClass
    {
        $active = fn ($query) => $query->where('status', AcademicStatus::Active->value);
        $activeLessons = fn ($query) => $query
            ->where('status', AcademicStatus::Active->value)
            ->where('is_authoring_draft', false);

        return $learningClass->load([
            'course.program',
            'course.competencies' => $active,
            'course.competencies.modules' => $active,
            'course.competencies.modules.lessons' => $activeLessons,
        ]);
    }

    /**
     * @param  Collection<int, Enrollment>  $enrollments
     * @return array<int, array{completed_lessons: int, total_lessons: int, percentage: int, continue_lesson_id: int|null}>
     */
    public function summariesForEnrollments(Collection $enrollments): array
    {
        if ($enrollments->isEmpty()) {
            return [];
        }

        $enrollments->loadMissing('learningClass:id,course_id');
        $lessons = $this->activeLessonsForCourseIds(
            $enrollments->pluck('learningClass.course_id')->unique()->values()->all(),
        );
        $progress = LessonProgress::query()
            ->whereIn('enrollment_id', $enrollments->modelKeys())
            ->whereIn('lesson_id', $lessons->modelKeys())
            ->get();
        $lessonsByCourse = $lessons->groupBy(
            fn (Lesson $lesson): int => $lesson->module->competency->course_id,
        );
        $progressByEnrollment = $progress->groupBy('enrollment_id');
        $summaries = [];

        foreach ($enrollments as $enrollment) {
            $courseLessons = $lessonsByCourse->get($enrollment->learningClass->course_id, new Collection);
            $records = $progressByEnrollment->get($enrollment->id, new Collection)->keyBy('lesson_id');
            $completed = $records->filter(
                fn (LessonProgress $record): bool => $record->status === LessonProgressStatus::Completed
                    && $courseLessons->contains('id', $record->lesson_id),
            )->count();
            $total = $courseLessons->count();
            $recentLessonId = null;
            $recentRecords = $records->filter(
                fn (LessonProgress $record): bool => $record->status === LessonProgressStatus::InProgress
                    && $courseLessons->contains('id', $record->lesson_id),
            )->sortByDesc('last_viewed_at');

            foreach ($recentRecords as $record) {
                $recentLessonId = $record->lesson_id;
                break;
            }

            $firstIncompleteLessonId = null;

            foreach ($courseLessons as $courseLesson) {
                if (! $records->has($courseLesson->id)
                    || $records->get($courseLesson->id)->status !== LessonProgressStatus::Completed) {
                    $firstIncompleteLessonId = $courseLesson->id;
                    break;
                }
            }

            $summaries[$enrollment->id] = [
                'completed_lessons' => $completed,
                'total_lessons' => $total,
                'percentage' => $total === 0 ? 0 : (int) round(($completed / $total) * 100),
                'continue_lesson_id' => $recentLessonId ?? $firstIncompleteLessonId,
            ];
        }

        return $summaries;
    }

    /**
     * @param  array<int, int>  $courseIds
     * @return Collection<int, Lesson>
     */
    public function activeLessonsForCourseIds(array $courseIds): Collection
    {
        if ($courseIds === []) {
            return new Collection;
        }

        return Lesson::query()
            ->select('lessons.*')
            ->join('modules', 'modules.id', '=', 'lessons.module_id')
            ->join('competencies', 'competencies.id', '=', 'modules.competency_id')
            ->join('courses', 'courses.id', '=', 'competencies.course_id')
            ->join('programs', 'programs.id', '=', 'courses.program_id')
            ->whereIn('competencies.course_id', $courseIds)
            ->where('lessons.status', AcademicStatus::Active->value)
            ->where('lessons.is_authoring_draft', false)
            ->where('modules.status', AcademicStatus::Active->value)
            ->where('competencies.status', AcademicStatus::Active->value)
            ->where('courses.status', AcademicStatus::Active->value)
            ->where('programs.status', AcademicStatus::Active->value)
            ->whereNull('lessons.deleted_at')
            ->whereNull('modules.deleted_at')
            ->whereNull('competencies.deleted_at')
            ->whereNull('courses.deleted_at')
            ->whereNull('programs.deleted_at')
            ->with(['module:id,competency_id,name,sort_order', 'module.competency:id,course_id,name,sort_order'])
            ->orderBy('competencies.sort_order')
            ->orderBy('competencies.name')
            ->orderBy('modules.sort_order')
            ->orderBy('modules.name')
            ->orderBy('lessons.sort_order')
            ->orderBy('lessons.title')
            ->get();
    }
}
