<?php

namespace Tests\Feature\Admin;

use App\Enums\ParentRelationshipType;
use App\Models\ParentStudentRelationship;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ParentStudentRelationshipTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_parent_can_be_linked_to_student(): void
    {
        [$admin, $parent, $student] = $this->context();

        $this->actingAs($admin)->post(route('admin.parent-students.store'), [
            'parent_id' => $parent->id,
            'student_id' => $student->id,
            'relationship_type' => ParentRelationshipType::Mother->value,
        ])->assertRedirect(route('admin.parent-students.index'));

        $this->assertDatabaseHas('parent_student_relationships', [
            'parent_id' => $parent->id,
            'student_id' => $student->id,
            'relationship_type' => ParentRelationshipType::Mother->value,
        ]);
    }

    public function test_non_parent_cannot_be_parent_side(): void
    {
        [$admin, , $student] = $this->context();

        $this->actingAs($admin)->post(route('admin.parent-students.store'), [
            'parent_id' => $this->userWithRole('Tutor')->id,
            'student_id' => $student->id,
            'relationship_type' => ParentRelationshipType::Guardian->value,
        ])->assertSessionHasErrors('parent_id');
    }

    public function test_non_student_cannot_be_student_side(): void
    {
        [$admin, $parent] = $this->context();

        $this->actingAs($admin)->post(route('admin.parent-students.store'), [
            'parent_id' => $parent->id,
            'student_id' => $this->userWithRole('Tutor')->id,
            'relationship_type' => ParentRelationshipType::Guardian->value,
        ])->assertSessionHasErrors('student_id');
    }

    public function test_same_user_cannot_be_both_sides(): void
    {
        $admin = $this->userWithRole('Admin');
        $user = User::factory()->create();
        $user->assignRole(['Parent', 'Student']);

        $this->actingAs($admin)->post(route('admin.parent-students.store'), [
            'parent_id' => $user->id,
            'student_id' => $user->id,
            'relationship_type' => ParentRelationshipType::Other->value,
        ])->assertSessionHasErrors('student_id');
    }

    public function test_duplicate_parent_student_pair_is_rejected(): void
    {
        [$admin, $parent, $student] = $this->context();
        ParentStudentRelationship::factory()->create([
            'parent_id' => $parent->id,
            'student_id' => $student->id,
        ]);

        $this->actingAs($admin)->post(route('admin.parent-students.store'), [
            'parent_id' => $parent->id,
            'student_id' => $student->id,
            'relationship_type' => ParentRelationshipType::Father->value,
        ])->assertSessionHasErrors('student_id');
    }

    public function test_relationship_can_be_removed(): void
    {
        [$admin, $parent, $student] = $this->context();
        $relationship = ParentStudentRelationship::factory()->create([
            'parent_id' => $parent->id,
            'student_id' => $student->id,
        ]);

        $this->actingAs($admin)->delete(route('admin.parent-students.destroy', $relationship))
            ->assertRedirect(route('admin.parent-students.index'));

        $this->assertDatabaseMissing('parent_student_relationships', ['id' => $relationship->id]);
    }

    public function test_multiple_parents_can_link_to_one_student(): void
    {
        $student = $this->userWithRole('Student');

        foreach ([$this->userWithRole('Parent'), $this->userWithRole('Parent')] as $parent) {
            ParentStudentRelationship::factory()->create([
                'parent_id' => $parent->id,
                'student_id' => $student->id,
            ]);
        }

        $this->assertCount(2, $student->parents);
    }

    public function test_parent_can_link_to_multiple_students(): void
    {
        $parent = $this->userWithRole('Parent');

        foreach ([$this->userWithRole('Student'), $this->userWithRole('Student')] as $student) {
            ParentStudentRelationship::factory()->create([
                'parent_id' => $parent->id,
                'student_id' => $student->id,
            ]);
        }

        $this->assertCount(2, $parent->children);
    }

    public function test_relationship_list_filters_and_paginates(): void
    {
        $admin = $this->userWithRole('Admin');
        $expectedParent = $this->userWithRole('Parent');
        $expectedStudent = $this->userWithRole('Student');
        ParentStudentRelationship::factory()->create([
            'parent_id' => $expectedParent->id,
            'student_id' => $expectedStudent->id,
            'relationship_type' => ParentRelationshipType::Father,
        ]);
        ParentStudentRelationship::factory(10)->create();

        $this->actingAs($admin)->get(route('admin.parent-students.index', [
            'search' => $expectedParent->email,
            'relationship_type' => ParentRelationshipType::Father->value,
        ]))->assertInertia(fn (Assert $page) => $page
            ->component('admin/parent-students/Index')
            ->has('relationships.data', 1)
            ->where('relationships.data.0.parent.email', $expectedParent->email));

        $this->actingAs($admin)->get(route('admin.parent-students.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('relationships.data', 10)
                ->where('relationships.total', 11));
    }

    public function test_create_selector_contains_only_parent_and_student_roles(): void
    {
        $admin = $this->userWithRole('Admin');
        $parent = $this->userWithRole('Parent');
        $student = $this->userWithRole('Student');
        $this->userWithRole('Tutor');

        $this->actingAs($admin)->get(route('admin.parent-students.create'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('parents', 1)
                ->where('parents.0.id', $parent->id)
                ->has('students', 1)
                ->where('students.0.id', $student->id));
    }

    public function test_non_admin_cannot_manage_parent_relationships(): void
    {
        $this->actingAs($this->userWithRole('Parent'))
            ->get(route('admin.parent-students.index'))
            ->assertForbidden();
    }

    /** @return array{User, User, User} */
    private function context(): array
    {
        return [
            $this->userWithRole('Admin'),
            $this->userWithRole('Parent'),
            $this->userWithRole('Student'),
        ];
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }
}
