<?php

namespace Database\Seeders;

use App\Enums\AcademicStatus;
use App\Enums\LessonType;
use App\Models\Competency;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\Program;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AcademicSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            $this->command->warn('Development academic sample was not seeded in production.');

            return;
        }

        DB::transaction(function (): void {
            $program = Program::withTrashed()->updateOrCreate(
                ['slug' => 'web-development'],
                [
                    'name' => 'Web Development',
                    'code' => 'WEB-DEV',
                    'description' => 'A practical web development learning program.',
                    'status' => AcademicStatus::Active,
                ],
            );
            $program->restore();

            $frontend = $this->course($program, [
                'name' => 'Frontend Fundamentals',
                'slug' => 'frontend-fundamentals',
                'code' => 'FE-101',
                'description' => 'Core browser technologies and responsive interfaces.',
                'sort_order' => 1,
            ]);

            $backend = $this->course($program, [
                'name' => 'Backend Fundamentals',
                'slug' => 'backend-fundamentals',
                'code' => 'BE-101',
                'description' => 'Server-side development, databases, and Laravel.',
                'sort_order' => 2,
            ]);

            $frontendCompetencies = $this->competencies($frontend, [
                ['HTML Fundamentals', 'HTML-01'],
                ['CSS Fundamentals', 'CSS-01'],
                ['Responsive Web Design', 'RWD-01'],
                ['JavaScript Fundamentals', 'JS-01'],
            ]);

            $this->competencies($backend, [
                ['PHP Fundamentals', 'PHP-01'],
                ['Database Fundamentals', 'DB-01'],
                ['Laravel Fundamentals', 'LARAVEL-01'],
            ]);

            $this->htmlContent($frontendCompetencies['HTML-01']);
        });
    }

    /**
     * @param  array{name: string, slug: string, code: string, description: string, sort_order: int}  $data
     */
    private function course(Program $program, array $data): Course
    {
        $course = Course::withTrashed()->updateOrCreate(
            ['slug' => $data['slug']],
            [
                ...$data,
                'program_id' => $program->id,
                'status' => AcademicStatus::Active,
            ],
        );
        $course->restore();

        return $course;
    }

    /**
     * @param  array<int, array{0: string, 1: string}>  $competencies
     * @return array<string, Competency>
     */
    private function competencies(Course $course, array $competencies): array
    {
        $seeded = [];

        foreach ($competencies as $index => [$name, $code]) {
            $competency = Competency::withTrashed()->updateOrCreate(
                [
                    'course_id' => $course->id,
                    'code' => $code,
                ],
                [
                    'name' => $name,
                    'slug' => str($name)->slug()->toString(),
                    'description' => "Core competency for {$name}.",
                    'learning_objectives' => "Understand and apply {$name} in practical work.",
                    'sort_order' => $index + 1,
                    'status' => AcademicStatus::Active,
                ],
            );
            $competency->restore();
            $seeded[$code] = $competency;
        }

        return $seeded;
    }

    private function htmlContent(Competency $competency): void
    {
        $gettingStarted = $this->module($competency, [
            'name' => 'Getting Started with HTML',
            'slug' => 'getting-started-with-html',
            'description' => 'An introduction to HTML and document structure.',
            'sort_order' => 1,
        ]);

        $elements = $this->module($competency, [
            'name' => 'Working with HTML Elements',
            'slug' => 'working-with-html-elements',
            'description' => 'Practice with the most useful semantic HTML elements.',
            'sort_order' => 2,
        ]);

        $this->lessons($gettingStarted, [
            [
                'title' => 'Introduction to HTML',
                'type' => LessonType::Text,
                'content' => 'HTML gives structure and meaning to content on the web.',
                'external_url' => null,
            ],
            [
                'title' => 'HTML Document Structure',
                'type' => LessonType::Text,
                'content' => 'Learn the doctype, html, head, and body elements that form every HTML document.',
                'external_url' => null,
            ],
            [
                'title' => 'Useful HTML References',
                'type' => LessonType::Link,
                'content' => 'Use this reference when exploring HTML elements and attributes.',
                'external_url' => 'https://developer.mozilla.org/en-US/docs/Web/HTML',
            ],
        ]);

        $this->lessons($elements, [
            ['title' => 'Headings and Paragraphs', 'type' => LessonType::Text, 'content' => 'Structure readable text with headings and paragraphs.', 'external_url' => null],
            ['title' => 'Links and Images', 'type' => LessonType::Text, 'content' => 'Connect pages and add meaningful images with accessible alternatives.', 'external_url' => null],
            ['title' => 'Tables', 'type' => LessonType::Text, 'content' => 'Represent genuinely tabular data with clear headers and captions.', 'external_url' => null],
            ['title' => 'Forms', 'type' => LessonType::Text, 'content' => 'Collect user input with labels, controls, and semantic form structure.', 'external_url' => null],
        ]);
    }

    /**
     * @param  array{name: string, slug: string, description: string, sort_order: int}  $data
     */
    private function module(Competency $competency, array $data): Module
    {
        $module = Module::withTrashed()->updateOrCreate(
            [
                'competency_id' => $competency->id,
                'slug' => $data['slug'],
            ],
            [
                ...$data,
                'status' => AcademicStatus::Active,
            ],
        );
        $module->restore();

        return $module;
    }

    /**
     * @param  array<int, array{title: string, type: LessonType, content: string, external_url: string|null}>  $lessons
     */
    private function lessons(Module $module, array $lessons): void
    {
        foreach ($lessons as $index => $data) {
            $lesson = Lesson::withTrashed()->updateOrCreate(
                [
                    'module_id' => $module->id,
                    'slug' => str($data['title'])->slug()->toString(),
                ],
                [
                    'title' => $data['title'],
                    'lesson_type' => $data['type'],
                    'content' => $data['content'],
                    'external_url' => $data['external_url'],
                    'file_path' => null,
                    'duration_minutes' => 15,
                    'sort_order' => $index + 1,
                    'status' => AcademicStatus::Active,
                ],
            );
            $lesson->restore();
        }
    }
}
