<?php

namespace App\Http\Requests\Admin;

use App\Models\Competency;
use App\Models\Question;
use App\Models\QuestionBank;

class UpdateQuestionRequest extends StoreQuestionRequest
{
    public function authorize(): bool
    {
        $bank = QuestionBank::query()->find($this->integer('question_bank_id'));
        $competency = Competency::query()->find($this->integer('competency_id'));

        return $bank instanceof QuestionBank && $competency instanceof Competency
            && $this->user()->can('update', $this->question())
            && $this->user()->can('create', [Question::class, $bank, $competency]);
    }

    private function question(): Question
    {
        $question = $this->route('question');
        abort_unless($question instanceof Question, 404);

        return $question;
    }
}
