<?php

namespace App\Http\Requests\Admin;

use App\Enums\AcademicStatus;
use App\Models\Course;
use App\Models\QuestionBank;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreQuestionBankRequest extends FormRequest
{
    public function authorize(): bool
    {
        $course = Course::query()->find($this->integer('course_id'));

        return $course instanceof Course && ($this->user()?->can('create', [QuestionBank::class, $course]) ?? false);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $courseId = $this->integer('course_id');

        return [
            'course_id' => ['required', 'integer', Rule::exists(Course::class, 'id')->whereNull('deleted_at')],
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:50', Rule::unique(QuestionBank::class)->where('course_id', $courseId)],
            'description' => ['nullable', 'string'],
            'status' => ['required', Rule::enum(AcademicStatus::class)],
        ];
    }

    /** @return array{course_id: int, name: string, code: string|null, description: string|null, status: AcademicStatus} */
    public function payload(): array
    {
        return [
            'course_id' => $this->integer('course_id'),
            'name' => trim($this->string('name')->toString()),
            'code' => $this->filled('code') ? trim($this->string('code')->toString()) : null,
            'description' => $this->filled('description') ? $this->string('description')->toString() : null,
            'status' => AcademicStatus::from($this->string('status')->toString()),
        ];
    }
}
