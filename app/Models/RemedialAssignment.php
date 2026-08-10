<?php

namespace App\Models;

use App\Enums\RemedialAssignmentStatus;
use Database\Factories\RemedialAssignmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $enrollment_id
 * @property int $competency_id
 * @property int $mastery_rule_id
 * @property int $source_assessment_attempt_id
 * @property RemedialAssignmentStatus $status
 * @property bool|null $open_slot
 * @property Carbon $assigned_at
 * @property Carbon|null $completed_at
 * @property string|null $notes
 * @property-read Enrollment $enrollment
 * @property-read Competency $competency
 * @property-read MasteryRule $masteryRule
 * @property-read AssessmentAttempt $sourceAttempt
 * @property-read Collection<int, RemedialAssignmentLesson> $lessons
 */
#[Fillable([
    'enrollment_id',
    'competency_id',
    'mastery_rule_id',
    'source_assessment_attempt_id',
    'status',
    'open_slot',
    'assigned_at',
    'completed_at',
    'notes',
])]
class RemedialAssignment extends Model
{
    /** @use HasFactory<RemedialAssignmentFactory> */
    use HasFactory;

    /** @return BelongsTo<Enrollment, $this> */
    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }

    /** @return BelongsTo<Competency, $this> */
    public function competency(): BelongsTo
    {
        return $this->belongsTo(Competency::class);
    }

    /** @return BelongsTo<MasteryRule, $this> */
    public function masteryRule(): BelongsTo
    {
        return $this->belongsTo(MasteryRule::class);
    }

    /** @return BelongsTo<AssessmentAttempt, $this> */
    public function sourceAttempt(): BelongsTo
    {
        return $this->belongsTo(AssessmentAttempt::class, 'source_assessment_attempt_id');
    }

    /** @return HasMany<RemedialAssignmentLesson, $this> */
    public function lessons(): HasMany
    {
        return $this->hasMany(RemedialAssignmentLesson::class)->orderBy('sort_order')->orderBy('id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => RemedialAssignmentStatus::class,
            'open_slot' => 'boolean',
            'assigned_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }
}
