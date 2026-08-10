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

class CourseManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_admin_can_view_courses(): void
    {
        $admin = $this->userWithRole('Admin');
        $course = Course::factory()->create();

        $this->actingAs($admin)
            ->get(route('admin.courses.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/courses/Index')
                ->has('courses.data', 1)
                ->where('courses.data.0.id', $course->id)
                ->where('canManage', true));
    }

    public function test_tutor_can_view_courses_but_cannot_mutate_them(): void
    {
        $tutor = $this->userWithRole('Tutor');
        $course = Course::factory()->create();

        $this->actingAs($tutor)
            ->get(route('admin.courses.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('canManage', false));

        $this->actingAs($tutor)
            ->put(route('admin.courses.update', $course), $this->validPayload())
            ->assertForbidden();

        $this->actingAs($tutor)
            ->delete(route('admin.courses.destroy', $course))
            ->assertForbidden();
    }

    public function test_admin_can_create_course_under_a_program(): void
    {
        $admin = $this->userWithRole('Admin');
        $program = Program::factory()->create();

        $this->actingAs($admin)
            ->post(route('admin.courses.store'), $this->validPayload([
                'program_id' => $program->id,
                'name' => 'Frontend Fundamentals',
                'slug' => '',
            ]))
            ->assertRedirect(route('admin.courses.index'));

        $course = Course::query()->where('slug', 'frontend-fundamentals')->firstOrFail();

        $this->assertTrue($course->program->is($program));
        $this->assertSame(AcademicStatus::Active, $course->status);
    }

    public function test_course_requires_a_valid_non_deleted_program(): void
    {
        $admin = $this->userWithRole('Admin');
        $deletedProgram = Program::factory()->create();
        $deletedProgram->delete();

        $this->actingAs($admin)
            ->post(route('admin.courses.store'), $this->validPayload([
                'program_id' => 999999,
            ]))
            ->assertSessionHasErrors('program_id');

        $this->actingAs($admin)
            ->post(route('admin.courses.store'), $this->validPayload([
                'program_id' => $deletedProgram->id,
            ]))
            ->assertSessionHasErrors('program_id');
    }

    public function test_course_validation_works(): void
    {
        $admin = $this->userWithRole('Admin');
        $program = Program::factory()->create();

        $this->actingAs($admin)
            ->post(route('admin.courses.store'), [
                'program_id' => $program->id,
                'name' => '',
                'slug' => 'invalid slug',
                'status' => 'draft',
                'sort_order' => -1,
            ])
            ->assertSessionHasErrors(['name', 'slug', 'status', 'sort_order']);
    }

    public function test_admin_can_update_course(): void
    {
        $admin = $this->userWithRole('Admin');
        $newProgram = Program::factory()->create();
        $course = Course::factory()->create();

        $this->actingAs($admin)
            ->put(route('admin.courses.update', $course), $this->validPayload([
                'program_id' => $newProgram->id,
                'name' => 'Updated Backend',
                'slug' => 'updated-backend',
                'status' => AcademicStatus::Inactive->value,
                'sort_order' => 7,
            ]))
            ->assertRedirect(route('admin.courses.index'));

        $course->refresh();

        $this->assertTrue($course->program->is($newProgram));
        $this->assertSame('Updated Backend', $course->name);
        $this->assertSame(AcademicStatus::Inactive, $course->status);
        $this->assertSame(7, $course->sort_order);
    }

    public function test_course_with_academic_records_cannot_move_to_another_program(): void
    {
        $admin = $this->userWithRole('Admin');
        $course = Course::factory()->create();
        Competency::factory()->for($course)->create();
        $newProgram = Program::factory()->create();

        $this->actingAs($admin)
            ->put(route('admin.courses.update', $course), $this->validPayload([
                'program_id' => $newProgram->id,
                'slug' => $course->slug,
                'code' => $course->code,
            ]))
            ->assertSessionHasErrors('program_id');

        $this->assertNotSame($newProgram->id, $course->fresh()->program_id);
    }

    public function test_admin_can_soft_delete_an_empty_course(): void
    {
        $admin = $this->userWithRole('Admin');
        $course = Course::factory()->create();

        $this->actingAs($admin)
            ->delete(route('admin.courses.destroy', $course))
            ->assertRedirect(route('admin.courses.index'));

        $this->assertSoftDeleted($course);
    }

    public function test_course_with_competencies_cannot_be_deleted(): void
    {
        $admin = $this->userWithRole('Admin');
        $course = Course::factory()->create();
        Competency::factory()->for($course)->create();

        $this->actingAs($admin)
            ->delete(route('admin.courses.destroy', $course))
            ->assertSessionHasErrors('course');

        $this->assertNotSoftDeleted($course);
    }

    public function test_course_list_can_filter_by_program(): void
    {
        $admin = $this->userWithRole('Admin');
        $program = Program::factory()->create();
        $otherProgram = Program::factory()->create();
        $expected = Course::factory()->for($program)->create();
        Course::factory()->for($otherProgram)->create();

        $this->actingAs($admin)
            ->get(route('admin.courses.index', ['program_id' => $program->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('courses.data', 1)
                ->where('courses.data.0.id', $expected->id)
                ->where('filters.program_id', (string) $program->id));
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'program_id' => Program::factory()->create()->id,
            'name' => 'Backend Fundamentals',
            'slug' => 'backend-fundamentals',
            'code' => 'BACKEND',
            'description' => 'Backend development foundations.',
            'status' => AcademicStatus::Active->value,
            'sort_order' => 0,
        ], $overrides);
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }
}
