<?php

namespace Tests\Feature\Admin;

use App\Enums\AcademicStatus;
use App\Enums\LearningClassStatus;
use App\Enums\LessonType;
use App\Models\Competency;
use App\Models\Course;
use App\Models\LearningClass;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class TutorAssignmentAndScopedContentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_admin_can_assign_and_unassign_tutor(): void
    {
        $admin = $this->userWithRole('Admin');
        $tutor = $this->userWithRole('Tutor');
        $learningClass = LearningClass::factory()->create();

        $this->actingAs($admin)->post(route('admin.classes.tutors.store', $learningClass), [
            'tutor_id' => $tutor->id,
        ])->assertRedirect();
        $this->assertTrue($learningClass->tutors()->whereKey($tutor->id)->exists());

        $this->actingAs($admin)->delete(route('admin.classes.tutors.destroy', [$learningClass, $tutor]))
            ->assertRedirect();
        $this->assertFalse($learningClass->tutors()->whereKey($tutor->id)->exists());
    }

    public function test_non_tutor_cannot_be_assigned(): void
    {
        $learningClass = LearningClass::factory()->create();

        $this->actingAs($this->userWithRole('Admin'))->post(route('admin.classes.tutors.store', $learningClass), [
            'tutor_id' => $this->userWithRole('Student')->id,
        ])->assertSessionHasErrors('tutor_id');
    }

    public function test_duplicate_tutor_assignment_is_prevented(): void
    {
        $tutor = $this->userWithRole('Tutor');
        $learningClass = LearningClass::factory()->create();
        $learningClass->tutors()->attach($tutor);

        $this->actingAs($this->userWithRole('Admin'))->post(route('admin.classes.tutors.store', $learningClass), [
            'tutor_id' => $tutor->id,
        ])->assertSessionHasErrors('tutor_id');
    }

    public function test_tutor_can_belong_to_multiple_classes(): void
    {
        $tutor = $this->userWithRole('Tutor');
        $classes = LearningClass::factory(2)->create();

        foreach ($classes as $learningClass) {
            $learningClass->tutors()->attach($tutor);
        }

        $this->assertCount(2, $tutor->teachingClasses);
    }

    public function test_tutor_sees_only_assigned_classes(): void
    {
        $tutor = $this->userWithRole('Tutor');
        $assigned = LearningClass::factory()->create();
        $other = LearningClass::factory()->create();
        $assigned->tutors()->attach($tutor);

        $this->actingAs($tutor)->get(route('tutor.classes.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('tutor/classes/Index')
                ->has('classes.data', 1)
                ->where('classes.data.0.id', $assigned->id)
                ->missing('classes.data.1'));

        $this->actingAs($tutor)->get(route('tutor.classes.show', $other))->assertForbidden();
        $this->actingAs($tutor)->get(route('tutor.classes.show', $assigned))->assertOk();
    }

    public function test_tutor_assigned_to_active_course_can_create_update_and_delete_module(): void
    {
        [$tutor, $course] = $this->assignedTutorContext();
        $competency = Competency::factory()->for($course)->create();

        $this->actingAs($tutor)->post(route('admin.modules.store'), $this->modulePayload($competency, [
            'slug' => 'tutor-created',
        ]))->assertRedirect(route('admin.modules.index'));

        $module = Module::query()->where('slug', 'tutor-created')->firstOrFail();

        $this->actingAs($tutor)->put(route('admin.modules.update', $module), $this->modulePayload($competency, [
            'name' => 'Tutor Updated',
            'slug' => 'tutor-updated',
        ]))->assertRedirect();

        $this->actingAs($tutor)->delete(route('admin.modules.destroy', $module))->assertRedirect();
        $this->assertSoftDeleted($module);
    }

    public function test_tutor_cannot_create_or_move_module_to_unassigned_course(): void
    {
        [$tutor, $assignedCourse] = $this->assignedTutorContext();
        $assignedCompetency = Competency::factory()->for($assignedCourse)->create();
        $otherCompetency = Competency::factory()->create();
        $module = Module::factory()->for($assignedCompetency)->create();

        $this->actingAs($tutor)->post(route('admin.modules.store'), $this->modulePayload($otherCompetency))
            ->assertForbidden();
        $this->actingAs($tutor)->put(route('admin.modules.update', $module), $this->modulePayload($otherCompetency))
            ->assertForbidden();
    }

    public function test_tutor_content_pages_expose_controls_and_targets_only_for_assigned_course(): void
    {
        [$tutor, $assignedCourse] = $this->assignedTutorContext();
        $assignedCompetency = Competency::factory()->for($assignedCourse)->create();
        $otherCompetency = Competency::factory()->create();
        $assignedModule = Module::factory()->for($assignedCompetency)->create();
        Module::factory()->for($otherCompetency)->create();
        Lesson::factory()->for($assignedModule)->create();

        $this->actingAs($tutor)->get(route('admin.modules.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('canManage', true)
                ->where('modules.data.0.can_update', true)
                ->where('modules.data.1.can_update', false));

        $this->actingAs($tutor)->get(route('admin.modules.create'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('competencies', 1)
                ->where('competencies.0.id', $assignedCompetency->id));

        $this->actingAs($tutor)->get(route('admin.lessons.create'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('modules', 1)
                ->where('modules.0.id', $assignedModule->id));
    }

    public function test_tutor_cannot_edit_or_delete_module_from_other_course(): void
    {
        [$tutor] = $this->assignedTutorContext();
        $module = Module::factory()->create();

        $this->actingAs($tutor)->put(route('admin.modules.update', $module), $this->modulePayload($module->competency))
            ->assertForbidden();
        $this->actingAs($tutor)->delete(route('admin.modules.destroy', $module))->assertForbidden();
    }

    public function test_tutor_assigned_to_active_course_can_create_update_and_delete_lesson(): void
    {
        [$tutor, $course] = $this->assignedTutorContext();
        $module = Module::factory()->for(Competency::factory()->for($course))->create();

        $this->actingAs($tutor)->post(route('admin.lessons.store'), $this->lessonPayload($module, [
            'slug' => 'tutor-lesson',
        ]))->assertRedirect();

        $lesson = Lesson::query()->where('slug', 'tutor-lesson')->firstOrFail();
        $this->actingAs($tutor)->put(route('admin.lessons.update', $lesson), $this->lessonPayload($module, [
            'title' => 'Updated Tutor Lesson',
            'slug' => 'updated-tutor-lesson',
        ]))->assertRedirect();
        $this->actingAs($tutor)->delete(route('admin.lessons.destroy', $lesson))->assertRedirect();
        $this->assertSoftDeleted($lesson);
    }

    public function test_tutor_cannot_mutate_lessons_in_unassigned_course(): void
    {
        [$tutor] = $this->assignedTutorContext();
        $otherModule = Module::factory()->create();
        $lesson = Lesson::factory()->for($otherModule)->create();

        $this->actingAs($tutor)->post(route('admin.lessons.store'), $this->lessonPayload($otherModule))
            ->assertForbidden();
        $this->actingAs($tutor)->put(route('admin.lessons.update', $lesson), $this->lessonPayload($otherModule))
            ->assertForbidden();
        $this->actingAs($tutor)->delete(route('admin.lessons.destroy', $lesson))->assertForbidden();
    }

    public function test_inactive_and_completed_class_assignments_do_not_grant_content_mutation(): void
    {
        foreach ([LearningClassStatus::Inactive, LearningClassStatus::Completed] as $status) {
            $tutor = $this->userWithRole('Tutor');
            $course = Course::factory()->create();
            $learningClass = LearningClass::factory()->for($course)->create(['status' => $status]);
            $learningClass->tutors()->attach($tutor);
            $competency = Competency::factory()->for($course)->create();
            $module = Module::factory()->for($competency)->create();

            $this->actingAs($tutor)->post(route('admin.modules.store'), $this->modulePayload($competency))
                ->assertForbidden();
            $this->actingAs($tutor)->post(route('admin.lessons.store'), $this->lessonPayload($module))
                ->assertForbidden();
        }
    }

    public function test_student_and_parent_cannot_mutate_content_by_direct_request(): void
    {
        $competency = Competency::factory()->create();
        $module = Module::factory()->for($competency)->create();

        foreach (['Student', 'Parent'] as $role) {
            $user = $this->userWithRole($role);
            $this->actingAs($user)->post(route('admin.modules.store'), $this->modulePayload($competency))
                ->assertForbidden();
            $this->actingAs($user)->post(route('admin.lessons.store'), $this->lessonPayload($module))
                ->assertForbidden();
        }
    }

    public function test_admin_can_still_manage_content_without_assignment(): void
    {
        $admin = $this->userWithRole('Admin');
        $competency = Competency::factory()->create();
        $module = Module::factory()->for($competency)->create();

        $this->actingAs($admin)->post(route('admin.modules.store'), $this->modulePayload($competency, [
            'slug' => 'admin-scoped-check',
        ]))->assertRedirect();
        $this->actingAs($admin)->post(route('admin.lessons.store'), $this->lessonPayload($module, [
            'slug' => 'admin-lesson-check',
        ]))->assertRedirect();
    }

    /** @return array{User, Course, LearningClass} */
    private function assignedTutorContext(): array
    {
        $tutor = $this->userWithRole('Tutor');
        $course = Course::factory()->create();
        $learningClass = LearningClass::factory()->for($course)->create([
            'status' => LearningClassStatus::Active,
        ]);
        $learningClass->tutors()->attach($tutor);

        return [$tutor, $course, $learningClass];
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function modulePayload(Competency $competency, array $overrides = []): array
    {
        return array_merge([
            'competency_id' => $competency->id,
            'name' => 'Scoped Module',
            'slug' => fake()->unique()->slug(),
            'description' => 'Tutor-authored content.',
            'sort_order' => 0,
            'status' => AcademicStatus::Active->value,
        ], $overrides);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function lessonPayload(Module $module, array $overrides = []): array
    {
        return array_merge([
            'module_id' => $module->id,
            'title' => 'Scoped Lesson',
            'slug' => fake()->unique()->slug(),
            'lesson_type' => LessonType::Text->value,
            'content' => 'Tutor-authored lesson content.',
            'external_url' => null,
            'duration_minutes' => 15,
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
