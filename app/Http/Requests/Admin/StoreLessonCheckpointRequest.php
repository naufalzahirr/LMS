<?php

namespace App\Http\Requests\Admin;

use App\Enums\LessonCheckpointType;
use App\Models\Lesson;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreLessonCheckpointRequest extends FormRequest
{
    public function authorize(): bool
    {
        $lesson = $this->route('lesson');

        return $lesson instanceof Lesson && ($this->user()?->can('update', $lesson) ?? false);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $type = LessonCheckpointType::tryFrom($this->string('checkpoint_type')->toString());
        $usesOptions = in_array($type, [
            LessonCheckpointType::MultipleChoice,
            LessonCheckpointType::MultipleSelect,
        ], true);

        return [
            'checkpoint_type' => ['required', Rule::enum(LessonCheckpointType::class)],
            'prompt' => ['required', 'string', 'max:5000'],
            'correct_feedback' => ['nullable', 'string', 'max:1000'],
            'incorrect_feedback' => ['nullable', 'string', 'max:1000'],
            'explanation' => ['nullable', 'string', 'max:10000'],
            'options' => $usesOptions ? ['required', 'array', 'min:2', 'max:10'] : ['prohibited'],
            'options.*.id' => ['required', 'uuid', 'distinct'],
            'options.*.text' => ['required', 'string', 'max:500'],
            'correct_option_ids' => $usesOptions ? ['required', 'array', 'min:1', 'max:10'] : ['prohibited'],
            'correct_option_ids.*' => ['required', 'uuid', 'distinct'],
            'correct_boolean' => $type === LessonCheckpointType::TrueFalse
                ? ['required', 'boolean']
                : ['prohibited'],
            'accepted_answers' => $type === LessonCheckpointType::FillBlank
                ? ['required', 'array', 'min:1', 'max:10']
                : ['prohibited'],
            'accepted_answers.*' => ['required', 'string', 'max:500'],
        ];
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $type = LessonCheckpointType::tryFrom($this->string('checkpoint_type')->toString());

            if (in_array($type, [LessonCheckpointType::MultipleChoice, LessonCheckpointType::MultipleSelect], true)) {
                $optionIds = collect($this->array('options'))
                    ->map(fn (mixed $option): mixed => is_array($option) ? ($option['id'] ?? null) : null)
                    ->filter(fn (mixed $id): bool => is_string($id))
                    ->values();
                $correctIds = collect($this->array('correct_option_ids'))
                    ->filter(fn (mixed $id): bool => is_string($id))
                    ->values();

                if ($correctIds->contains(fn (string $id): bool => ! $optionIds->containsStrict($id))) {
                    $validator->errors()->add(
                        'correct_option_ids',
                        __('Every correct answer must reference one of the configured options.'),
                    );
                }

                if ($type === LessonCheckpointType::MultipleChoice && $correctIds->count() !== 1) {
                    $validator->errors()->add(
                        'correct_option_ids',
                        __('Multiple choice checkpoints require exactly one correct answer.'),
                    );
                }
            }

            if ($type === LessonCheckpointType::FillBlank) {
                $normalized = collect($this->array('accepted_answers'))
                    ->filter(fn (mixed $answer): bool => is_string($answer))
                    ->map(fn (string $answer): string => mb_strtolower(trim($answer)));

                if ($normalized->contains('')) {
                    $validator->errors()->add(
                        'accepted_answers',
                        __('Accepted answers cannot be blank.'),
                    );
                }

                if ($normalized->unique()->count() !== $normalized->count()) {
                    $validator->errors()->add(
                        'accepted_answers',
                        __('Accepted answers must be unique when compared without case.'),
                    );
                }
            }
        }];
    }

    /**
     * @return array{
     *     checkpoint_type: LessonCheckpointType,
     *     prompt: string,
     *     correct_feedback: string|null,
     *     incorrect_feedback: string|null,
     *     explanation: string|null,
     *     configuration: array<string, mixed>,
     *     answer_key: array<string, mixed>
     * }
     */
    public function payload(): array
    {
        $type = LessonCheckpointType::from($this->string('checkpoint_type')->toString());
        $configuration = [];
        $answerKey = [];

        if (in_array($type, [LessonCheckpointType::MultipleChoice, LessonCheckpointType::MultipleSelect], true)) {
            $configuration = [
                'options' => collect($this->array('options'))->map(function (mixed $option): array {
                    /** @var array{id: string, text: string} $option */
                    return ['id' => $option['id'], 'text' => trim($option['text'])];
                })->values()->all(),
            ];
            $answerKey = [
                'correct_option_ids' => collect($this->array('correct_option_ids'))
                    ->map(fn (mixed $id): string => (string) $id)
                    ->values()
                    ->all(),
            ];
        } elseif ($type === LessonCheckpointType::TrueFalse) {
            $answerKey = ['correct_boolean' => $this->boolean('correct_boolean')];
        } else {
            $answerKey = [
                'accepted_answers' => collect($this->array('accepted_answers'))
                    ->map(fn (mixed $answer): string => trim((string) $answer))
                    ->values()
                    ->all(),
            ];
        }

        return [
            'checkpoint_type' => $type,
            'prompt' => trim($this->string('prompt')->toString()),
            'correct_feedback' => $this->trimmedOrNull('correct_feedback'),
            'incorrect_feedback' => $this->trimmedOrNull('incorrect_feedback'),
            'explanation' => $this->filled('explanation')
                ? trim($this->string('explanation')->toString())
                : null,
            'configuration' => $configuration,
            'answer_key' => $answerKey,
        ];
    }

    /**
     * Clearing an optional feedback field must persist as null so the shared
     * default applies again, rather than storing an empty string.
     */
    private function trimmedOrNull(string $key): ?string
    {
        $value = trim($this->string($key)->toString());

        return $value === '' ? null : $value;
    }
}
