<?php

namespace App\Services;

use App\Enums\AcademicStatus;
use App\Enums\LessonProgressStatus;
use App\Enums\RemedialAssignmentStatus;
use App\Enums\StudentCompetencyStatus;
use App\Models\Competency;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\StudentCompetencyProgress;
use Illuminate\Support\Facades\DB;

class StudentCompetencyProgressService
{
    public function refresh(Enrollment $enrollment, Competency $competency): StudentCompetencyProgress
    {
        return DB::transaction(function () use ($enrollment, $competency): StudentCompetencyProgress {
            $progress = StudentCompetencyProgress::query()->firstOrNew([
                'enrollment_id' => $enrollment->id,
                'competency_id' => $competency->id,
            ]);

            if ($progress->exists && $progress->status === StudentCompetencyStatus::Mastered) {
                return $progress;
            }

            $hasOpenRemedial = $enrollment->remedialAssignments()
                ->where('competency_id', $competency->id)
                ->where('status', RemedialAssignmentStatus::Assigned->value)
                ->exists();

            if ($hasOpenRemedial) {
                $progress->status = StudentCompetencyStatus::NeedsRemedial;
            } else {
                $lessonIds = $this->activeLessonIds($competency);
                $completed = $lessonIds === [] ? 0 : LessonProgress::query()
                    ->where('enrollment_id', $enrollment->id)
                    ->whereIn('lesson_id', $lessonIds)
                    ->where('status', LessonProgressStatus::Completed->value)
                    ->count();
                $progress->status = $lessonIds !== [] && $completed === count($lessonIds)
                    ? StudentCompetencyStatus::ReadyForAssessment
                    : StudentCompetencyStatus::Learning;
            }

            $progress->started_at ??= now();
            $progress->save();

            return $progress->refresh();
        });
    }

    /** @return array<int, int> */
    public function activeLessonIds(Competency $competency): array
    {
        return Lesson::query()
            ->where('status', AcademicStatus::Active->value)
            ->whereHas('module', fn ($query) => $query
                ->where('competency_id', $competency->id)
                ->where('status', AcademicStatus::Active->value))
            ->pluck('id')
            ->all();
    }
}
