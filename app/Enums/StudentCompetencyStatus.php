<?php

namespace App\Enums;

enum StudentCompetencyStatus: string
{
    case Learning = 'learning';
    case ReadyForAssessment = 'ready_for_assessment';
    case NeedsRemedial = 'needs_remedial';
    case Mastered = 'mastered';

    public function label(): string
    {
        return match ($this) {
            self::Learning => 'Learning',
            self::ReadyForAssessment => 'Ready for Assessment',
            self::NeedsRemedial => 'Needs Remedial',
            self::Mastered => 'Mastered',
        };
    }
}
