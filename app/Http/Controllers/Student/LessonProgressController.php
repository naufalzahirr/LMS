<?php

namespace App\Http\Controllers\Student;

use App\Enums\LessonProgressStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Student\UpdateLessonProgressRequest;
use App\Models\Enrollment;
use App\Models\LearningClass;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\User;
use App\Services\LessonProgressService;
use App\Services\StudentLearningAccessService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class LessonProgressController extends Controller
{
    public function __construct(
        private readonly StudentLearningAccessService $access,
        private readonly LessonProgressService $progressService,
    ) {}

    public function update(
        UpdateLessonProgressRequest $request,
        LearningClass $learningClass,
        Lesson $lesson,
    ): RedirectResponse {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $enrollment = $this->access->enrollmentForViewing($user, $learningClass);
        abort_unless($enrollment instanceof Enrollment, 403);
        abort_unless($this->access->lessonBelongsToActiveCourse($lesson, $learningClass), 403);
        $this->authorize('mutate', [LessonProgress::class, $enrollment, $lesson]);

        if ($request->status() === LessonProgressStatus::Completed) {
            $this->progressService->complete($user, $enrollment, $lesson);
            $message = __('Lesson marked complete.');
        } else {
            $this->progressService->reopen($user, $enrollment, $lesson);
            $message = __('Lesson reopened.');
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return back();
    }
}
