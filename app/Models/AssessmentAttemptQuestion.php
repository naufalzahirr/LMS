<?php

namespace App\Models;

use App\Enums\QuestionType;
use Database\Factories\AssessmentAttemptQuestionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property int $id
 * @property int $assessment_attempt_id
 * @property int|null $source_question_id
 * @property int|null $question_asset_id
 * @property QuestionType $question_type
 * @property string $prompt
 * @property string|null $explanation
 * @property string $points
 * @property int $sort_order
 * @property bool|null $correct_boolean
 * @property-read AssessmentAttempt $attempt
 * @property-read Question|null $sourceQuestion
 * @property-read QuestionAsset|null $questionAsset
 * @property-read Collection<int, AssessmentAttemptOption> $options
 * @property-read Collection<int, AssessmentAttemptAcceptedAnswer> $acceptedAnswers
 * @property-read AssessmentAnswer|null $answer
 */
#[Fillable([
    'assessment_attempt_id',
    'source_question_id',
    'question_asset_id',
    'question_type',
    'prompt',
    'explanation',
    'points',
    'sort_order',
    'correct_boolean',
])]
#[Hidden(['correct_boolean', 'explanation'])]
class AssessmentAttemptQuestion extends Model
{
    /** @use HasFactory<AssessmentAttemptQuestionFactory> */
    use HasFactory;

    /** @return BelongsTo<AssessmentAttempt, $this> */
    public function attempt(): BelongsTo
    {
        return $this->belongsTo(AssessmentAttempt::class, 'assessment_attempt_id');
    }

    /** @return BelongsTo<Question, $this> */
    public function sourceQuestion(): BelongsTo
    {
        return $this->belongsTo(Question::class, 'source_question_id');
    }

    /**
     * The image as snapshotted when this attempt started.
     *
     * @return BelongsTo<QuestionAsset, $this>
     */
    public function questionAsset(): BelongsTo
    {
        return $this->belongsTo(QuestionAsset::class);
    }

    /** @return HasMany<AssessmentAttemptOption, $this> */
    public function options(): HasMany
    {
        return $this->hasMany(AssessmentAttemptOption::class)->orderBy('sort_order')->orderBy('id');
    }

    /** @return HasMany<AssessmentAttemptAcceptedAnswer, $this> */
    public function acceptedAnswers(): HasMany
    {
        return $this->hasMany(AssessmentAttemptAcceptedAnswer::class);
    }

    /** @return HasOne<AssessmentAnswer, $this> */
    public function answer(): HasOne
    {
        return $this->hasOne(AssessmentAnswer::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'question_type' => QuestionType::class,
            'points' => 'decimal:2',
            'sort_order' => 'integer',
            'correct_boolean' => 'boolean',
        ];
    }
}
