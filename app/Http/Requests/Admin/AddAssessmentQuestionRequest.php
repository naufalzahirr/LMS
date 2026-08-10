<?php

namespace App\Http\Requests\Admin;

use App\Models\Assessment;
use App\Models\Question;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AddAssessmentQuestionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->assessment()) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'question_id' => ['required', 'integer', Rule::exists(Question::class, 'id')->whereNull('deleted_at')],
            'points' => ['nullable', 'numeric', 'gt:0', 'decimal:0,2'],
        ];
    }

    public function question(): Question
    {
        return Question::query()->findOrFail($this->integer('question_id'));
    }

    public function points(): ?string
    {
        return $this->filled('points') ? $this->string('points')->toString() : null;
    }

    private function assessment(): Assessment
    {
        $assessment = $this->route('assessment');
        abort_unless($assessment instanceof Assessment, 404);

        return $assessment;
    }
}
