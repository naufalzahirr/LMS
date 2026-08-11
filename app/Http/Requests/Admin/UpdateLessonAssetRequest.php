<?php

namespace App\Http\Requests\Admin;

use App\Enums\LessonAssetType;
use App\Models\Lesson;
use App\Models\LessonAsset;
use Illuminate\Foundation\Http\FormRequest;

class UpdateLessonAssetRequest extends FormRequest
{
    public function authorize(): bool
    {
        $lesson = $this->route('lesson');
        $asset = $this->route('asset');

        return $lesson instanceof Lesson
            && $asset instanceof LessonAsset
            && $asset->lesson_id === $lesson->id
            && ($this->user()?->can('update', $lesson) ?? false);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $asset = $this->route('asset');
        $isImage = $asset instanceof LessonAsset && $asset->asset_type === LessonAssetType::Image;

        return [
            'alt_text' => [$isImage ? 'required' : 'prohibited', 'nullable', 'string', 'max:500'],
            'caption' => ['nullable', 'string', 'max:2000'],
            'file' => ['prohibited'],
            'file_path' => ['prohibited'],
            'lesson_id' => ['prohibited'],
            'asset_type' => ['prohibited'],
        ];
    }

    /** @return array{alt_text?: string|null, caption?: string|null} */
    public function metadata(): array
    {
        return [
            'alt_text' => $this->filled('alt_text') ? trim($this->string('alt_text')->toString()) : null,
            'caption' => $this->filled('caption') ? trim($this->string('caption')->toString()) : null,
        ];
    }
}
