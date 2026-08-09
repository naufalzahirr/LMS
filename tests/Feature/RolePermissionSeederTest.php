<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\AdminUserSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RolePermissionSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_roles_receive_the_expected_permissions(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $this->assertEqualsCanonicalizing([
            'manage-users',
            'manage-programs',
            'manage-courses',
            'manage-competencies',
            'manage-modules',
            'manage-lessons',
            'manage-classes',
            'manage-assessments',
            'view-all-progress',
            'view-class-progress',
            'view-own-progress',
            'view-child-progress',
        ], Role::findByName('Admin')->permissions->pluck('name')->all());

        $this->assertEqualsCanonicalizing([
            'manage-assessments',
            'view-class-progress',
        ], Role::findByName('Tutor')->permissions->pluck('name')->all());

        $this->assertSame(
            ['view-own-progress'],
            Role::findByName('Student')->permissions->pluck('name')->all(),
        );

        $this->assertSame(
            ['view-child-progress'],
            Role::findByName('Parent')->permissions->pluck('name')->all(),
        );
    }

    public function test_development_admin_is_created_with_the_admin_role(): void
    {
        $this->seed([
            RolePermissionSeeder::class,
            AdminUserSeeder::class,
        ]);

        $admin = User::query()->where('email', 'admin@mlc.test')->firstOrFail();

        $this->assertSame(['Admin'], $admin->getRoleNames()->all());
        $this->assertTrue(Hash::check('password', $admin->password));
        $this->assertNotNull($admin->email_verified_at);
    }
}
