<?php

namespace App\Enums;

enum AssessmentFeedbackMode: string
{
    case ScoreOnly = 'score_only';
    case AfterFinalAttempt = 'after_final_attempt';
    case AfterEachAttempt = 'after_each_attempt';

    public function label(): string
    {
        return match ($this) {
            self::ScoreOnly => 'Score only',
            self::AfterFinalAttempt => 'After final attempt',
            self::AfterEachAttempt => 'After each attempt',
        };
    }

    /** @return array<int, array{value: string, label: string}> */
    public static function options(): array
    {
        return array_map(
            fn (self $mode): array => ['value' => $mode->value, 'label' => $mode->label()],
            self::cases(),
        );
    }
}
