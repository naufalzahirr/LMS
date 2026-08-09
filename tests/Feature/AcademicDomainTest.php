<?php

namespace Tests\Feature;

use App\Models\Competency;
use App\Models\Course;
use App\Models\Program;
use Database\Seeders\AcademicSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AcademicDomainTest extends TestCase
{
    use RefreshDatabase;

    public function test_academic_model_relationships_and_ordering(): void
    {
        $program = Program::factory()->create();
        $laterCourse = Course::factory()->for($program)->create([
            'name' => 'Alpha Course',
            'sort_order' => 2,
        ]);
        $firstCourse = Course::factory()->for($program)->create([
            'name' => 'Zeta Course',
            'sort_order' => 1,
        ]);
        $alphabeticalCourse = Course::factory()->for($program)->create([
            'name' => 'Beta Course',
            'sort_order' => 2,
        ]);

        $laterCompetency = Competency::factory()->for($firstCourse)->create([
            'name' => 'Alpha Skill',
            'sort_order' => 2,
        ]);
        $firstCompetency = Competency::factory()->for($firstCourse)->create([
            'name' => 'Zeta Skill',
            'sort_order' => 1,
        ]);
        $alphabeticalCompetency = Competency::factory()->for($firstCourse)->create([
            'name' => 'Beta Skill',
            'sort_order' => 2,
        ]);

        $this->assertEquals(
            [$firstCourse->id, $laterCourse->id, $alphabeticalCourse->id],
            $program->courses->modelKeys(),
        );
        $this->assertTrue($firstCourse->program->is($program));
        $this->assertEquals(
            [$firstCompetency->id, $laterCompetency->id, $alphabeticalCompetency->id],
            $firstCourse->competencies->modelKeys(),
        );
        $this->assertTrue($firstCompetency->course->is($firstCourse));
    }

    public function test_development_academic_seeder_is_readable_and_idempotent(): void
    {
        $this->seed(AcademicSeeder::class);
        $this->seed(AcademicSeeder::class);

        $program = Program::query()->where('slug', 'web-development')->firstOrFail();

        $this->assertSame(1, Program::query()->count());
        $this->assertSame(2, $program->courses()->count());
        $this->assertSame(7, Competency::query()->count());
        $this->assertDatabaseHas('courses', ['name' => 'Frontend Fundamentals']);
        $this->assertDatabaseHas('courses', ['name' => 'Backend Fundamentals']);
        $this->assertDatabaseHas('competencies', ['name' => 'Laravel Fundamentals']);
    }
}
