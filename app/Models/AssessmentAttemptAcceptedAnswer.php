<?php

namespace App\Models;

use Database\Factories\AssessmentAttemptAcceptedAnswerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $assessment_attempt_question_id
 * @property string $answer_text
 * @property bool $case_sensitive
 * @property-read AssessmentAttemptQuestion $attemptQuestion
 */
#[Fillable(['assessment_attempt_question_id', 'answer_text', 'case_sensitive'])]
#[Hidden(['answer_text', 'case_sensitive'])]
class AssessmentAttemptAcceptedAnswer extends Model
{
    /** @use HasFactory<AssessmentAttemptAcceptedAnswerFactory> */
    use HasFactory;

    /** @return BelongsTo<AssessmentAttemptQuestion, $this> */
    public function attemptQuestion(): BelongsTo
    {
        return $this->belongsTo(AssessmentAttemptQuestion::class, 'assessment_attempt_question_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['case_sensitive' => 'boolean'];
    }
}
