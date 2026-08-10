<?php

namespace Tests\Feature\Admin;

use App\Models\AssessmentAnswer;
use App\Models\LearningClass;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_admin_can_access_user_management(): void
    {
        $admin = $this->userWithRole('Admin');

        $this->actingAs($admin)
            ->get(route('admin.users.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/users/Index')
                ->has('users.data', 1)
                ->where('users.data.0.id', $admin->id));
    }

    public function test_non_authorized_user_cannot_access_user_management(): void
    {
        $student = $this->userWithRole('Student');

        $this->actingAs($student)
            ->get(route('admin.users.index'))
            ->assertForbidden();
    }

    public function test_admin_can_create_user_with_one_primary_role(): void
    {
        $admin = $this->userWithRole('Admin');

        $this->actingAs($admin)
            ->post(route('admin.users.store'), [
                'name' => 'New Student',
                'email' => 'student@mlc.test',
                'password' => 'password123',
                'password_confirmation' => 'password123',
                'role' => 'Student',
            ])
            ->assertRedirect(route('admin.users.index'));

        $student = User::query()->where('email', 'student@mlc.test')->firstOrFail();

        $this->assertSame(['Student'], $student->getRoleNames()->all());
        $this->assertTrue(Hash::check('password123', $student->password));
        $this->assertNotNull($student->email_verified_at);
    }

    public function test_admin_can_update_user_and_replace_primary_role(): void
    {
        $admin = $this->userWithRole('Admin');
        $user = $this->userWithRole('Tutor');
        $originalPassword = $user->password;

        $this->actingAs($admin)
            ->put(route('admin.users.update', $user), [
                'name' => 'Updated Parent',
                'email' => 'parent@mlc.test',
                'password' => '',
                'password_confirmation' => '',
                'role' => 'Parent',
            ])
            ->assertRedirect(route('admin.users.index'));

        $user->refresh();

        $this->assertSame('Updated Parent', $user->name);
        $this->assertSame('parent@mlc.test', $user->email);
        $this->assertSame($originalPassword, $user->password);
        $this->assertSame(['Parent'], $user->getRoleNames()->all());
    }

    public function test_admin_can_delete_another_user(): void
    {
        $admin = $this->userWithRole('Admin');
        $user = $this->userWithRole('Student');

        $this->actingAs($admin)
            ->delete(route('admin.users.destroy', $user))
            ->assertRedirect(route('admin.users.index'));

        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    }

    public function test_admin_cannot_delete_themselves(): void
    {
        $admin = $this->userWithRole('Admin');

        $this->actingAs($admin)
            ->delete(route('admin.users.destroy', $admin))
            ->assertForbidden();

        $this->assertDatabaseHas('users', ['id' => $admin->id]);
    }

    public function test_user_creation_is_validated(): void
    {
        $admin = $this->userWithRole('Admin');

        $this->actingAs($admin)
            ->post(route('admin.users.store'), [
                'name' => '',
                'email' => 'not-an-email',
                'password' => 'short',
                'password_confirmation' => 'different',
                'role' => 'Unknown',
            ])
            ->assertSessionHasErrors(['name', 'email', 'password', 'role']);
    }

    public function test_user_list_is_paginated(): void
    {
        $admin = $this->userWithRole('Admin');
        User::factory(11)->create();

        $this->actingAs($admin)
            ->get(route('admin.users.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('users.data', 10)
                ->where('users.total', 12));
    }

    public function test_role_change_cannot_invalidate_existing_delivery_relationships(): void
    {
        $admin = $this->userWithRole('Admin');
        $tutor = $this->userWithRole('Tutor');
        LearningClass::factory()->create()->tutors()->attach($tutor);

        $this->actingAs($admin)
            ->put(route('admin.users.update', $tutor), [
                'name' => $tutor->name,
                'email' => $tutor->email,
                'password' => '',
                'password_confirmation' => '',
                'role' => 'Parent',
            ])
            ->assertSessionHasErrors('role');

        $this->assertTrue($tutor->fresh()->hasRole('Tutor'));
    }

    public function test_grading_history_prevents_user_deletion(): void
    {
        $admin = $this->userWithRole('Admin');
        $grader = $this->userWithRole('Tutor');
        AssessmentAnswer::factory()->create(['graded_by' => $grader->id]);

        $this->actingAs($admin)
            ->delete(route('admin.users.destroy', $grader))
            ->assertSessionHasErrors('user');

        $this->assertNotNull($grader->fresh());
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }
}
