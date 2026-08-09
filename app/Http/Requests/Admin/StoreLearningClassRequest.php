<?php

namespace App\Http\Requests\Admin;

use App\Enums\LearningClassStatus;
use App\Models\Course;
use App\Models\LearningClass;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLearningClassRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', LearningClass::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'course_id' => ['required', 'integer', Rule::exists(Course::class, 'id')->whereNull('deleted_at')],
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:50', 'alpha_dash:ascii', Rule::unique(LearningClass::class)],
            'description' => ['nullable', 'string'],
            'start_date' => ['nullable', 'date_format:Y-m-d'],
            'end_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'status' => ['required', Rule::enum(LearningClassStatus::class)],
        ];
    }

    /**
     * @return array{course_id: int, name: string, code: string, description: string|null, start_date: string|null, end_date: string|null, status: LearningClassStatus}
     */
    public function payload(): array
    {
        return [
            'course_id' => $this->integer('course_id'),
            'name' => $this->string('name')->toString(),
            'code' => $this->string('code')->toString(),
            'description' => $this->filled('description') ? $this->string('description')->toString() : null,
            'start_date' => $this->filled('start_date') ? $this->string('start_date')->toString() : null,
            'end_date' => $this->filled('end_date') ? $this->string('end_date')->toString() : null,
            'status' => LearningClassStatus::from($this->string('status')->toString()),
        ];
    }
}
