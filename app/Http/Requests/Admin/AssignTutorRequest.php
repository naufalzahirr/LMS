<?php

namespace App\Http\Requests\Admin;

use App\Models\LearningClass;
use App\Models\User;
use App\Rules\UserHasRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssignTutorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manageTutors', $this->learningClass()) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'tutor_id' => ['required', 'integer', Rule::exists(User::class, 'id'), new UserHasRole('Tutor')],
        ];
    }

    public function tutor(): User
    {
        return User::query()->findOrFail($this->integer('tutor_id'));
    }

    private function learningClass(): LearningClass
    {
        $learningClass = $this->route('learningClass');

        abort_unless($learningClass instanceof LearningClass, 404);

        return $learningClass;
    }
}
