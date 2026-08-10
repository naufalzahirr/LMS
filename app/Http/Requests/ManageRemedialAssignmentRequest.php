<?php

namespace App\Http\Requests;

use App\Models\Lesson;
use App\Models\RemedialAssignment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ManageRemedialAssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $assignment = $this->route('remedialAssignment');

        return $assignment instanceof RemedialAssignment
            && ($this->user()?->can('manage', $assignment) ?? false);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'lesson_id' => ['sometimes', 'required', 'integer', Rule::exists(Lesson::class, 'id')->whereNull('deleted_at')],
            'notes' => ['sometimes', 'nullable', 'string'],
        ];
    }
}
