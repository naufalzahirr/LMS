<?php

namespace Tests\Feature\Admin;

use App\Enums\EnrollmentStatus;
use App\Models\Enrollment;
use App\Models\LearningClass;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EnrollmentManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_admin_can_enroll_student(): void
    {
        [$admin, $student, $learningClass] = $this->context();

        $this->actingAs($admin)->post(route('admin.classes.enrollments.store', $learningClass), [
            'student_id' => $student->id,
        ])->assertRedirect(route('admin.classes.show', $learningClass));

        $this->assertDatabaseHas('enrollments', [
            'learning_class_id' => $learningClass->id,
            'student_id' => $student->id,
            'status' => EnrollmentStatus::Active->value,
        ]);
    }

    public function test_non_student_cannot_be_enrolled(): void
    {
        [$admin, , $learningClass] = $this->context();
        $tutor = $this->userWithRole('Tutor');

        $this->actingAs($admin)->post(route('admin.classes.enrollments.store', $learningClass), [
            'student_id' => $tutor->id,
        ])->assertSessionHasErrors('student_id');
    }

    public function test_duplicate_active_enrollment_is_prevented(): void
    {
        [$admin, $student, $learningClass] = $this->context();
        Enrollment::factory()->for($learningClass)->create(['student_id' => $student->id]);

        $this->actingAs($admin)->post(route('admin.classes.enrollments.store', $learningClass), [
            'student_id' => $student->id,
        ])->assertSessionHasErrors('student_id');

        $this->assertDatabaseCount('enrollments', 1);
    }

    public function test_withdraw_changes_status_without_deleting_row(): void
    {
        [$admin, $student, $learningClass] = $this->context();
        $enrollment = Enrollment::factory()->for($learningClass)->create(['student_id' => $student->id]);

        $this->actingAs($admin)->patch(route('admin.classes.enrollments.withdraw', [$learningClass, $enrollment]))
            ->assertRedirect();

        $this->assertDatabaseHas('enrollments', [
            'id' => $enrollment->id,
            'status' => EnrollmentStatus::Withdrawn->value,
        ]);
        $this->assertDatabaseCount('enrollments', 1);
    }

    public function test_re_enrollment_reactivates_existing_row(): void
    {
        [$admin, $student, $learningClass] = $this->context();
        $enrollment = Enrollment::factory()->withdrawn()->for($learningClass)->create(['student_id' => $student->id]);

        $this->actingAs($admin)->post(route('admin.classes.enrollments.store', $learningClass), [
            'student_id' => $student->id,
        ])->assertRedirect();

        $enrollment->refresh();
        $this->assertSame(EnrollmentStatus::Active, $enrollment->status);
        $this->assertDatabaseCount('enrollments', 1);
    }

    public function test_completed_enrollment_sets_completed_at(): void
    {
        [$admin, $student, $learningClass] = $this->context();
        $enrollment = Enrollment::factory()->for($learningClass)->create(['student_id' => $student->id]);

        $this->actingAs($admin)->patch(route('admin.classes.enrollments.complete', [$learningClass, $enrollment]));

        $enrollment->refresh();
        $this->assertSame(EnrollmentStatus::Completed, $enrollment->status);
        $this->assertNotNull($enrollment->completed_at);
    }

    public function test_reactivating_completed_enrollment_clears_completed_at(): void
    {
        [$admin, $student, $learningClass] = $this->context();
        $enrollment = Enrollment::factory()->completed()->for($learningClass)->create(['student_id' => $student->id]);

        $this->actingAs($admin)->patch(route('admin.classes.enrollments.reactivate', [$learningClass, $enrollment]));

        $enrollment->refresh();
        $this->assertSame(EnrollmentStatus::Active, $enrollment->status);
        $this->assertNull($enrollment->completed_at);
    }

    public function test_student_can_belong_to_multiple_classes(): void
    {
        $student = $this->userWithRole('Student');
        $classes = LearningClass::factory(2)->create();

        foreach ($classes as $learningClass) {
            Enrollment::factory()->for($learningClass)->create(['student_id' => $student->id]);
        }

        $this->assertCount(2, $student->learningClasses);
    }

    public function test_enrollment_action_rejects_record_from_another_class(): void
    {
        [$admin, $student, $learningClass] = $this->context();
        $otherEnrollment = Enrollment::factory()->create(['student_id' => $student->id]);

        $this->actingAs($admin)->patch(route('admin.classes.enrollments.withdraw', [$learningClass, $otherEnrollment]))
            ->assertSessionHasErrors('enrollment');
    }

    /** @return array{User, User, LearningClass} */
    private function context(): array
    {
        return [
            $this->userWithRole('Admin'),
            $this->userWithRole('Student'),
            LearningClass::factory()->create(),
        ];
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }
}
