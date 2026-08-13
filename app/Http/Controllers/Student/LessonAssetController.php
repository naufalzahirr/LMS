<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Enrollment;
use App\Models\LearningClass;
use App\Models\Lesson;
use App\Models\LessonAsset;
use App\Models\User;
use App\Services\CompetencyAccessService;
use App\Services\LessonAssetService;
use App\Services\LessonContentService;
use App\Services\StudentLearningAccessService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LessonAssetController extends Controller
{
    public function __construct(
        private readonly StudentLearningAccessService $access,
        private readonly CompetencyAccessService $competencyAccess,
        private readonly LessonAssetService $assets,
        private readonly LessonContentService $content,
    ) {}

    public function file(
        Request $request,
        LearningClass $learningClass,
        Lesson $lesson,
        LessonAsset $asset,
    ): StreamedResponse {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $enrollment = $this->access->enrollmentForViewing($user, $learningClass);
        abort_unless($enrollment instanceof Enrollment, 403);
        abort_unless($this->access->lessonBelongsToActiveCourse($lesson, $learningClass), 403);
        $lesson->load('module.competency');
        abort_unless($this->competencyAccess->mayOpenLesson($enrollment, $lesson), 403);
        abort_unless($asset->lesson_id === $lesson->id, 404);
        abort_unless(in_array(
            $asset->id,
            $this->content->referencedAssetIds($lesson->content_document),
            true,
        ), 404);

        return $this->assets->response($request, $asset);
    }
}
