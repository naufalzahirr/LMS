<?php

namespace App\Http\Requests\Admin;

use App\Models\Lesson;
use Illuminate\Foundation\Http\FormRequest;
use JsonException;

class PreviewLessonRequest extends FormRequest
{
    public function authorize(): bool
    {
        $lesson = $this->route('lesson');

        return $lesson instanceof Lesson && ($this->user()?->can('update', $lesson) ?? false);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'content_document' => ['required', 'array', $this->documentSizeRule()],
            'content_document.type' => ['required', 'in:doc'],
            'content_document.content' => ['required', 'array'],
        ];
    }

    /** @return array<string, mixed> */
    public function document(): array
    {
        /** @var array<string, mixed> $document */
        $document = $this->validated('content_document');

        return $document;
    }

    protected function prepareForValidation(): void
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
            // Leave malformed input for the array validation rule.
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
}
