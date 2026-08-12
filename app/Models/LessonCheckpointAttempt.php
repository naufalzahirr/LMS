<?php

namespace App\Models;

use Database\Factories\LessonCheckpointAttemptFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $lesson_checkpoint_id
 * @property int $enrollment_id
 * @property array<string, mixed> $submitted_answer
 * @property bool $is_correct
 * @property int $attempt_number
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read LessonCheckpoint $checkpoint
 * @property-read Enrollment $enrollment
 */
#[Fillable([
    'lesson_checkpoint_id',
    'enrollment_id',
    'submitted_answer',
    'is_correct',
    'attempt_number',
])]
class LessonCheckpointAttempt extends Model
{
    /** @use HasFactory<LessonCheckpointAttemptFactory> */
    use HasFactory;

    /** @return BelongsTo<LessonCheckpoint, $this> */
    public function checkpoint(): BelongsTo
    {
        return $this->belongsTo(LessonCheckpoint::class, 'lesson_checkpoint_id');
    }

    /** @return BelongsTo<Enrollment, $this> */
    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'submitted_answer' => 'array',
            'is_correct' => 'boolean',
            'attempt_number' => 'integer',
        ];
    }
}
