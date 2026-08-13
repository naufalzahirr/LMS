<?php

namespace App\Listeners;

use App\Events\AssessmentAttemptGraded;
use App\Notifications\AssessmentGradedNotification;
use App\Notifications\ChildAssessmentGradedNotification;
use Illuminate\Support\Facades\Notification;

class NotifyOnAssessmentGraded
{
    public function handle(AssessmentAttemptGraded $event): void
    {
        $event->attempt->loadMissing('enrollment.student.parents');
        $student = $event->attempt->enrollment->student;

        $student->notify(new AssessmentGradedNotification($event->attempt));

        if ($student->parents->isNotEmpty()) {
            Notification::send($student->parents, new ChildAssessmentGradedNotification($event->attempt));
        }
    }
}
