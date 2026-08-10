<?php

namespace App\Models;

use App\Enums\AssessmentAttemptStatus;
use Database\Factories\AssessmentAttemptFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $learning_class_assessment_id
 * @property int $enrollment_id
 * @property int $attempt_number
 * @property AssessmentAttemptStatus $status
 * @property Carbon $started_at
 * @property Carbon|null $submitted_at
 * @property Carbon|null $graded_at
 * @property string|null $auto_points
 * @property string|null $manual_points
 * @property string|null $earned_points
 * @property string $max_points
 * @property string|null $percentage
 * @property-read LearningClassAssessment $classAssessment
 * @property-read Enrollment $enrollment
 * @property-read Collection<int, AssessmentAttemptQuestion> $attemptQuestions
 * @property-read Collection<int, AssessmentAnswer> $answers
 * @property-read Collection<int, RemedialAssignment> $sourceRemedialAssignments
 */
#[Fillable([
    'learning_class_assessment_id',
    'enrollment_id',
    'attempt_number',
    'status',
    'started_at',
    'submitted_at',
    'graded_at',
    'auto_points',
    'manual_points',
    'earned_points',
    'max_points',
    'percentage',
])]
class AssessmentAttempt extends Model
{
    /** @use HasFactory<AssessmentAttemptFactory> */
    use HasFactory;

    /** @return BelongsTo<LearningClassAssessment, $this> */
    public function classAssessment(): BelongsTo
    {
        return $this->belongsTo(LearningClassAssessment::class, 'learning_class_assessment_id');
    }

    /** @return BelongsTo<Enrollment, $this> */
    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }

    /** @return HasMany<AssessmentAttemptQuestion, $this> */
    public function attemptQuestions(): HasMany
    {
        return $this->hasMany(AssessmentAttemptQuestion::class)->orderBy('sort_order')->orderBy('id');
    }

    /** @return HasMany<AssessmentAnswer, $this> */
    public function answers(): HasMany
    {
        return $this->hasMany(AssessmentAnswer::class);
    }

    /** @return HasMany<RemedialAssignment, $this> */
    public function sourceRemedialAssignments(): HasMany
    {
        return $this->hasMany(RemedialAssignment::class, 'source_assessment_attempt_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'attempt_number' => 'integer',
            'status' => AssessmentAttemptStatus::class,
            'started_at' => 'datetime',
            'submitted_at' => 'datetime',
            'graded_at' => 'datetime',
            'auto_points' => 'decimal:2',
            'manual_points' => 'decimal:2',
            'earned_points' => 'decimal:2',
            'max_points' => 'decimal:2',
            'percentage' => 'decimal:2',
        ];
    }
}
