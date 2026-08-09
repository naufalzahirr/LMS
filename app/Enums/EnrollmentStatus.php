<?php

namespace App\Enums;

enum EnrollmentStatus: string
{
    case Active = 'active';
    case Completed = 'completed';
    case Withdrawn = 'withdrawn';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Completed => 'Completed',
            self::Withdrawn => 'Withdrawn',
        };
    }
}
