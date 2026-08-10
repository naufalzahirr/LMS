<?php

namespace App\Http\Requests\Admin;

use App\Enums\AssessmentPurpose;
use App\Models\Assessment;
use App\Models\Competency;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAssessmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $competency = Competency::query()->find($this->integer('competency_id'));

        return $competency instanceof Competency
            && ($this->user()?->can('create', [Assessment::class, $competency]) ?? false);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $competencyId = $this->integer('competency_id');

        return [
            'competency_id' => ['required', 'integer', Rule::exists(Competency::class, 'id')->whereNull('deleted_at')],
            'title' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:50', Rule::unique(Assessment::class)->where('competency_id', $competencyId)],
            'description' => ['nullable', 'string'],
            'purpose' => ['required', Rule::enum(AssessmentPurpose::class)],
            'instructions' => ['nullable', 'string'],
            'shuffle_questions' => ['nullable', 'boolean'],
        ];
    }

    /** @return array{competency_id: int, title: string, code: string|null, description: string|null, purpose: AssessmentPurpose, instructions: string|null, shuffle_questions: bool} */
    public function payload(): array
    {
        return [
            'competency_id' => $this->integer('competency_id'),
            'title' => trim($this->string('title')->toString()),
            'code' => $this->filled('code') ? trim($this->string('code')->toString()) : null,
            'description' => $this->filled('description') ? $this->string('description')->toString() : null,
            'purpose' => AssessmentPurpose::from($this->string('purpose')->toString()),
            'instructions' => $this->filled('instructions') ? $this->string('instructions')->toString() : null,
            'shuffle_questions' => $this->boolean('shuffle_questions'),
        ];
    }
}
