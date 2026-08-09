<?php

namespace Database\Seeders;

use App\Enums\AcademicStatus;
use App\Models\Competency;
use App\Models\Course;
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

            $this->competencies($frontend, [
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
     */
    private function competencies(Course $course, array $competencies): void
    {
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
        }
    }
}
