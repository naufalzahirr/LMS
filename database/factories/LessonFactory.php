<?php

namespace Database\Factories;

use App\Enums\AcademicStatus;
use App\Enums\LessonType;
use App\Models\Lesson;
use App\Models\Module;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Lesson>
 */
class LessonFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = str(fake()->unique()->sentence(4))->trim('.')->toString();
        $slugSuffix = fake()->unique()->bothify('###');
        $content = fake()->paragraph();

        return [
            'module_id' => Module::factory(),
            'title' => str($title)->title()->toString(),
            'slug' => str($title)->slug()->append('-', $slugSuffix)->toString(),
            'lesson_type' => LessonType::Text,
            'content' => $content,
            'external_url' => null,
            'file_path' => null,
            'content_document' => [
                'type' => 'doc',
                'content' => [[
                    'type' => 'paragraph',
                    'content' => [['type' => 'text', 'text' => $content]],
                ]],
            ],
            'duration_minutes' => fake()->numberBetween(10, 60),
            'sort_order' => fake()->numberBetween(0, 20),
            'status' => AcademicStatus::Active,
        ];
    }

    public function video(): static
    {
        return $this->state(fn (): array => [
            'lesson_type' => LessonType::Video,
            'content' => 'Optional teacher notes for this video.',
            'external_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
            'file_path' => null,
            'content_document' => null,
        ]);
    }

    public function link(): static
    {
        return $this->state(fn (): array => [
            'lesson_type' => LessonType::Link,
            'content' => 'A useful external learning resource.',
            'external_url' => 'https://developer.mozilla.org/',
            'file_path' => null,
            'content_document' => null,
        ]);
    }

    public function document(): static
    {
        return $this->state(fn (): array => [
            'lesson_type' => LessonType::Document,
            'content' => 'Notes for the accompanying document.',
            'external_url' => null,
            'file_path' => null,
            'content_document' => null,
        ])->afterCreating(function (Lesson $lesson): void {
            $lesson->forceFill(['file_path' => "lesson-files/{$lesson->id}/document.pdf"])->save();
        });
    }

    public function image(): static
    {
        return $this->state(fn (): array => [
            'lesson_type' => LessonType::Image,
            'content' => 'Notes for the accompanying image.',
            'external_url' => null,
            'file_path' => null,
            'content_document' => null,
        ])->afterCreating(function (Lesson $lesson): void {
            $lesson->forceFill(['file_path' => "lesson-files/{$lesson->id}/image.png"])->save();
        });
    }
}
