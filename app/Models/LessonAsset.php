<?php

namespace App\Models;

use App\Enums\LessonAssetType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $lesson_id
 * @property LessonAssetType $asset_type
 * @property string $original_name
 * @property string $file_path
 * @property string $mime_type
 * @property int $file_size
 * @property string|null $alt_text
 * @property string|null $caption
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Lesson $lesson
 */
#[Fillable([
    'lesson_id',
    'asset_type',
    'original_name',
    'file_path',
    'mime_type',
    'file_size',
    'alt_text',
    'caption',
])]
#[Hidden(['file_path'])]
class LessonAsset extends Model
{
    /** @return BelongsTo<Lesson, $this> */
    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }

    public function managedFilePath(): ?string
    {
        $prefixes = [
            "lesson-assets/{$this->lesson_id}/",
            "lesson-files/{$this->lesson_id}/",
        ];

        $hasManagedPrefix = collect($prefixes)
            ->contains(fn (string $prefix): bool => str_starts_with($this->file_path, $prefix));

        if (! $hasManagedPrefix
            || str_contains($this->file_path, '..')
            || str_contains($this->file_path, '\\')
            || basename($this->file_path) === '') {
            return null;
        }

        return $this->file_path;
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'asset_type' => LessonAssetType::class,
            'file_size' => 'integer',
        ];
    }
}
