<?php

namespace App\Enums;

enum LessonCheckpointType: string
{
    case MultipleChoice = 'multiple_choice';
    case MultipleSelect = 'multiple_select';
    case TrueFalse = 'true_false';
    case FillBlank = 'fill_blank';

    public function label(): string
    {
        return match ($this) {
            self::MultipleChoice => 'Multiple Choice',
            self::MultipleSelect => 'Multiple Select',
            self::TrueFalse => 'True / False',
            self::FillBlank => 'Fill in the Blank',
        };
    }

    /** @return array<int, array{value: string, label: string}> */
    public static function options(): array
    {
        return array_map(
            fn (self $type): array => ['value' => $type->value, 'label' => $type->label()],
            self::cases(),
        );
    }
}
