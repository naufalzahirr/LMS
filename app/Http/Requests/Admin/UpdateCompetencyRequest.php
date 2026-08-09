<?php

namespace App\Http\Requests\Admin;

use App\Enums\AcademicStatus;
use App\Models\Competency;
use App\Models\Course;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UpdateCompetencyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->competency()) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $competency = $this->competency();
        $courseId = $this->integer('course_id');

        return [
            'course_id' => ['required', 'integer', Rule::exists(Course::class, 'id')->whereNull('deleted_at')],
            'code' => ['required', 'string', 'max:50', Rule::unique(Competency::class)->where('course_id', $courseId)->ignore($competency)],
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'alpha_dash:ascii', Rule::unique(Competency::class)->where('course_id', $courseId)->ignore($competency)],
            'description' => ['nullable', 'string'],
            'learning_objectives' => ['nullable', 'string'],
            'sort_order' => ['required', 'integer', 'min:0'],
            'status' => ['required', Rule::enum(AcademicStatus::class)],
        ];
    }

    /**
     * @return array{course_id: int, code: string, name: string, slug: string, description: string|null, learning_objectives: string|null, sort_order: int, status: AcademicStatus}
     */
    public function payload(): array
    {
        return [
            'course_id' => $this->integer('course_id'),
            'code' => $this->string('code')->toString(),
            'name' => $this->string('name')->toString(),
            'slug' => $this->string('slug')->toString(),
            'description' => $this->filled('description') ? $this->string('description')->toString() : null,
            'learning_objectives' => $this->filled('learning_objectives') ? $this->string('learning_objectives')->toString() : null,
            'sort_order' => $this->integer('sort_order'),
            'status' => AcademicStatus::from($this->string('status')->toString()),
        ];
    }

    protected function prepareForValidation(): void
    {
        if (! $this->filled('slug') && $this->filled('name')) {
            $this->merge(['slug' => Str::slug($this->string('name')->toString())]);
        }
    }

    private function competency(): Competency
    {
        $competency = $this->route('competency');

        abort_unless($competency instanceof Competency, 404);

        return $competency;
    }
}
