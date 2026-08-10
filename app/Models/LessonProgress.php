<?php

namespace App\Models;

use App\Enums\LessonProgressStatus;
use Carbon\CarbonInterface;
use Database\Factories\LessonProgressFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $enrollment_id
 * @property int $lesson_id
 * @property LessonProgressStatus $status
 * @property CarbonInterface|null $started_at
 * @property CarbonInterface|null $completed_at
 * @property CarbonInterface|null $last_viewed_at
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 * @property-read Enrollment $enrollment
 * @property-read Lesson $lesson
 */
#[Fillable(['enrollment_id', 'lesson_id', 'status', 'started_at', 'completed_at', 'last_viewed_at'])]
class LessonProgress extends Model
{
    /** @use HasFactory<LessonProgressFactory> */
    use HasFactory;

    protected $table = 'lesson_progress';

    /**
     * @return BelongsTo<Enrollment, $this>
     */
    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }

    /**
     * @return BelongsTo<Lesson, $this>
     */
    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => LessonProgressStatus::class,
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'last_viewed_at' => 'datetime',
        ];
    }
}
