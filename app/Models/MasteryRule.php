<?php

namespace App\Models;

use App\Enums\MasteryRuleStatus;
use Database\Factories\MasteryRuleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $learning_class_id
 * @property int $competency_id
 * @property int $learning_class_assessment_id
 * @property string $mastery_score
 * @property bool $require_remedial
 * @property MasteryRuleStatus $status
 * @property-read LearningClass $learningClass
 * @property-read Competency $competency
 * @property-read LearningClassAssessment $classAssessment
 * @property-read Collection<int, Lesson> $remedialLessons
 * @property-read Collection<int, RemedialAssignment> $remedialAssignments
 */
#[Fillable([
    'learning_class_id',
    'competency_id',
    'learning_class_assessment_id',
    'mastery_score',
    'require_remedial',
    'status',
])]
class MasteryRule extends Model
{
    /** @use HasFactory<MasteryRuleFactory> */
    use HasFactory;

    /** @return BelongsTo<LearningClass, $this> */
    public function learningClass(): BelongsTo
    {
        return $this->belongsTo(LearningClass::class);
    }

    /** @return BelongsTo<Competency, $this> */
    public function competency(): BelongsTo
    {
        return $this->belongsTo(Competency::class);
    }

    /** @return BelongsTo<LearningClassAssessment, $this> */
    public function classAssessment(): BelongsTo
    {
        return $this->belongsTo(LearningClassAssessment::class, 'learning_class_assessment_id');
    }

    /** @return BelongsToMany<Lesson, $this> */
    public function remedialLessons(): BelongsToMany
    {
        return $this->belongsToMany(Lesson::class, 'mastery_rule_remedial_lessons')
            ->withPivot(['sort_order'])
            ->withTimestamps()
            ->orderByPivot('sort_order');
    }

    /** @return HasMany<RemedialAssignment, $this> */
    public function remedialAssignments(): HasMany
    {
        return $this->hasMany(RemedialAssignment::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'mastery_score' => 'decimal:2',
            'require_remedial' => 'boolean',
            'status' => MasteryRuleStatus::class,
        ];
    }
}
