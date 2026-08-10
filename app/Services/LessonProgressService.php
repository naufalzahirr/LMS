<?php

namespace App\Services;

use App\Enums\LessonProgressStatus;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

class LessonProgressService
{
    public function __construct(
        private readonly StudentLearningAccessService $access,
        private readonly StudentCompetencyProgressService $competencyProgress,
    ) {}

    public function view(User $user, Enrollment $enrollment, Lesson $lesson): LessonProgress
    {
        $this->ensureMayMutate($user, $enrollment, $lesson);

        return DB::transaction(function () use ($enrollment, $lesson): LessonProgress {
            $progress = $this->findOrNew($enrollment, $lesson);
            $now = now();

            if (! $progress->exists) {
                $progress->status = LessonProgressStatus::InProgress;
            }

            $progress->started_at ??= $now;
            $progress->last_viewed_at = $now;
            $progress->save();
            $lesson->loadMissing('module.competency');
            $this->competencyProgress->refresh($enrollment, $lesson->module->competency);

            return $progress->refresh();
        });
    }

    public function complete(User $user, Enrollment $enrollment, Lesson $lesson): LessonProgress
    {
        $this->ensureMayMutate($user, $enrollment, $lesson);

        return DB::transaction(function () use ($enrollment, $lesson): LessonProgress {
            $progress = $this->findOrNew($enrollment, $lesson);
            $now = now();
            $progress->status = LessonProgressStatus::Completed;
            $progress->started_at ??= $now;
            $progress->completed_at = $now;
            $progress->last_viewed_at = $now;
            $progress->save();
            $lesson->loadMissing('module.competency');
            $this->competencyProgress->refresh($enrollment, $lesson->module->competency);

            return $progress->refresh();
        });
    }

    public function reopen(User $user, Enrollment $enrollment, Lesson $lesson): LessonProgress
    {
        $this->ensureMayMutate($user, $enrollment, $lesson);

        return DB::transaction(function () use ($enrollment, $lesson): LessonProgress {
            $progress = $this->findOrNew($enrollment, $lesson);
            $now = now();
            $progress->status = LessonProgressStatus::InProgress;
            $progress->started_at ??= $now;
            $progress->completed_at = null;
            $progress->last_viewed_at = $now;
            $progress->save();
            $lesson->loadMissing('module.competency');
            $this->competencyProgress->refresh($enrollment, $lesson->module->competency);

            return $progress->refresh();
        });
    }

    private function findOrNew(Enrollment $enrollment, Lesson $lesson): LessonProgress
    {
        return LessonProgress::query()->firstOrNew([
            'enrollment_id' => $enrollment->id,
            'lesson_id' => $lesson->id,
        ]);
    }

    private function ensureMayMutate(User $user, Enrollment $enrollment, Lesson $lesson): void
    {
        if (! $this->access->mayMutateProgress($user, $enrollment, $lesson)) {
            throw new AuthorizationException;
        }
    }
}
