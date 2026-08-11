<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PreviewLessonRequest;
use App\Models\Lesson;
use App\Models\LessonAsset;
use App\Services\LessonContentService;
use Illuminate\Http\JsonResponse;

class LessonPreviewController extends Controller
{
    public function __construct(private readonly LessonContentService $content) {}

    public function store(PreviewLessonRequest $request, Lesson $lesson): JsonResponse
    {
        return response()->json([
            'content_document' => $this->content->forPreview(
                $lesson,
                $request->document(),
                fn (LessonAsset $asset): array => [
                    'url' => route('admin.lesson-assets.file', [$lesson, $asset]),
                    'downloadUrl' => route('admin.lesson-assets.file', [$lesson, $asset, 'download' => 1]),
                ],
            ),
        ]);
    }
}
