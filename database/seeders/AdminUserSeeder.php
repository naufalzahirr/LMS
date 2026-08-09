<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            $this->command->warn('Development admin was not seeded in production.');

            return;
        }

        $admin = User::query()->updateOrCreate(
            ['email' => 'admin@mlc.test'],
            [
                'name' => 'Mastery Learning Center Admin',
                'email_verified_at' => now(),
                'password' => 'password',
            ],
        );

        $admin->syncRoles(['Admin']);
    }
}
