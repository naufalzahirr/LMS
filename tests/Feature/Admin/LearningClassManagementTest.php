<?php

namespace Tests\Feature\Admin;

use App\Enums\LearningClassStatus;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\LearningClass;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class LearningClassManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_admin_can_list_paginated_classes(): void
    {
        $admin = $this->userWithRole('Admin');
        LearningClass::factory(11)->create();

        $this->actingAs($admin)->get(route('admin.classes.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/classes/Index')
                ->has('classes.data', 10)
                ->where('classes.total', 11));
    }

    public function test_admin_can_create_class(): void
    {
        $admin = $this->userWithRole('Admin');
        $course = Course::factory()->create();

        $this->actingAs($admin)->post(route('admin.classes.store'), $this->payload($course, [
            'name' => 'Frontend Batch A',
            'code' => 'FE-A',
        ]))->assertRedirect();

        $this->assertDatabaseHas('learning_classes', [
            'course_id' => $course->id,
            'name' => 'Frontend Batch A',
            'code' => 'FE-A',
        ]);
    }

    public function test_course_must_exist_and_not_be_deleted(): void
    {
        $admin = $this->userWithRole('Admin');
        $deleted = Course::factory()->create();
        $deleted->delete();

        $this->actingAs($admin)->post(route('admin.classes.store'), $this->payload(null, [
            'course_id' => 999999,
        ]))->assertSessionHasErrors('course_id');

        $this->actingAs($admin)->post(route('admin.classes.store'), $this->payload(null, [
            'course_id' => $deleted->id,
        ]))->assertSessionHasErrors('course_id');
    }

    public function test_class_code_is_globally_unique(): void
    {
        $admin = $this->userWithRole('Admin');
        LearningClass::factory()->create(['code' => 'UNIQUE-CLASS']);

        $this->actingAs($admin)->post(route('admin.classes.store'), $this->payload(null, [
            'code' => 'UNIQUE-CLASS',
        ]))->assertSessionHasErrors('code');
    }

    public function test_end_date_cannot_precede_start_date(): void
    {
        $this->actingAs($this->userWithRole('Admin'))
            ->post(route('admin.classes.store'), $this->payload(null, [
                'start_date' => '2026-09-10',
                'end_date' => '2026-09-09',
            ]))->assertSessionHasErrors('end_date');
    }

    public function test_admin_can_update_class(): void
    {
        $admin = $this->userWithRole('Admin');
        $learningClass = LearningClass::factory()->create();
        $course = Course::factory()->create();

        $this->actingAs($admin)->put(route('admin.classes.update', $learningClass), $this->payload($course, [
            'name' => 'Updated Cohort',
            'code' => $learningClass->code,
            'status' => LearningClassStatus::Completed->value,
        ]))->assertRedirect(route('admin.classes.show', $learningClass));

        $learningClass->refresh();
        $this->assertSame('Updated Cohort', $learningClass->name);
        $this->assertSame(LearningClassStatus::Completed, $learningClass->status);
        $this->assertTrue($learningClass->course->is($course));
    }

    public function test_empty_class_can_be_soft_deleted(): void
    {
        $learningClass = LearningClass::factory()->create();

        $this->actingAs($this->userWithRole('Admin'))
            ->delete(route('admin.classes.destroy', $learningClass))
            ->assertRedirect(route('admin.classes.index'));

        $this->assertSoftDeleted($learningClass);
    }

    public function test_class_with_enrollment_history_cannot_be_deleted(): void
    {
        $learningClass = LearningClass::factory()->create();
        Enrollment::factory()->for($learningClass)->create();

        $this->actingAs($this->userWithRole('Admin'))
            ->delete(route('admin.classes.destroy', $learningClass))
            ->assertSessionHasErrors('learning_class');

        $this->assertNotSoftDeleted($learningClass);
    }

    public function test_class_with_tutor_assignment_cannot_be_deleted(): void
    {
        $learningClass = LearningClass::factory()->create();
        $learningClass->tutors()->attach($this->userWithRole('Tutor'));

        $this->actingAs($this->userWithRole('Admin'))
            ->delete(route('admin.classes.destroy', $learningClass))
            ->assertSessionHasErrors('learning_class');
    }

    public function test_course_with_class_cannot_be_deleted(): void
    {
        $course = Course::factory()->create();
        LearningClass::factory()->for($course)->create();

        $this->actingAs($this->userWithRole('Admin'))
            ->delete(route('admin.courses.destroy', $course))
            ->assertSessionHasErrors('course');

        $this->assertNotSoftDeleted($course);
    }

    public function test_class_filters_work(): void
    {
        $course = Course::factory()->create();
        $expected = LearningClass::factory()->for($course)->create([
            'name' => 'Distinct Evening Cohort',
            'status' => LearningClassStatus::Inactive,
        ]);
        LearningClass::factory()->create();

        $this->actingAs($this->userWithRole('Admin'))->get(route('admin.classes.index', [
            'search' => 'Distinct Evening',
            'program_id' => $course->program_id,
            'course_id' => $course->id,
            'status' => LearningClassStatus::Inactive->value,
        ]))->assertInertia(fn (Assert $page) => $page
            ->has('classes.data', 1)
            ->where('classes.data.0.id', $expected->id));
    }

    public function test_non_admin_cannot_manage_classes(): void
    {
        $this->actingAs($this->userWithRole('Student'))
            ->get(route('admin.classes.index'))
            ->assertForbidden();
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(?Course $course = null, array $overrides = []): array
    {
        return array_merge([
            'course_id' => ($course ?? Course::factory()->create())->id,
            'name' => 'Frontend Batch',
            'code' => fake()->unique()->bothify('TEST-####'),
            'description' => 'Delivery class.',
            'start_date' => '2026-09-01',
            'end_date' => '2026-12-01',
            'status' => LearningClassStatus::Active->value,
        ], $overrides);
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }
}
