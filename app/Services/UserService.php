<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UserService
{
    /**
     * @param  array{name: string, email: string, password: string, role: string}  $data
     */
    public function create(array $data): User
    {
        return DB::transaction(function () use ($data): User {
            $user = User::query()->create([
                ...Arr::only($data, ['name', 'email', 'password']),
                'email_verified_at' => now(),
            ]);

            $user->syncRoles([$data['role']]);

            return $user;
        });
    }

    /**
     * @param  array{name: string, email: string, password?: string|null, role: string}  $data
     */
    public function update(User $user, array $data): User
    {
        return DB::transaction(function () use ($user, $data): User {
            $this->ensureRoleChangeIsSafe($user, $data['role']);
            $attributes = Arr::only($data, ['name', 'email']);

            if (! empty($data['password'])) {
                $attributes['password'] = $data['password'];
            }

            $user->update($attributes);
            $user->syncRoles([$data['role']]);

            return $user->refresh();
        });
    }

    public function delete(User $user): void
    {
        DB::transaction(function () use ($user): void {
            if (
                $user->enrollments()->exists()
                || $user->teachingClasses()->exists()
                || $user->children()->exists()
                || $user->parents()->exists()
                || $user->gradedAssessmentAnswers()->exists()
            ) {
                throw ValidationException::withMessages([
                    'user' => __('This user cannot be deleted while referenced by class delivery records.'),
                ]);
            }

            $user->delete();
        });
    }

    private function ensureRoleChangeIsSafe(User $user, string $role): void
    {
        $invalid = ($role !== 'Student' && ($user->enrollments()->exists() || $user->parents()->exists()))
            || ($role !== 'Tutor' && $user->teachingClasses()->exists())
            || ($role !== 'Parent' && $user->children()->exists())
            || (! in_array($role, ['Admin', 'Tutor'], true) && $user->gradedAssessmentAnswers()->exists());

        if ($invalid) {
            throw ValidationException::withMessages([
                'role' => __('This role cannot be changed while the user is referenced by class delivery records.'),
            ]);
        }
    }
}
