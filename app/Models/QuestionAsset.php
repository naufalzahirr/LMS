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
     * A token identifying the stored binary.
     *
     * Uploads land on a freshly generated random path, so the stored path
     * changes exactly when the file behind it does — and never when only the
     * alt text is corrected. Hashed so the payload carries no storage detail.
     */
    public function version(): string
    {
        return substr(hash('sha256', $this->file_path), 0, 12);
    }

    /**
     * The authoring preview URL, versioned.
     *
     * The route addresses the image by question id, so a replacement would
     * otherwise reach the browser as a byte-identical URL and leave the
     * previous image on screen until a manual reload. Always build authoring
     * URLs through here so a caller cannot omit the version.
     */
    public function authoringUrl(): string
    {
        return route('admin.questions.image', [
            'question' => $this->question_id,
            'v' => $this->version(),
        ]);
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
