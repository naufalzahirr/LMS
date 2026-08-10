<?php

namespace App\Http\Requests\Student;

use App\Enums\LessonProgressStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateLessonProgressRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('Student') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(LessonProgressStatus::class)],
        ];
    }

    public function status(): LessonProgressStatus
    {
        return LessonProgressStatus::from($this->string('status')->toString());
    }
}
