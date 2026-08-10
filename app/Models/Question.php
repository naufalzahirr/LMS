<?php

namespace App\Models;

use App\Enums\AcademicStatus;
use App\Enums\QuestionType;
use Database\Factories\QuestionFactory;
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
 * @property int $question_bank_id
 * @property int $competency_id
 * @property QuestionType $question_type
 * @property string $prompt
 * @property string|null $explanation
 * @property string $default_points
 * @property bool|null $correct_boolean
 * @property AcademicStatus $status
 * @property int $sort_order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read QuestionBank $questionBank
 * @property-read Competency $competency
 * @property-read Collection<int, QuestionOption> $options
 * @property-read Collection<int, QuestionAcceptedAnswer> $acceptedAnswers
 * @property-read Collection<int, AssessmentQuestion> $assessmentQuestions
 * @property-read Collection<int, Assessment> $assessments
 * @property-read Collection<int, AssessmentAttemptQuestion> $attemptQuestions
 */
#[Fillable([
    'question_bank_id',
    'competency_id',
    'question_type',
    'prompt',
    'explanation',
    'default_points',
    'correct_boolean',
    'status',
    'sort_order',
])]
class Question extends Model
{
    /** @use HasFactory<QuestionFactory> */
    use HasFactory, SoftDeletes;

    /** @return BelongsTo<QuestionBank, $this> */
    public function questionBank(): BelongsTo
    {
        return $this->belongsTo(QuestionBank::class);
    }

    /** @return BelongsTo<Competency, $this> */
    public function competency(): BelongsTo
    {
        return $this->belongsTo(Competency::class);
    }

    /** @return HasMany<QuestionOption, $this> */
    public function options(): HasMany
    {
        return $this->hasMany(QuestionOption::class)->orderBy('sort_order')->orderBy('id');
    }

    /** @return HasMany<QuestionAcceptedAnswer, $this> */
    public function acceptedAnswers(): HasMany
    {
        return $this->hasMany(QuestionAcceptedAnswer::class)->orderBy('id');
    }

    /** @return HasMany<AssessmentQuestion, $this> */
    public function assessmentQuestions(): HasMany
    {
        return $this->hasMany(AssessmentQuestion::class);
    }

    /** @return BelongsToMany<Assessment, $this> */
    public function assessments(): BelongsToMany
    {
        return $this->belongsToMany(Assessment::class, 'assessment_questions')
            ->withPivot(['id', 'points', 'sort_order'])
            ->withTimestamps();
    }

    /** @return HasMany<AssessmentAttemptQuestion, $this> */
    public function attemptQuestions(): HasMany
    {
        return $this->hasMany(AssessmentAttemptQuestion::class, 'source_question_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'question_type' => QuestionType::class,
            'default_points' => 'decimal:2',
            'correct_boolean' => 'boolean',
            'status' => AcademicStatus::class,
            'sort_order' => 'integer',
        ];
    }
}
