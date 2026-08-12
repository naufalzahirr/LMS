<?php

namespace App\Models;

use App\Enums\AcademicStatus;
use App\Enums\LessonType;
use Database\Factories\LessonFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
 * @property array<string, mixed>|null $content_document
 * @property int|null $duration_minutes
 * @property int $sort_order
 * @property AcademicStatus $status
 * @property bool $is_authoring_draft
 * @property int|null $draft_owner_id
 * @property Carbon|null $draft_expires_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Module $module
 * @property-read Collection<int, LessonProgress> $progressRecords
 * @property-read Collection<int, LessonAsset> $assets
 * @property-read Collection<int, LessonCheckpoint> $checkpoints
 * @property-read Collection<int, MasteryRule> $defaultRemedialRules
 * @property-read Collection<int, RemedialAssignmentLesson> $remedialAssignmentLessons
 */
#[Fillable([
    'module_id',
    'title',
    'slug',
    'lesson_type',
    'content',
    'external_url',
    'content_document',
    'duration_minutes',
    'sort_order',
    'status',
    'is_authoring_draft',
    'draft_owner_id',
    'draft_expires_at',
])]
#[Hidden(['file_path'])]
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
     * @return HasMany<LessonProgress, $this>
     */
    public function progressRecords(): HasMany
    {
        return $this->hasMany(LessonProgress::class);
    }

    /** @return HasMany<LessonAsset, $this> */
    public function assets(): HasMany
    {
        return $this->hasMany(LessonAsset::class);
    }

    /** @return HasMany<LessonCheckpoint, $this> */
    public function checkpoints(): HasMany
    {
        return $this->hasMany(LessonCheckpoint::class);
    }

    /** @return BelongsToMany<MasteryRule, $this> */
    public function defaultRemedialRules(): BelongsToMany
    {
        return $this->belongsToMany(MasteryRule::class, 'mastery_rule_remedial_lessons')
            ->withPivot(['sort_order'])
            ->withTimestamps();
    }

    /** @return HasMany<RemedialAssignmentLesson, $this> */
    public function remedialAssignmentLessons(): HasMany
    {
        return $this->hasMany(RemedialAssignmentLesson::class);
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

        if (! str_starts_with($this->file_path, $prefix)
            || str_contains($this->file_path, '..')
            || str_contains($this->file_path, '\\')
            || basename($this->file_path) === '') {
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
            'content_document' => 'array',
            'duration_minutes' => 'integer',
            'sort_order' => 'integer',
            'status' => AcademicStatus::class,
            'is_authoring_draft' => 'boolean',
            'draft_expires_at' => 'datetime',
        ];
    }
}
