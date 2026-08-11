<?php

namespace App\Services;

use App\Enums\LessonAssetType;
use App\Enums\LessonType;
use App\Models\Lesson;
use App\Models\LessonAsset;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

final class LessonContentMigrationService
{
    public function __construct(
        private readonly LessonContentService $content,
        private readonly LessonVideoEmbedService $videoEmbed,
    ) {}

    public function migrateAll(): void
    {
        Lesson::withTrashed()
            ->whereNull('content_document')
            ->orderBy('id')
            ->chunkById(100, function ($lessons): void {
                foreach ($lessons as $lesson) {
                    $this->migrateLesson($lesson);
                }
            });
    }

    public function migrateLesson(Lesson $lesson): Lesson
    {
        return DB::transaction(function () use ($lesson): Lesson {
            $locked = Lesson::withTrashed()->lockForUpdate()->findOrFail($lesson->id);

            if ($locked->content_document !== null) {
                return $locked;
            }

            $document = $this->legacyDocument($locked);
            $locked->forceFill([
                'content_document' => $this->content->normalize($locked, $document),
            ])->save();

            return $locked->refresh();
        });
    }

    /** @return array<string, mixed> */
    public function legacyDocument(Lesson $lesson): array
    {
        $blocks = $this->paragraphs($lesson->content);

        match ($lesson->lesson_type) {
            LessonType::Video => $this->appendVideoOrLink($blocks, $lesson),
            LessonType::Link => $this->appendLink($blocks, $lesson->external_url, 'Open learning resource'),
            LessonType::Image => $this->appendAsset($blocks, $lesson, LessonAssetType::Image),
            LessonType::Document => $this->appendAsset($blocks, $lesson, LessonAssetType::Document),
            LessonType::Text => null,
        };

        return [
            'type' => 'doc',
            'content' => $blocks === [] ? [['type' => 'paragraph']] : $blocks,
        ];
    }

    /** @param array<int, array<string, mixed>> $blocks */
    private function appendVideoOrLink(array &$blocks, Lesson $lesson): void
    {
        $url = $lesson->external_url;

        if ($url === null) {
            return;
        }

        $video = $this->videoEmbed->parse($url);

        if ($video === null) {
            $this->appendLink($blocks, $url, 'Open video');

            return;
        }

        $blocks[] = [
            'type' => 'externalVideo',
            'attrs' => [
                'url' => $url,
                'title' => $lesson->title,
                'caption' => null,
                'provider' => $video['provider'],
                'videoId' => $video['video_id'],
            ],
        ];
    }

    /** @param array<int, array<string, mixed>> $blocks */
    private function appendLink(array &$blocks, ?string $url, string $label): void
    {
        if ($url === null || ! $this->videoEmbed->isSafeHttpUrl($url)) {
            return;
        }

        $blocks[] = [
            'type' => 'paragraph',
            'content' => [[
                'type' => 'text',
                'text' => $label,
                'marks' => [[
                    'type' => 'link',
                    'attrs' => [
                        'href' => $url,
                        'target' => '_blank',
                        'rel' => 'noopener noreferrer',
                        'class' => null,
                    ],
                ]],
            ]],
        ];
    }

    /** @param array<int, array<string, mixed>> $blocks */
    private function appendAsset(array &$blocks, Lesson $lesson, LessonAssetType $type): void
    {
        $path = $lesson->managedFilePath();

        if ($path === null) {
            return;
        }

        $asset = LessonAsset::query()->firstOrCreate(
            ['lesson_id' => $lesson->id, 'file_path' => $path],
            [
                'asset_type' => $type,
                'original_name' => basename($path),
                'mime_type' => $this->mimeType($path, $type),
                'file_size' => $this->fileSize($path),
                'alt_text' => $type === LessonAssetType::Image ? $lesson->title : null,
                'caption' => null,
            ],
        );

        $blocks[] = $type === LessonAssetType::Image
            ? [
                'type' => 'lessonImage',
                'attrs' => [
                    'lessonAssetId' => $asset->id,
                    'altText' => $asset->alt_text ?? $lesson->title,
                    'caption' => $asset->caption,
                    'alignment' => 'center',
                    'size' => 'large',
                    'decorative' => false,
                ],
            ]
            : [
                'type' => 'lessonFile',
                'attrs' => [
                    'lessonAssetId' => $asset->id,
                    'title' => $asset->original_name,
                    'caption' => $asset->caption,
                ],
            ];
    }

    /** @return array<int, array<string, mixed>> */
    private function paragraphs(?string $content): array
    {
        if ($content === null || trim($content) === '') {
            return [];
        }

        $paragraphs = preg_split('/\R{2,}/u', trim($content)) ?: [];

        return array_map(fn (string $paragraph): array => [
            'type' => 'paragraph',
            'content' => [[
                'type' => 'text',
                'text' => $paragraph,
            ]],
        ], $paragraphs);
    }

    private function fileSize(string $path): int
    {
        try {
            return Storage::disk('local')->exists($path)
                ? max(0, Storage::disk('local')->size($path))
                : 0;
        } catch (Throwable) {
            return 0;
        }
    }

    private function mimeType(string $path, LessonAssetType $type): string
    {
        try {
            $mime = Storage::disk('local')->exists($path)
                ? Storage::disk('local')->mimeType($path)
                : null;

            if (is_string($mime) && $mime !== '') {
                return $mime;
            }
        } catch (Throwable) {
            // Fall back to a type-safe legacy MIME below.
        }

        if ($type === LessonAssetType::Document) {
            return 'application/pdf';
        }

        return match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
            'jpg', 'jpeg' => 'image/jpeg',
            'webp' => 'image/webp',
            default => 'image/png',
        };
    }
}
