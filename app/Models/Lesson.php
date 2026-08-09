<?php

namespace App\Models;

use App\Enums\AcademicStatus;
use App\Enums\LessonType;
use Database\Factories\LessonFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $module_id
 * @property string $title
 * @property string $slug
 * @property LessonType $lesson_type
 * @property string|null $content
 * @property string|null $external_url
 * @property string|null $file_path
 * @property int|null $duration_minutes
 * @property int $sort_order
 * @property AcademicStatus $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Module $module
 */
#[Fillable([
    'module_id',
    'title',
    'slug',
    'lesson_type',
    'content',
    'external_url',
    'file_path',
    'duration_minutes',
    'sort_order',
    'status',
])]
class Lesson extends Model
{
    /** @use HasFactory<LessonFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @return BelongsTo<Module, $this>
     */
    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }

    /**
     * Return the file path only when it belongs to this lesson's managed directory.
     */
    public function managedFilePath(): ?string
    {
        if ($this->file_path === null) {
            return null;
        }

        $prefix = "lesson-files/{$this->id}/";

        if (! str_starts_with($this->file_path, $prefix) || str_contains($this->file_path, '..')) {
            return null;
        }

        return $this->file_path;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'lesson_type' => LessonType::class,
            'duration_minutes' => 'integer',
            'sort_order' => 'integer',
            'status' => AcademicStatus::class,
        ];
    }
}
