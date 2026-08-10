<?php

namespace App\Models;

use App\Enums\AssessmentPurpose;
use App\Enums\AssessmentStatus;
use Database\Factories\AssessmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $competency_id
 * @property string $title
 * @property string|null $code
 * @property string|null $description
 * @property AssessmentPurpose $purpose
 * @property AssessmentStatus $status
 * @property string|null $instructions
 * @property bool $shuffle_questions
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Competency $competency
 * @property-read Collection<int, AssessmentQuestion> $assessmentQuestions
 * @property-read Collection<int, Question> $questions
 * @property-read Collection<int, LearningClassAssessment> $classAssignments
 * @property-read Collection<int, LearningClass> $learningClasses
 * @property-read int|null $assessment_questions_count
 */
#[Fillable([
    'competency_id',
    'title',
    'code',
    'description',
    'purpose',
    'status',
    'instructions',
    'shuffle_questions',
])]
class Assessment extends Model
{
    /** @use HasFactory<AssessmentFactory> */
    use HasFactory, SoftDeletes;

    /** @return BelongsTo<Competency, $this> */
    public function competency(): BelongsTo
    {
        return $this->belongsTo(Competency::class);
    }

    /** @return HasMany<AssessmentQuestion, $this> */
    public function assessmentQuestions(): HasMany
    {
        return $this->hasMany(AssessmentQuestion::class)->orderBy('sort_order')->orderBy('id');
    }

    /** @return BelongsToMany<Question, $this> */
    public function questions(): BelongsToMany
    {
        return $this->belongsToMany(Question::class, 'assessment_questions')
            ->withPivot(['id', 'points', 'sort_order'])
            ->withTimestamps()
            ->orderByPivot('sort_order')
            ->orderByPivot('id');
    }

    /** @return HasMany<LearningClassAssessment, $this> */
    public function classAssignments(): HasMany
    {
        return $this->hasMany(LearningClassAssessment::class);
    }

    /** @return BelongsToMany<LearningClass, $this> */
    public function learningClasses(): BelongsToMany
    {
        return $this->belongsToMany(LearningClass::class, 'learning_class_assessments')
            ->withPivot(['id', 'opens_at', 'closes_at', 'max_attempts', 'status'])
            ->withTimestamps();
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'purpose' => AssessmentPurpose::class,
            'status' => AssessmentStatus::class,
            'shuffle_questions' => 'boolean',
        ];
    }
}
