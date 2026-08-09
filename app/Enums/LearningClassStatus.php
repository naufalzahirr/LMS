<?php

namespace App\Enums;

enum LearningClassStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
    case Completed = 'completed';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Inactive => 'Inactive',
            self::Completed => 'Completed',
        };
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $status): array => ['value' => $status->value, 'label' => $status->label()],
            self::cases(),
        );
    }
}
