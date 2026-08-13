<?php

namespace App\Listeners;

use App\Events\StudentRemedialAssigned;
use App\Listeners\Concerns\FiltersAuthorizedTutors;
use App\Notifications\ChildRemedialAssignedNotification;
use App\Notifications\RemedialAssignedNotification;
use App\Notifications\StudentNeedsRemedialNotification;
use Illuminate\Support\Facades\Notification;

class NotifyOnRemedialAssigned
{
    use FiltersAuthorizedTutors;

    public function handle(StudentRemedialAssigned $event): void
    {
        $event->assignment->loadMissing('enrollment.student.parents', 'enrollment.learningClass.tutors');
        $enrollment = $event->assignment->enrollment;
        $student = $enrollment->student;

        $student->notify(new RemedialAssignedNotification($event->assignment));

        $tutors = $this->authorizedTutors($enrollment->learningClass);

        if ($tutors->isNotEmpty()) {
            Notification::send($tutors, new StudentNeedsRemedialNotification($event->assignment));
        }

        if ($student->parents->isNotEmpty()) {
            Notification::send($student->parents, new ChildRemedialAssignedNotification($event->assignment));
        }
    }
}
