<?php

namespace App\Http\Requests\Admin;

use App\Enums\LessonAssetType;
use App\Models\Lesson;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;

class StoreLessonAssetRequest extends FormRequest
{
    public function authorize(): bool
    {
        $lesson = $this->route('lesson');

        return $lesson instanceof Lesson && ($this->user()?->can('update', $lesson) ?? false);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $type = LessonAssetType::tryFrom($this->string('asset_type')->toString());

        return [
            'asset_type' => ['required', Rule::enum(LessonAssetType::class)],
            'file' => match ($type) {
                LessonAssetType::Image => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
                LessonAssetType::Document => ['required', 'file', 'mimes:pdf', 'max:20480'],
                default => ['required', 'file'],
            },
            'alt_text' => [
                Rule::requiredIf($type === LessonAssetType::Image && ! $this->boolean('decorative')),
                'nullable',
                'string',
                'max:500',
            ],
            'decorative' => $type === LessonAssetType::Image
                ? ['sometimes', 'boolean']
                : ['prohibited'],
            'caption' => ['nullable', 'string', 'max:2000'],
            'file_path' => ['prohibited'],
            'lesson_id' => ['prohibited'],
        ];
    }

    public function assetType(): LessonAssetType
    {
        return LessonAssetType::from($this->string('asset_type')->toString());
    }

    public function uploadedFile(): UploadedFile
    {
        $file = $this->file('file');

        abort_unless($file instanceof UploadedFile, 422);

        return $file;
    }

    /** @return array{alt_text: string|null, caption: string|null} */
    public function metadata(): array
    {
        return [
            'alt_text' => $this->filled('alt_text') ? trim($this->string('alt_text')->toString()) : null,
            'caption' => $this->filled('caption') ? trim($this->string('caption')->toString()) : null,
        ];
    }
}
