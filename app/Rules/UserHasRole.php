<?php

namespace App\Rules;

use App\Models\User;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class UserHasRole implements ValidationRule
{
    public function __construct(private readonly string $role) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $user = is_numeric($value) ? User::query()->find((int) $value) : null;

        if (! $user?->hasRole($this->role)) {
            $fail(__('The selected user must have the :role role.', [
                'role' => $this->role,
            ]));
        }
    }
}
