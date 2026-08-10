<?php

namespace App\Models;

use Database\Factories\AssessmentAttemptOptionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @property int $id
 * @property int $assessment_attempt_question_id
 * @property string $option_text
 * @property bool $is_correct
 * @property int $sort_order
 * @property-read AssessmentAttemptQuestion $attemptQuestion
 * @property-read Collection<int, AssessmentAnswer> $answers
 */
#[Fillable(['assessment_attempt_question_id', 'option_text', 'is_correct', 'sort_order'])]
#[Hidden(['is_correct'])]
class AssessmentAttemptOption extends Model
{
    /** @use HasFactory<AssessmentAttemptOptionFactory> */
    use HasFactory;

    /** @return BelongsTo<AssessmentAttemptQuestion, $this> */
    public function attemptQuestion(): BelongsTo
    {
        return $this->belongsTo(AssessmentAttemptQuestion::class, 'assessment_attempt_question_id');
    }

    /** @return BelongsToMany<AssessmentAnswer, $this, AssessmentAnswerOption> */
    public function answers(): BelongsToMany
    {
        return $this->belongsToMany(AssessmentAnswer::class, 'assessment_answer_options')
            ->using(AssessmentAnswerOption::class)
            ->withTimestamps();
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['is_correct' => 'boolean', 'sort_order' => 'integer'];
    }
}
