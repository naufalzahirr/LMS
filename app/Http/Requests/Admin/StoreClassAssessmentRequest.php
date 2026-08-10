<?php

namespace App\Http\Requests\Admin;

use App\Enums\ClassAssessmentStatus;
use App\Models\Assessment;
use App\Models\LearningClass;
use App\Models\LearningClassAssessment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreClassAssessmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', [LearningClassAssessment::class, $this->learningClass()]) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'assessment_id' => ['required', 'integer', Rule::exists(Assessment::class, 'id')->whereNull('deleted_at')],
            'opens_at' => ['nullable', 'date'],
            'closes_at' => ['nullable', 'date', 'after:opens_at'],
            'max_attempts' => ['required', 'integer', 'min:1'],
            'status' => ['required', Rule::enum(ClassAssessmentStatus::class)],
        ];
    }

    /** @return array{assessment_id: int, opens_at: string|null, closes_at: string|null, max_attempts: int, status: ClassAssessmentStatus} */
    public function payload(): array
    {
        return [
            'assessment_id' => $this->integer('assessment_id'),
            'opens_at' => $this->filled('opens_at') ? $this->string('opens_at')->toString() : null,
            'closes_at' => $this->filled('closes_at') ? $this->string('closes_at')->toString() : null,
            'max_attempts' => $this->integer('max_attempts'),
            'status' => ClassAssessmentStatus::from($this->string('status')->toString()),
        ];
    }

    public function assessment(): Assessment
    {
        return Assessment::query()->findOrFail($this->integer('assessment_id'));
    }

    private function learningClass(): LearningClass
    {
        $learningClass = $this->route('learningClass');
        abort_unless($learningClass instanceof LearningClass, 404);

        return $learningClass;
    }
}
