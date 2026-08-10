<?php

namespace Tests\Feature\Admin;

use App\Enums\AcademicStatus;
use App\Models\Competency;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\Program;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ModuleManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_admin_can_view_modules(): void
    {
        $admin = $this->userWithRole('Admin');
        $module = Module::factory()->create();

        $this->actingAs($admin)
            ->get(route('admin.modules.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/modules/Index')
                ->has('modules.data', 1)
                ->where('modules.data.0.id', $module->id)
                ->where('canManage', true));
    }

    public function test_tutor_can_view_modules_read_only(): void
    {
        $tutor = $this->userWithRole('Tutor');
        $module = Module::factory()->create();

        $this->actingAs($tutor)
            ->get(route('admin.modules.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('canManage', false));

        $this->actingAs($tutor)
            ->post(route('admin.modules.store'), $this->validPayload())
            ->assertForbidden();

        $this->actingAs($tutor)
            ->delete(route('admin.modules.destroy', $module))
            ->assertForbidden();
    }

    public function test_student_and_parent_cannot_access_module_management(): void
    {
        foreach (['Student', 'Parent'] as $role) {
            $this->actingAs($this->userWithRole($role))
                ->get(route('admin.modules.index'))
                ->assertForbidden();
        }
    }

    public function test_admin_can_create_module_with_generated_slug(): void
    {
        $admin = $this->userWithRole('Admin');
        $competency = Competency::factory()->create();

        $this->actingAs($admin)
            ->post(route('admin.modules.store'), $this->validPayload([
                'competency_id' => $competency->id,
                'name' => 'Getting Started with HTML',
                'slug' => '',
            ]))
            ->assertRedirect(route('admin.modules.index'));

        $module = Module::query()->where('slug', 'getting-started-with-html')->firstOrFail();

        $this->assertTrue($module->competency->is($competency));
        $this->assertSame(AcademicStatus::Active, $module->status);
    }

    public function test_module_requires_valid_non_deleted_competency(): void
    {
        $admin = $this->userWithRole('Admin');
        $deletedCompetency = Competency::factory()->create();
        $deletedCompetency->delete();

        $this->actingAs($admin)
            ->post(route('admin.modules.store'), $this->validPayload(['competency_id' => 999999]))
            ->assertSessionHasErrors('competency_id');

        $this->actingAs($admin)
            ->post(route('admin.modules.store'), $this->validPayload(['competency_id' => $deletedCompetency->id]))
            ->assertSessionHasErrors('competency_id');
    }

    public function test_module_slug_is_unique_within_competency(): void
    {
        $admin = $this->userWithRole('Admin');
        $competency = Competency::factory()->create();
        Module::factory()->for($competency)->create(['slug' => 'introduction']);

        $this->actingAs($admin)
            ->post(route('admin.modules.store'), $this->validPayload([
                'competency_id' => $competency->id,
                'slug' => 'introduction',
            ]))
            ->assertSessionHasErrors('slug');
    }

    public function test_same_module_slug_is_allowed_in_different_competencies(): void
    {
        $admin = $this->userWithRole('Admin');
        $first = Competency::factory()->create();
        $second = Competency::factory()->create();
        Module::factory()->for($first)->create(['slug' => 'introduction']);

        $this->actingAs($admin)
            ->post(route('admin.modules.store'), $this->validPayload([
                'competency_id' => $second->id,
                'slug' => 'introduction',
            ]))
            ->assertRedirect(route('admin.modules.index'));

        $this->assertDatabaseHas('modules', [
            'competency_id' => $second->id,
            'slug' => 'introduction',
        ]);
    }

    public function test_admin_can_update_module(): void
    {
        $admin = $this->userWithRole('Admin');
        $newCompetency = Competency::factory()->create();
        $module = Module::factory()->create();

        $this->actingAs($admin)
            ->put(route('admin.modules.update', $module), $this->validPayload([
                'competency_id' => $newCompetency->id,
                'name' => 'Updated Module',
                'slug' => 'updated-module',
                'sort_order' => 8,
                'status' => AcademicStatus::Inactive->value,
            ]))
            ->assertRedirect(route('admin.modules.index'));

        $module->refresh();

        $this->assertTrue($module->competency->is($newCompetency));
        $this->assertSame('Updated Module', $module->name);
        $this->assertSame(8, $module->sort_order);
        $this->assertSame(AcademicStatus::Inactive, $module->status);
    }

    public function test_module_with_lessons_cannot_move_to_another_competency(): void
    {
        $admin = $this->userWithRole('Admin');
        $module = Module::factory()->create();
        Lesson::factory()->for($module)->create();
        $otherCompetency = Competency::factory()->create();

        $this->actingAs($admin)
            ->put(route('admin.modules.update', $module), $this->validPayload([
                'competency_id' => $otherCompetency->id,
                'slug' => $module->slug,
            ]))
            ->assertSessionHasErrors('competency_id');

        $this->assertNotSame($otherCompetency->id, $module->fresh()->competency_id);
    }

    public function test_admin_can_soft_delete_empty_module(): void
    {
        $admin = $this->userWithRole('Admin');
        $module = Module::factory()->create();

        $this->actingAs($admin)
            ->delete(route('admin.modules.destroy', $module))
            ->assertRedirect(route('admin.modules.index'));

        $this->assertSoftDeleted($module);
    }

    public function test_module_with_lessons_cannot_be_deleted(): void
    {
        $admin = $this->userWithRole('Admin');
        $module = Module::factory()->create();
        Lesson::factory()->for($module)->create();

        $this->actingAs($admin)
            ->delete(route('admin.modules.destroy', $module))
            ->assertSessionHasErrors('module');

        $this->assertNotSoftDeleted($module);
    }

    public function test_competency_with_modules_cannot_be_deleted(): void
    {
        $admin = $this->userWithRole('Admin');
        $competency = Competency::factory()->create();
        Module::factory()->for($competency)->create();

        $this->actingAs($admin)
            ->delete(route('admin.competencies.destroy', $competency))
            ->assertSessionHasErrors('competency');

        $this->assertNotSoftDeleted($competency);
    }

    public function test_module_filters_work(): void
    {
        $admin = $this->userWithRole('Admin');
        $program = Program::factory()->create();
        $course = Course::factory()->for($program)->create();
        $competency = Competency::factory()->for($course)->create();
        $expected = Module::factory()->for($competency)->create([
            'name' => 'Unique Semantic Module',
            'status' => AcademicStatus::Inactive,
        ]);
        Module::factory()->create();

        $this->actingAs($admin)
            ->get(route('admin.modules.index', [
                'search' => 'Unique Semantic',
                'program_id' => $program->id,
                'course_id' => $course->id,
                'competency_id' => $competency->id,
                'status' => AcademicStatus::Inactive->value,
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('modules.data', 1)
                ->where('modules.data.0.id', $expected->id));
    }

    public function test_competency_modules_relationship_is_ordered(): void
    {
        $competency = Competency::factory()->create();
        $alpha = Module::factory()->for($competency)->create(['name' => 'Alpha', 'sort_order' => 2]);
        $first = Module::factory()->for($competency)->create(['name' => 'Zeta', 'sort_order' => 1]);
        $beta = Module::factory()->for($competency)->create(['name' => 'Beta', 'sort_order' => 2]);

        $this->assertEquals(
            [$first->id, $alpha->id, $beta->id],
            $competency->modules->modelKeys(),
        );
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'competency_id' => Competency::factory()->create()->id,
            'name' => 'HTML Introduction',
            'slug' => 'html-introduction',
            'description' => 'A structured introduction to HTML.',
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
