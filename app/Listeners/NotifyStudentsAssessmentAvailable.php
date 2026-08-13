<?php

namespace App\Listeners;

use App\Events\LearningClassAssessmentActivated;
use App\Services\AssessmentAvailabilityNotifier;

class NotifyStudentsAssessmentAvailable
{
    public function __construct(private readonly AssessmentAvailabilityNotifier $notifier) {}

    public function handle(LearningClassAssessmentActivated $event): void
    {
        $this->notifier->notify($event->assignment);
    }
}
