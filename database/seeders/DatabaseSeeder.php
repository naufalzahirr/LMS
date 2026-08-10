<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(RolePermissionSeeder::class);

        if (app()->isProduction()) {
            $this->command->warn('Production seeding stopped after roles and permissions. Demo users and learning data were not created.');

            return;
        }

        $this->call([
            AdminUserSeeder::class,
            AcademicSeeder::class,
            DeliverySeeder::class,
            AssessmentSeeder::class,
            AssessmentAttemptSeeder::class,
            MasteryLearningSeeder::class,
        ]);
    }
}
