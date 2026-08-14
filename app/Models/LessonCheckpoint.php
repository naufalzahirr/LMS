<?php

namespace App\Models;

use App\Enums\LessonCheckpointType;
use Database\Factories\LessonCheckpointFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $lesson_id
 * @property LessonCheckpointType $checkpoint_type
 * @property string $prompt
 * @property string|null $correct_feedback
 * @property string|null $incorrect_feedback
 * @property string|null $explanation
 * @property array<string, mixed> $configuration
 * @property array<string, mixed> $answer_key
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Lesson $lesson
 * @property-read User|null $creator
 * @property-read Collection<int, LessonCheckpointAttempt> $attempts
 */
#[Fillable([
    'lesson_id',
    'checkpoint_type',
    'prompt',
    'correct_feedback',
    'incorrect_feedback',
    'explanation',
    'configuration',
    'answer_key',
    'created_by',
])]
#[Hidden(['answer_key'])]
class LessonCheckpoint extends Model
{
    /** @use HasFactory<LessonCheckpointFactory> */
    use HasFactory;

    /** @return BelongsTo<Lesson, $this> */
    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return HasMany<LessonCheckpointAttempt, $this> */
    public function attempts(): HasMany
    {
        return $this->hasMany(LessonCheckpointAttempt::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'checkpoint_type' => LessonCheckpointType::class,
            'configuration' => 'array',
            'answer_key' => 'array',
        ];
    }
}
