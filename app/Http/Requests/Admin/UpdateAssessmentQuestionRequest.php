<?php

namespace App\Http\Requests\Admin;

use App\Models\Assessment;
use Illuminate\Foundation\Http\FormRequest;

class UpdateAssessmentQuestionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->assessment()) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['points' => ['required', 'numeric', 'gt:0', 'decimal:0,2']];
    }

    public function points(): string
    {
        return $this->string('points')->toString();
    }

    private function assessment(): Assessment
    {
        $assessment = $this->route('assessment');
        abort_unless($assessment instanceof Assessment, 404);

        return $assessment;
    }
}
