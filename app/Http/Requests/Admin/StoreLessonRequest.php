<?php

namespace App\Http\Requests\Admin;

use App\Enums\AcademicStatus;
use App\Enums\LessonType;
use App\Models\Lesson;
use App\Models\Module;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StoreLessonRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Lesson::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $moduleId = $this->integer('module_id');
        $lessonType = LessonType::tryFrom($this->string('lesson_type')->toString());

        return [
            'module_id' => ['required', 'integer', Rule::exists(Module::class, 'id')->whereNull('deleted_at')],
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'alpha_dash:ascii', Rule::unique(Lesson::class)->where('module_id', $moduleId)],
            'lesson_type' => ['required', Rule::enum(LessonType::class)],
            'content' => [Rule::requiredIf($lessonType === LessonType::Text), 'nullable', 'string'],
            'external_url' => [Rule::requiredIf(in_array($lessonType, [LessonType::Video, LessonType::Link], true)), 'nullable', 'string', 'max:2048', 'url:http,https'],
            'file' => $this->fileRules($lessonType, $lessonType?->usesUploadedFile() ?? false),
            'duration_minutes' => ['nullable', 'integer', 'min:0'],
            'sort_order' => ['required', 'integer', 'min:0'],
            'status' => ['required', Rule::enum(AcademicStatus::class)],
        ];
    }

    /**
     * @return array{module_id: int, title: string, slug: string, lesson_type: LessonType, content: string|null, external_url: string|null, duration_minutes: int|null, sort_order: int, status: AcademicStatus}
     */
    public function payload(): array
    {
        return [
            'module_id' => $this->integer('module_id'),
            'title' => $this->string('title')->toString(),
            'slug' => $this->string('slug')->toString(),
            'lesson_type' => LessonType::from($this->string('lesson_type')->toString()),
            'content' => $this->filled('content') ? $this->string('content')->toString() : null,
            'external_url' => $this->filled('external_url') ? $this->string('external_url')->toString() : null,
            'duration_minutes' => $this->filled('duration_minutes') ? $this->integer('duration_minutes') : null,
            'sort_order' => $this->integer('sort_order'),
            'status' => AcademicStatus::from($this->string('status')->toString()),
        ];
    }

    public function uploadedFile(): ?UploadedFile
    {
        $file = $this->file('file');

        return $file instanceof UploadedFile ? $file : null;
    }

    protected function prepareForValidation(): void
    {
        if (! $this->filled('slug') && $this->filled('title')) {
            $this->merge(['slug' => Str::slug($this->string('title')->toString())]);
        }
    }

    /**
     * @return array<int, mixed>
     */
    private function fileRules(?LessonType $lessonType, bool $required): array
    {
        return match ($lessonType) {
            LessonType::Document => [$required ? 'required' : 'nullable', 'file', 'mimes:pdf', 'max:20480'],
            LessonType::Image => [$required ? 'required' : 'nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
            default => ['prohibited'],
        };
    }
}
