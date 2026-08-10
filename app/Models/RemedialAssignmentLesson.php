<?php

namespace App\Models;

use Database\Factories\RemedialAssignmentLessonFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $remedial_assignment_id
 * @property int $lesson_id
 * @property int $sort_order
 * @property Carbon|null $completed_at
 * @property-read RemedialAssignment $remedialAssignment
 * @property-read Lesson $lesson
 */
#[Fillable(['remedial_assignment_id', 'lesson_id', 'sort_order', 'completed_at'])]
class RemedialAssignmentLesson extends Model
{
    /** @use HasFactory<RemedialAssignmentLessonFactory> */
    use HasFactory;

    /** @return BelongsTo<RemedialAssignment, $this> */
    public function remedialAssignment(): BelongsTo
    {
        return $this->belongsTo(RemedialAssignment::class);
    }

    /** @return BelongsTo<Lesson, $this> */
    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class)->withTrashed();
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['sort_order' => 'integer', 'completed_at' => 'datetime'];
    }
}
