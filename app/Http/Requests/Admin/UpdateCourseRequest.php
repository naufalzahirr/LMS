<?php

namespace App\Http\Requests\Admin;

use App\Enums\AcademicStatus;
use App\Models\Course;
use App\Models\Program;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UpdateCourseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->course()) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $course = $this->course();

        return [
            'program_id' => ['required', 'integer', Rule::exists(Program::class, 'id')->whereNull('deleted_at')],
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'alpha_dash:ascii', Rule::unique(Course::class)->ignore($course)],
            'code' => ['nullable', 'string', 'max:50', Rule::unique(Course::class)->ignore($course)],
            'description' => ['nullable', 'string'],
            'status' => ['required', Rule::enum(AcademicStatus::class)],
            'sort_order' => ['required', 'integer', 'min:0'],
        ];
    }

    /**
     * @return array{program_id: int, name: string, slug: string, code: string|null, description: string|null, status: AcademicStatus, sort_order: int}
     */
    public function payload(): array
    {
        return [
            'program_id' => $this->integer('program_id'),
            'name' => $this->string('name')->toString(),
            'slug' => $this->string('slug')->toString(),
            'code' => $this->filled('code') ? $this->string('code')->toString() : null,
            'description' => $this->filled('description') ? $this->string('description')->toString() : null,
            'status' => AcademicStatus::from($this->string('status')->toString()),
            'sort_order' => $this->integer('sort_order'),
        ];
    }

    protected function prepareForValidation(): void
    {
        if (! $this->filled('slug') && $this->filled('name')) {
            $this->merge(['slug' => Str::slug($this->string('name')->toString())]);
        }
    }

    private function course(): Course
    {
        $course = $this->route('course');

        abort_unless($course instanceof Course, 404);

        return $course;
    }
}
