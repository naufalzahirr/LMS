<?php

namespace App\Services;

use App\Enums\LessonAssetType;
use App\Models\Lesson;
use App\Models\LessonAsset;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

final class LessonAssetService
{
    public function __construct(private readonly LessonContentService $content) {}

    /** @param array{alt_text?: string|null, caption?: string|null} $metadata */
    public function create(
        Lesson $lesson,
        LessonAssetType $type,
        UploadedFile $file,
        array $metadata,
    ): LessonAsset {
        $path = $file->store("lesson-assets/{$lesson->id}", 'local');

        if (! is_string($path)) {
            throw new RuntimeException('The lesson asset could not be stored.');
        }

        try {
            return LessonAsset::query()->create([
                'lesson_id' => $lesson->id,
                'asset_type' => $type,
                'original_name' => $this->safeOriginalName($file),
                'file_path' => $path,
                'mime_type' => $file->getMimeType() ?: $file->getClientMimeType(),
                'file_size' => max(0, (int) $file->getSize()),
                'alt_text' => $type === LessonAssetType::Image ? ($metadata['alt_text'] ?? null) : null,
                'caption' => $metadata['caption'] ?? null,
            ]);
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($path);

            throw $exception;
        }
    }

    /** @param array{alt_text?: string|null, caption?: string|null} $metadata */
    public function update(LessonAsset $asset, array $metadata): LessonAsset
    {
        $asset->update([
            'alt_text' => $asset->asset_type === LessonAssetType::Image
                ? ($metadata['alt_text'] ?? $asset->alt_text)
                : null,
            'caption' => array_key_exists('caption', $metadata) ? $metadata['caption'] : $asset->caption,
        ]);

        return $asset->refresh();
    }

    public function delete(LessonAsset $asset): void
    {
        $asset->lesson->refresh();
        $references = $this->content->referencedAssetIds($asset->lesson->content_document);

        if (in_array($asset->id, $references, true)) {
            throw ValidationException::withMessages([
                'asset' => __('Remove the asset from the lesson content before deleting it.'),
            ]);
        }

        $path = $asset->managedFilePath();

        DB::transaction(fn () => $asset->delete());

        if ($path !== null) {
            Storage::disk('local')->delete($path);
        }
    }

    public function response(Request $request, LessonAsset $asset): StreamedResponse
    {
        $path = $asset->managedFilePath();
        abort_if($path === null, 404);

        $disk = Storage::disk('local');
        abort_unless($disk->exists($path), 404);

        $headers = [
            'Cache-Control' => 'private, no-store, no-cache, must-revalidate, max-age=0',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
            'X-Content-Type-Options' => 'nosniff',
        ];

        return $request->boolean('download')
            ? $disk->download($path, $asset->original_name, $headers)
            : $disk->response($path, $asset->original_name, $headers);
    }

    private function safeOriginalName(UploadedFile $file): string
    {
        $name = trim(str_replace(["\0", "\r", "\n"], '', basename($file->getClientOriginalName())));

        return mb_substr($name !== '' ? $name : 'lesson-asset', 0, 255);
    }
}
