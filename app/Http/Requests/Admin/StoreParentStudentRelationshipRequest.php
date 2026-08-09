<?php

namespace App\Http\Requests\Admin;

use App\Enums\ParentRelationshipType;
use App\Models\ParentStudentRelationship;
use App\Models\User;
use App\Rules\UserHasRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreParentStudentRelationshipRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', ParentStudentRelationship::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'parent_id' => ['required', 'integer', Rule::exists(User::class, 'id'), new UserHasRole('Parent')],
            'student_id' => ['required', 'integer', 'different:parent_id', Rule::exists(User::class, 'id'), new UserHasRole('Student')],
            'relationship_type' => ['required', Rule::enum(ParentRelationshipType::class)],
        ];
    }

    public function parent(): User
    {
        return User::query()->findOrFail($this->integer('parent_id'));
    }

    public function student(): User
    {
        return User::query()->findOrFail($this->integer('student_id'));
    }

    public function relationshipType(): ParentRelationshipType
    {
        return ParentRelationshipType::from($this->string('relationship_type')->toString());
    }
}
