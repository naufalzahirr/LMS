<?php

namespace App\Services;

use App\Enums\AcademicStatus;
use App\Enums\LessonType;
use App\Models\Lesson;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class LessonService
{
    /**
     * @param  array{module_id: int, title: string, slug: string, lesson_type: LessonType, content: string|null, external_url: string|null, duration_minutes: int|null, sort_order: int, status: AcademicStatus}  $data
     */
    public function create(array $data, ?UploadedFile $file): Lesson
    {
        $storedPath = null;

        try {
            return DB::transaction(function () use ($data, $file, &$storedPath): Lesson {
                $lesson = Lesson::query()->create($data);

                if ($file !== null) {
                    $storedPath = $this->storeFile($lesson, $file);
                    $lesson->update(['file_path' => $storedPath]);
                }

                return $lesson->refresh();
            });
        } catch (Throwable $exception) {
            $this->deleteManagedPath($storedPath);

            throw $exception;
        }
    }

    /**
     * @param  array{module_id: int, title: string, slug: string, lesson_type: LessonType, content: string|null, external_url: string|null, duration_minutes: int|null, sort_order: int, status: AcademicStatus}  $data
     */
    public function update(Lesson $lesson, array $data, ?UploadedFile $file): Lesson
    {
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
                $lesson->update([
                    ...$data,
                    'file_path' => $filePath,
                ]);

                return $lesson->refresh();
            });
        } catch (Throwable $exception) {
            $this->deleteManagedPath($newPath);

            throw $exception;
        }

        if ($oldPath !== null && $oldPath !== $updatedLesson->file_path) {
            $this->deleteManagedPath($oldPath);
        }

        return $updatedLesson;
    }

    public function delete(Lesson $lesson): void
    {
        $filePath = $lesson->managedFilePath();

        DB::transaction(function () use ($lesson): void {
            $lesson->update(['file_path' => null]);
            $lesson->delete();
        });

        $this->deleteManagedPath($filePath);
        Storage::disk('local')->deleteDirectory("lesson-files/{$lesson->id}");
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
}
