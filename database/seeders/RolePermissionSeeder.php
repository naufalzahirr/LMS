<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    /**
     * @var array<int, string>
     */
    private const PERMISSIONS = [
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
    ];

    /**
     * @var array<string, array<int, string>>
     */
    private const ROLE_PERMISSIONS = [
        'Admin' => self::PERMISSIONS,
        'Tutor' => [
            'manage-assessments',
            'view-class-progress',
        ],
        'Student' => ['view-own-progress'],
        'Parent' => ['view-child-progress'],
    ];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        DB::transaction(function (): void {
            $permissions = collect(self::PERMISSIONS)
                ->mapWithKeys(fn (string $name): array => [
                    $name => Permission::findOrCreate($name, 'web'),
                ]);

            foreach (self::ROLE_PERMISSIONS as $roleName => $rolePermissions) {
                Role::findOrCreate($roleName, 'web')->syncPermissions(
                    $permissions->only($rolePermissions),
                );
            }
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
