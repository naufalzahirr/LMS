<?php

namespace App\Http\Requests\Admin;

use App\Models\Assessment;
use App\Models\Competency;
use Illuminate\Validation\Rule;

class UpdateAssessmentRequest extends StoreAssessmentRequest
{
    public function authorize(): bool
    {
        $competency = Competency::query()->find($this->integer('competency_id'));

        return $competency instanceof Competency
            && $this->user()->can('update', $this->assessment())
            && $this->user()->can('create', [Assessment::class, $competency]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $rules = parent::rules();
        $rules['code'] = ['nullable', 'string', 'max:50', Rule::unique(Assessment::class)
            ->where('competency_id', $this->integer('competency_id'))->ignore($this->assessment())];

        return $rules;
    }

    private function assessment(): Assessment
    {
        $assessment = $this->route('assessment');
        abort_unless($assessment instanceof Assessment, 404);

        return $assessment;
    }
}
