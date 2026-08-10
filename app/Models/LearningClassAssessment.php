<?php

namespace App\Models;

use App\Enums\ClassAssessmentStatus;
use Carbon\CarbonInterface;
use Database\Factories\LearningClassAssessmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $learning_class_id
 * @property int $assessment_id
 * @property CarbonInterface|null $opens_at
 * @property CarbonInterface|null $closes_at
 * @property int $max_attempts
 * @property ClassAssessmentStatus $status
 * @property-read LearningClass $learningClass
 * @property-read Assessment $assessment
 */
#[Fillable(['learning_class_id', 'assessment_id', 'opens_at', 'closes_at', 'max_attempts', 'status'])]
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

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'opens_at' => 'datetime',
            'closes_at' => 'datetime',
            'max_attempts' => 'integer',
            'status' => ClassAssessmentStatus::class,
        ];
    }
}
