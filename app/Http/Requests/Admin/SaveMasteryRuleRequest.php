<?php

namespace App\Http\Requests\Admin;

use App\Enums\MasteryRuleStatus;
use App\Models\LearningClass;
use App\Models\LearningClassAssessment;
use App\Models\Lesson;
use App\Models\MasteryRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveMasteryRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        $learningClass = $this->route('learningClass');

        return $learningClass instanceof LearningClass
            && ($this->user()?->can('manage', [MasteryRule::class, $learningClass]) ?? false);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'learning_class_assessment_id' => ['required', 'integer', Rule::exists(LearningClassAssessment::class, 'id')],
            'mastery_score' => ['required', 'numeric', 'gt:0', 'max:100', 'decimal:0,2'],
            'require_remedial' => ['required', 'boolean'],
            'status' => ['required', Rule::enum(MasteryRuleStatus::class)],
            'remedial_lesson_ids' => ['nullable', 'array'],
            'remedial_lesson_ids.*' => ['integer', 'distinct', Rule::exists(Lesson::class, 'id')->whereNull('deleted_at')],
        ];
    }

    /** @return array{learning_class_assessment_id: int, mastery_score: string, require_remedial: bool, status: MasteryRuleStatus, remedial_lesson_ids: array<int, int>} */
    public function payload(): array
    {
        return [
            'learning_class_assessment_id' => $this->integer('learning_class_assessment_id'),
            'mastery_score' => $this->string('mastery_score')->toString(),
            'require_remedial' => $this->boolean('require_remedial'),
            'status' => MasteryRuleStatus::from($this->string('status')->toString()),
            'remedial_lesson_ids' => array_map('intval', $this->array('remedial_lesson_ids')),
        ];
    }
}
