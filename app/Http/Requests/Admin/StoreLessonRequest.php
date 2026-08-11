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
use JsonException;

class StoreLessonRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if ($this->integer('draft_id') > 0) {
            $draft = $this->draft();

            if (! $draft instanceof Lesson
                || ! $draft->is_authoring_draft
                || ! ($user?->can('update', $draft) ?? false)) {
                return false;
            }
        }

        if ($user?->hasRole('Admin')) {
            return $user->can('create', Lesson::class);
        }

        $module = Module::query()->find($this->integer('module_id'));

        return $module !== null && ($user?->can('create', [Lesson::class, $module]) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $moduleId = $this->integer('module_id');
        $lessonType = LessonType::tryFrom($this->string('lesson_type')->toString());
        $richContent = is_array($this->input('content_document'));
        $draft = $this->draft();

        return [
            'draft_id' => ['nullable', 'integer', Rule::exists(Lesson::class, 'id')->where(fn ($query) => $query
                ->where('is_authoring_draft', true)
                ->whereNull('deleted_at'))],
            'module_id' => ['required', 'integer', Rule::exists(Module::class, 'id')->whereNull('deleted_at')],
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'alpha_dash:ascii', Rule::unique(Lesson::class)->where('module_id', $moduleId)->ignore($draft)],
            'lesson_type' => [$richContent ? 'nullable' : 'required', Rule::enum(LessonType::class)],
            'content' => $richContent
                ? ['prohibited']
                : [Rule::requiredIf($lessonType === LessonType::Text), 'nullable', 'string'],
            'external_url' => $richContent
                ? ['prohibited']
                : [Rule::requiredIf(in_array($lessonType, [LessonType::Video, LessonType::Link], true)), 'nullable', 'string', 'max:2048', 'url:http,https'],
            'file' => $richContent
                ? ['prohibited']
                : $this->fileRules($lessonType, $lessonType?->usesUploadedFile() ?? false),
            'content_document' => ['nullable', 'array', $this->documentSizeRule()],
            'content_document.type' => [$richContent ? 'required' : 'nullable', 'in:doc'],
            'content_document.content' => [$richContent ? 'required' : 'nullable', 'array'],
            'duration_minutes' => ['nullable', 'integer', 'min:0'],
            'sort_order' => ['required', 'integer', 'min:0'],
            'status' => ['required', Rule::enum(AcademicStatus::class)],
        ];
    }

    /**
     * @return array{module_id: int, title: string, slug: string, lesson_type: LessonType, content: string|null, external_url: string|null, content_document: array<string, mixed>|null, rich_content: bool, duration_minutes: int|null, sort_order: int, status: AcademicStatus}
     */
    public function payload(): array
    {
        return [
            'module_id' => $this->integer('module_id'),
            'title' => $this->string('title')->toString(),
            'slug' => $this->string('slug')->toString(),
            'lesson_type' => LessonType::tryFrom($this->string('lesson_type')->toString()) ?? LessonType::Text,
            'content' => $this->filled('content') ? $this->string('content')->toString() : null,
            'external_url' => $this->filled('external_url') ? $this->string('external_url')->toString() : null,
            'content_document' => is_array($this->input('content_document')) ? $this->input('content_document') : null,
            'rich_content' => is_array($this->input('content_document')),
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

    public function draft(): ?Lesson
    {
        $id = $this->integer('draft_id');

        return $id > 0 ? Lesson::query()->find($id) : null;
    }

    protected function prepareForValidation(): void
    {
        $this->decodeContentDocument();

        if (! $this->filled('slug') && $this->filled('title')) {
            $this->merge(['slug' => Str::slug($this->string('title')->toString())]);
        }
    }

    private function decodeContentDocument(): void
    {
        $document = $this->input('content_document');

        if (! is_string($document)) {
            return;
        }

        try {
            $decoded = json_decode($document, true, 512, JSON_THROW_ON_ERROR);

            if (is_array($decoded)) {
                $this->merge(['content_document' => $decoded]);
            }
        } catch (JsonException) {
            // Leave malformed JSON in place so the array validation rule rejects it.
        }
    }

    /** @return \Closure(string, mixed, \Closure(string): void): void */
    private function documentSizeRule(): \Closure
    {
        return static function (string $attribute, mixed $value, \Closure $fail): void {
            if (is_array($value) && strlen((string) json_encode($value)) > 2_000_000) {
                $fail(__('The lesson document is too large.'));
            }
        };
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
