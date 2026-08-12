<?php

namespace Database\Factories;

use App\Enums\LessonCheckpointType;
use App\Models\Lesson;
use App\Models\LessonCheckpoint;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<LessonCheckpoint> */
class LessonCheckpointFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        $correctId = (string) Str::uuid();
        $incorrectId = (string) Str::uuid();

        return [
            'lesson_id' => Lesson::factory(),
            'checkpoint_type' => LessonCheckpointType::MultipleChoice,
            'prompt' => 'Which answer is correct?',
            'explanation' => 'Review the explanation and try again if needed.',
            'configuration' => ['options' => [
                ['id' => $correctId, 'text' => 'Correct answer'],
                ['id' => $incorrectId, 'text' => 'Incorrect answer'],
            ]],
            'answer_key' => ['correct_option_ids' => [$correctId]],
            'created_by' => User::factory(),
        ];
    }
}
