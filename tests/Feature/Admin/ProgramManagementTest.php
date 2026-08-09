<?php

namespace Tests\Feature\Admin;

use App\Enums\AcademicStatus;
use App\Models\Course;
use App\Models\Program;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ProgramManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_admin_can_view_programs(): void
    {
        $admin = $this->userWithRole('Admin');
        $program = Program::factory()->create();

        $this->actingAs($admin)
            ->get(route('admin.programs.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/programs/Index')
                ->has('programs.data', 1)
                ->where('programs.data.0.id', $program->id)
                ->where('canManage', true));
    }

    public function test_tutor_can_view_programs_but_cannot_mutate_them(): void
    {
        $tutor = $this->userWithRole('Tutor');
        $program = Program::factory()->create();

        $this->actingAs($tutor)
            ->get(route('admin.programs.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('canManage', false));

        $this->actingAs($tutor)
            ->post(route('admin.programs.store'), $this->validPayload())
            ->assertForbidden();

        $this->actingAs($tutor)
            ->delete(route('admin.programs.destroy', $program))
            ->assertForbidden();
    }

    public function test_student_cannot_access_program_management(): void
    {
        $student = $this->userWithRole('Student');

        $this->actingAs($student)
            ->get(route('admin.programs.index'))
            ->assertForbidden();
    }

    public function test_admin_can_create_program_with_an_automatically_generated_slug(): void
    {
        $admin = $this->userWithRole('Admin');

        $this->actingAs($admin)
            ->post(route('admin.programs.store'), $this->validPayload([
                'name' => 'Software Engineering',
                'slug' => '',
            ]))
            ->assertRedirect(route('admin.programs.index'));

        $program = Program::query()->where('slug', 'software-engineering')->firstOrFail();

        $this->assertSame(AcademicStatus::Active, $program->status);
        $this->assertSame('Software Engineering', $program->name);
    }

    public function test_program_validation_works(): void
    {
        $admin = $this->userWithRole('Admin');

        $this->actingAs($admin)
            ->post(route('admin.programs.store'), [
                'name' => '',
                'slug' => 'invalid slug',
                'status' => 'draft',
            ])
            ->assertSessionHasErrors(['name', 'slug', 'status']);
    }

    public function test_program_slug_must_be_unique(): void
    {
        $admin = $this->userWithRole('Admin');
        Program::factory()->create(['slug' => 'web-development']);

        $this->actingAs($admin)
            ->post(route('admin.programs.store'), $this->validPayload([
                'slug' => 'web-development',
            ]))
            ->assertSessionHasErrors('slug');
    }

    public function test_admin_can_update_program(): void
    {
        $admin = $this->userWithRole('Admin');
        $program = Program::factory()->create();

        $this->actingAs($admin)
            ->put(route('admin.programs.update', $program), $this->validPayload([
                'name' => 'Updated Program',
                'slug' => 'updated-program',
                'code' => null,
                'status' => AcademicStatus::Inactive->value,
            ]))
            ->assertRedirect(route('admin.programs.index'));

        $program->refresh();

        $this->assertSame('Updated Program', $program->name);
        $this->assertSame('updated-program', $program->slug);
        $this->assertNull($program->code);
        $this->assertSame(AcademicStatus::Inactive, $program->status);
    }

    public function test_admin_can_soft_delete_an_empty_program(): void
    {
        $admin = $this->userWithRole('Admin');
        $program = Program::factory()->create();

        $this->actingAs($admin)
            ->delete(route('admin.programs.destroy', $program))
            ->assertRedirect(route('admin.programs.index'));

        $this->assertSoftDeleted($program);
    }

    public function test_program_with_courses_cannot_be_deleted(): void
    {
        $admin = $this->userWithRole('Admin');
        $program = Program::factory()->create();
        Course::factory()->for($program)->create();

        $this->actingAs($admin)
            ->delete(route('admin.programs.destroy', $program))
            ->assertSessionHasErrors('program');

        $this->assertNotSoftDeleted($program);
    }

    public function test_program_list_is_paginated_and_filterable(): void
    {
        $admin = $this->userWithRole('Admin');
        Program::factory(11)->create();
        $expected = Program::factory()->create([
            'name' => 'Unique Robotics',
            'code' => 'ROBOT',
            'status' => AcademicStatus::Inactive,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.programs.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('programs.data', 10)
                ->where('programs.total', 12));

        $this->actingAs($admin)
            ->get(route('admin.programs.index', [
                'search' => 'ROBOT',
                'status' => AcademicStatus::Inactive->value,
            ]))
            ->assertInertia(fn (Assert $page) => $page
                ->has('programs.data', 1)
                ->where('programs.data.0.id', $expected->id));
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Web Development',
            'slug' => 'web-development',
            'code' => 'WEB',
            'description' => 'A practical web development program.',
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
