<?php

namespace App\Services;

use App\Enums\AcademicStatus;
use App\Models\Lesson;
use App\Models\RemedialAssignment;
use App\Models\RemedialAssignmentLesson;

class RemedialAssignmentPayloadService
{
    public function __construct(private readonly CompetencyAccessService $competencyAccess) {}

    /** @return array<string, mixed> */
    public function management(RemedialAssignment $assignment, string $routePrefix): array
    {
        $assignment->loadMissing([
            'enrollment.student:id,name,email',
            'competency:id,name',
            'masteryRule:id,learning_class_id,mastery_score',
            'sourceAttempt:id,percentage,attempt_number',
            'lessons.lesson.module:id,competency_id,name,status',
        ]);
        $available = Lesson::query()
            ->where('status', AcademicStatus::Active->value)
            ->whereHas('module', fn ($query) => $query
                ->where('competency_id', $assignment->competency_id)
                ->where('status', AcademicStatus::Active->value))
            ->with('module:id,name,competency_id')
            ->orderBy('module_id')
            ->orderBy('sort_order')
            ->get();

        return [
            'assignment' => $this->summary($assignment),
            'lessons' => $assignment->lessons->map(fn (RemedialAssignmentLesson $item): array => [
                'id' => $item->id,
                'lesson_id' => $item->lesson_id,
                'title' => $item->lesson->title,
                'module' => $item->lesson->module->name,
                'completed_at' => $item->completed_at?->toDateTimeString(),
                'remove_url' => route("{$routePrefix}.remedial-lessons.destroy", [$assignment, $item]),
            ])->all(),
            'lessonOptions' => $available->map(fn (Lesson $lesson): array => [
                'id' => $lesson->id,
                'title' => $lesson->title,
                'module' => $lesson->module->name,
            ])->all(),
            'lessonStoreUrl' => route("{$routePrefix}.remedial-lessons.store", $assignment),
            'updateUrl' => route("{$routePrefix}.remedials.update", $assignment),
            'completeUrl' => route("{$routePrefix}.remedials.complete", $assignment),
            'backUrl' => route("{$routePrefix}.classes.show", $assignment->masteryRule->learning_class_id),
        ];
    }

    /** @return array<string, mixed> */
    public function student(RemedialAssignment $assignment): array
    {
        $assignment->loadMissing([
            'enrollment.learningClass:id,course_id',
            'competency:id,name',
            'masteryRule:id,learning_class_id,mastery_score',
            'sourceAttempt:id,percentage,attempt_number',
            'lessons.lesson.module:id,competency_id,name,status',
        ]);

        return [
            ...$this->summary($assignment),
            'class_url' => route('student.classes.show', $assignment->masteryRule->learning_class_id),
            'lessons' => $assignment->lessons->map(fn (RemedialAssignmentLesson $item): array => [
                'id' => $item->id,
                'title' => $item->lesson->title,
                'module' => $item->lesson->module->name,
                'completed_at' => $item->completed_at?->toDateTimeString(),
                'lesson_url' => $item->lesson->trashed()
                    || $item->lesson->status !== AcademicStatus::Active
                    || $item->lesson->module->status !== AcademicStatus::Active
                    || ! $this->competencyAccess->mayOpenLesson($assignment->enrollment, $item->lesson)
                    ? null
                    : route('student.lessons.show', [$assignment->masteryRule->learning_class_id, $item->lesson]),
                'complete_url' => route('student.remedial-lessons.complete', [$assignment, $item]),
            ])->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function summary(RemedialAssignment $assignment): array
    {
        return [
            'id' => $assignment->id,
            'student' => $assignment->relationLoaded('enrollment') ? $assignment->enrollment->student->name : null,
            'email' => $assignment->relationLoaded('enrollment') ? $assignment->enrollment->student->email : null,
            'competency' => $assignment->competency->name,
            'status' => $assignment->status->value,
            'latest_score' => $assignment->sourceAttempt->percentage,
            'required_score' => $assignment->masteryRule->mastery_score,
            'attempt_number' => $assignment->sourceAttempt->attempt_number,
            'assigned_at' => $assignment->assigned_at->toDateTimeString(),
            'completed_at' => $assignment->completed_at?->toDateTimeString(),
            'notes' => $assignment->notes,
        ];
    }
}
