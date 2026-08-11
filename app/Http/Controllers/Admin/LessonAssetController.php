<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreLessonAssetRequest;
use App\Http\Requests\Admin\UpdateLessonAssetRequest;
use App\Models\Lesson;
use App\Models\LessonAsset;
use App\Services\LessonAssetService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LessonAssetController extends Controller
{
    public function __construct(private readonly LessonAssetService $assets) {}

    public function store(StoreLessonAssetRequest $request, Lesson $lesson): JsonResponse
    {
        $asset = $this->assets->create(
            $lesson,
            $request->assetType(),
            $request->uploadedFile(),
            $request->metadata(),
        );

        return response()->json(['asset' => $this->payload($lesson, $asset)], Response::HTTP_CREATED);
    }

    public function update(
        UpdateLessonAssetRequest $request,
        Lesson $lesson,
        LessonAsset $asset,
    ): JsonResponse {
        $this->ensureBelongsToLesson($lesson, $asset);

        return response()->json([
            'asset' => $this->payload($lesson, $this->assets->update($asset, $request->metadata())),
        ]);
    }

    public function destroy(Request $request, Lesson $lesson, LessonAsset $asset): Response
    {
        $this->authorize('update', $lesson);
        $this->ensureBelongsToLesson($lesson, $asset);
        $this->assets->delete($asset);

        return response()->noContent();
    }

    public function file(Request $request, Lesson $lesson, LessonAsset $asset): StreamedResponse
    {
        $this->authorize('view', $lesson);
        $this->ensureBelongsToLesson($lesson, $asset);

        return $this->assets->response($request, $asset);
    }

    /** @return array<string, mixed> */
    private function payload(Lesson $lesson, LessonAsset $asset): array
    {
        return [
            'id' => $asset->id,
            'asset_type' => $asset->asset_type->value,
            'original_name' => $asset->original_name,
            'mime_type' => $asset->mime_type,
            'file_size' => $asset->file_size,
            'alt_text' => $asset->alt_text,
            'caption' => $asset->caption,
            'url' => route('admin.lesson-assets.file', [$lesson, $asset]),
            'download_url' => route('admin.lesson-assets.file', [$lesson, $asset, 'download' => 1]),
        ];
    }

    private function ensureBelongsToLesson(Lesson $lesson, LessonAsset $asset): void
    {
        abort_unless($asset->lesson_id === $lesson->id, 404);
    }
}
