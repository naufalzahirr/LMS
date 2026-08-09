<?php

namespace Tests\Feature;

use App\Models\Competency;
use App\Models\Lesson;
use App\Models\Module;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContentDatabaseIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_enforces_module_slug_uniqueness_within_competency(): void
    {
        $competency = Competency::factory()->create();
        Module::factory()->for($competency)->create(['slug' => 'same-slug']);

        $this->expectException(QueryException::class);

        Module::factory()->for($competency)->create(['slug' => 'same-slug']);
    }

    public function test_database_enforces_lesson_slug_uniqueness_within_module(): void
    {
        $module = Module::factory()->create();
        Lesson::factory()->for($module)->create(['slug' => 'same-slug']);

        $this->expectException(QueryException::class);

        Lesson::factory()->for($module)->create(['slug' => 'same-slug']);
    }

    public function test_database_restricts_deleting_competency_with_modules(): void
    {
        $competency = Competency::factory()->create();
        Module::factory()->for($competency)->create();

        $this->expectException(QueryException::class);

        $competency->forceDelete();
    }

    public function test_database_restricts_deleting_module_with_lessons(): void
    {
        $module = Module::factory()->create();
        Lesson::factory()->for($module)->create();

        $this->expectException(QueryException::class);

        $module->forceDelete();
    }
}
