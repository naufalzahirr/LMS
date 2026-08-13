<?php

namespace App\Events;

use App\Models\RemedialAssignment;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

class StudentRemedialAssigned implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(public readonly RemedialAssignment $assignment) {}
}
