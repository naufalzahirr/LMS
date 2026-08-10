<?php

namespace App\Models;

use Database\Factories\AssessmentAnswerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $assessment_attempt_id
 * @property int $assessment_attempt_question_id
 * @property string|null $answer_text
 * @property bool|null $answer_boolean
 * @property string|null $auto_score
 * @property string|null $manual_score
 * @property bool|null $is_correct
 * @property string|null $feedback
 * @property int|null $graded_by
 * @property Carbon|null $graded_at
 * @property-read AssessmentAttempt $attempt
 * @property-read AssessmentAttemptQuestion $attemptQuestion
 * @property-read Collection<int, AssessmentAttemptOption> $selectedOptions
 * @property-read User|null $grader
 */
#[Fillable([
    'assessment_attempt_id',
    'assessment_attempt_question_id',
    'answer_text',
    'answer_boolean',
    'auto_score',
    'manual_score',
    'is_correct',
    'feedback',
    'graded_by',
    'graded_at',
])]
#[Hidden(['auto_score', 'manual_score', 'is_correct', 'graded_by'])]
class AssessmentAnswer extends Model
{
    /** @use HasFactory<AssessmentAnswerFactory> */
    use HasFactory;

    /** @return BelongsTo<AssessmentAttempt, $this> */
    public function attempt(): BelongsTo
    {
        return $this->belongsTo(AssessmentAttempt::class, 'assessment_attempt_id');
    }

    /** @return BelongsTo<AssessmentAttemptQuestion, $this> */
    public function attemptQuestion(): BelongsTo
    {
        return $this->belongsTo(AssessmentAttemptQuestion::class, 'assessment_attempt_question_id');
    }

    /** @return BelongsToMany<AssessmentAttemptOption, $this, AssessmentAnswerOption> */
    public function selectedOptions(): BelongsToMany
    {
        return $this->belongsToMany(AssessmentAttemptOption::class, 'assessment_answer_options')
            ->using(AssessmentAnswerOption::class)
            ->withTimestamps();
    }

    /** @return BelongsTo<User, $this> */
    public function grader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'graded_by');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'answer_boolean' => 'boolean',
            'auto_score' => 'decimal:2',
            'manual_score' => 'decimal:2',
            'is_correct' => 'boolean',
            'graded_at' => 'datetime',
        ];
    }
}
