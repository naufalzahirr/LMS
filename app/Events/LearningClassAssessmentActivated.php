<?php

namespace App\Events;

use App\Models\LearningClassAssessment;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

class LearningClassAssessmentActivated implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(public readonly LearningClassAssessment $assignment) {}
}
