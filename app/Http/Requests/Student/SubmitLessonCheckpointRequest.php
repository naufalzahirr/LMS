<?php

namespace App\Http\Requests\Student;

use App\Enums\LessonCheckpointType;
use App\Models\LessonCheckpoint;
use Illuminate\Foundation\Http\FormRequest;

class SubmitLessonCheckpointRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return match ($this->checkpoint()->checkpoint_type) {
            LessonCheckpointType::MultipleChoice => [
                'answer' => ['required', 'string', 'uuid'],
            ],
            LessonCheckpointType::MultipleSelect => [
                'answer' => ['required', 'array', 'min:1', 'max:10'],
                'answer.*' => ['required', 'string', 'uuid', 'distinct'],
            ],
            LessonCheckpointType::TrueFalse => [
                'answer' => ['required', 'boolean'],
            ],
            LessonCheckpointType::FillBlank => [
                'answer' => ['required', 'string', 'max:500'],
            ],
        };
    }

    /** @return string|bool|array<int, mixed> */
    public function answer(): string|bool|array
    {
        $answer = $this->validated('answer');

        if (is_string($answer) || is_bool($answer) || is_array($answer)) {
            return $answer;
        }

        abort(422);
    }

    private function checkpoint(): LessonCheckpoint
    {
        $checkpoint = $this->route('checkpoint');
        abort_unless($checkpoint instanceof LessonCheckpoint, 404);

        return $checkpoint;
    }
}
