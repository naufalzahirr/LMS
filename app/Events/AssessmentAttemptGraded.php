<?php

namespace App\Events;

use App\Models\AssessmentAttempt;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

class AssessmentAttemptGraded implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(public readonly AssessmentAttempt $attempt) {}
}
