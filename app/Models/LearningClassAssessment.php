<?php

namespace App\Models;

use App\Enums\AssessmentFeedbackMode;
use App\Enums\ClassAssessmentStatus;
use Carbon\CarbonInterface;
use Database\Factories\LearningClassAssessmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property int $id
 * @property int $learning_class_id
 * @property int $assessment_id
 * @property CarbonInterface|null $opens_at
 * @property CarbonInterface|null $closes_at
 * @property int $max_attempts
 * @property ClassAssessmentStatus $status
 * @property AssessmentFeedbackMode $feedback_mode
 * @property-read LearningClass $learningClass
 * @property-read Assessment $assessment
 * @property-read Collection<int, AssessmentAttempt> $attempts
 * @property-read int|null $attempts_count
 * @property-read MasteryRule|null $masteryRule
 */
#[Fillable(['learning_class_id', 'assessment_id', 'opens_at', 'closes_at', 'max_attempts', 'status', 'feedback_mode'])]
class LearningClassAssessment extends Model
{
    /** @use HasFactory<LearningClassAssessmentFactory> */
    use HasFactory;

    /** @return BelongsTo<LearningClass, $this> */
    public function learningClass(): BelongsTo
    {
        return $this->belongsTo(LearningClass::class);
    }

    /** @return BelongsTo<Assessment, $this> */
    public function assessment(): BelongsTo
    {
        return $this->belongsTo(Assessment::class);
    }

    /** @return HasMany<AssessmentAttempt, $this> */
    public function attempts(): HasMany
    {
        return $this->hasMany(AssessmentAttempt::class);
    }

    /** @return HasOne<MasteryRule, $this> */
    public function masteryRule(): HasOne
    {
        return $this->hasOne(MasteryRule::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'opens_at' => 'datetime',
            'closes_at' => 'datetime',
            'max_attempts' => 'integer',
            'status' => ClassAssessmentStatus::class,
            'feedback_mode' => AssessmentFeedbackMode::class,
        ];
    }
}
