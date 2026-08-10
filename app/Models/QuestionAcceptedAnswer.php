<?php

namespace App\Models;

use Database\Factories\QuestionAcceptedAnswerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $question_id
 * @property string $answer_text
 * @property bool $case_sensitive
 * @property-read Question $question
 */
#[Fillable(['question_id', 'answer_text', 'case_sensitive'])]
#[Hidden(['answer_text', 'case_sensitive'])]
class QuestionAcceptedAnswer extends Model
{
    /** @use HasFactory<QuestionAcceptedAnswerFactory> */
    use HasFactory;

    /** @return BelongsTo<Question, $this> */
    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['case_sensitive' => 'boolean'];
    }
}
