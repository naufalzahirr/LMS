<?php

namespace App\Listeners;

use App\Events\AssessmentSubmittedForGrading;
use App\Listeners\Concerns\FiltersAuthorizedTutors;
use App\Notifications\AssessmentNeedsGradingNotification;
use Illuminate\Support\Facades\Notification;

class NotifyTutorsAssessmentNeedsGrading
{
    use FiltersAuthorizedTutors;

    public function handle(AssessmentSubmittedForGrading $event): void
    {
        $event->attempt->loadMissing('classAssessment.learningClass.tutors');
        $tutors = $this->authorizedTutors($event->attempt->classAssessment->learningClass);

        if ($tutors->isEmpty()) {
            return;
        }

        Notification::send($tutors, new AssessmentNeedsGradingNotification($event->attempt));
    }
}
