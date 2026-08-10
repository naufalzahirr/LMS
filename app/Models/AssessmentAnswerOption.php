<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * @property int $assessment_answer_id
 * @property int $assessment_attempt_option_id
 * @property-read AssessmentAnswer $answer
 * @property-read AssessmentAttemptOption $attemptOption
 */
#[Fillable(['assessment_answer_id', 'assessment_attempt_option_id'])]
class AssessmentAnswerOption extends Pivot
{
    public $incrementing = false;

    /** @return BelongsTo<AssessmentAnswer, $this> */
    public function answer(): BelongsTo
    {
        return $this->belongsTo(AssessmentAnswer::class, 'assessment_answer_id');
    }

    /** @return BelongsTo<AssessmentAttemptOption, $this> */
    public function attemptOption(): BelongsTo
    {
        return $this->belongsTo(AssessmentAttemptOption::class, 'assessment_attempt_option_id');
    }
}
