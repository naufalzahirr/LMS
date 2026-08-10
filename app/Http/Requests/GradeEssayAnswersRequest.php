<?php

namespace App\Http\Requests;

use App\Models\AssessmentAttempt;
use Illuminate\Foundation\Http\FormRequest;

class GradeEssayAnswersRequest extends FormRequest
{
    public function authorize(): bool
    {
        $attempt = $this->route('attempt');

        return $attempt instanceof AssessmentAttempt && $this->user()->can('grade', $attempt);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'grades' => ['required', 'array', 'min:1'],
            'grades.*.attempt_question_id' => ['required', 'integer', 'distinct'],
            'grades.*.manual_score' => ['required', 'numeric', 'min:0', 'decimal:0,2'],
            'grades.*.feedback' => ['nullable', 'string'],
        ];
    }

    /** @return array<int, array{attempt_question_id: int, manual_score: string, feedback: string|null}> */
    public function grades(): array
    {
        $grades = [];

        foreach ($this->array('grades') as $grade) {
            if (! is_array($grade)) {
                continue;
            }

            $grades[] = [
                'attempt_question_id' => (int) ($grade['attempt_question_id'] ?? 0),
                'manual_score' => (string) ($grade['manual_score'] ?? ''),
                'feedback' => is_string($grade['feedback'] ?? null) && $grade['feedback'] !== ''
                    ? $grade['feedback']
                    : null,
            ];
        }

        return $grades;
    }
}
