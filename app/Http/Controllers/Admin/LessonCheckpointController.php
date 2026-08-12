<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreLessonCheckpointRequest;
use App\Http\Requests\Admin\UpdateLessonCheckpointRequest;
use App\Models\Lesson;
use App\Models\LessonCheckpoint;
use App\Models\User;
use App\Services\LessonCheckpointService;
use App\Services\LessonDraftService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class LessonCheckpointController extends Controller
{
    public function __construct(
        private readonly LessonCheckpointService $checkpoints,
        private readonly LessonDraftService $drafts,
    ) {}

    public function store(StoreLessonCheckpointRequest $request, Lesson $lesson): JsonResponse
    {
        $author = $request->user();
        abort_unless($author instanceof User, 401);
        $checkpoint = $this->checkpoints->create($lesson, $author, $request->payload());
        $this->drafts->extend($lesson);

        return response()->json([
            'checkpoint' => $this->checkpoints->authorPayload($checkpoint),
        ], Response::HTTP_CREATED);
    }

    public function update(
        UpdateLessonCheckpointRequest $request,
        Lesson $lesson,
        LessonCheckpoint $checkpoint,
    ): JsonResponse {
        abort_unless($checkpoint->lesson_id === $lesson->id, 404);
        $updated = $this->checkpoints->update($checkpoint, $request->payload());
        $this->drafts->extend($lesson);

        return response()->json([
            'checkpoint' => $this->checkpoints->authorPayload($updated),
        ]);
    }
}
