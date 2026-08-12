<?php

namespace App\Services;

use App\Enums\AcademicStatus;
use App\Enums\LessonType;
use App\Models\Lesson;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class LessonService
{
    public function __construct(
        private readonly LessonContentService $contentService,
        private readonly LessonContentMigrationService $contentMigration,
        private readonly LessonCheckpointService $checkpoints,
    ) {}

    /**
     * @param  array{module_id: int, title: string, slug: string, lesson_type: LessonType, content: string|null, external_url: string|null, content_document: array<string, mixed>|null, rich_content: bool, duration_minutes: int|null, sort_order: int, status: AcademicStatus}  $data
     */
    public function create(array $data, ?UploadedFile $file, ?Lesson $draft = null): Lesson
    {
        if ($draft instanceof Lesson) {
            return $this->finalizeDraft($draft, $data, $file);
        }

        $storedPath = null;

        try {
            $lesson = DB::transaction(function () use ($data, $file, &$storedPath): Lesson {
                $lesson = Lesson::query()->create($this->legacyAttributes($data));

                if ($file !== null) {
                    $storedPath = $this->storeFile($lesson, $file);
                    $lesson->forceFill(['file_path' => $storedPath])->save();
                }

                if ($data['rich_content']) {
                    $document = $this->contentService->normalize(
                        $lesson,
                        $data['content_document'] ?? $this->contentService->emptyDocument(),
                    );
                    $lesson->forceFill([
                        'content_document' => $document,
                    ])->save();
                    $this->checkpoints->deleteUnreferenced(
                        $lesson,
                        $this->contentService->referencedCheckpointIds($document),
                    );
                }

                return $lesson->refresh();
            });

            return $data['rich_content']
                ? $lesson
                : $this->contentMigration->migrateLesson($lesson);
        } catch (Throwable $exception) {
            $this->deleteManagedPath($storedPath);

            throw $exception;
        }
    }

    /**
     * @param  array{module_id: int, title: string, slug: string, lesson_type: LessonType, content: string|null, external_url: string|null, content_document: array<string, mixed>|null, rich_content: bool, duration_minutes: int|null, sort_order: int, status: AcademicStatus}  $data
     */
    public function update(Lesson $lesson, array $data, ?UploadedFile $file): Lesson
    {
        if ($lesson->module_id !== $data['module_id']
            && ($lesson->progressRecords()->exists()
                || $lesson->defaultRemedialRules()->exists()
                || $lesson->remedialAssignmentLessons()->exists())) {
            throw ValidationException::withMessages([
                'module_id' => __('A lesson with learning or remedial history cannot be moved to another module.'),
            ]);
        }

        if ($data['rich_content']) {
            $document = $this->contentService->normalize(
                $lesson,
                $data['content_document'] ?? $this->contentService->emptyDocument(),
            );

            $lesson->forceFill([
                ...$this->metadataAttributes($data),
                'content_document' => $document,
            ])->save();
            $this->checkpoints->deleteUnreferenced(
                $lesson,
                $this->contentService->referencedCheckpointIds($document),
            );

            return $lesson->refresh();
        }

        $oldPath = $lesson->managedFilePath();
        $newPath = null;

        try {
            if ($file !== null) {
                $newPath = $this->storeFile($lesson, $file);
            }

            $filePath = $data['lesson_type']->usesUploadedFile()
                ? ($newPath ?? $oldPath)
                : null;

            $updatedLesson = DB::transaction(function () use ($lesson, $data, $filePath): Lesson {
                $lesson->forceFill([
                    ...$this->legacyAttributes($data),
                    'file_path' => $filePath,
                    'content_document' => null,
                ])->save();

                return $lesson->refresh();
            });
        } catch (Throwable $exception) {
            $this->deleteManagedPath($newPath);

            throw $exception;
        }

        $updatedLesson = $this->contentMigration->migrateLesson($updatedLesson);
        $this->checkpoints->deleteUnreferenced(
            $updatedLesson,
            $this->contentService->referencedCheckpointIds($updatedLesson->content_document),
        );

        if ($oldPath !== null && $oldPath !== $updatedLesson->file_path) {
            $lesson->assets()->where('file_path', $oldPath)->delete();
            $this->deleteManagedPath($oldPath);
        }

        return $updatedLesson;
    }

    public function delete(Lesson $lesson): void
    {
        $filePath = $lesson->managedFilePath();

        DB::transaction(function () use ($lesson): void {
            $lesson->assets()->delete();
            $lesson->checkpoints()->delete();
            $lesson->forceFill(['file_path' => null])->save();
            $lesson->delete();
        });

        $this->deleteManagedPath($filePath);
        Storage::disk('local')->deleteDirectory("lesson-files/{$lesson->id}");
        Storage::disk('local')->deleteDirectory("lesson-assets/{$lesson->id}");
    }

    /**
     * @param  array{module_id: int, title: string, slug: string, lesson_type: LessonType, content: string|null, external_url: string|null, content_document: array<string, mixed>|null, rich_content: bool, duration_minutes: int|null, sort_order: int, status: AcademicStatus}  $data
     */
    private function finalizeDraft(Lesson $draft, array $data, ?UploadedFile $file): Lesson
    {
        $storedPath = null;

        try {
            $lesson = DB::transaction(function () use ($draft, $data, $file, &$storedPath): Lesson {
                $lesson = Lesson::query()->lockForUpdate()->findOrFail($draft->id);

                if (! $lesson->is_authoring_draft) {
                    throw ValidationException::withMessages([
                        'draft_id' => __('This lesson draft has already been finalized.'),
                    ]);
                }

                if ($file !== null) {
                    $storedPath = $this->storeFile($lesson, $file);
                }

                $document = $data['rich_content']
                    ? $this->contentService->normalize(
                        $lesson,
                        $data['content_document'] ?? $this->contentService->emptyDocument(),
                    )
                    : null;

                $lesson->forceFill([
                    ...$this->legacyAttributes($data),
                    'file_path' => $storedPath,
                    'content_document' => $document,
                    'is_authoring_draft' => false,
                    'draft_owner_id' => null,
                    'draft_expires_at' => null,
                ])->save();
                $this->checkpoints->deleteUnreferenced(
                    $lesson,
                    $this->contentService->referencedCheckpointIds($document),
                );

                return $lesson->refresh();
            });

            return $data['rich_content']
                ? $lesson
                : $this->contentMigration->migrateLesson($lesson);
        } catch (Throwable $exception) {
            $this->deleteManagedPath($storedPath);

            throw $exception;
        }
    }

    private function storeFile(Lesson $lesson, UploadedFile $file): string
    {
        $path = $file->store("lesson-files/{$lesson->id}", 'local');

        if (! is_string($path)) {
            throw new RuntimeException('The lesson file could not be stored.');
        }

        return $path;
    }

    private function deleteManagedPath(?string $path): void
    {
        if ($path === null || ! str_starts_with($path, 'lesson-files/') || str_contains($path, '..')) {
            return;
        }

        Storage::disk('local')->delete($path);
    }

    /**
     * @param  array{module_id: int, title: string, slug: string, lesson_type: LessonType, content: string|null, external_url: string|null, content_document: array<string, mixed>|null, rich_content: bool, duration_minutes: int|null, sort_order: int, status: AcademicStatus}  $data
     * @return array<string, mixed>
     */
    private function legacyAttributes(array $data): array
    {
        return [
            ...$this->metadataAttributes($data),
            'lesson_type' => $data['lesson_type'],
            'content' => $data['content'],
            'external_url' => $data['external_url'],
        ];
    }

    /**
     * @param  array{module_id: int, title: string, slug: string, lesson_type: LessonType, content: string|null, external_url: string|null, content_document: array<string, mixed>|null, rich_content: bool, duration_minutes: int|null, sort_order: int, status: AcademicStatus}  $data
     * @return array<string, mixed>
     */
    private function metadataAttributes(array $data): array
    {
        return [
            'module_id' => $data['module_id'],
            'title' => $data['title'],
            'slug' => $data['slug'],
            'duration_minutes' => $data['duration_minutes'],
            'sort_order' => $data['sort_order'],
            'status' => $data['status'],
        ];
    }
}
