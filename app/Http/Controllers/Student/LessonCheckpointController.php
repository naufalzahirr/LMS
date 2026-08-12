<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Http\Requests\Student\SubmitLessonCheckpointRequest;
use App\Models\Enrollment;
use App\Models\LearningClass;
use App\Models\Lesson;
use App\Models\LessonCheckpoint;
use App\Models\User;
use App\Services\CompetencyAccessService;
use App\Services\LessonCheckpointService;
use App\Services\LessonContentService;
use App\Services\StudentLearningAccessService;
use Illuminate\Http\JsonResponse;

class LessonCheckpointController extends Controller
{
    public function __construct(
        private readonly StudentLearningAccessService $access,
        private readonly CompetencyAccessService $competencyAccess,
        private readonly LessonContentService $content,
        private readonly LessonCheckpointService $checkpoints,
    ) {}

    public function store(
        SubmitLessonCheckpointRequest $request,
        LearningClass $learningClass,
        Lesson $lesson,
        LessonCheckpoint $checkpoint,
    ): JsonResponse {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $enrollment = $this->access->enrollmentForViewing($user, $learningClass);
        abort_unless($enrollment instanceof Enrollment, 403);
        abort_unless($this->access->lessonBelongsToActiveCourse($lesson, $learningClass), 403);
        abort_unless($this->competencyAccess->mayOpenLesson($enrollment, $lesson), 403);
        abort_unless($this->access->mayMutateProgress($user, $enrollment, $lesson), 403);
        abort_unless($checkpoint->lesson_id === $lesson->id, 404);
        abort_unless(in_array(
            $checkpoint->id,
            $this->content->referencedCheckpointIds($lesson->content_document),
            true,
        ), 404);

        return response()->json(
            $this->checkpoints->submit($checkpoint, $enrollment, $request->answer()),
        );
    }
}
