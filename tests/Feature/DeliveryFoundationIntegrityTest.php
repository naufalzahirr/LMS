<?php

namespace Tests\Feature;

use App\Enums\EnrollmentStatus;
use App\Enums\ParentRelationshipType;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\LearningClass;
use App\Models\ParentStudentRelationship;
use App\Models\User;
use Database\Seeders\AcademicSeeder;
use Database\Seeders\DeliverySeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeliveryFoundationIntegrityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_all_new_eloquent_relationships_are_connected(): void
    {
        $course = Course::factory()->create();
        $firstClass = LearningClass::factory()->for($course)->create(['name' => 'Beta']);
        $secondClass = LearningClass::factory()->for($course)->create(['name' => 'Alpha']);
        $student = $this->userWithRole('Student');
        $tutor = $this->userWithRole('Tutor');
        $parent = $this->userWithRole('Parent');
        $enrollment = Enrollment::factory()->for($firstClass)->create(['student_id' => $student->id]);
        $firstClass->tutors()->attach($tutor);
        ParentStudentRelationship::factory()->create([
            'parent_id' => $parent->id,
            'student_id' => $student->id,
            'relationship_type' => ParentRelationshipType::Guardian,
        ]);

        $this->assertEquals([$secondClass->id, $firstClass->id], $course->learningClasses->modelKeys());
        $this->assertTrue($firstClass->course->is($course));
        $this->assertTrue($enrollment->learningClass->is($firstClass));
        $this->assertTrue($enrollment->student->is($student));
        $this->assertTrue($student->enrollments->contains($enrollment));
        $this->assertTrue($student->learningClasses->contains($firstClass));
        $this->assertTrue($firstClass->students->contains($student));
        $this->assertTrue($firstClass->tutors->contains($tutor));
        $this->assertTrue($tutor->teachingClasses->contains($firstClass));
        $this->assertTrue($parent->children->contains($student));
        $this->assertTrue($student->parents->contains($parent));
    }

    public function test_student_with_enrollment_cannot_be_deleted(): void
    {
        $student = $this->userWithRole('Student');
        Enrollment::factory()->create(['student_id' => $student->id]);

        $this->actingAs($this->userWithRole('Admin'))->delete(route('admin.users.destroy', $student))
            ->assertSessionHasErrors('user');
        $this->assertDatabaseHas('users', ['id' => $student->id]);
    }

    public function test_tutor_with_assignment_cannot_be_deleted(): void
    {
        $tutor = $this->userWithRole('Tutor');
        LearningClass::factory()->create()->tutors()->attach($tutor);

        $this->actingAs($this->userWithRole('Admin'))->delete(route('admin.users.destroy', $tutor))
            ->assertSessionHasErrors('user');
    }

    public function test_parent_with_child_relationship_cannot_be_deleted(): void
    {
        $parent = $this->userWithRole('Parent');
        ParentStudentRelationship::factory()->create([
            'parent_id' => $parent->id,
            'student_id' => $this->userWithRole('Student')->id,
        ]);

        $this->actingAs($this->userWithRole('Admin'))->delete(route('admin.users.destroy', $parent))
            ->assertSessionHasErrors('user');
    }

    public function test_unreferenced_user_deletion_still_works(): void
    {
        $user = $this->userWithRole('Student');

        $this->actingAs($this->userWithRole('Admin'))->delete(route('admin.users.destroy', $user))
            ->assertRedirect();
        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    }

    public function test_database_enforces_unique_enrollment_pair(): void
    {
        $learningClass = LearningClass::factory()->create();
        $student = User::factory()->create();
        Enrollment::factory()->for($learningClass)->create(['student_id' => $student->id]);

        $this->expectException(QueryException::class);
        Enrollment::factory()->for($learningClass)->create(['student_id' => $student->id]);
    }

    public function test_database_enforces_unique_tutor_assignment_pair(): void
    {
        $learningClass = LearningClass::factory()->create();
        $tutor = User::factory()->create();
        $learningClass->tutors()->attach($tutor);

        $this->expectException(QueryException::class);
        $learningClass->tutors()->attach($tutor);
    }

    public function test_database_enforces_unique_parent_student_pair(): void
    {
        $parent = User::factory()->create();
        $student = User::factory()->create();
        ParentStudentRelationship::factory()->create(['parent_id' => $parent->id, 'student_id' => $student->id]);

        $this->expectException(QueryException::class);
        ParentStudentRelationship::factory()->create(['parent_id' => $parent->id, 'student_id' => $student->id]);
    }

    public function test_database_enforces_unique_class_code(): void
    {
        LearningClass::factory()->create(['code' => 'DB-UNIQUE']);

        $this->expectException(QueryException::class);
        LearningClass::factory()->create(['code' => 'DB-UNIQUE']);
    }

    public function test_database_restricts_deleting_course_with_class(): void
    {
        $course = Course::factory()->create();
        LearningClass::factory()->for($course)->create();

        $this->expectException(QueryException::class);
        $course->forceDelete();
    }

    public function test_database_restricts_deleting_class_and_users_with_delivery_records(): void
    {
        $learningClass = LearningClass::factory()->create();
        $student = User::factory()->create();
        Enrollment::factory()->for($learningClass)->create(['student_id' => $student->id]);

        try {
            $learningClass->forceDelete();
            $this->fail('Class deletion should have been restricted.');
        } catch (QueryException) {
            $this->assertDatabaseHas('learning_classes', ['id' => $learningClass->id]);
        }

        $this->expectException(QueryException::class);
        $student->delete();
    }

    public function test_delivery_seeder_is_minimal_and_idempotent(): void
    {
        $this->seed([AcademicSeeder::class, DeliverySeeder::class, DeliverySeeder::class]);

        $learningClass = LearningClass::query()->where('code', 'FE-BATCH-A')->firstOrFail();
        $this->assertSame('Frontend Fundamentals - Batch A', $learningClass->name);
        $this->assertCount(1, $learningClass->tutors);
        $this->assertCount(2, $learningClass->enrollments);
        $this->assertTrue($learningClass->enrollments->every(
            fn (Enrollment $enrollment): bool => $enrollment->status === EnrollmentStatus::Active,
        ));
        $this->assertDatabaseCount('parent_student_relationships', 1);
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }
}
