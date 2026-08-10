<?php

namespace App\Enums;

enum AssessmentPurpose: string
{
    case Practice = 'practice';
    case Formative = 'formative';
    case Mastery = 'mastery';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    /** @return array<int, array{value: string, label: string}> */
    public static function options(): array
    {
        return array_map(
            fn (self $purpose): array => ['value' => $purpose->value, 'label' => $purpose->label()],
            self::cases(),
        );
    }
}
