<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $question_id
 * @property string $original_name
 * @property string $file_path
 * @property string $mime_type
 * @property int $file_size
 * @property string $alt_text
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Question $question
 */
#[Fillable([
    'question_id',
    'original_name',
    'file_path',
    'mime_type',
    'file_size',
    'alt_text',
])]
#[Hidden(['file_path'])]
class QuestionAsset extends Model
{
    /** @return BelongsTo<Question, $this> */
    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }

    /**
     * The stored path only when it is one this application wrote itself.
     * Mirrors LessonAsset: a row whose path was tampered with resolves to
     * null and is served as 404 rather than reaching the filesystem.
     */
    public function managedFilePath(): ?string
    {
        $prefix = "question-assets/{$this->question_id}/";

        if (! str_starts_with($this->file_path, $prefix)
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
            'file_size' => 'integer',
        ];
    }
}
