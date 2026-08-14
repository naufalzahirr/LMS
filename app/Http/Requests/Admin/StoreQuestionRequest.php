<?php

namespace App\Http\Requests\Admin;

use App\Enums\AcademicStatus;
use App\Enums\QuestionType;
use App\Models\Competency;
use App\Models\Question;
use App\Models\QuestionBank;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;

class StoreQuestionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $bank = QuestionBank::query()->find($this->integer('question_bank_id'));
        $competency = Competency::query()->find($this->integer('competency_id'));

        return $bank instanceof QuestionBank && $competency instanceof Competency
            && ($this->user()?->can('create', [Question::class, $bank, $competency]) ?? false);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'question_bank_id' => ['required', 'integer', Rule::exists(QuestionBank::class, 'id')->whereNull('deleted_at')],
            'competency_id' => ['required', 'integer', Rule::exists(Competency::class, 'id')->whereNull('deleted_at')],
            'question_type' => ['required', Rule::enum(QuestionType::class)],
            'prompt' => ['required', 'string'],
            'explanation' => ['nullable', 'string'],
            'default_points' => ['required', 'numeric', 'gt:0', 'decimal:0,2'],
            'correct_boolean' => ['nullable', 'boolean'],
            'status' => ['required', Rule::enum(AcademicStatus::class)],
            'sort_order' => ['required', 'integer', 'min:0'],
            'options' => ['nullable', 'array'],
            'options.*.option_text' => ['nullable', 'string'],
            'options.*.is_correct' => ['nullable', 'boolean'],
            'options.*.sort_order' => ['nullable', 'integer', 'min:0'],
            'accepted_answers' => ['nullable', 'array'],
            'accepted_answers.*.answer_text' => ['nullable', 'string'],
            'accepted_answers.*.case_sensitive' => ['nullable', 'boolean'],
            // Same envelope as a lesson image: private disk, real MIME check,
            // 10 MB ceiling, and alt text required whenever a file is present.
            'image' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
            'image_alt_text' => [
                Rule::requiredIf($this->hasFile('image')),
                'nullable',
                'string',
                'max:500',
            ],
            'remove_image' => ['sometimes', 'boolean'],
            'image_path' => ['prohibited'],
        ];
    }

    public function uploadedImage(): ?UploadedFile
    {
        $image = $this->file('image');

        return $image instanceof UploadedFile ? $image : null;
    }

    public function imageAltText(): ?string
    {
        $value = trim($this->string('image_alt_text')->toString());

        return $value === '' ? null : $value;
    }

    public function shouldRemoveImage(): bool
    {
        return $this->boolean('remove_image');
    }

    /**
     * @return array{
     *   question_bank_id: int, competency_id: int, question_type: QuestionType,
     *   prompt: string, explanation: string|null, default_points: string,
     *   correct_boolean: bool|null, status: AcademicStatus, sort_order: int,
     *   options: array<int, array{option_text: string, is_correct: bool, sort_order: int}>,
     *   accepted_answers: array<int, array{answer_text: string, case_sensitive: bool}>
     * }
     */
    public function payload(): array
    {
        $type = QuestionType::from($this->string('question_type')->toString());
        $options = [];

        foreach ($this->array('options') as $index => $option) {
            if (! is_array($option)) {
                continue;
            }
            $options[] = [
                'option_text' => is_string($option['option_text'] ?? null) ? $option['option_text'] : '',
                'is_correct' => filter_var($option['is_correct'] ?? false, FILTER_VALIDATE_BOOL),
                'sort_order' => is_numeric($option['sort_order'] ?? null) ? (int) $option['sort_order'] : $index,
            ];
        }

        $answers = [];
        foreach ($this->array('accepted_answers') as $answer) {
            if (! is_array($answer)) {
                continue;
            }
            $answers[] = [
                'answer_text' => is_string($answer['answer_text'] ?? null) ? $answer['answer_text'] : '',
                'case_sensitive' => filter_var($answer['case_sensitive'] ?? false, FILTER_VALIDATE_BOOL),
            ];
        }

        return [
            'question_bank_id' => $this->integer('question_bank_id'),
            'competency_id' => $this->integer('competency_id'),
            'question_type' => $type,
            'prompt' => $this->string('prompt')->toString(),
            'explanation' => $this->filled('explanation') ? $this->string('explanation')->toString() : null,
            'default_points' => $this->string('default_points')->toString(),
            'correct_boolean' => $type === QuestionType::TrueFalse && $this->filled('correct_boolean')
                ? $this->boolean('correct_boolean')
                : null,
            'status' => AcademicStatus::from($this->string('status')->toString()),
            'sort_order' => $this->integer('sort_order'),
            'options' => $options,
            'accepted_answers' => $answers,
        ];
    }
}
