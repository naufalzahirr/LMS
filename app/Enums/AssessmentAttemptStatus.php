<?php

namespace App\Enums;

enum AssessmentAttemptStatus: string
{
    case InProgress = 'in_progress';
    case PendingGrading = 'pending_grading';
    case Graded = 'graded';

    public function label(): string
    {
        return match ($this) {
            self::InProgress => 'In Progress',
            self::PendingGrading => 'Pending Grading',
            self::Graded => 'Graded',
        };
    }

    /** @return array<int, array{value: string, label: string}> */
    public static function options(): array
    {
        return array_map(
            fn (self $status): array => ['value' => $status->value, 'label' => $status->label()],
            self::cases(),
        );
    }
}
