<?php

namespace App\Http\Requests\Admin;

use App\Models\Competency;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCompetencyPrerequisiteRequest extends FormRequest
{
    public function authorize(): bool
    {
        $competency = $this->route('competency');

        return $competency instanceof Competency && ($this->user()?->can('update', $competency) ?? false);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'prerequisite_competency_id' => ['required', 'integer', Rule::exists(Competency::class, 'id')->whereNull('deleted_at')],
        ];
    }

    public function prerequisite(): Competency
    {
        return Competency::query()->findOrFail($this->integer('prerequisite_competency_id'));
    }
}
