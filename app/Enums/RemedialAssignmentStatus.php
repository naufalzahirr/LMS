<?php

namespace App\Enums;

enum RemedialAssignmentStatus: string
{
    case Assigned = 'assigned';
    case Completed = 'completed';
    case Superseded = 'superseded';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
