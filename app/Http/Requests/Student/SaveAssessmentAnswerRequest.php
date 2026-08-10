<?php

namespace App\Http\Requests\Student;

use App\Models\AssessmentAttempt;
use Illuminate\Foundation\Http\FormRequest;

class SaveAssessmentAnswerRequest extends FormRequest
{
    public function authorize(): bool
    {
        $attempt = $this->route('attempt');

        return $attempt instanceof AssessmentAttempt && $this->user()->can('update', $attempt);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'answer_text' => ['nullable', 'string'],
            'answer_boolean' => ['nullable', 'boolean'],
            'selected_option_ids' => ['nullable', 'array'],
            'selected_option_ids.*' => ['integer', 'distinct'],
        ];
    }

    /** @return array{answer_text: string|null, answer_boolean: bool|null, selected_option_ids: array<int, int>} */
    public function payload(): array
    {
        return [
            'answer_text' => $this->filled('answer_text') ? $this->string('answer_text')->toString() : null,
            'answer_boolean' => $this->filled('answer_boolean') ? $this->boolean('answer_boolean') : null,
            'selected_option_ids' => array_map('intval', $this->array('selected_option_ids')),
        ];
    }
}
