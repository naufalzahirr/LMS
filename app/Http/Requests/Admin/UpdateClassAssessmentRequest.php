<?php

namespace App\Http\Requests\Admin;

use App\Enums\AssessmentFeedbackMode;
use App\Enums\ClassAssessmentStatus;
use App\Models\LearningClassAssessment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateClassAssessmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->assignment()) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'opens_at' => ['nullable', 'date'],
            'closes_at' => ['nullable', 'date', 'after:opens_at'],
            'max_attempts' => ['required', 'integer', 'min:1'],
            'status' => ['required', Rule::enum(ClassAssessmentStatus::class)],
            'feedback_mode' => ['required', Rule::enum(AssessmentFeedbackMode::class)],
        ];
    }

    /** @return array{opens_at: string|null, closes_at: string|null, max_attempts: int, status: ClassAssessmentStatus, feedback_mode: AssessmentFeedbackMode} */
    public function payload(): array
    {
        return [
            'opens_at' => $this->filled('opens_at') ? $this->string('opens_at')->toString() : null,
            'closes_at' => $this->filled('closes_at') ? $this->string('closes_at')->toString() : null,
            'max_attempts' => $this->integer('max_attempts'),
            'status' => ClassAssessmentStatus::from($this->string('status')->toString()),
            'feedback_mode' => AssessmentFeedbackMode::from($this->string('feedback_mode')->toString()),
        ];
    }

    private function assignment(): LearningClassAssessment
    {
        $assignment = $this->route('assignment');
        abort_unless($assignment instanceof LearningClassAssessment, 404);

        return $assignment;
    }
}
