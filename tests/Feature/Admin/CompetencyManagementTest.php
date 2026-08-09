<?php

namespace Tests\Feature\Admin;

use App\Enums\AcademicStatus;
use App\Models\Competency;
use App\Models\Course;
use App\Models\Program;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CompetencyManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_admin_can_view_competencies(): void
    {
        $admin = $this->userWithRole('Admin');
        $competency = Competency::factory()->create();

        $this->actingAs($admin)
            ->get(route('admin.competencies.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/competencies/Index')
                ->has('competencies.data', 1)
                ->where('competencies.data.0.id', $competency->id)
                ->where('canManage', true));
    }

    public function test_admin_can_create_competency_under_a_course(): void
    {
        $admin = $this->userWithRole('Admin');
        $course = Course::factory()->create();

        $this->actingAs($admin)
            ->post(route('admin.competencies.store'), $this->validPayload([
                'course_id' => $course->id,
                'name' => 'HTML Fundamentals',
                'slug' => '',
            ]))
            ->assertRedirect(route('admin.competencies.index'));

        $competency = Competency::query()
            ->where('course_id', $course->id)
            ->where('slug', 'html-fundamentals')
            ->firstOrFail();

        $this->assertSame('C01', $competency->code);
        $this->assertSame(AcademicStatus::Active, $competency->status);
    }

    public function test_competency_code_must_be_unique_within_its_course(): void
    {
        $admin = $this->userWithRole('Admin');
        $course = Course::factory()->create();
        Competency::factory()->for($course)->create(['code' => 'C01']);

        $this->actingAs($admin)
            ->post(route('admin.competencies.store'), $this->validPayload([
                'course_id' => $course->id,
                'code' => 'C01',
                'slug' => 'different-slug',
            ]))
            ->assertSessionHasErrors('code');
    }

    public function test_same_competency_code_is_allowed_in_different_courses(): void
    {
        $admin = $this->userWithRole('Admin');
        $firstCourse = Course::factory()->create();
        $secondCourse = Course::factory()->create();
        Competency::factory()->for($firstCourse)->create(['code' => 'C01']);

        $this->actingAs($admin)
            ->post(route('admin.competencies.store'), $this->validPayload([
                'course_id' => $secondCourse->id,
                'code' => 'C01',
            ]))
            ->assertRedirect(route('admin.competencies.index'));

        $this->assertDatabaseHas('competencies', [
            'course_id' => $secondCourse->id,
            'code' => 'C01',
        ]);
    }

    public function test_competency_slug_uniqueness_is_scoped_to_course(): void
    {
        $admin = $this->userWithRole('Admin');
        $firstCourse = Course::factory()->create();
        $secondCourse = Course::factory()->create();
        Competency::factory()->for($firstCourse)->create([
            'code' => 'C01',
            'slug' => 'html-fundamentals',
        ]);

        $this->actingAs($admin)
            ->post(route('admin.competencies.store'), $this->validPayload([
                'course_id' => $firstCourse->id,
                'code' => 'C02',
                'slug' => 'html-fundamentals',
            ]))
            ->assertSessionHasErrors('slug');

        $this->actingAs($admin)
            ->post(route('admin.competencies.store'), $this->validPayload([
                'course_id' => $secondCourse->id,
                'code' => 'C01',
                'slug' => 'html-fundamentals',
            ]))
            ->assertRedirect(route('admin.competencies.index'));

        $this->assertDatabaseHas('competencies', [
            'course_id' => $secondCourse->id,
            'slug' => 'html-fundamentals',
        ]);
    }

    public function test_competency_validation_works(): void
    {
        $admin = $this->userWithRole('Admin');

        $this->actingAs($admin)
            ->post(route('admin.competencies.store'), [
                'course_id' => 999999,
                'code' => '',
                'name' => '',
                'slug' => 'invalid slug',
                'status' => 'draft',
                'sort_order' => -1,
            ])
            ->assertSessionHasErrors([
                'course_id',
                'code',
                'name',
                'slug',
                'status',
                'sort_order',
            ]);
    }

    public function test_admin_can_update_competency(): void
    {
        $admin = $this->userWithRole('Admin');
        $newCourse = Course::factory()->create();
        $competency = Competency::factory()->create();

        $this->actingAs($admin)
            ->put(
                route('admin.competencies.update', $competency),
                $this->validPayload([
                    'course_id' => $newCourse->id,
                    'code' => 'C99',
                    'name' => 'Updated Competency',
                    'slug' => 'updated-competency',
                    'learning_objectives' => 'Explain the updated skill.',
                    'sort_order' => 9,
                    'status' => AcademicStatus::Inactive->value,
                ]),
            )
            ->assertRedirect(route('admin.competencies.index'));

        $competency->refresh();

        $this->assertTrue($competency->course->is($newCourse));
        $this->assertSame('C99', $competency->code);
        $this->assertSame('Updated Competency', $competency->name);
        $this->assertSame(9, $competency->sort_order);
        $this->assertSame(AcademicStatus::Inactive, $competency->status);
    }

    public function test_admin_can_soft_delete_competency(): void
    {
        $admin = $this->userWithRole('Admin');
        $competency = Competency::factory()->create();

        $this->actingAs($admin)
            ->delete(route('admin.competencies.destroy', $competency))
            ->assertRedirect(route('admin.competencies.index'));

        $this->assertSoftDeleted($competency);
    }

    public function test_non_admin_cannot_mutate_competency(): void
    {
        $tutor = $this->userWithRole('Tutor');
        $student = $this->userWithRole('Student');
        $competency = Competency::factory()->create();

        $this->actingAs($tutor)
            ->get(route('admin.competencies.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('canManage', false));

        $this->actingAs($tutor)
            ->post(route('admin.competencies.store'), $this->validPayload())
            ->assertForbidden();

        $this->actingAs($tutor)
            ->delete(route('admin.competencies.destroy', $competency))
            ->assertForbidden();

        $this->actingAs($student)
            ->get(route('admin.competencies.index'))
            ->assertForbidden();
    }

    public function test_competency_list_filters_work(): void
    {
        $admin = $this->userWithRole('Admin');
        $program = Program::factory()->create();
        $otherProgram = Program::factory()->create();
        $course = Course::factory()->for($program)->create();
        $otherCourse = Course::factory()->for($otherProgram)->create();
        $expected = Competency::factory()->for($course)->create([
            'code' => 'FILTER-01',
            'name' => 'Accessible Interfaces',
            'status' => AcademicStatus::Inactive,
        ]);
        Competency::factory()->for($course)->create([
            'status' => AcademicStatus::Active,
        ]);
        Competency::factory()->for($otherCourse)->create([
            'code' => 'FILTER-01',
            'status' => AcademicStatus::Inactive,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.competencies.index', [
                'search' => 'FILTER-01',
                'program_id' => $program->id,
                'course_id' => $course->id,
                'status' => AcademicStatus::Inactive->value,
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('competencies.data', 1)
                ->where('competencies.data.0.id', $expected->id)
                ->where('filters.program_id', (string) $program->id)
                ->where('filters.course_id', (string) $course->id)
                ->where('filters.status', AcademicStatus::Inactive->value));
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'course_id' => Course::factory()->create()->id,
            'code' => 'C01',
            'name' => 'PHP Fundamentals',
            'slug' => 'php-fundamentals',
            'description' => 'Core PHP language skills.',
            'learning_objectives' => 'Write clear PHP applications.',
            'sort_order' => 0,
            'status' => AcademicStatus::Active->value,
        ], $overrides);
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }
}
